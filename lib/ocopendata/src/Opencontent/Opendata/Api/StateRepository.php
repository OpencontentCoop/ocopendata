<?php

namespace Opencontent\Opendata\Api;

use eZDir;
use eZSys;
use eZClusterFileHandler;
use eZContentObjectStateGroup;
use Opencontent\Opendata\Api\Exception\NotFoundException;
use Opencontent\Opendata\Api\Values\ContentState;

class StateRepository
{
    private static $states;

    public function load( $identifier )
    {
        $all = $this->internalLoadStates();
        foreach( $all as $state )
        {
            if ( ( is_numeric( $identifier ) && $state['id'] == $identifier )
                 || ( $state['identifier'] == $identifier ) )
            {
                return $state;
            }
        }
        // FIXME: da errore se passo stateIdentifiers vuoto --> da capire
        //throw new NotFoundException( $identifier, 'State' );
    }

    public function defaultStates()
    {
        $defaults = array();
        /** @var \eZContentObjectState[] $defaultStates */
        $defaultStates = \eZContentObjectState::defaults();
        foreach( $defaultStates as $defaultState )
        {
            $default = $this->load( $defaultState->attribute( 'id' ) );
            $defaults[$default['identifier']] = $default;
        }
        return $defaults;
    }

    public function loadAll()
    {
        return $this->internalLoadStates();
    }

    protected function internalLoadStates()
    {
        if (self::$states == null) {

            self::$states = $this->getCacheManager()->processCache(
                array(__CLASS__, 'retrieveCache'),
                array(__CLASS__, 'generateCache'),
                null,
                null,
                'states'
            );
        }
        return self::$states;
    }

    protected static function getCacheManager()
    {
        $cacheFile = 'states.cache';
        $cacheFilePath = eZDir::path(
            array( eZSys::cacheDirectory(), 'ocopendata', $cacheFile )
        );

        return eZClusterFileHandler::instance( $cacheFilePath );
    }

    public function clearCache()
    {
        $this->getCacheManager()->purge();
    }

    public static function retrieveCache( $file, $mtime, $identifier )
    {
        $content = include( $file );

        return $content;
    }

    public static function generateCache( $file, $identifier )
    {
        $stateList = array();

        /** @var eZContentObjectStateGroup[] $groups */
        $groups = eZContentObjectStateGroup::fetchObjectList( eZContentObjectStateGroup::definition() ); //@todo

        foreach( $groups as $group )
        {
            $stateGroup = array(
                'group_id' => $group->attribute( 'id' ),
                'group_identifier' => $group->attribute( 'identifier' )
            );

            /** @var \eZContentObjectState $state */
            foreach( $group->attribute( 'states' ) as $state )
            {
                $stateList[] = new ContentState( array_merge(
                    array(
                    'id' => $state->attribute( 'id' ),
                    'identifier' => $stateGroup['group_identifier'] . '.' . $state->attribute( 'identifier' ),
                    'state_identifier' => $state->attribute( 'identifier' ),
                    ), $stateGroup
                ) );
            }
        }

        return array(
            'content' => $stateList,
            'scope' => 'ocopendata-cache',
            'datatype' => 'php',
            'store' => true
        );
    }
}
