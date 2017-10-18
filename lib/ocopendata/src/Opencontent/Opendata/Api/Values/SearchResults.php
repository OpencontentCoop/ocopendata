<?php

namespace Opencontent\Opendata\Api\Values;


use Opencontent\Opendata\GeoJson\Feature;
use Opencontent\Opendata\GeoJson\FeatureCollection;

class SearchResults
{
    /**
     * @var string
     */
    public $query;

    /**
     * @var string
     */
    public $nextPageQuery;

    /**
     * @var int
     */
    public $totalCount;

    /**
     * @var Content[]|Feature[]
     */
    public $searchHits = array();

    /**
     * @var array
     */
    public $facets = array();

    public function jsonSerialize()
    {
        $searchHits = array_map(function($value){
            if ($value instanceof Content){
                $value = $value->jsonSerialize();
            }
            return $value;
        }, $this->searchHits);

        return array(
          'query' => $this->query,
          'nextPageQuery' => $this->nextPageQuery,
          'totalCount' => $this->totalCount,
          'searchHits' => $searchHits,
          'facets' => $this->facets,
        );
    }
}
