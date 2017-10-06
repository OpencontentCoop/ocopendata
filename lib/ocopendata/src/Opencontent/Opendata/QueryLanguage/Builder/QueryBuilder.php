<?php

namespace Opencontent\QueryLanguage;

use Opencontent\QueryLanguage\Converter\QueryConverter;
use Opencontent\QueryLanguage\Parser\TokenFactory;

abstract class QueryBuilder
{
    protected $fields = array();

    protected $metaFields = array();

    protected $parameters = array();

    protected $operators = array();

    public $clauses = array('and', 'or');

    /**
     * @var TokenFactory
     */
    protected $tokenFactory;

    /**
     * @var QueryConverter
     */
    protected $converter;

    /**
     * @param $string
     *
     * @return Query
     */
    public function instanceQuery($string)
    {
        $query = new Query((string)$string);
        $query->setTokenFactory($this->tokenFactory)
              ->setConverter($this->converter);

        return $query;
    }

    /**
     * @return TokenFactory
     */
    public function getTokenFactory()
    {
        return $this->tokenFactory;
    }

    /**
     * @param TokenFactory $tokenFactory
     */
    public function setTokenFactory($tokenFactory)
    {
        $this->tokenFactory = $tokenFactory;
    }

    /**
     * @return QueryConverter
     */
    public function getConverter()
    {
        return $this->converter;
    }

    /**
     * @param QueryConverter $converter
     */
    public function setConverter($converter)
    {
        $this->converter = $converter;
    }

    /**
     * @return array
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * @param array $fields
     */
    public function setFields($fields)
    {
        $this->fields = $fields;
    }

    /**
     * @return array
     */
    public function getMetaFields()
    {
        return $this->metaFields;
    }

    /**
     * @param array $metaFields
     */
    public function setMetaFields($metaFields)
    {
        $this->metaFields = $metaFields;
    }

    /**
     * @return array
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * @param array $parameters
     */
    public function setParameters($parameters)
    {
        $this->parameters = $parameters;
    }

    /**
     * @return array
     */
    public function getOperators()
    {
        return $this->operators;
    }

    /**
     * @param array $operators
     */
    public function setOperators($operators)
    {
        $this->operators = $operators;
    }

    /**
     * @return array
     */
    public function getClauses()
    {
        return $this->clauses;
    }

    /**
     * @param array $clauses
     */
    public function setClauses($clauses)
    {
        $this->clauses = $clauses;
    }

}
