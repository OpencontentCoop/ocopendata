<?php

namespace Opencontent\Opendata\Api;

use eZContentClass;
use Opencontent\Opendata\Api\Exception\NotFoundException;
use Opencontent\Opendata\Api\Values\ContentClass;
use eZDir;
use eZSys;
use eZClusterFileHandler;
use eZDB;
use eZExpiryHandler;
use eZPHPCreator;
use eZContentObject;

class ClassRepository
{
    private static $identifierHash = null;

    private static $classes = array();

    /**
     * @param $identifier
     *
     * @return ContentClass
     * @throws NotFoundException
     */
    public function load( $identifier )
    {
        return $this->internalLoadClass( $identifier );

    }

    protected function internalLoadClass( $identifier )
    {
        if (!isset(self::$classes[$identifier])) {
            self::$classes[$identifier] = $this->getCacheManager($identifier)->processCache(
                array(__CLASS__, 'retrieveCache'),
                array(__CLASS__, 'generateCache'),
                null,
                null,
                $identifier
            );
            //force regenerate avoiding fatal handling a eZClusterFileFailure instead of ContentClass
            if (self::$classes[$identifier] instanceof \eZClusterFileFailure){
                self::$classes[$identifier] = $this->getCacheManager($identifier)->processCache(
                    null,
                    array(__CLASS__, 'generateCache'),
                    null,
                    null,
                    $identifier
                );
            }
        }

        return self::$classes[$identifier];
    }

    protected static function findClass( $identifier )
    {
        $class = eZContentClass::fetchByIdentifier( $identifier );
        if ( !$class instanceof eZContentClass )
        {
            throw new NotFoundException( $identifier, 'Class' );
        }

        return ContentClass::createFromEzContentClass( $class );
    }

    /**
     * @return ContentClass[]
     */
    public function listAll()
    {
        $classes = array();
        $classList = self::classIdentifiersHash();
        ksort( $classList );

        $db = eZDB::instance();
        $list = $db->arrayQuery(
            'SELECT id, identifier, serialized_name_list ' .
            'FROM ezcontentclass ' .
            'WHERE id IN (' . implode( ',', $classList ) . ')'
        );

        $classIdentifierBlackList = array();
        if ( EnvironmentLoader::ini()->hasVariable(
            'ContentSettings',
            'ClassIdentifierBlackListForExternal' )
        ){
            $classIdentifierBlackList = (array)EnvironmentLoader::ini()->variable(
                'ContentSettings',
                'ClassIdentifierBlackListForExternal'
            );
        }

        $nameList = new \eZContentClassNameList();
        foreach ($list as $item) {
            if (!in_array($item['identifier'], $classIdentifierBlackList)) {
                $nameList->initFromSerializedList($item['serialized_name_list']);
                $classes[] = array(
                    'name' => $nameList->name(),
                    'nameList' => $nameList->NameList,
                    'identifier' => $item['identifier'],
                    'contents' => 0
                );
            }
        }

        return $classes;
    }

    public function listClassIdentifiers()
    {
        $classList = self::classIdentifiersHash();
        return array_keys( $classList );
    }

    /**
     * @return array
     */
    public function listAttributesGroupedByIdentifier()
    {
        $attributes = array();
        $classList = self::classIdentifiersHash();
        foreach ( $classList as $identifier => $id )
        {
            if ( ContentClass::isSearchable( $identifier ) )
            {
                $class = $this->internalLoadClass( $identifier );
                if ( $class instanceof ContentClass )
                {
                    foreach ( $class->fields as $field )
                    {
                        if ( $field['isSearchable'] )
                        {
                            if ( !isset( $attributes[$field['identifier']] ) )
                            {
                                $attributes[$field['identifier']] = array();
                            }

                            if ( !array_key_exists( $field['dataType'], $attributes[$field['identifier']] ) )
                            {
                                $attributes[$field['identifier']][$field['dataType']] = array();
                            }

                            $attributes[$field['identifier']][$field['dataType']][] = $class->identifier;
                        }
                    }
                }
            }
        }

        return $attributes;
    }

