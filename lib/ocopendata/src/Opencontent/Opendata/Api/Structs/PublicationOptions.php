<?php

namespace Opencontent\Opendata\Api\Structs;

class PublicationOptions extends \SQLIContentPublishOptions
{
    public function __construct( array $options = array() )
    {
        $this->properties = array(
            'parent_node_id'            => null,
            'modification_check'        => false,
            'copy_prev_version'         => true,
            'update_null_field'         => false, // If true, will update any field in DB, even if data is not set (null)
        );

        parent::__construct( $options );
    }
}
