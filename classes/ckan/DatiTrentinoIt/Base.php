<?php


namespace Opencontent\Ckan\DatiTrentinoIt;


class Base implements \JsonSerializable
{
    protected $data = array();

    public function getData( $key = null )
    {
        if ( $key )
            return isset( $this->data[$key] ) ? $this->data[$key] : null;
        return $this->data;
    }

    public function setData( $key, $value )
    {
        $this->data[$key] = $value;
        return $this;
    }

    function jsonSerialize()
    {
        $vars = array_filter(
            get_object_vars( $this ),
            function ( $item, $key )
            {
                if ( $key == 'data' )
                {
                    return false;
                }

                // Keep only not-NULL values
                return !is_null( $item );
            },
            ARRAY_FILTER_USE_BOTH
        );
        ksort($vars);

        return $vars;
    }

    public static function fromArray( array $data )
    {

        $instance = new static();
        foreach ( $data as $key => $value )
        {
            if ( property_exists( $instance, $key ) )
            {
                $instance->{$key} = $value;
            }
            else
            {
                $instance->data[$key] = $value;
            }
        }

        return $instance;
    }
}
