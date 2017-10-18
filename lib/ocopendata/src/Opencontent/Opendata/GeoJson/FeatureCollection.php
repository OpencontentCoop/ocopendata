<?php

namespace Opencontent\Opendata\GeoJson;

use Opencontent\Opendata\Api\Values\SearchResults;

class FeatureCollection
{
    public $type = 'FeatureCollection';
    public $features = array();

    public function add( Feature $feature )
    {
        $this->features[] = $feature;
    }
}