<?php

namespace Opencontent\Opendata\Api\Values;

use eZContentClass;
use eZContentLanguage;
use eZINI;
use eZContentClassClassGroup;
use eZContentClassAttribute;
use Opencontent\Opendata\Api\AttributeConverterLoader;
use Opencontent\Opendata\Api\Exception\OutOfRangeException;

class ContentClass
{
    public $identifier;

    public $name = array();

    public $description = array();

    public $fields = array();

    public $isContainer;

    public $isSearchable = true;

    public $groups = array();

    public function __construct( array $properties = array() )
    {
        foreach ( $properties as $property => $value )
        {
            if ( property_exists( $this, $property ) ){
                $this->$property = $value;
            }else{
                throw new OutOfRangeException( $property );
            }
        }
    }

    public static function isSearchable( $classIdentifier )
    {
        if ( eZINI::instance( 'ezfind.ini' )->hasVariable( 'IndexExclude', 'ClassIdentifierList' ) )
        {
            $indexExcludeList = (array)eZINI::instance( 'ezfind.ini' )->variable(
                'IndexExclude',
                'ClassIdentifierList'
            );

            return !in_array( $classIdentifier, $indexExcludeList );
        }

        return true;
    }

    /**
     * @param eZContentClass $contentClass
     *
     * @return ContentClass
     */
    public static function createFromEzContentClass( eZContentClass $contentClass )
    {
        $languages = eZContentLanguage::fetchLocaleList();
        $class = new ContentClass();
        $class->identifier = $contentClass->attribute( 'identifier' );
        $class->isContainer = (bool)$contentClass->attribute( 'is_container' );
        $class->isSearchable = self::isSearchable( $contentClass->attribute( 'identifier' ) );

        $names = array();
        foreach ( $languages as $language )
        {
            $name = $contentClass->name( $language );
            if ( !empty( $name ) )
            {
                $names[$language] = $name;
            }
        }
        $class->name = $names;

        $description = array();
        foreach ( $languages as $language )
        {
            $name = $contentClass->description( $language );
            if ( !empty( $name ) )
            {
                $description[$language] = $name;
            }
        }
        $class->description = $description;

        /** @var eZContentClassClassGroup[] $groups */
        $groups = eZContentClassClassGroup::fetchGroupList(
            $contentClass->attribute( "id" ),
            $contentClass->attribute( "version" ),
            true
        );
        $class->groups = array();
        foreach ( $groups as $group )
        {
            $class->groups[] = $group->attribute( 'group_name' );
        }

        /** @var eZContentClassAttribute[] $attributes */
        $attributes = (array)$contentClass->dataMap();
        foreach ( $attributes as $attribute )
        {
            $converter = AttributeConverterLoader::load(
                $class->identifier,
                $attribute->attribute( 'identifier' ),
                $attribute->attribute( 'data_type_string' )
            );

            $attributeNames = array();
            foreach ( $languages as $language )
            {
                $name = $attribute->name( $language );
                if ( !empty( $name ) )
                {
                    $attributeNames[$language] = $name;
                }
            }

            $attributeDescriptions = array();
            foreach ( $languages as $language )
            {
                $name = $attribute->description( $language );
                if ( !empty( $name ) )
                {
                    $attributeDescriptions[$language] = $name;
                }
            }

            $type = $converter->type( $attribute );
            $help = $converter->help( $attribute );
            $template = array( 'type' => $type['identifier'] );
            if ( isset( $type['format'] ) )
            {
                $template['format'] = array( $type['format'] );
            }
            if ( $help )
            {
                $template['help'] = $help;
            }

            $dataTypeInfo = array( 'name' => null );
            /** @var \eZDataType $dataType */
            $dataType = $attribute->dataType();
            if ( $dataType instanceof \eZDataType ){
                $dataTypeInfo = $dataType->attribute( 'information' );
            }
            $class->fields[] = array(
                'identifier' => $attribute->attribute( 'identifier' ),
                'name' => $attributeNames,
                'description' => $attributeDescriptions,
                'dataType' => $attribute->attribute( 'data_type_string' ),
                'dataTypeName' => $dataTypeInfo['name'],
                'template' => $template,
                'isSearchable' => (bool)$attribute->attribute( 'is_searchable' ),
                'isRequired' => (bool)$attribute->attribute( 'is_required' ),
            );
        }

        return $class;
    }

    public static function __set_state( array $array )
    {
        return new static( $array );
    }

    /**
     * @return int
     */
    public function getClassId()
    {
        return eZContentClass::classIDByIdentifier( $this->identifier );
    }

    /**
     * @return eZContentClass
     */
    public function getClassObject()
    {
        return eZContentClass::fetchByIdentifier( $this->identifier );
    }
}
