<?php

namespace Opencontent\QueryLanguage\Parser;

class Parameter extends Sentence
{
    protected $key;

    protected $value;

    public function setKey(Token $data)
    {
        $this->key = $data;
    }

    public function getKey()
    {
        return $this->key;
    }

    public function setValue(Token $data)
    {
        $this->value = $data;
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
