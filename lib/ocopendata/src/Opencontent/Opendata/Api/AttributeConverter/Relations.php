<?php

namespace Opencontent\Opendata\Api\AttributeConverter;

use eZContentObjectAttribute;
use eZContentClassAttribute;
use eZContentObject;
use Opencontent\Opendata\Api\ContentRepository;
use Opencontent\Opendata\Api\EnvironmentSettings;
use Opencontent\Opendata\Api\Exception\InvalidInputException;
use Opencontent\Opendata\Api\Gateway\FileSystem;
use Opencontent\Opendata\Api\PublicationProcess;
use Opencontent\Opendata\Api\Values\Content;
use Opencontent\Opendata\Api\Values\Metadata;

class Relations extends Base
{

    /**
     * @var FileSystem
     */
    protected static $gateway;

    protected static function gateway()
    {
        if (self::$gateway === null) {
            self::$gateway = new FileSystem();
        }

        return self::$gateway;
    }

    private function parseAttributeContent(eZContentObjectAttribute $attribute)
    {
        $content = $attribute->content();
        $data = null;
        if ($content instanceof eZContentObject) {
            $data = array(
                Metadata::createFromEzContentObject($content)
            );
        } elseif (isset( $content['relation_list'] )) {
            $data = array();
            foreach ($content['relation_list'] as $relationItem) {
                $id = (int)$relationItem['contentobject_id'];
                $contentObject = eZContentObject::fetch($id);
                if ($contentObject instanceof eZContentObject) {
                    $dataItem = Metadata::createFromEzContentObject($contentObject);
                    $extra = array();
                    if (isset( $relationItem['extra_fields'] )) {
                        foreach ($relationItem['extra_fields'] as $extraFieldKey => $extraFieldValue) {
                            if (is_array($extraFieldValue) && isset( $extraFieldValue['identifier'] )) {
                                $extraFieldValue = $extraFieldValue['identifier'];
                            }
                            //$extraFieldKey = \ezfSolrDocumentFieldBase::generateSubattributeFieldName( $attribute->contentClassAttribute(), $extraFieldKey, 'string' );
                            $extra[$extraFieldKey] = $extraFieldValue;
                        }
                    }
                    if (isset( $content['extra_fields_attribute_level'] )) {
                        foreach ($content['extra_fields_attribute_level'] as $extraFieldKey => $extraFieldValue) {
                            if (is_array($extraFieldValue) && isset( $extraFieldValue['identifier'] )) {
                                $extraFieldValue = $extraFieldValue['identifier'];
                            }
                            //$extraFieldKey = \ezfSolrDocumentFieldBase::generateSubattributeFieldName( $attribute->contentClassAttribute(), $extraFieldKey, 'string' );
                            $extra[$extraFieldKey] = $extraFieldValue;
                        }
                    }
                    if (!empty($extra)){
                        $dataItem->addExtra('in_context', $extra);
                    }
                    $data[] = $dataItem;
                }
            }
        }

        return $data;
    }

    public function get(eZContentObjectAttribute $attribute)
    {
        $content = parent::get($attribute);
        $content['content'] = $this->parseAttributeContent($attribute);

        return $content;
    }

    public function set($data, PublicationProcess $process)
    {
        $data = self::findContents($data);

        //@todo handle image and files
        return empty( $data['ids'] ) ? null : implode('-', $data['ids']);
    }

    public static function validate($identifier, $data, eZContentClassAttribute $attribute)
    {
        if (is_array($data)) {
            foreach ($data as $item) {
                if (is_array($item)) {
                    if (isset( $data['image'] )) {
                        Image::validate($identifier, $data, $attribute);
                    } elseif (isset( $data['file'] )) {
                        File::validate($identifier, $data, $attribute);
                    } elseif (isset( $data['remoteId'] )) {
                        try {
                            self::gateway()->loadContent($data['remoteId']);
                        } catch (\Exception $e) {
                            throw new InvalidInputException('Invalid content identifier', $identifier, array($item));
                        }
                    } elseif (isset( $data['id'] )) {
                        try {
                            self::gateway()->loadContent($data['id']);
                        } catch (\Exception $e) {
                            throw new InvalidInputException('Invalid content identifier', $identifier, array($item));
                        }
                    } else {
                        throw new InvalidInputException('Invalid input', $identifier, array($item));
                    }
                } else {
                    try {
                        self::gateway()->loadContent($item);
                    } catch (\Exception $e) {
                        throw new InvalidInputException('Invalid content identifier', $identifier, array($item));
                    }
                }
            }
        } else {
            throw new InvalidInputException('Invalid data', $identifier, $data);
        }
    }

    protected static function findContents($data)
    {
        $result = array(
            'images' => array(),
            'files' => array(),
            'ids' => array()
        );
        foreach ($data as $item) {
            if (is_array($item)) {
                if (isset( $data['image'] )) {
                    $result['images'][] = $item;
                } elseif (isset( $data['file'] )) {
                    $result['files'][] = $item;
                }
            } else {
                $content = self::gateway()->loadContent($item);
                $result['ids'][] = $content->metadata->id;
            }
        }

        return $result;
    }

    public function type(eZContentClassAttribute $attribute)
    {
        $data = array(
            'identifier' => 'array of id or remoteId or file or image'
        );

        return $data;
    }

    public function toCSVString($content, $language = null)
    {
        $data = array();
        foreach ($content as $metadata) {
            $data[] = $metadata['name'][$language];
        }

        return implode("\n", $data);
    }
}
