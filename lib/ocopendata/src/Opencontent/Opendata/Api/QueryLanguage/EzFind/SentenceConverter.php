<?php

namespace Opencontent\Opendata\Api\QueryLanguage\EzFind;

use Opencontent\Opendata\Api\ClassRepository;
use Opencontent\Opendata\Api\StateRepository;
use Opencontent\Opendata\Api\SectionRepository;
use Opencontent\Opendata\Api\Values\ContentSection;
use Opencontent\Opendata\Api\Values\ContentState;
use Opencontent\QueryLanguage\Parser\Sentence;
use Opencontent\QueryLanguage\Converter\Exception;
use ArrayObject;
use ezfSolrDocumentFieldBase;
use Opencontent\QueryLanguage\Parser\Token;

class SentenceConverter
{
    /**
     * @var ArrayObject
     */
    protected $convertedQuery;

    /**
     * @var StateRepository
     */
    protected $stateRepository;

    /**
     * @var SectionRepository
     */
    protected $sectionRepository;

    /**
     * @var ClassRepository
     */
    protected $classRepository;

    /**
     * @var SolrNamesHelper
     */
    protected $solrNamesHelper;

    protected static $isRecurrenceEnabled;

    public function __construct( SolrNamesHelper $solrNamesHelper )
    {
        $this->solrNamesHelper = $solrNamesHelper;

        $this->stateRepository = new StateRepository();
        $this->sectionRepository = new SectionRepository();
        $this->classRepository = new ClassRepository();
    }

    /**
     * @param ArrayObject $convertedQuery
     */
    public function setCurrentConvertedQuery( ArrayObject $convertedQuery )
    {
        $this->convertedQuery = $convertedQuery;
    }

    /**
     * @param Sentence $sentence
     * @return array|mixed|null
     * @throws Exception
     * @throws \Opencontent\Opendata\Api\Exception\NotFoundException
     */
    public function convert( Sentence $sentence )
    {
        $field = $sentence->getField();
        $operator = (string)$sentence->getOperator();
        $value = $sentence->getValue();

        if ( $field == 'q' )
        {
            $this->convertedQuery['_query'] = $this->cleanValue($sentence->stringValue());
            return null;
        }

        $value = $this->cleanValue( $value );



        if ( $field->data( 'is_function_field' ) )
        {
            switch( $field->data( 'function' ) )
            {
                case 'calendar':
                    return $this->convertCalendar( $field, $operator, $value );
                    break;

                case 'raw':
                    $fieldNames = Sentence::parseString( str_replace( 'raw', '', (string)$field ) );
                    $fieldNames = array_combine($fieldNames, $fieldNames);
                    break;
            }
        }

        if ( !isset( $fieldNames ) )
        {
            $fieldNames = $this->solrNamesHelper->generateFieldNames( $field, 'filter' );
        }

        switch ( $operator )
        {
            case 'contains':
            case '!contains':
                {
                    $data = $this->generateContainsFilter(
                        $fieldNames,
                        $value,
                        $operator == '!contains'
                    );
                }
                break;

            case 'in':
            case '!in':
                {
                    $data = $this->generateInFilter( $fieldNames, $value, $operator == '!in' );
                }
                break;

            case 'range':
            case '!range':
                {
                    $data = $this->generateRangeFilter( $fieldNames, $value, $operator == '!range' );
                }
                break;

            case '=':
            case '!=':
                {
                    $data = $this->generateEqFilter( $fieldNames, $value, $operator == '!=' );
                }
                break;

            default:
                throw new Exception( "Operator $operator not handled" );
        }
        return ( empty( $data ) ) ? null : $data;
    }

