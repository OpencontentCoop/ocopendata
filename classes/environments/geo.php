<?php

use Opencontent\Opendata\Api\EnvironmentSettings;
use Opencontent\Opendata\Api\Values\Content;
use Opencontent\Opendata\Api\Values\ContentData;
use Opencontent\Opendata\GeoJson\FeatureCollection;
use Opencontent\Opendata\GeoJson\Feature;

class GeoEnvironmentSettings extends EnvironmentSettings
{

    protected $maxSearchLimit = 500;

    protected $defaultSearchLimit = 500;

    public function filterContent(Content $content)
    {
        $language = isset( $this->request->variables['language'] ) ? $this->request->variables['language'] : null;

        return $content->geoJsonSerialize($language);
    }

    public function filterSearchResult(
        \Opencontent\Opendata\Api\Values\SearchResults $searchResults,
        \ArrayObject $query,
        \Opencontent\QueryLanguage\QueryBuilder $builder
    ) {

        $searchResults = parent::filterSearchResult( $searchResults, $query, $builder );

        $collection = new FeatureCollection();
        /** @var Feature $content */
        foreach ($searchResults->searchHits as $i => $content) {
            if ($this->issetGeoDistFilter($query)) {
                $content->properties->add('index', ++$i);
            }
            $collection->add($content);
        }
        $collection->query = $searchResults->query;
        $collection->nextPageQuery = $searchResults->nextPageQuery;
        $collection->totalCount = $searchResults->totalCount;
        $collection->facets = $searchResults->facets;

        return $collection;
    }

    protected function issetGeoDistFilter(\ArrayObject $query)
    {
        if (isset( $query['ExtendedAttributeFilter'] )) {
            foreach ($query['ExtendedAttributeFilter'] as $filter) {
                if ($filter['id'] == 'geodist') {
                    return true;
                }
            }
        }

        return false;
    }

    public function filterQuery(\ArrayObject $query, \Opencontent\QueryLanguage\QueryBuilder $builder)
    {
        if (!$this->issetGeoDistFilter($query)) {
            $filters = array();
            /** @var Opencontent\Opendata\Api\QueryLanguage\EzFind\QueryBuilder $builder */
            $fields = $builder->getSolrNamesHelper()->getIdentifiersByDatatype('ezgmaplocation');
            foreach ($fields as $field) {
                $field = $builder->getSolrNamesHelper()->generateSolrSubFieldName($field, 'coordinates', 'geopoint');
                $filters[] = "{$field}:[-90,-90 TO 90,90]";
                //                $field = $builder->getSolrNamesHelper()->generateSolrSubFieldName($field, 'latitude', 'float');
                //                $filters[] = "{$field}:[* TO *]";
            }

            if (!empty( $filters )) {
                if (!isset( $query['Filter'] )) {
                    $query['Filter'] = array();
                }
                if (count($filters) > 1) {
                    array_unshift($filters, 'or');
                }
                $query['Filter'][] = $filters;
            } else {
                throw new RuntimeException("No attribute type ezgmaplocation found");
            }
        }

        return parent::filterQuery($query, $builder);
    }
}