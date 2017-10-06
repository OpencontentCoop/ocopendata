<?php

namespace Opencontent\QueryLanguage\Parser;

class Item
{
    private static $count = 0;

    public $id;

    public $clause;

    /**
     * @var Item[]
     */
    public $children = array();

    public $sentences = array();

    public function __construct()
    {
        self::$count++;
        $this->id = self::$count;
    }

    public function addChild(Item $item)
    {
        $this->children[] = $item;
        $this->id = $item->id;
    }

    public function hasChildren()
    {
        return count($this->children) > 0;
    }

    public function hasSentences()
    {
        foreach ($this->children as $child) {
            if ($child->hasSentences()) {
                return true;
            }
        }

        return count($this->sentences) > 0;
    }

    public function getChildren()
    {
        return $this->children;
    }

    /**
     * @return Sentence[]
     */
    public function getSentences()
    {
        return $this->sentences;
    }

    public function add(Sentence $sentence)
    {
        $this->sentences[] = $sentence;
    }

    public function __toString()
    {
        return (string)$this->id;
    }
}
