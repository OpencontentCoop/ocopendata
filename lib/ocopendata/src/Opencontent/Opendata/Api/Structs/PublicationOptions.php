<?php

namespace Opencontent\Opendata\Api\Structs;

class PublicationOptions extends \ezcBaseOptions
{
    public function __construct( array $options = array() )
    {
        $this->properties = array(
            'modification_check'        => false,
            'update_null_field'         => false, // If true, will update any field in DB, even if data is not set (null)
            'copy_prev_version_fields'  => array()
        );

        parent::__construct( $options );
    }

    public function __set( $optionName, $optionValue )
    {
        if( !array_key_exists( $optionName, $this->properties ) )
            throw new \ezcBasePropertyNotFoundException( $optionName );

        $this->properties[$optionName] = $optionValue;
    }

    public function isUpdateNullFields()
    {
        return $this->update_null_field == true;
    }

    public function isCopyPrevVersionField($identifier)
    {
        return in_array($identifier, $this->copy_prev_version_fields);
    }

    public function getSQLIContentPublishOptions()
    {
        return new \SQLIContentPublishOptions(array(
            'parent_node_id'            => null,
            'modification_check'        => $this->modification_check,
            'copy_prev_version'         => true,
            'update_null_field'         => $this->update_null_field,
        ));
    }
}
