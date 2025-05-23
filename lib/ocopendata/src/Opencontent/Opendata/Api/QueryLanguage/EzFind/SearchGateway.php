<?php

namespace Opencontent\Opendata\Api\QueryLanguage\EzFind;

use Opencontent\Opendata\Api\ContentRepository;
use Opencontent\Opendata\Api\Exception\SearchEngineError;
use Opencontent\Opendata\Api\Gateway\FileSystem;
use Opencontent\Opendata\Api\Gateway\SolrStorage;
use Opencontent\Opendata\Api\SearchGateway as BaseGateway;
use Opencontent\Opendata\Api\SearchResultDecoratorQueryBuilderAware;
use Opencontent\Opendata\Api\Values\Content;
use Opencontent\Opendata\Api\Values\Metadata;
use Opencontent\Opendata\Api\Values\ContentData;
use Opencontent\Opendata\Api\Values\ExtraData;
use Opencontent\Opendata\Api\Values\SearchResults;
use Opencontent\Opendata\Api\EnvironmentSettings;
use Opencontent\QueryLanguage\QueryBuilderInterface;
use Opencontent\Opendata\Api\SearchResultDecoratorInterface;
use ArrayObject;
use eZSolr;
use ezfSearchResultInfo;
use Exception;
use eZINI;

class SearchGateway implements BaseGateway
{
    /**
     * @var EnvironmentSettings
     */
    private $environmentSettings;

    /**
     * @var QueryBuilder
     */
    private $queryBuilder;

    public function search($query, $limitation = null)
    {
        $queryObject = $this->queryBuilder->instanceQuery($query);
        $ezFindQueryObject = $queryObject->convert();

        if (!$ezFindQueryObject instanceof ArrayObject) {
            throw new \RuntimeException("Query builder did not return a valid query");
        }

        if ($ezFindQueryObject->getArrayCopy() === array("_query" => null) && !empty($query)){
            throw new \RuntimeException("Inconsistent query");
        }

        $ezFindQueryObject = $this->environmentSettings->filterQuery($ezFindQueryObject, $this->queryBuilder);
        $ezFindQuery = $ezFindQueryObject->getArrayCopy();

        //$ezFindQuery['Filter'][] = ezfSolrDocumentFieldBase::generateMetaFieldName('installation_id') . ':' . eZSolr::installationID();
        if (is_array($limitation) && empty($limitation)) {
            $installationFilter = \ezfSolrDocumentFieldBase::generateMetaFieldName('installation_id') . ':' . \eZSolr::installationID();
            if (isset($ezFindQuery['Filter'][0]) && $ezFindQuery['Filter'][0] == 'or'){
                $ezFindQuery['Filter'] = array($ezFindQuery['Filter'], $installationFilter);
            }else{
                $ezFindQuery['Filter'][] = $installationFilter;
            }
        }
        $ezFindQuery['Limitation'] = $limitation;
        $ezFindQuery['AsObjects'] = false;
        $ezFindQuery['FieldsToReturn'] = array(SolrStorage::getSolrIdentifier());

        $filterFields = isset($ezFindQuery['_filterFields']) ? $ezFindQuery['_filterFields'] : null;
        unset($ezFindQuery['_filterFields']);

        $filterLanguages = isset($ezFindQuery['_filterLanguages']) ? $ezFindQuery['_filterLanguages'] : null;
        unset($ezFindQuery['_filterLanguages']);

        $solr = new eZSolr();
        $rawResults = @$solr->search(
            $ezFindQuery['_query'],
            $ezFindQuery
        );

        $searchExtra = null;
        if ($rawResults['SearchExtras'] instanceof ezfSearchResultInfo) {
            if ($rawResults['SearchExtras']->attribute('hasError')) {
                $error = $rawResults['SearchExtras']->attribute('error');
                if (is_array($error)) {
                    $error = (string)$error['msg'];
                }
                throw new SearchEngineError('Search engine error: ' . $error);
            }

            $searchExtra = SearchResultInfo::fromEzfSearchResultInfo($rawResults['SearchExtras']);
        }

        $searchResults = new SearchResults();
        $filterFieldsResult = array();

        if ($this->environmentSettings->isDebug()) {
            $searchResults->query = array(
                'string' => (string)$queryObject,
                'eZFindQuery' => $ezFindQuery
            );

            if ($searchExtra instanceof SearchResultInfo) {
                $searchResults->query['responseHeader'] = $searchExtra->attribute('responseHeader');
            }
        } else {
            $searchResults->query = (string)$queryObject;
        }

        $searchResults->totalCount = (int)$rawResults['SearchCount'];

        if (($ezFindQuery['SearchLimit'] + $ezFindQuery['SearchOffset']) < $searchResults->totalCount) {
            $nextPageQuery = clone $queryObject;
            $nextPageQuery->setParameter('offset', ($ezFindQuery['SearchLimit'] + $ezFindQuery['SearchOffset']));
            $searchResults->nextPageQuery = (string)$nextPageQuery;
        }

        $fileSystemGateway = new FileSystem();
        $contentRepository = new ContentRepository();
        $contentRepository->setEnvironment($this->environmentSettings);

        foreach ($rawResults['SearchResult'] as $resultItem) {
            $id = isset($resultItem['meta_id_si']) ? $resultItem['meta_id_si'] : (isset($resultItem['id_si']) ? $resultItem['id_si'] : $resultItem['id']);
            try {
                if (isset($resultItem['data_map']['opendatastorage'])) {
                    $contentArray = $resultItem['data_map']['opendatastorage'];
                    $content = new Content();
                    $content->metadata = new Metadata((array)$contentArray['metadata']);
                    $content->data = new ContentData((array)$contentArray['data']);
                    if (isset($contentArray['extradata'])) {
                        $content->extradata = new ExtraData((array)$contentArray['extradata']);
                    }
                } else {
                    $content = $fileSystemGateway->loadContent((int)$id);
                }

                $ignorePolicies = false;
                if (is_array($limitation)) {
                    if (empty($limitation) || (isset($limitation['accessWord']) && $limitation['accessWord'] == 'yes')) {
                        $ignorePolicies = true;
                    }
                }
                $content = $contentRepository->read($content, $ignorePolicies);

                if ($filterFields !== null) {
                    $this->filterFields($filterFieldsResult, $content, $filterFields, $filterLanguages);
                } else {
                    $searchResults->searchHits[] = $content;
                }

            } catch (Exception $e) {
                $content = new Content();
                $content->metadata = new Metadata(array('id' => $id));
                $content->data = new ContentData(
                    array(
                        '_error' => $e->getMessage(),
                        '_rawresult' => $resultItem
                    )
                );
            }
        }

        if (isset($ezFindQuery['Facet'])
            && is_array($ezFindQuery['Facet'])
            && !empty($ezFindQuery['Facet'])
            && $searchExtra instanceof SearchResultInfo
        ) {
            $facets = array();
            $facetResults = $searchExtra->attribute('facet_fields');
            foreach ($ezFindQuery['Facet'] as $index => $facetDefinition) {
                $facetResult = $facetResults[$index];
                $facets[] = array(
                    'name' => $facetDefinition['name'],
                    'data' => $facetResult['countList']
                );
            }
            $searchResults->facets = $facets;
        }

        $filtersList = (array)eZINI::instance('ezfind.ini')->variable('ExtendedAttributeFilters', 'FiltersList');
        foreach (array_keys($filtersList) as $filterId){
            $filter = \eZFindExtendedAttributeFilterFactory::getInstance($filterId);
            if ($filter instanceof SearchResultDecoratorQueryBuilderAware){
                $filter->setQueryBuilder($this->getQueryBuilder());
            }
            if ($filter instanceof SearchResultDecoratorInterface){
                $filter->decorate($searchResults, $rawResults);
            }
        }

        if ($filterFields !== null) {
            return $filterFieldsResult;
        }

        return $this->environmentSettings->filterSearchResult(
            $searchResults,
            $ezFindQueryObject,
            $this->queryBuilder
        );
    }

