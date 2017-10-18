<?php

namespace Opencontent\Opendata\GeoJson;

class Properties
{
    public function __construct( array $properties = array() )
    {
        foreach( $properties as $key => $value )
        {
            $this->{$key} = $value;
        }
    }

    public function add( $key, $value )
    {
        $this->{$key} = $value;
    }
}