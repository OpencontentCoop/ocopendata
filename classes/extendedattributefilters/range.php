<?php

use Opencontent\Opendata\Api\SearchResultDecoratorInterface;
use Opencontent\Opendata\Api\Values\SearchResults;
use Opencontent\Opendata\Api\QueryLanguage\EzFind\SearchResultInfo;

class OpendataRangeExtendedAttributeFilter implements eZFindExtendedAttributeFilterInterface, SearchResultDecoratorInterface
{
    public function filterQueryParams( array $queryParamList, array $filterParams )
    {
        if ( !empty( $filterParams['field'])
             && !empty( $filterParams['start'])
             && !empty( $filterParams['end'])
             && !empty( $filterParams['gap'])
        ){
            $fieldName = $filterParams['field'];

            $perFieldRangePrefix = 'f.' . $fieldName . '.facet.range';

            $queryParamList['facet.range'] = $fieldName;

            $queryParamList[$perFieldRangePrefix . '.start'] = $this->formatValue($fieldName, $filterParams['start']);
            $queryParamList[$perFieldRangePrefix . '.end'] = $this->formatValue($fieldName, $filterParams['end']);
            $queryParamList[$perFieldRangePrefix . '.gap'] = '+' . trim(ltrim($filterParams['gap'], '+'));

            if( !empty( $filterParams['hardend'])){
                $queryParamList[$perFieldRangePrefix . '.hardend'] = $filterParams['hardend'];
            }

            if( !empty( $filterParams['include'])){
                $queryParamList[$perFieldRangePrefix . '.include'] = $filterParams['include'];
            }

            if( !empty( $filterParams['other'])){
                $queryParamList[$perFieldRangePrefix . '.other'] = $filterParams['other'];
            }
        }

        return $queryParamList;
    }

    private function formatValue($fieldName, $value)
    {
        if (strpos($fieldName, '_dt') !== false){
            $time = new \DateTime( $value, new \DateTimeZone('UTC') );
            return ezfSolrDocumentFieldBase::convertTimestampToDate( $time->format('U') );
        }
            
        return $value;     
    }

    public function decorate(SearchResults $searchResults, $rawResults)
    {
        $searchExtra = SearchResultInfo::fromEzfSearchResultInfo($rawResults['SearchExtras']);
        $result = $searchExtra->attribute('result');
        if (isset($result['facet_counts']['facet_ranges'])) {
            $searchResults->facet_range = $result['facet_counts']['facet_ranges'];
        }
    }
}
