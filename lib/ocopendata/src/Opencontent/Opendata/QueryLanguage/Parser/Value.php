<?php

namespace Opencontent\QueryLanguage\Parser;

class Value
{
    protected $data = '';

    public function __construct($data = null)
    {
        if (!empty($data)) {
            $this->data = $data;
        }
    }

    public function __toString()
    {
        return (string)$this->data;
    }
}
