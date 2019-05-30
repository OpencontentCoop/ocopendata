<?php

namespace Opencontent\QueryLanguage\Parser;

class Parameter extends Sentence
{
    /**
     * @var Token
     */
    protected $key;

    /**
     * @var Value
     */
    protected $value;

    public function setKey( Token $data )
    {
        $this->key = $data;
    }

    public function getKey()
    {
        return $this->key;
    }

    public function isValid()
    {
        return $this->key !== null && $this->value !== null;
    }

    public function __toString()
    {
        return $this->getKey() . ' ' . $this->stringValue();
    }
}