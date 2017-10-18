<?php

namespace Opencontent\Opendata\Api\Structs;

use Opencontent\Opendata\Api\Exception\OutOfRangeException;

class ContentCreateStruct implements \ArrayAccess
{
    /**
     * @var MetadataStruct
     */
    public $metadata;

    /**
     * @var ContentDataStruct
     */
    public $data;

    public function __construct(MetadataStruct $metadata, ContentDataStruct $data)
    {
        $this->metadata = $metadata;
        $this->data = $data;
    }

    public function validate()
    {
        $this->metadata->validateOnCreate();
        $this->data->validateOnCreate( $this->metadata );
    }

    public static function fromArray(array $array)
    {
        $metadata = array();
        if (isset( $array['metadata'] )) {
            $metadata = $array['metadata'];
        }
        $data = array();
        if (isset( $array['data'] )) {
            $data = $array['data'];
        }

        return new static(
            new MetadataStruct($metadata),
            new ContentDataStruct($data)
        );
    }

    public function checkAccess( \eZUser $user )
    {
        $this->metadata->checkAccess( $user );
    }

    public function offsetExists($property)
    {
        return isset( $this->{$property} );
    }

    public function offsetGet($property)
    {
        return $this->{$property};
    }

    public function offsetSet($property, $value)
    {
        if ( property_exists( $this, $property ) )
            $this->{$property} = $value;
        else
            throw new OutOfRangeException( $property );
    }

    public function offsetUnset($property)
    {
        $this->{$property} = null;
    }

}