<?php

namespace Opencontent\QueryLanguage\EzFind;

use Opencontent\QueryLanguage\Converter\Exception;
use Opencontent\QueryLanguage\Parser\TokenFactory;
use Opencontent\QueryLanguage\QueryBuilder;
use Opencontent\QueryLanguage\Query;
use Opencontent\QueryLanguage\EzFind\SingleClassQueryConverter;
use eZContentClass;
use eZContentClassAttribute;

class SingleClassEzFindBuilder extends QueryBuilder
{
    public $fields = array(
        'q'
    );

    public $metaFields = array(
        'id',
        'remote_id',
        'name',
        'published',
        'modified'
    );

    public $parameters = array(
        'sort',
        'limit',
        'offset',
        'classes'
    );

    public $operators = array(
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

    public function __construct( $classIdentifier )
    {
        $this->class = eZContentClass::fetchByIdentifier( $classIdentifier );
        if ( !$this->class instanceof eZContentClass )
        {
            throw new Exception( "Class $classIdentifier not found" );
        }

        /** @var eZContentClassAttribute[] $attributes */
        $attributes = eZContentClassAttribute::fetchFilteredList( array( "contentclass_id" => $this->class->ID,
                                                           "version" => $this->class->Version,
                                                           "is_searchable" => 1) );
        foreach ( $attributes as $attribute )
        {
            $this->classAttributes[$attribute->attribute( 'identifier' )] = $attribute;
        }
        $this->fields = array_merge( $this->fields, $this->metaFields, array_keys( $this->classAttributes ) );
        $this->converter = new SingleClassQueryConverter( $this->classAttributes, $this->metaFields );
        $this->tokenFactory = new TokenFactory( $this->fields, $this->operators, $this->parameters, $this->clauses );
    }

    public function instanceQuery( $string )
    {
        $classQuery = new Query( "classes {$this->class->attribute('identifier')}" );
        $classQuery->setTokenFactory( $this->tokenFactory );

        $query = new Query( $string );
        $query->setTokenFactory( $this->tokenFactory );
        $query->setConverter( $this->converter );

        $query->merge( $classQuery );

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