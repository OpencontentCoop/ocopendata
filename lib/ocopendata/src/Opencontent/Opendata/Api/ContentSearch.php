<?php

namespace Opencontent\Opendata\Api;

use Opencontent\Opendata\Api\Gateway\FileSystem;
use Opencontent\Opendata\Api\Gateway\SolrStorage;
use Opencontent\Opendata\Api\QueryLanguage\EzFind\QueryBuilder;
use Opencontent\Opendata\Api\Values\Content;
use Opencontent\Opendata\Api\Values\ContentData;
use Opencontent\Opendata\Api\Values\ExtraData;
use Opencontent\Opendata\Api\Values\Metadata;
use Opencontent\Opendata\Api\Values\SearchResults;
use Exception;
use eZSolr;
use ezfSearchResultInfo;
use ArrayObject;

class ContentSearch
{
    /**
     * @var string
     */
    private $query;

    /**
     * @var EnvironmentSettings
     */
    private $currentEnvironmentSettings;

    /**
     * @var QueryBuilder
     */
    private $queryBuilder;

    public function __construct()
    {
        $this->queryBuilder = new QueryBuilder();
    }

    public function search($query, array $limitation = null)
    {
        $this->query = $query;

        $queryObject = $this->queryBuilder->instanceQuery($query);
        $ezFindQueryObject = $queryObject->convert();

        if (!$ezFindQueryObject instanceof ArrayObject) {
            throw new \RuntimeException("Query builder did not return a valid query");
        }

        $ezFindQueryObject = $this->currentEnvironmentSettings->filterQuery($ezFindQueryObject, $this->queryBuilder);
        $ezFindQuery = $ezFindQueryObject->getArrayCopy();

        //$ezFindQuery['Filter'][] = ezfSolrDocumentFieldBase::generateMetaFieldName('installation_id') . ':' . eZSolr::installationID();
        if (is_array($limitation) && empty( $limitation )) {
            $ezFindQuery['Filter'][] = \ezfSolrDocumentFieldBase::generateMetaFieldName('installation_id') . ':' . \eZSolr::installationID();
        }
        $ezFindQuery['Limitation'] = $limitation;
        $ezFindQuery['AsObjects'] = false;
        $ezFindQuery['FieldsToReturn'] = array(SolrStorage::getSolrIdentifier());

        $filterFields = isset( $ezFindQuery['_filterFields'] ) ? $ezFindQuery['_filterFields'] : null;
        unset( $ezFindQuery['_filterFields'] );

        $filterLanguages = isset( $ezFindQuery['_filterLanguages'] ) ? $ezFindQuery['_filterLanguages'] : null;
        unset( $ezFindQuery['_filterLanguages'] );

        $solr = new eZSolr();
        $rawResults = @$solr->search(
            $ezFindQuery['_query'],
            $ezFindQuery
        );
        if ($rawResults['SearchExtras'] instanceof ezfSearchResultInfo) {
            if ($rawResults['SearchExtras']->attribute('hasError')) {
                $error = $rawResults['SearchExtras']->attribute('error');
                if (is_array($error)) {
                    $error = (string)$error['msg'];
                }
                throw new \RuntimeException($error);
            }
        }

        $searchResults = new SearchResults();
        $filterFieldsResult = array();

        if ($this->currentEnvironmentSettings->__get('debug') == true) {
            $searchResults->query = array(
                'string' => (string)$queryObject,
                'eZFindQuery' => $ezFindQuery
            );

            if ($rawResults['SearchExtras'] instanceof ezfSearchResultInfo) {
                $searchResults->query['responseHeader'] = $rawResults['SearchExtras']->attribute(
                    'responseHeader'
                );
            }
        } else {
            $searchResults->query = (string)$queryObject;
        }

        $searchResults->totalCount = (int)$rawResults['SearchCount'];

        if (( $ezFindQuery['SearchLimit'] + $ezFindQuery['SearchOffset'] ) < $searchResults->totalCount) {
            $nextPageQuery = clone $queryObject;
            $nextPageQuery->setParameter('offset', ( $ezFindQuery['SearchLimit'] + $ezFindQuery['SearchOffset'] ));
            $searchResults->nextPageQuery = (string)$nextPageQuery;
        }

        $fileSystemGateway = new FileSystem();
        $contentRepository = new ContentRepository();
        $contentRepository->setEnvironment($this->currentEnvironmentSettings);

        foreach ($rawResults['SearchResult'] as $resultItem) {
            $id = isset( $resultItem['meta_id_si'] ) ? $resultItem['meta_id_si'] : isset( $resultItem['id_si'] ) ? $resultItem['id_si'] : $resultItem['id'];
            try {
                if (isset( $resultItem['data_map']['opendatastorage'] )) {
                    $contentArray = $resultItem['data_map']['opendatastorage'];
                    $content = new Content();
                    $content->metadata = new Metadata((array)$contentArray['metadata']);
                    $content->data = new ContentData((array)$contentArray['data']);
                    if (isset( $contentArray['extradata'] )) {
                        $content->extradata = new ExtraData((array)$contentArray['extradata']);
                    }
                } else {
                    $content = $fileSystemGateway->loadContent((int)$id);
                }

                $ignorePolicies = false;
                if (is_array($limitation)) {
                    if (empty( $limitation ) || ( isset( $limitation['accessWord'] ) && $limitation['accessWord'] == 'yes' )) {
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

        if (isset( $ezFindQuery['Facet'] )
            && is_array($ezFindQuery['Facet'])
            && !empty( $ezFindQuery['Facet'] )
            && $rawResults['SearchExtras'] instanceof ezfSearchResultInfo
        ) {
            $facets = array();
            $facetResults = $rawResults['SearchExtras']->attribute('facet_fields');
            foreach ($ezFindQuery['Facet'] as $index => $facetDefinition) {
                $facetResult = $facetResults[$index];
                $facets[] = array(
                    'name' => $facetDefinition['name'],
                    'data' => $facetResult['countList']
                );
            }
            $searchResults->facets = $facets;
        }

        if ($filterFields !== null) {
            return $filterFieldsResult;
        }

        return $this->currentEnvironmentSettings->filterSearchResult($searchResults, $ezFindQueryObject,
            $this->queryBuilder);
    }

    /**
     * @return string
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * @param $query
     *
     * @return $this
     */
    public function setQuery($query)
    {
        $this->query = $query;

        return $this;
    }

    /**
     * @return EnvironmentSettings
     */
    public function getCurrentEnvironmentSettings()
    {
        return $this->currentEnvironmentSettings;
    }

    /**
     * @param EnvironmentSettings $environmentSettings
     *
     * @return $this
     */
    public function setEnvironment(EnvironmentSettings $environmentSettings)
    {
        $this->currentEnvironmentSettings = $environmentSettings;

        return $this;
    }

    /**
     * Alias of method setEnvironment
     *
     * @param $currentEnvironmentSettings
     *
     * @return $this
     */
    public function setCurrentEnvironmentSettings($currentEnvironmentSettings)
    {
        $this->currentEnvironmentSettings = $currentEnvironmentSettings;

        return $this;
    }

    /**
     * @return QueryBuilder
     */
    public function getQueryBuilder()
    {
        return $this->queryBuilder;
    }

    /**
     * @param QueryBuilder $queryBuilder
     *
     * @return ContentSearch
     */
    public function setQueryBuilder($queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;

        return $this;
    }

    private function filterFields(&$filterFieldsResult, $content, array $fields, array $languages = null)
    {
        $data = array();

        if (!$languages) {
            $languages = $content['metadata']['languages'];
        }

        $combine = false;

        if (count($fields) == 1) {
            reset($fields);
            $firstKey = key($fields);
            if (!is_numeric($firstKey)) {
                $fields = array_values($fields);
                array_unshift($fields, $firstKey);
                $combine = true;
            }
        }

        $fieldIdentifiers = array();
        foreach ($fields as $field) {

            $fieldNameParts = explode(' as ', $field);
            $parts = explode('.', $fieldNameParts[0], 2);
            $groupIdentifier = $parts[0];
            $fieldIdentifier = $parts[1];

            if ($combine) {
                $fieldName = $fieldIdentifier;
                $fieldIdentifiers[] = $fieldIdentifier;
            } else {
                $fieldName = isset( $fieldNameParts[1] ) ? $fieldNameParts[1] : null;
            }

            $item = null;
            if (count($languages) == 1 || $combine) {
                $item = self::getValueFromDottedKey($content, "$groupIdentifier.$fieldIdentifier", $languages[0]);
            } else {
                $item = array();
                foreach ($languages as $language) {
                    $item[$language] = self::getValueFromDottedKey($content, "$groupIdentifier.$fieldIdentifier", $language);
                }
            }
            if ($fieldName) {
                $data[$fieldName] = $item;
            } else {
                if (count($fields) == 1){
                    $data = $item;
                }else{
                    $data[] = $item;
                }
            }
        }

        if ($combine) {
            $filterFieldsResult[$data[$fieldIdentifiers[0]]] = $data[$fieldIdentifiers[1]];
        } else {
            $filterFieldsResult[] = $data;
        }
    }

    private static function getValueFromDottedKey($array, $key, $language, $default = null)
    {
        $value = $default;
        foreach (explode('.', $key) as $segment) {
            if (isset($array[$segment])){
                $value = $array[$segment];
                if (is_array($array[$segment])){
                    $array = $array[$segment];
                    if (isset($array[$language]) && is_array($array[$language])){
                        $array = $array[$language];
                    }
                }
            }else{
                return $default;
            }
        }
        if (isset($value[$language])){
            $value = $value[$language];
        }
        return $value;
    }
}
