<?php

namespace Opencontent\QueryLanguage\EzFind;

use Opencontent\QueryLanguage\Converter\QueryConverter as QueryConverterInterface;
use Opencontent\QueryLanguage\Parser\Parameter;
use Opencontent\QueryLanguage\Parser\Sentence;
use Opencontent\QueryLanguage\Query;
use Opencontent\QueryLanguage\Parser\Item;
use Opencontent\QueryLanguage\Converter\Exception;
use eZContentClassAttribute;
use ezfSolrDocumentFieldBase;
use eZSolr;

class SingleClassQueryConverter implements QueryConverterInterface
{
    /**
     * @var Query
     */
    protected $query;

    /**
     * @var array
     */
    protected $convertedQuery;

    /**
     * @var eZContentClassAttribute[]
     */
    protected $classAttributes;

    /**
     * @var array
     */
    protected $metaFields;

    public function __construct( array $classAttributes, array $metaFields )
    {
        $this->classAttributes = $classAttributes;
        $this->metaFields = $metaFields;
    }

    public function setQuery( Query $query )
    {
        $this->query = $query;
    }

    public function convert()
    {
        $this->convertedQuery = array( '_query' => null );
        if ( $this->query instanceof Query )
        {
            $filters = array();
            foreach ( $this->query->getFilters() as $item )
            {
                $filter = $this->parseItem( $item );
                if ( !empty( $filter ) )
                {
                    $filters[] = $filter;
                }
            }
            if ( !empty( $filters ) )
            {
                $this->convertedQuery['Filter'] = $filters;
            }

            foreach ( $this->query->getParameters() as $parameters )
            {
                foreach ( $parameters->getSentences() as $parameter )
                {
                    if ( $parameter instanceof Parameter )
                    {
                        $this->convertParameter( $parameter );
                    }
                }
            }
        }

        return $this->convertedQuery;
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
                if ( $sentence->getField() == 'q' )
                {
                    $this->convertedQuery['_query'] = $sentence->stringValue();
                }
                else
                    $filters[] = $this->convertSentence( $sentence );
            }
        }
        if ( $item->hasChildren() )
        {
            foreach ( $item->getChildren() as $child )
            {
                $filters[] = $this->parseItem( $child );
            }
        }

        return $filters;
    }

    protected function convertParameter( Parameter $parameter )
    {
        $originalKey = (string)$parameter->getKey();
        $value = $parameter->getValue();

        switch( $originalKey )
        {
            case 'classes':
                $key = 'SearchContentClassID';
                if ( !is_array( $value ) )
                {
                    $value = array( $value );
                }
                break;

            case 'sort':
            {
                $key = 'SortBy';

                if ( is_array( $value ) )
                {
                    $data = array();
                    foreach( $value as $field => $order )
                    {
                        $fieldName = $this->generateSortName( $field );
                        if ( !in_array( $order, array( 'asc', 'desc' ) ) )
                        {
                            throw new Exception( "Can not convert sort order value" );
                        }
                        $data[$fieldName] = $order;
                    }
                    $value = $data;
                }
                else
                {
                    throw new Exception( "Sort parameter require an hash value" );
                }

            } break;

            case 'limit':
            {
                $key = 'SearchLimit';
                if ( is_array( $value ) )
                {
                    throw new Exception( "Limit parameter require an integer value" );
                }
                else
                {
                    $value = intval( $value );
                }
            } break;

            case 'offset':
            {
                $key = 'SearchOffset';
                if ( is_array( $value ) )
                {
                    throw new Exception( "Offset parameter require an integer value" );
                }
                else
                {
                    $value = intval( $value );
                }
            } break;

            default:
                throw new Exception( "Can not convert $originalKey parameter" );
        }

        $this->convertedQuery[$key] = $value;
    }

    protected function generateSortName( $field )
    {
        $attribute = isset( $this->classAttributes[$field]) ? $this->classAttributes[$field] : null;
        if ( $attribute instanceof eZContentClassAttribute )
        {
            $data = ezfSolrDocumentFieldBase::getFieldName( $attribute, null, 'sort' );
        }
        elseif ( in_array( $field, $this->metaFields ) )
        {
            $data = eZSolr::getMetaFieldName( $field, 'sort' );
        }
        else
        {
            throw new Exception( "Can not convert field $field" );
        }
        return $data;
    }

    protected function convertSentence( Sentence $sentence )
    {
        $field = (string)$sentence->getField();
        $operator = (string)$sentence->getOperator();
        $value = $sentence->getValue();

        $value = $this->cleanValue( $value );

        if ( strpos( $this->generateFieldName( $field ), '_dt' ) > 0 )
        {
            $value = $this->convertDateTimeValue( $value );
        }

        switch ( $operator )
        {
            case 'contains':
            case '!contains':
            {
                $data = $this->generateContainsFilter( $field, $value, $operator == '!contains' );
            } break;

            case 'in':
            case '!in':
            {
                $data = $this->generateInFilter( $field, $value, $operator == '!in' );
            } break;

            case 'range':
            case '!range':
            {
                $data = $this->generateRangeFilter( $field, $value, $operator == '!range' );
            } break;

            case '=':
            case '!=':
            {
                $data = $this->generateFilter( $field, $value, $operator == '!=' );
            } break;

            default:
                $data = $this->generateFilter( $field, $value );
        }


        return $data;

    }

    protected function generateFieldName( $field )
    {
        $attribute = isset( $this->classAttributes[$field]) ? $this->classAttributes[$field] : null;
        if ( $attribute instanceof eZContentClassAttribute )
        {
            switch( $attribute->attribute( 'data_type_string' ) )
            {
                case 'ezobjectrelationlist':
                case 'ezobjectrelation':
                {
                    $fieldName = ezfSolrDocumentFieldBase::generateSubattributeFieldName( $attribute, 'name', 'string' );
                } break;

                case 'ezinteger':
                {
                    $fieldName = ezfSolrDocumentFieldBase::generateAttributeFieldName( $attribute, 'sint' );
                } break;

                default:
                    $fieldName = ezfSolrDocumentFieldBase::getFieldName( $attribute );
            }

        }
        elseif ( in_array( $field, $this->metaFields ) )
        {
            $fieldName = ezfSolrDocumentFieldBase::generateMetaFieldName( $field );
        }
        else
        {
            throw new Exception( "Can not convert field $field" );
        }
        return $fieldName;
    }

    protected function needQuotes( $field )
    {
        $addQuote = false;
        $attribute = isset( $this->classAttributes[$field]) ? $this->classAttributes[$field] : null;
        if ( $attribute instanceof eZContentClassAttribute )
        {
            if ( $attribute->attribute( 'data_type_string' ) == 'ezobjectrelationlist'
                 || $attribute->attribute( 'data_type_string' ) == 'ezobjectrelation')
            {
                $addQuote = true;
            }
        }
        if ( strpos( $this->generateFieldName( $field ), '_dt' ) > 0 )
            $addQuote = true;
        return $addQuote;
    }

    protected function cleanValue( $value )
    {
        if ( is_array( $value ) )
        {
            $data = array();
            foreach( $value as $item )
            {
                $data[] = trim( $item, "'" );
            }
        }
        else
        {
            $data = trim( $value, "'" );
        }
        return $data;
    }

    protected function convertDateTimeValue( $value )
    {
        if ( is_array( $value ) )
        {
            $data = array();
            foreach( $value as $item )
            {
                $data[] = ezfSolrDocumentFieldBase::convertTimestampToDate( strtotime( $item ) );
            }
        }
        else
        {
            $data = ezfSolrDocumentFieldBase::convertTimestampToDate( strtotime( $value ) );
        }
        return $data;
    }

    protected function generateFilter( $field, $value, $not = false )
    {
        if ( $not )
            $not = '!';

        $fieldName = $this->generateFieldName( $field );
        $addQuote = $this->needQuotes( $field );

        if ( is_array( $value ) )
        {
            $data = array( 'and' );
            foreach ( $value as $item )
            {
                $data[] = $not . $fieldName . ':' . $this->addQuote( $item, $addQuote );
            }
        }
        else
        {
            $data = $not . $fieldName . ':' . $this->addQuote( $value, $addQuote );
        }

        return $data;
    }

    protected function generateContainsFilter( $field, $value, $not = false )
    {
        if ( $not )
            $not = '!';

        $fieldName = $this->generateFieldName( $field );

        if ( is_array( $value ) )
        {
            $data = array( 'and' );
            foreach ( $value as $item )
            {
                $data[] = $not . $fieldName . ':' . '((*' . strtolower( $item ) . '*) OR ' . strtolower( $item ) . ')';
            }
        }
        else
        {
            $data = $not . $fieldName . ':' . '((*' . strtolower( $value ) . '*) OR ' . strtolower( $value ) . ')';
        }

        return $data;
    }

    protected function generateInFilter( $field, $value, $not = false )
    {
        if ( $not )
            $not = '!';

        $fieldName = $this->generateFieldName( $field );

        if ( is_array( $value ) )
        {
            $data = array( 'or' );
            foreach ( $value as $item )
            {
                $data[] = $not . $fieldName . ':' . $item;
            }
        }
        else
        {
            $data = $not . $fieldName . ':' . $value;
        }

        return $data;
    }

    protected function generateRangeFilter( $field, $value, $not = false )
    {
        if ( $not )
            $not = '!';

        $fieldName = $this->generateFieldName( $field );
        $addQuote = $this->needQuotes( $field );
        if ( is_array( $value ) )
        {
            return $not . $fieldName . ':[' . $this->addQuote( $value[0], $addQuote ) . ' TO ' . $this->addQuote( $value[1], $addQuote ) . ']';
        }
        throw new Exception( "Range require an array value" );
    }

    protected function addQuote( $value, $addQuote )
    {
        if ( $addQuote )
        {
            $value = '"' . $value .'"';
        }
        return $value;
    }
}