    /**
     * @param Token $field
     * @param $operator
     * @param $value
     * @return array|bool|mixed
     * @throws Exception
     * @throws \Opencontent\Opendata\Api\Exception\NotFoundException
     */
    protected function convertCalendar( Token $field, $operator, $value )
    {
        $fields = Sentence::parseString( str_replace( 'calendar', '', (string)$field ) );

        if ( $operator != '=' ){
            throw new Exception( "The operator of function field 'calendar' must be '=' (e.g: calendar[from_time, to_time] = [yesterday, today])" );
        }

        if ( !is_array( $value ) || ( is_array( $value ) && count( $value ) != 2 ) ){
            throw new Exception( "The value of function field 'calendar' requires a two elements array (e.g: calendar[from_time, to_time] = [yesterday, today])" );
        }

        $fromDateValue = $this->formatFilterValue( $value[0], 'date' );
        $toDateValue = $this->formatFilterValue( $value[1], 'date' );

        if (self::isRecurrenceEnabled())
        {
            \OCRecurrenceHelper::addSolrFieldTypeMap();            
            if ( empty( $fields ) )
            {
                try
                {
                    //se l'attributo non è contemplato in base al parameter 'classes', viene sollevata eccezione
                    $this->solrNamesHelper->getDatatypesByIdentifier( 'recurrences' );
                    $fields = array( 'from_time', 'to_time', 'recurrences' );
                }
                catch( Exception $e )
                {
                    $fields = array( 'from_time', 'to_time' );
                }
            }
        }
        else
        {
            if ( empty( $fields ) )
            {
                $fields = array( 'from_time', 'to_time' ); //default
            }

            if ( count( $fields ) !== 2 )
            {
                throw new Exception( "Function field 'calendar' requires two parameters (e.g: calendar[from_time, to_time] = [yesterday, today])" );
            }
        }

        $fromField = $toField = $recurrenceField = $fromToFilter = $recurrenceFilter = false;
        if ( count( $fields ) >= 2 )
        {
            $fromField = $fields[0];
            $toField = $fields[1];
            if (isset($fields[2])){
                $recurrenceField = $fields[2];
            }
        }
        elseif( count( $fields ) == 1 )
        {
            $recurrenceField = $fields[0];
        }

        if ($fromField && $toField)
        {
            $fromFieldNames = $this->solrNamesHelper->generateFieldNames( $fromField );
            $toFieldNames = $this->solrNamesHelper->generateFieldNames( $toField );

            $fromToFilter = $this->convertCalendarDateTimeFields($fromFieldNames, $toFieldNames, $fromDateValue, $toDateValue);
        }

        if ($recurrenceField && self::isRecurrenceEnabled()){
            $recurrencesFiledNames = $this->solrNamesHelper->generateFieldNames( $recurrenceField );
            $fromTimestampValue = $value[0] == '*' ? \OCRecurrenceHelper::MIN_BOUND : $this->formatFilterValue( $value[0], 'timestamp' );
            $toTimestampValue = $value[1] == '*' ? \OCRecurrenceHelper::MAX_BOUND : $this->formatFilterValue( $value[1], 'timestamp' );
            $recurrenceFilter = $this->convertCalendarRecurrenceFields($recurrencesFiledNames, $fromTimestampValue, $toTimestampValue);
        }

        if ( $fromToFilter && $recurrenceFilter )
        {
            $filter = array( 'or', $fromToFilter, $recurrenceFilter );
        }
        elseif( $fromToFilter )
        {
            $filter = $fromToFilter;
        }
        else
        {
            $filter = $recurrenceFilter;
        }

        return $filter;
    }

    /**
     * @param $recurrencesFiledNames
     * @param $fromTimestampValue
     * @param $toTimestampValue
     * @return array|mixed
     * @throws Exception
     */
    private function convertCalendarRecurrenceFields($recurrencesFiledNames, $fromTimestampValue, $toTimestampValue)
    {
        $minBound = \OCRecurrenceHelper::MIN_BOUND;
        $maxBound = \OCRecurrenceHelper::MAX_BOUND;
        $filter = array();
        foreach ($recurrencesFiledNames as $type => $fieldName)
        {
            if ( substr($fieldName, -3) !== '_dp')
            {
                throw new Exception( "Function field 'calendar' arguments must be a date or recurrence identifier" );
            }

            $filter[] = $fieldName . ":\"Intersects($minBound $fromTimestampValue $toTimestampValue $maxBound)\"";
        }

        if ( count( $filter ) == 1 )
            $filter = array_pop( $filter );
        elseif ( count( $filter ) > 1 )
            array_unshift( $filter, 'or' );

        return $filter;
    }

