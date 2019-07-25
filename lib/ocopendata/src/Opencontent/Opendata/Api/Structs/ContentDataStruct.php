<?php

namespace Opencontent\Opendata\Api\Structs;


use Opencontent\Opendata\Api\AttributeConverterLoader;
use Opencontent\Opendata\Api\Exception\CreateContentException;
use Opencontent\Opendata\Api\Exception\UpdateContentException;


class ContentDataStruct extends \ArrayObject
{
    const METHOD_CREATE = 1;
    const METHOD_UPDATE = 2;

    protected $method;

    public static function __set_state(array $array)
    {
        return new static($array);
    }

    public function jsonSerialize()
    {
        return $this->getArrayCopy();
    }

    protected function throwException( $message )
    {
        if ( $this->method == self::METHOD_UPDATE)
            throw new UpdateContentException( $message );
        elseif ( $this->method == self::METHOD_CREATE)
            throw new CreateContentException( $message );
        else
            throw new \Exception( $message );
    }

    protected function validate(MetadataStruct $metadata, $update = false, PublicationOptions $options = null)
    {
        if (empty($this)){
            $this->throwException("No data found");
        }

        if ($metadata->useDefaultLanguage()) {
            $language = $metadata->languages[0];
            if (!isset( $this[$language] )) {
                $this->exchangeArray(array($language => $this->getArrayCopy()));
            }
        }
        $contentClass = $metadata->getClass()->getClassObject();
        $contentClassIdentifier = $contentClass->attribute('identifier');

        /** @var \eZContentClassAttribute[] $attributes */
        $attributes = $contentClass->dataMap();
        $identifiers = array_keys($attributes);
        foreach ($attributes as $attribute) {
            $identifier = $attribute->attribute('identifier');
            $dataType = $attribute->attribute('data_type_string');
            $isRequired = (bool)$attribute->attribute('is_required');
            $converter = AttributeConverterLoader::load(
                $contentClassIdentifier,
                $identifier,
                $dataType
            );
            foreach ($metadata->languages as $language) {
                if (!isset($this[$language])){
                    $this->throwException("Data in language $language not found");
                }
                $dataTranslation = $this[$language];
                $notValidFields = array_diff(array_keys($dataTranslation), $identifiers);
                if (count($notValidFields) > 0) {
                    $this->throwException("Invalid fields '" . implode("', '", $notValidFields) . "'");
                }
                if (isset( $dataTranslation[$identifier] )) {
                    if (!$update) {
                        $converter->validateOnCreate($identifier, $dataTranslation[$identifier], $attribute);
                    } else {
                        $converter->validateOnUpdate($identifier, $dataTranslation[$identifier], $attribute);
                    }
                    if (!$update && empty($dataTranslation[$identifier])){
                        if ($isRequired) {
                            $this->throwException("Field $identifier is required");
                        }elseif($options && $options->isUpdateNullFields() == true){
                            $this[$language][$identifier] = null;
                        }
                    }
                } elseif (!$update) {
                    if ($isRequired) {
                        $this->throwException("Field $identifier is required");
                    }elseif($options && $options->isUpdateNullFields() == true){
                        $this[$language][$identifier] = null;
                    }
                }
            }
        }
    }

    public function validateOnCreate(MetadataStruct $metadata, PublicationOptions $options = null)
    {
        $this->method = self::METHOD_CREATE;

        $this->validate($metadata, false, $options);
    }

    public function validateOnUpdate(MetadataStruct $metadata, PublicationOptions $options = null)
    {
        $this->method = self::METHOD_UPDATE;

        $this->validate($metadata, true, $options);
    }

}
