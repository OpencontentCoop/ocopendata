<?php

namespace Opencontent\Opendata\Api;

use eZDir;
use eZSys;
use eZClusterFileHandler;
use eZSection;
use Opencontent\Opendata\Api\Exception\NotFoundException;
use Opencontent\Opendata\Api\Values\ContentSection;

class SectionRepository
{
    public function load( $identifier )
    {
        $all = $this->internalLoadSections();

        foreach( $all as $section )
        {
            if ( ( is_numeric( $identifier ) && $section['id'] == $identifier )
                 || ( $section['identifier'] == $identifier ) )
            {
                return $section;
            }
        }
        throw new NotFoundException( $identifier, 'Section' );
    }

    public function loadAll()
    {
        return $this->internalLoadSections();
    }

    protected function internalLoadSections()
    {
        return $this->getCacheManager()->processCache(
            array( __CLASS__, 'retrieveCache' ),
            array( __CLASS__, 'generateCache' ),
            null,
            null,
            'sections'
        );
    }

    protected static function getCacheManager()
    {
        $cacheFile = 'sections.cache';
        $cacheFilePath = eZDir::path(
            array( eZSys::cacheDirectory(), 'ocopendata', $cacheFile )
        );

        return eZClusterFileHandler::instance( $cacheFilePath );
    }

    public function clearCache()
    {
        $this->getCacheManager()->delete();
    }

    public static function retrieveCache( $file, $mtime, $identifier )
    {
        $content = include( $file );
        if ($content instanceof \eZClusterFileFailure){
            $data = self::generateCache($file, $identifier);
            return $data['content'];
        }
        return $content;
    }

    public static function generateCache( $file, $identifier )
    {
        $sectionList = eZSection::fetchObjectList(
            eZSection::definition(),
            null,
            null,
            null,
            null,
            false
        );
        $data = array();
        foreach( $sectionList as $section )
        {
            $data[] = new ContentSection( $section );
        }
        return array(
            'content' => $data,
            'scope' => 'ocopendata-cache',
            'datatype' => 'php',
            'store' => true
        );
    }
}