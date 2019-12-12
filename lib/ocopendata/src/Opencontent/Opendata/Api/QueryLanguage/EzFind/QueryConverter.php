<?php

namespace Opencontent\Opendata\Api\QueryLanguage\EzFind;

use Opencontent\QueryLanguage\Converter\QueryConverter as QueryConverterInterface;
use Opencontent\QueryLanguage\Query;
use Opencontent\QueryLanguage\Parser\Item;
use Opencontent\QueryLanguage\Parser\Parameter;
use ArrayObject;


class QueryConverter implements QueryConverterInterface
{
    /**
     * @var Query
     */
    protected $query;

    /**
     * @var ArrayObject
     */
    protected $convertedQuery;

    /**
     * @var SentenceConverter
     */
    protected $sentenceConverter;

    /**
     * @var ParameterConverter
     */
    protected $parameterConverter;

    public function __construct(
        SentenceConverter $sentenceConverter,
        ParameterConverter $parameterConverter
    ){
        $this->parameterConverter = $parameterConverter;
        $this->sentenceConverter = $sentenceConverter;
    }

    public function setQuery( Query $query )
    {
        $this->query = $query;
    }

    /**
     * @return ArrayObject
     */
    public function convert()
    {
        if ( $this->query instanceof Query )
        {
            $this->convertedQuery = new ArrayObject(
                array( '_query' => null )
            );
            $this->parameterConverter->setCurrentConvertedQuery( $this->convertedQuery );
            $this->sentenceConverter->setCurrentConvertedQuery( $this->convertedQuery );

            $this->convertParameters();
            $this->convertFilters();

            if ( isset( $this->convertedQuery['Filter'] ) && empty( $this->convertedQuery['Filter'] ) )
            {
                unset( $this->convertedQuery['Filter'] );
            }
        }
        return $this->convertedQuery;
    }

    protected function convertFilters()
    {
        $filters = array();        
        foreach ( $this->query->getFilters() as $item )
        {
            $filter = $this->parseItem( $item );
            if ( !empty( $filter ) && $filter !== null )
            {
                $filter = $this->flatFilter( $filter );                
                $filters[] = $filter;
            }
        }

        $filters = $this->cleanFilters($filters);

        if ( !empty( $filters ) )
        {
            $this->convertedQuery['Filter'] = (array)$filters;
        }
    }

    // elimina innestamenti superflui
    private function flatFilter( $filter )
    {
        if ( is_array( $filter ) )
        {
            if ( count( $filter ) == 1 )
            {
                $filter = array_pop( $filter );
                
                return $this->flatFilter( $filter );
            }
            else
            {
                $flat = array();
                foreach ($filter as $item) 
                {
                    $flat[] = $this->flatFilter( $item );
                }
            }
            
            return $flat;
        }
        else{
            return $filter;
        }
    }

    // use cleanFilter per correggere innestamenti superflui
    private function cleanFilters( $filters )
    {
        $cleanFilters = array();                        
        
        foreach ($filters as $filter) 
        {
            $this->cleanFilter( $filter, $cleanFilters );
        }

        return $this->flatFilter( $cleanFilters );
    }

    private function cleanFilter( $filter, &$stack )
    {
        if ( is_array( $filter ) )
        {
            if ( $filter[0] == 'or' || $filter[0] == 'and' )
            {
                // $stack[] = $filter;                
                $subStack = array();
                foreach ( $filter as $item )
                {
                    if ( is_array( $item ) )
                    {
                        $itemStack = array();
                        $this->cleanFilter( $item, $itemStack );
                        $subStack[] = $itemStack;
                    }
                    else
                    {
                        $subStack[] = $item;
                    }                    
                }
                $stack[] = $subStack;
            }
            else
            {
                foreach ( $filter as $item )
                {
                    $this->cleanFilter( $item, $stack );
                }
            }
        }
        else
        {
            $stack[] = $filter;
        }        
    }

    protected function convertParameters()
    {
        foreach ( $this->query->getParameters() as $parameters )
        {
            foreach ( $parameters->getSentences() as $parameter )
            {
                if ( $parameter instanceof Parameter )
                {
                    $this->parameterConverter->convert( $parameter );
                }
            }
        }
    }

    protected function parseItem( Item $item )
    {
        $filters = array();
        if ( $item->hasSentences() || $item->clause == 'or' )
        {
            if ( $item->clause == 'or' )
            {
                $filters[] = (string)$item->clause;
            }

            foreach ( $item->getSentences() as $sentence )
            {
                $result = $this->sentenceConverter->convert( $sentence );
                if ( $result !== null )
                    $filters[] = $result;
            }
            if ( $item->hasChildren() )
            {
                foreach ( $item->getChildren() as $child )
                {
                    $filters[] = $this->parseItem( $child );
                }
            }
        }
        return $filters;
    }

}