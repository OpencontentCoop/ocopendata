<?php

use Opencontent\Opendata\Api\SearchResultDecoratorInterface;
use Opencontent\Opendata\Api\Values\SearchResults;
use Opencontent\Opendata\Api\QueryLanguage\EzFind\SearchResultInfo;

/*
 * $queryParamList['stats'] = 'true';
 * $queryParamList['stats.field'] = 'meta_section_id_si';
 * $queryParamList['stats.facet'] = ['meta_owner_id_si', 'extra_project_name_s'];
 *
 *  fetch( 'ezfind', 'search', hash(
 *        'extended_attribute_filter', array(
 *            hash(
 *             'id', 'stats',
 *             'params', hash(
 *                 'field', 'meta_section_id_si',
 *                 'facet', array('meta_owner_id_si', 'extra_project_name_s')
 *             )
 *            )
 *        )
 *  ))
 *
 */

class OpendataStatsExtendedAttributeFilter implements eZFindExtendedAttributeFilterInterface, SearchResultDecoratorInterface
{
    public function filterQueryParams(array $queryParams, array $filterParams)
    {
        if (!isset($filterParams['field'])) {
            throw new Exception('Missing filter parameter "field" in stats');
        }

        $fields = $filterParams['field'];
        if (!is_array($fields)){
            $fields = [$fields];
        }

        foreach ($fields as $field) {
            $fieldName = eZSolr::getFieldName($field);
            $queryParams['stats'] = 'true';
            $queryParams['stats.field'][] = $fieldName;

            if (isset($filterParams['facet'])) {
                $facetsParams = $filterParams['facet'];
                if (!is_array($facetsParams)) {
                    $facetsParams = array($facetsParams);
                }
                $facetNameList = array();
                foreach ($facetsParams as $facetParam) {
                    $facetName = eZSolr::getFieldName($facetParam, false, 'facet');
                    $facetNameList[] = $facetName;
                }
                $queryParams['stats.facet'] = $facetNameList;
            }
        }

        return $queryParams;
    }

    public function decorate(SearchResults $searchResults, $rawResults)
    {
        $searchExtra = SearchResultInfo::fromEzfSearchResultInfo($rawResults['SearchExtras']);
        $result = $searchExtra->attribute('result');
        if (isset($result['stats'])) {
            $searchResults->stats = $result['stats'];
        }
    }
}
