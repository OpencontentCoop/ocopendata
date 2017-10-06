<?php

namespace Opencontent\QueryLanguage\EzFind;

use Opencontent\QueryLanguage\Converter\Exception;
use Opencontent\QueryLanguage\Parser\TokenFactory;
use Opencontent\QueryLanguage\QueryBuilder;
use Opencontent\QueryLanguage\Query;
use eZContentClass;
use eZContentClassAttribute;

class SingleClassEzFindBuilder extends QueryBuilder
{
    protected $fields = array(
        'q'
    );

    protected $metaFields = array(
        'id',
        'remote_id',
        'name',
        'published',
        'modified'
    );

    protected $parameters = array(
        'sort',
        'limit',
        'offset',
        'classes'
    );

    protected $operators = array(
        '=',
        '!=',
        'in',
        '!in',
        'contains',
        '!contains',
        'range',
        '!range'
    );

    /**
     * @var eZContentClass
     */
    protected $class;

    /**
     * @var eZContentClassAttribute[]
     */
    protected $classAttributes;

    public function __construct($classIdentifier)
    {
        $this->class = eZContentClass::fetchByIdentifier($classIdentifier);
        if (!$this->class instanceof eZContentClass) {
            throw new Exception("Class $classIdentifier not found");
        }

        /** @var eZContentClassAttribute[] $attributes */
        $attributes = eZContentClassAttribute::fetchFilteredList(array(
            "contentclass_id" => $this->class->ID,
            "version" => $this->class->Version,
            "is_searchable" => 1
        ));
        foreach ($attributes as $attribute) {
            $this->classAttributes[$attribute->attribute('identifier')] = $attribute;
        }
        $this->fields = array_merge($this->fields, $this->metaFields, array_keys($this->classAttributes));
        $this->converter = new SingleClassQueryConverter($this->classAttributes, $this->metaFields);
        $this->tokenFactory = new TokenFactory();
        $this->tokenFactory->setFields($this->fields)
                           ->setOperators($this->operators)
                           ->setParameters($this->parameters)
                           ->setClauses($this->clauses);
    }

    public function instanceQuery($string)
    {
        $classQuery = new Query("classes {$this->class->attribute('identifier')}");
        $classQuery->setTokenFactory($this->tokenFactory);

        $query = new Query($string);
        $query->setTokenFactory($this->tokenFactory);
        $query->setConverter($this->converter);

        $query->merge($classQuery);

        return $query;
    }

    public function getClass()
    {
        return $this->class;
    }

    public function getClassAttributes()
    {
        return $this->classAttributes;
    }

    public function getMetaFields()
    {
        return $this->metaFields;
    }

    public function getOperators()
    {
        return $this->operators;
    }

    public function getParameters()
    {
        return $this->parameters;
    }
}
