<?php

namespace Opencontent\Opendata\Api\AttributeConverter;

use eZContentClass;
use eZContentClassAttribute;
use eZContentObjectAttribute;
use Opencontent\Opendata\Api\Exception\InvalidInputException;
use eZTagsObject;
use Opencontent\Opendata\Api\PublicationProcess;

class Tags extends Base
{
    private $parentTagId;

    public function get(eZContentObjectAttribute $attribute)
    {
        $content = parent::get($attribute);
        $tags = [];
        if ($attribute->attribute('data_type_string') === \eZTagsType::DATA_TYPE_STRING) {
            $tags = $attribute->content()->attribute('keywords');
        } else {
            $tagsList = explode(', ', $attribute->metaData());
            foreach ($tagsList as $tag) {
                $tag = trim($tag);
                if (!empty($tag)) {
                    $tags[] = $tag;
                }
            }
        }
        $content['content'] = $tags;
        return $content;
    }

    private function getParentTagId()
    {
        if ($this->parentTagId === null) {
            $this->parentTagId = 0;
            $class = eZContentClass::fetchByIdentifier($this->classIdentifier);
            if ($class instanceof eZContentClass) {
                $attribute = $class->fetchAttributeByIdentifier($this->identifier);
                if ($attribute instanceof eZContentClassAttribute) {
                    $this->parentTagId = (int)$attribute->attribute('data_int1');
                }
            }
        }

        return $this->parentTagId;
    }

    public function set($data, PublicationProcess $process)
    {
        if (empty($data)) {
            return null;
        }

        $tagIDs = array();
        $tagKeywords = array();
        $tagParents = array();
        $tagLanguages = array();

        foreach ((array)$data as $keyword) {

            $keywordsFound = eZTagsObject::fetchByKeyword($keyword);
            if (!empty($keywordsFound)) {
                if ($this->getParentTagId() > 0) {
                    foreach ($keywordsFound as $keywordFound) {
                        $pathArray = explode('/', trim($keywordFound->attribute('path_string'), '/'));
                        if (in_array($this->getParentTagId(), $pathArray)) {
                            $tagIDs[] = $keywordFound->attribute('id');
                            $tagKeywords[] = $keywordFound->attribute('keyword');
                            $tagParents[] = $keywordFound->attribute('parent_id');
                            $tagLanguages[] = \eZLocale::currentLocaleCode();
                        }
                    }
                }else{
                    $tagIDs[] = $keywordsFound[0]->ID;
                    $tagKeywords[] = $keywordsFound[0]->Keyword;
                    $tagParents[] = $keywordsFound[0]->ParentID;
                    $tagLanguages[] = \eZLocale::currentLocaleCode();
                }
            } else {
                $tagIDs[] = 0;
                $tagKeywords[] = $keyword;
                $tagParents[] = $this->getParentTagId();
                $tagLanguages[] = \eZLocale::currentLocaleCode();
            }
        }

        $tagIDs = implode('|#', $tagIDs);
        $tagKeywords = implode('|#', $tagKeywords);
        $tagParents = implode('|#', $tagParents);
        $tagLanguages = implode('|#', $tagLanguages);

        $data = $tagIDs . '|#' . $tagKeywords . '|#' . $tagParents . '|#' . $tagLanguages;

        return parent::set($data, $process);
    }

    public static function validate($identifier, $data, eZContentClassAttribute $attribute)
    {
        if (is_array($data)) {
            foreach ($data as $item) {
                if (!is_string($item)) {
                    throw new InvalidInputException('Invalid data', $identifier, $data);
                }
            }
        }
    }

    public function toCSVString($content, $params = null)
    {
        return implode(',', $content);
    }

    public function type(eZContentClassAttribute $attribute)
    {
        return array(
            'identifier' => 'tag',
            'format' => 'array'
        );
    }
}
