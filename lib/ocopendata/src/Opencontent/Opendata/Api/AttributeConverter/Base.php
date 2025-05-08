<?php

namespace Opencontent\Opendata\Api\AttributeConverter;

use eZContentObjectAttribute;
use eZContentClassAttribute;
use Opencontent\Opendata\Api\Exception\InvalidInputException;
use Opencontent\Opendata\Api\PublicationProcess;

class Base
{
    /**
     * @var string
     */
    protected $identifier;

    /**
     * @var string
     */
    protected $classIdentifier;


    public function __construct(
        $classIdentifier,
        $identifier
    ) {
        $this->classIdentifier = $classIdentifier;
        $this->identifier = $identifier;
    }

    public function getIdentifier()
    {
        return $this->identifier;
    }

    /**
     * @param eZContentObjectAttribute $attribute
     *
     * @return array|string|int|null|\JsonSerializable
     */
    public function get(eZContentObjectAttribute $attribute)
    {
        $data = array(
            'id' => intval($attribute->attribute('id')),
            'version' => intval($attribute->attribute('version')),
            'identifier' => $this->classIdentifier . '/' . $this->identifier,
            'datatype' => $attribute->attribute('data_type_string'),
            'contentclassattribute_id' => $attribute->attribute('contentclassattribute_id'),
            'sort_key_int' => $attribute->attribute('sort_key_int'),
            'sort_key_string' => $attribute->attribute('sort_key_string'),
            'data_text' => $attribute->attribute('data_text'),
            'data_int' => $attribute->attribute('data_int'),
            'data_float' => $attribute->attribute('data_float'),
            'is_information_collector' => $attribute->attribute('is_information_collector'),
            'content' => $this->attributeContent($attribute),
        );

        return $data;
    }

    private function attributeContent(eZContentObjectAttribute $attribute)
    {
        if ($attribute->attribute('data_type_string') == \eZSelectionType::DATA_TYPE_STRING){
            return $attribute->toString();
        }
        return $attribute->hasContent() ? $attribute->toString() : null;
    }

    public function set($data, PublicationProcess $process)
    {
        return $data;
    }

    public static function validate($identifier, $data, eZContentClassAttribute $attribute)
    {
        if ($data !== null && $data !== false && !is_scalar($data)) {
            throw new InvalidInputException('Invalid type', $identifier, $data);
        }
    }

    public function validateOnCreate($identifier, $data, eZContentClassAttribute $attribute)
    {
        static::validate($identifier, $data, $attribute);
    }

    public function validateOnUpdate($identifier, $data, eZContentClassAttribute $attribute)
    {
        static::validate($identifier, $data, $attribute);
    }

    /**
     * @param eZContentClassAttribute $attribute
     *
     * @return string|null
     */
    public function help(eZContentClassAttribute $attribute)
    {
        return null;
    }

    public function type(eZContentClassAttribute $attribute)
    {
        if ($attribute->attribute('is_information_collector')) {
            return array('identifier' => 'readonly');
        }

        return array('identifier' => 'string');
    }

    public static function clean()
    {

    }

    public function toCSVString($content, $params = null)
    {
        return is_string($content) ? $content : '';
    }

    public function onPublishNullData(
        eZContentObjectAttribute $attribute,
        PublicationProcess $process
    ): bool {
        return false;
    }
}
