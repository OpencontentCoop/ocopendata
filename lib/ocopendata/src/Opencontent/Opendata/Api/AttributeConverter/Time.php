<?php

namespace Opencontent\Opendata\Api\AttributeConverter;

use eZContentClassAttribute;
use Opencontent\Opendata\Api\Exception\InvalidInputException;

class Time extends Base
{

    public function type(eZContentClassAttribute $attribute)
    {
        return array('identifier' => 'hh:mm:ss');
    }

    public static function validate($identifier, $data, eZContentClassAttribute $attribute)
    {
        parent::validate($identifier, $data, $attribute);
        $parts = explode(':', $data);
        if (count($parts) < 2) {
            throw new InvalidInputException('Invalid type', $identifier, $data);
        }
    }
}
