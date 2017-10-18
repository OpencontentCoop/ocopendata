<?php

namespace Opencontent\Opendata\Api\AttributeConverter;

use eZContentObjectAttribute;
use eZContentClassAttribute;
use Opencontent\Opendata\Api\Exception\InvalidInputException;
use Opencontent\Opendata\Api\PublicationProcess;

class Selection extends Base
{
    public function get(eZContentObjectAttribute $attribute)
    {
        $content = parent::get($attribute);
        $content['content'] = \eZStringUtils::explodeStr($content['content'], '|');

        return $content;
    }

    public function set($data, PublicationProcess $process)
    {
        $data = (array)$data;
        if (empty($data)){
            return " ";
        }else{
            return \eZStringUtils::implodeStr((array)$data, '|');
        }
    }

    public static function validate($identifier, $data, eZContentClassAttribute $attribute)
    {
        if (is_array($data)) {
            $values = self::getClassAttributeContentValues($attribute);
            foreach($data as $name){
                if (!in_array($name, $values)){
                    throw new InvalidInputException('Invalid selection', $identifier, $data);
                }
            }
        }
    }

    public function type(eZContentClassAttribute $attribute)
    {
        return array(
            'identifier' => 'selection',
            'format' => 'array',
            'allowed_values' => self::getClassAttributeContentValues($attribute)
        );
    }

    public function toCSVString($content, $params = null)
    {
        if (is_array($content) && isset( $content['value'] )) {
            return \eZStringUtils::implodeStr((array)$content['value'], '|');
        }

        return '';
    }

    private static function getClassAttributeContentValues(eZContentClassAttribute $attribute)
    {
        $eZSelectionType = new \eZSelectionType();
        $classContent = $eZSelectionType->classAttributeContent($attribute);
        $values = array();
        foreach($classContent['options'] as $option){
            $values[] = $option['name'];
        }
        return $values;
    }
}
