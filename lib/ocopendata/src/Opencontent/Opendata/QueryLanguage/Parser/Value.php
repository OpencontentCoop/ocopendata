<?php

namespace Opencontent\QueryLanguage\Parser;

class Value
{
    protected $data = '';

    public function __construct($data = null)
    {
        if (!self::isEmpty($data)) {
            $this->data = $data;
        }
    }

    public static function isEmpty($data)
    {
        if (is_string($data) && trim($data) == ''){
            return true;
        }

        if (is_array($data) && count($data) == 0){
            return true;
        }

        return false;
    }

    public function __toString()
    {
        return (string)$this->data;
    }
}