    public function getEnvironmentSettings()
    {
        return $this->environmentSettings;
    }

    public function setEnvironmentSettings(EnvironmentSettings $environmentSettings)
    {
        $this->environmentSettings = $environmentSettings;
    }

    /**
     * @return QueryBuilderInterface
     */
    public function getQueryBuilder()
    {
        return $this->queryBuilder;
    }

    /**
     * @param QueryBuilderInterface $queryBuilder
     */
    public function setQueryBuilder(QueryBuilderInterface $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
    }

    private function filterFields(&$filterFieldsResult, $content, array $fields, array $languages = null)
    {
        $data = array();

        if (!$languages) {
            $currentLocale = \eZLocale::currentLocaleCode();
            if (in_array($currentLocale, $content['metadata']['languages'])) {
                $languages = [$currentLocale];
            }else{
                $languages = [$content['metadata']['languages'][0]];
            }
        }

        if (count($fields) == 1) {
            reset($fields);
            $key = key($fields);
            if (!is_numeric($key)) {
                $value = $fields[$key];
                $this->filterHashedFields($filterFieldsResult, $content, $key, $value, $languages);
                return;
            }
        }

        foreach ($fields as $field) {
            $key = $this->getFieldKey($field);
            $value = $this->getFieldValue($content, $field, $languages);
            if (count($fields) == 1 && !$key) {
                $data = $value;
            } else {
                if ($key) {
                    $data[$key] = $value;
                } else {
                    $data[] = $value;
                }
            }
        }
        $filterFieldsResult[] = $data;
    }

    private function filterHashedFields(&$filterFieldsResult, $content, $key, $value, array $languages = null)
    {
        $filterFieldsResult[$this->getFieldValue($content, $key, $languages)] = $this->getFieldValue($content, $value, $languages);
    }

    private function getFieldIdentifier($field)
    {
        $fieldNameParts = explode(' as ', $field);

        return $fieldNameParts[0];
    }

    private function getFieldKey($field)
    {
        $fieldNameParts = explode(' as ', $field);

        return isset($fieldNameParts[1]) ? $fieldNameParts[1] : null;
    }

    private function getFieldValue($content, $field, $languages)
    {
        if (count($languages) == 1) {
            $item = self::getValueFromDottedKey($content, $this->getFieldIdentifier($field), $languages[0]);
        } else {
            $item = array();
            foreach ($languages as $language) {
                $item[$language] = self::getValueFromDottedKey($content, $this->getFieldIdentifier($field), $language);
            }
        }

        return $item;
    }

    private static function getValueFromDottedKey($data, $key, $language, $default = null)
    {
        $key = str_replace(['{', '}'], ['[', ']'], $key);
        $runtime = new \JmesPath\AstRuntime();
        $data['metadata']['name'] = $data['metadata']['name'][$language];
        $data['data'] = $data['data'][$language];
        $result = $runtime($key, $data);

        return $result[$language] ?? $result ?? $default;
    }
}
