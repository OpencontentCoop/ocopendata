<?php

namespace Opencontent\QueryLanguage;

use Opencontent\QueryLanguage\Converter\QueryConverter;
use Opencontent\QueryLanguage\Parser\TokenFactory;

abstract class QueryBuilder implements QueryBuilderInterface
{
    public $fields = array();

    public $metaFields = array();

    public $parameters = array();

    public $operators = array();

    /**
     * @var TokenFactory
     */
    protected $tokenFactory;

    /**
     * @var QueryConverter
     */
    protected $converter;

    public $clauses = array( 'and', 'or' );

    /**
     * @param $string
     *
     * @return Query
     */
    public function instanceQuery( $string )
    {
        $query = new Query( (string) $string );
        $query->setTokenFactory( $this->tokenFactory )
            ->setConverter( $this->converter );

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
     * @return QueryConverter
     */
    public function getConverter()
    {
        return $this->converter;
    }

}
