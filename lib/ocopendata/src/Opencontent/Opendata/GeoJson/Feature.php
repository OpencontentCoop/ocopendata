<?php

namespace Opencontent\Opendata\GeoJson;

use Opencontent\Opendata\Api\Values\Content;

class Feature
{
    public $id;
    public $geometry;
    public $type = "Feature";
    public $properties;


    public function __construct( $id, Geometry $geometry, Properties $properties )
    {
        $this->id = $id;
        $this->geometry = $geometry;
        $this->properties = $properties;
    }
}