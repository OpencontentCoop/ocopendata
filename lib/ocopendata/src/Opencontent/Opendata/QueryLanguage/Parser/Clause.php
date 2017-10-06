<?php

namespace Opencontent\QueryLanguage\Parser;

class Clause
{
    protected $clause;

    public function __construct($clause)
    {
        $this->clause = $clause;
    }

    public function __toString()
    {
        return $this->clause;
    }
}
