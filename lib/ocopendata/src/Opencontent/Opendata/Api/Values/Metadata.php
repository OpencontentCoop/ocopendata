<?php

namespace OpenContent\Opendata\Api\Values;

use Opencontent\Opendata\Api\Exception\OutOfRangeException;

class Metadata
{
    public $id;

    public $name;

    public $remoteId;

    public $ownerId;

    public $classIdentifier;

    public $nodeIds;

    public $parentNodeIds;

    public $sectionIdentifier;

    public $statusIdentifiers;

    public $published;

    public $modified;

    public $language;

    public function __construct( array $properties = array() )
    {
        foreach ( $properties as $property => $value )
        {
            if ( property_exists( $this, $property ) )
                $this->$property = $value;
            else
                throw new OutOfRangeException( $property );
        }
    }

    public static function __set_state( array $array )
    {
        return new static( $array );
    }
}