    /**
     * @param $fromFieldNames
     * @param $toFieldNames
     * @param $fromValue
     * @param $toValue
     * @return array|mixed
     * @throws Exception
     */
    private function convertCalendarDateTimeFields($fromFieldNames, $toFieldNames, $fromValue, $toValue)
    {
        $fieldCouples = array();
        $index = 0;
        foreach( $fromFieldNames as $type => $fieldName )
        {
            $typeParts = explode( '.', $type );
            $type = array_pop( $typeParts );
            if ( $type != 'date' && substr($fieldName, -3) !== '_dt')
            {
                throw new Exception( "Function field 'calendar' arguments must be a date or recurrence identifier" );
            }
            $fieldCouples[] = array(
                'from' =>  $fieldName
            );
            $index++;
        }

        $index = 0;
        foreach( $toFieldNames as $type => $fieldName )
        {
            $typeParts = explode( '.', $type );
            $type = array_pop( $typeParts );
            if ( $type != 'date' && substr($fieldName, -3) !== '_dt')
            {
                throw new Exception( "Function field 'calendar' arguments must be a date identifier" );
            }
            if ( isset( $fieldCouples[$index] ) )
                $fieldCouples[$index]['to'] = $fieldName;
            $index++;
        }

        $filter = array();
        foreach( $fieldCouples as $fieldCouple )
        {
            $toValueAndCondition = $toValue == '*' ? $fromValue : $toValue;
            $item = array(
                'or',
                $fieldCouple['from'] . ':[' . $fromValue . ' TO ' . $toValue . ']',
                $fieldCouple['to'] . ':[' . $fromValue . ' TO ' . $toValue . ']',
                array(
                    'and',
                    $fieldCouple['from'] . ':[* TO ' . $fromValue . ']',
                    $fieldCouple['to'] . ':[' . $toValueAndCondition . ' TO *]'
                )
            );
            $filter[] = $item;
        }

        if ( count( $filter ) == 1 )
            $filter = array_pop( $filter );
        elseif ( count( $filter ) > 1 )
            array_unshift( $filter, 'or' );

        return $filter;
    }

    protected static function isRecurrenceEnabled()
    {
        if ( self::$isRecurrenceEnabled === null )
        {
            self::$isRecurrenceEnabled = in_array( 'ocevents', \eZExtension::activeExtensions() ) && class_exists('OCRecurrenceHelper');
        }

        return self::$isRecurrenceEnabled;
    }

    protected function cleanValue( $value )
    {
        if ( is_array( $value ) )
        {
            $data = array();
            foreach( $value as $item )
            {
                $item = str_replace( "\'", "'", $item );
                $item = str_replace( "\)", ")", $item );
                $item = str_replace( "\(", "(", $item );
                $data[] = trim( $item, "'" );
            }
        }
        else
        {
            $value = str_replace( "\'", "'", $value );
            $value = str_replace( "\)", ")", $value );
            $value = str_replace( "\(", "(", $value );
            $data = trim( $value, "'" );
        }
        return $data;
    }

    /**
     * @param $value
     * @param $type
     * @return false|float|int|string
     * @throws Exception
     * @throws \Opencontent\Opendata\Api\Exception\NotFoundException
     */
    protected function formatFilterValue( $value, $type )
    {
        $typeParts = explode( '.', $type );
        $type = array_pop( $typeParts );
        switch ( $type )
        {
            case 'meta_id';
            case 'meta_owner_id';
            case 'tint':
            case 'sint':
            case 'int':
                $value = (int)$value;
                break;

            case 'float':
            case 'double':
            case 'sfloat':
            case 'tfloat':
                $value = (float)$value;
                break;

            case 'string':
                //$value = '((*' . strtolower( $value ) . '*) OR "' . $value . '"")';
                $value = '"' . $value . '"';
                break;

            case 'meta_class_name_ms':
            case 'meta_name':
            case 'sub_string':
                $value = '"' . $value . '"';
                break;

            case 'meta_published':
            case 'meta_modified':
            case 'date':
                {
                    if ( $value != '*' )
                    {
                        $time = new \DateTime( $value, new \DateTimeZone('UTC') );

                        if ( !$time instanceof \DateTime)
                        {
                            throw new Exception( "Problem with date $value" );
                        }
                        $value = '"' . ezfSolrDocumentFieldBase::convertTimestampToDate( $time->format('U') ) . '"';
                    }
                }
                break;

            case 'timestamp':
                {
                    $time = new \DateTime( $value, new \DateTimeZone('UTC') );

                    if ( !$time instanceof \DateTime)
                    {
                        throw new Exception( "Problem with date $value" );
                    }
                    $value = strtotime($value);
                }
                break;

            case 'meta_section_id':
                {
                    $section = $this->sectionRepository->load( $value );
                    if ( $section instanceof ContentSection )
                    {
                        $value = (int)$section['id'];
                    }
                }
                break;

            case 'meta_object_states':
                {
                    $state = $this->stateRepository->load( $value );
                    if ( $state instanceof ContentState )
                    {
                        $value = (int)$state['id'];
                    }
                }
                break;
        }

        return $value;
    }

