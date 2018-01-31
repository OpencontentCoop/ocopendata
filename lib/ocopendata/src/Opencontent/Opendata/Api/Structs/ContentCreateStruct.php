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

    /**
     * @var PublicationOptions
     */
    public $options;

    public function __construct(MetadataStruct $metadata, ContentDataStruct $data, PublicationOptions $options = null)
    {
        $this->metadata = $metadata;
        $this->data = $data;
        $this->options = $options instanceof PublicationOptions ? $options : new PublicationOptions();
    }

    public function validate()
    {
        $this->metadata->validateOnCreate();
        $this->metadata->checkAccess();
        $this->data->validateOnCreate( $this->metadata, $this->options );
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

        $options = array();
        if (isset( $array['options'] )) {
            $options = $array['options'];
        }

        return new static(
            new MetadataStruct($metadata),
            new ContentDataStruct($data),
            new PublicationOptions($options)
        );
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
