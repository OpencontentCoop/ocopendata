<?php

namespace Opencontent\Opendata\Api\Values;

class ContentSection implements \IteratorAggregate, \ArrayAccess
{
    protected $data = array();

    public function __construct( array $array = array() )
    {
        $this->data = $array;
    }

    public function getIterator()
    {
        return new \ArrayIterator( $this->data );
    }

    public function count()
    {
        return count( $this->data );
    }

    public static function __set_state( array $array )
    {
        $object = new static();
        foreach( $array as $key => $value )
        {
            $object->{$key} = $value;
        }
        return $object;
    }

    public function offsetExists( $offset )
    {
        return isset( $this->data[$offset] );
    }

    public function offsetGet( $offset )
    {
        return $this->data[$offset];
    }

    public function offsetSet( $offset, $value )
    {
        $this->data[$offset] = $value;
    }

    public function offsetUnset( $offset )
    {
        unset( $this->data[$offset] );
    }

    public function jsonSerialize()
    {
        return $this->data;
    }

}