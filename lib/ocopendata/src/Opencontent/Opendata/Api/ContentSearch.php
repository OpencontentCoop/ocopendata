<?php

namespace Opencontent\Opendata\Api;

use Opencontent\Opendata\Api\Gateway\FileSystem;
use Opencontent\Opendata\Api\Gateway\SolrStorage;
use Opencontent\Opendata\Api\QueryLanguage\EzFind\QueryBuilder as EzFindQueryBuilder;
use Opencontent\Opendata\Api\Values\Content;
use Opencontent\Opendata\Api\Values\ContentData;
use Opencontent\Opendata\Api\Values\Metadata;
use Opencontent\Opendata\Api\Values\SearchResults;
use Exception;
use eZSolr;
use ezfSearchResultInfo;
use ezfSolrDocumentFieldBase;
use ArrayObject;

class ContentSearch
{
    /**
     * @var string
     */
    protected $query;

    /**
     * @var EnvironmentSettings
     */
    protected $currentEnvironmentSettings;

    public function setEnvironment( EnvironmentSettings $environmentSettings )
    {
        $this->currentEnvironmentSettings = $environmentSettings;
    }

    public function search( $query, array $limitation = null )
    {
        $this->query = $query;

        $builder = new EzFindQueryBuilder();
        $queryObject = $builder->instanceQuery( $query );
        $ezFindQueryObject = $queryObject->convert();

        if ( !$ezFindQueryObject instanceof ArrayObject )
        {
            throw new \RuntimeException( "Query builder did not return a valid query" );
        }

        $ezFindQueryObject = $this->currentEnvironmentSettings->filterQuery( $ezFindQueryObject, $builder );
        $ezFindQuery = $ezFindQueryObject->getArrayCopy();

        //$ezFindQuery['Filter'][] = ezfSolrDocumentFieldBase::generateMetaFieldName('installation_id') . ':' . eZSolr::installationID();
        $ezFindQuery['Limitation'] = $limitation;
        $ezFindQuery['AsObjects'] = false;
        $ezFindQuery['FieldsToReturn'] = array( SolrStorage::getSolrIdentifier() );

        $solr = new eZSolr();
        $rawResults = @$solr->search(
            $ezFindQuery['_query'],
            $ezFindQuery
        );
        if ( $rawResults['SearchExtras'] instanceof ezfSearchResultInfo )
        {
            if ( $rawResults['SearchExtras']->attribute( 'hasError' ) )
            {
                $error = $rawResults['SearchExtras']->attribute( 'error' );
                if ( is_array( $error ) )
                {
                    $error = (string)$error['msg'];
                }
                throw new \RuntimeException( $error );
            }
        }

        $searchResults = new SearchResults();
        if ( $this->currentEnvironmentSettings->__get( 'debug' ) == true )
        {
            $searchResults->query = array(
                'string' => (string)$queryObject,
                'eZFindQuery' => $ezFindQuery
            );

            if ( $rawResults['SearchExtras'] instanceof ezfSearchResultInfo )
            {
                $searchResults->query['responseHeader'] = $rawResults['SearchExtras']->attribute(
                    'responseHeader'
                );
            }
        }
        else
        {
            $searchResults->query = (string)$queryObject;
        }

        $searchResults->totalCount = (int)$rawResults['SearchCount'];

        if ( ( $ezFindQuery['SearchLimit'] + $ezFindQuery['SearchOffset'] ) < $searchResults->totalCount )
        {
            $nextPageQuery = clone $queryObject;
            $nextPageQuery->setParameter( 'offset', ( $ezFindQuery['SearchLimit'] + $ezFindQuery['SearchOffset'] ) );
            $searchResults->nextPageQuery = (string)$nextPageQuery;
        }

        $fileSystemGateway = new FileSystem();
        $contentRepository = new ContentRepository();
        $contentRepository->setEnvironment( $this->currentEnvironmentSettings );

        foreach ( $rawResults['SearchResult'] as $resultItem )
        {
            $id = isset( $resultItem['meta_id_si'] ) ? $resultItem['meta_id_si'] : isset( $resultItem['id_si'] ) ? $resultItem['id_si'] : $resultItem['id'];
            try
            {
                if ( isset( $resultItem['data_map']['opendatastorage'] ) )
                {
                    $contentArray = $resultItem['data_map']['opendatastorage'];
                    $content = new Content();
                    $content->metadata = new Metadata( (array)$contentArray['metadata'] );
                    $content->data = new ContentData( (array)$contentArray['data'] );
                }
                else
                {
                    $content = $fileSystemGateway->loadContent( (int)$id );
                }

                $ignorePolicies = false;
                if (is_array($limitation)){
                    if (empty($limitation) || ( isset($limitation['accessWord']) && $limitation['accessWord'] == 'yes') ){
                        $ignorePolicies = true;
                    }
                }
                $content = $contentRepository->read( $content, $ignorePolicies );
                $searchResults->searchHits[] = $content;
            }
            catch ( Exception $e )
            {
                $content = new Content();
                $content->metadata = new Metadata( array( 'id' => $id ) );
                $content->data = new ContentData(
                    array(
                        '_error' => $e->getMessage(),
                        '_rawresult' => $resultItem
                    )
                );
            }
        }

        if ( isset( $ezFindQuery['Facet'] )
             && is_array( $ezFindQuery['Facet'] )
             && !empty( $ezFindQuery['Facet'] )
             && $rawResults['SearchExtras'] instanceof ezfSearchResultInfo)
        {
            $facets = array();
            $facetResults = $rawResults['SearchExtras']->attribute('facet_fields');
            foreach( $ezFindQuery['Facet'] as $index => $facetDefinition )
            {
                $facetResult = $facetResults[$index];
                $facets[] = array(
                    'name' => $facetDefinition['name'],
                    'data' => $facetResult['countList']
                );
            }
            $searchResults->facets = $facets;
        }

        return $this->currentEnvironmentSettings->filterSearchResult( $searchResults, $ezFindQueryObject, $builder );
    }

    /**
     * @return string
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * @param string $query
     */
    public function setQuery($query)
    {
        $this->query = $query;
    }

    /**
     * @return EnvironmentSettings
     */
    public function getCurrentEnvironmentSettings()
    {
        return $this->currentEnvironmentSettings;
    }

    /**
     * @param EnvironmentSettings $currentEnvironmentSettings
     */
    public function setCurrentEnvironmentSettings($currentEnvironmentSettings)
    {
        $this->currentEnvironmentSettings = $currentEnvironmentSettings;
    }


}
