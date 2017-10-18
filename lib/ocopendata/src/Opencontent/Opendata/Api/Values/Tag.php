<?php

namespace Opencontent\Opendata\Api\Values;

class Tag implements \JsonSerializable
{
    public $id;

    public $parentId;

    public $hasChildren;

    /**
     * @var Tag[]
     */
    public $children;

    public $synonymsCount;

    public $languageNameArray;

    public $keyword;

    public $keywordTranslations;

    public $url;

    public $icon;

    public function jsonSerialize()
    {
        $this->children = $this->serializeChildren();
        return (array)$this;
    }

    private function serializeChildren()
    {
        $data = array();
        foreach($this->children as $child){
            $data[] = $child->jsonSerialize();
        }
        return $data;
    }

    public function findByKeyword($name)
    {
        if ($this->keyword == $name){
            return $this;
        }
        if ($this->hasChildren){
            return $this->findInChildren($name);
        }
        return null;
    }

    private function findInChildren($name)
    {
        foreach($this->children as $tag){
            $tag = $tag->findByKeyword($name);
            if ($tag instanceof Tag){
                return $tag;
            }
        }
        return null;
    }

}
