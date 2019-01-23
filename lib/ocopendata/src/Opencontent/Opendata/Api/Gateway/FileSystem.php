<?php

namespace Opencontent\Opendata\Api\Gateway;

use Opencontent\Opendata\Api\Values\Content;
use eZDir;
use eZSys;
use eZClusterFileHandler;
use eZClusterFileFailure;
use eZDB;
use Opencontent\Opendata\Api\Exception\NotFoundException;

class FileSystem extends Database
{

    /**
     * @param $contentObjectIdentifier
     *
     * @return Content
     * @throws NotFoundException
     */
    public function loadContent( $contentObjectIdentifier )
    {
        $result = self::findContent( $contentObjectIdentifier );
        return $this->getCacheManager( $result['id'] )->processCache(
            array( __CLASS__, 'retrieveCache' ),
            array( __CLASS__, 'generateCache' ),
            null,
            null,
            array( $result['id'], $result['modified'] )
        );
    }
    
    /**
     * @param $contentObjectIdentifier
     *
     * @return int
     * @throws NotFoundException
     */
    protected static function findContent( $contentObjectIdentifier )
    {
        if ( is_numeric( $contentObjectIdentifier ) ) {
            $contentObjectIdentifierAsInt = (int)$contentObjectIdentifier;
            $whereSql = "ezcontentobject.id='$contentObjectIdentifierAsInt'";
        }else{
            $whereSql = "ezcontentobject.remote_id='$contentObjectIdentifier'";
        }
        $fetchSQLString = "SELECT ezcontentobject.id, ezcontentobject.modified
                           FROM
                               ezcontentobject
                           WHERE
                               $whereSql";
        $resArray = eZDB::instance()->arrayQuery( $fetchSQLString );
        if ( count( $resArray ) == 1 && $resArray !== false )
        {
            return $resArray[0];
        }
        else
        {
            throw new NotFoundException( $contentObjectIdentifier );
        }
    }

    protected static function getCacheManager( $contentObjectId )
    {
        $cacheFile = $contentObjectId . '.cache';
        $extraPath = eZDir::filenamePath( $contentObjectId );
        $cacheFilePath = eZDir::path( array( eZSys::cacheDirectory(), 'ocopendata',  'content', $extraPath, $cacheFile ) );
        return eZClusterFileHandler::instance( $cacheFilePath );
    }

    public function clearCache( $contentObjectId )
    {
        $this->getCacheManager( $contentObjectId )->purge();
    }

    public static function retrieveCache( $file, $mtime, $extraData )
    {
        if ( $mtime >= $extraData[1] )
        {
            $content = include( $file );
            return $content;
        }
        else
            return new eZClusterFileFailure( 1, "Modified timestamp greater then fime mtime" );
    }

    public static function generateCache( $file, $extraData )
    {
        $contentObject = parent::findContent( (int)$extraData[0] );
        $content = Content::createFromEzContentObject( $contentObject );;
        return array( 'content'  => $content,
                      'scope'    => 'ocopendata-cache',
                      'datatype' => 'php',
                      'store'    => true );

    }

    public function clearAllCache()
    {
        $commonPath = eZDir::path( array( eZSys::cacheDirectory(), 'ocopendata' ) );
        $fileHandler = eZClusterFileHandler::instance();
        $commonSuffix = '';
        $fileHandler->fileDeleteByDirList( array('content'), $commonPath, $commonSuffix );
    }
}