    /**
     * @param $fieldNames
     * @param $value
     * @param $negative
     * @return array|mixed
     * @throws Exception
     * @throws \Opencontent\Opendata\Api\Exception\NotFoundException
     */
    protected function generateContainsFilter( $fieldNames, $value, $negative )
    {
        if ( $negative ) $negative = '!';

        $filter = array();
        foreach( $fieldNames as $type => $fieldName )
        {
            if ( is_array( $value ) )
            {
                if ( count( $value ) > 1 )
                {
                    $data = array( 'and' );
                    foreach ( $value as $item )
                    {
                        $data[] = $negative . $fieldName . ':' . $this->formatFilterValue( $item, $type );
                    }
                }
                else
                {
                    $data = $negative . $fieldName . ':' . $this->formatFilterValue( $value[0], $type );
                }
            }
            else
            {
                $data = $negative . $fieldName . ':' . $this->formatFilterValue( $value, $type );
            }
            $filter[] = $data;
        }

        if ( count( $filter ) == 1 )
            $filter = array_pop( $filter );
        elseif ( count( $filter ) > 1 )
            array_unshift( $filter, 'or' );

        return $filter;
    }

    /**
     * @param $fieldNames
     * @param $value
     * @param $negative
     * @return array|mixed
     * @throws Exception
     * @throws \Opencontent\Opendata\Api\Exception\NotFoundException
     */
    protected function generateInFilter( $fieldNames, $value, $negative )
    {
        if ( $negative ) $negative = '!';

        $filter = array();
        foreach( $fieldNames as $type => $fieldName )
        {
            if ( is_array( $value ) )
            {
                if ( count( $value ) > 1 )
                {
                    $data = array( 'or' );
                    foreach ( $value as $item )
                    {
                        $data[] = $negative . $fieldName . ':' . $this->formatFilterValue( $item, $type );
                    }
                }
                else
                {
                    $data = $negative . $fieldName . ':' . $this->formatFilterValue( $value[0], $type );
                }
            }
            else
            {
                $data = $negative . $fieldName . ':' . $this->formatFilterValue( $value, $type );
            }
            $filter[] = $data;
        }

        if ( count( $filter ) == 1 )
            $filter = array_pop( $filter );
        elseif ( count( $filter ) > 1 )
            array_unshift( $filter, 'or' );

        return $filter;
    }

    /**
     * @param $fieldNames
     * @param $value
     * @param $negative
     * @return array|mixed
     * @throws Exception
     * @throws \Opencontent\Opendata\Api\Exception\NotFoundException
     */
    protected function generateRangeFilter( $fieldNames, $value, $negative )
    {
        if ( !is_array( $value ) )
            throw new Exception( "Range require an array value" );

        if ( $negative ) $negative = '!';

        $filter = array();
        foreach( $fieldNames as $type => $fieldName )
        {
            $filter[] = $negative . $fieldName . ':[' . $this->formatFilterValue( $value[0], $type ) . ' TO ' . $this->formatFilterValue( $value[1], $type ) . ']';
        }

        if ( count( $filter ) == 1 )
            $filter = array_pop( $filter );
        elseif ( count( $filter ) > 1 )
            array_unshift( $filter, 'or' );

        return $filter;
    }

    /**
     * @param $fieldNames
     * @param $value
     * @param $negative
     * @return array|mixed
     * @throws Exception
     * @throws \Opencontent\Opendata\Api\Exception\NotFoundException
     */
    protected function generateEqFilter( $fieldNames, $value, $negative )
    {
        return $this->generateInFilter( $fieldNames, $value, $negative );
    }

}
