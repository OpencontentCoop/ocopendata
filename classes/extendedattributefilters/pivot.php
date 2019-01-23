<?php

use Opencontent\Opendata\Api\SearchResultDecoratorInterface;
use Opencontent\Opendata\Api\Values\SearchResults;
use Opencontent\Opendata\Api\QueryLanguage\EzFind\SearchResultInfo;

// $queryParamList['facet.pivot'] = 'meta_owner_id_si,extra_project_name_s,attr_ore_lavorative_effettive_s';
// $queryParamList['facet.pivot.mincount'] = 0;

class OpendataPivotExtendedAttributeFilter implements eZFindExtendedAttributeFilterInterface, SearchResultDecoratorInterface
{
	public function filterQueryParams( array $queryParams, array $filterParams )
    {
    	if (isset($filterParams['facet'])){
            $queryParams['facet.pivot'] = is_array($filterParams['facet']) ? implode(',', $filterParams['facet']) : $filterParams['facet'];

            if (isset($filterParams['mincount'])){
                $queryParams['facet.pivot.mincount'] = (int)$filterParams['mincount'];
            }
        }        
        
        return $queryParams;
    }

    public function decorate(SearchResults $searchResults, $rawResults)
    {
        $searchExtra = SearchResultInfo::fromEzfSearchResultInfo($rawResults['SearchExtras']);
        $result = $searchExtra->attribute('result');
        if (isset($result['facet_counts']['facet_pivot'])) {
            $searchResults->pivot = $result['facet_counts']['facet_pivot'];
        }        
    }
}