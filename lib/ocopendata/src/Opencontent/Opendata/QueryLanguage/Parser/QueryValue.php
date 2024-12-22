<?php

namespace Opencontent\QueryLanguage\Parser;

class QueryValue extends ArrayValue
{
    /**
     * @var string
     */
    private $query;

    /**
     * @var callable
     */
    private $subQueryResolver;

    public function __construct(string $query, callable $subQueryResolver)
    {
        $this->query = $query;
        $this->subQueryResolver = $subQueryResolver;
    }

    public function __toString()
    {
        return $this->query;
    }

    public function toArray(): array
    {
        return ($this->subQueryResolver)(
            substr($this->query, 2, (mb_strlen($this->query) - 4))
        );
    }
}