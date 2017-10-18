<?php

namespace Opencontent\Opendata\Api\AttributeConverter;

use eZContentObjectAttribute;
use eZContentClassAttribute;
use Opencontent\Opendata\Api\PublicationProcess;
use Opencontent\Opendata\Api\Exception\InvalidInputException;

class Boolean extends Base
{
    public function get( eZContentObjectAttribute $attribute )
    {
        $content = parent::get( $attribute );
        $content['content'] = (int)$content['content'];
        return $content;
    }

    public function set( $data, PublicationProcess $process )
    {
        return (int) $data;
    }

    public function type( eZContentClassAttribute $attribute )
    {
        return array( 'identifier' => 'boolean' );
    }

    public static function validate($identifier, $data, eZContentClassAttribute $attribute)
    {
        if ($data !== null && !is_bool($data) && !is_integer($data)) {
            throw new InvalidInputException('Invalid type', $identifier, $data);
        }
    }
}