    /**
     * @return array
     */
    public function listAttributesGroupedByDatatype()
    {
        $attributes = array();
        $classList = self::classIdentifiersHash();
        foreach ( $classList as $identifier => $id )
        {
            if ( ContentClass::isSearchable( $identifier ) )
            {
                $class = $this->internalLoadClass( $identifier );
                if ( $class instanceof ContentClass )
                {
                    foreach ( $class->fields as $field )
                    {
                        if ( $field['isSearchable'] )
                        {
                            if ( !isset( $attributes[$field['dataType']] ) )
                            {
                                $attributes[$field['dataType']] = array();
                            }

                            if ( !array_key_exists( $field['identifier'], $attributes[$field['dataType']] ) )
                            {
                                $attributes[$field['dataType']][$field['identifier']] = array();
                            }

                            $attributes[$field['dataType']][$field['identifier']][] = $class->identifier;
                        }
                    }
                }
            }
        }

        return $attributes;
    }

    protected static function getCacheManager( $identifier )
    {
        $cacheFile = $identifier . '.cache';
        $cacheFilePath = eZDir::path(
            array( eZSys::cacheDirectory(), 'ocopendata', 'class', $cacheFile )
        );

        return eZClusterFileHandler::instance( $cacheFilePath );
    }

    public function clearCache( $identifier )
    {
        $this->getCacheManager( $identifier )->purge();
    }

    public function clearAllCache()
    {
        $commonPath = eZDir::path( array( eZSys::cacheDirectory(), 'ocopendata' ) );
        $fileHandler = eZClusterFileHandler::instance();
        $commonSuffix = '';
        $fileHandler->fileDeleteByDirList( array('class'), $commonPath, $commonSuffix );
    }

    public static function retrieveCache( $file, $mtime, $identifier )
    {
        $content = include( $file );

        return $content;
    }

    public static function generateCache( $file, $identifier )
    {
        $class = self::findClass( $identifier );

        return array(
            'content' => $class,
            'scope' => 'ocopendata-cache',
            'datatype' => 'php',
            'store' => true
        );
    }

    /**
     * @see eZContentClass::classIdentifiersHash
     * @return array|null
     */
    protected static function classIdentifiersHash()
    {
        if ( self::$identifierHash === null )
        {
            $db = eZDB::instance();
            $dbName = md5( $db->DB );

            $cacheDir = eZSys::cacheDirectory();
            $phpCache = new eZPHPCreator(
                $cacheDir,
                'classidentifiers_' . $dbName . '.php',
                '',
                array( 'clustering' => 'classidentifiers' )
            );

            eZExpiryHandler::registerShutdownFunction();
            $handler = eZExpiryHandler::instance();
            $expiryTime = 0;
            if ( $handler->hasTimestamp( 'class-identifier-cache' ) )
            {
                $expiryTime = $handler->timestamp( 'class-identifier-cache' );
            }

            if ( $phpCache->canRestore( $expiryTime ) )
            {
                $var = $phpCache->restore( array( 'identifierHash' => 'identifier_hash' ) );
                self::$identifierHash = $var['identifierHash'];
            }
            else
            {
                // Fetch identifier/id pair from db
                $query = "SELECT id, identifier FROM ezcontentclass where version = 0";
                $identifierArray = $db->arrayQuery( $query );

                self::$identifierHash = array();
                foreach ( $identifierArray as $identifierRow )
                {
                    self::$identifierHash[$identifierRow['identifier']] = $identifierRow['id'];
                }

                // Store identifier list to cache file
                $phpCache->addVariable( 'identifier_hash', self::$identifierHash );
                $phpCache->store();
            }
        }

        return self::$identifierHash;
    }
}
