<?php

namespace Opencontent\Opendata\Api;

use Opencontent\Opendata\Api\Exception\EnvironmentMisconfigurationException;
use eZINI;
use eZUser;
use Opencontent\Opendata\Api\Exception\EnvironmentForbiddenException;

class EnvironmentLoader
{
    public static function ini()
    {
        return eZINI::instance( 'ocopendata.ini' );
    }

    /**
     * @param $presetIdentifier
     *
     * @return EnvironmentSettings
     * @throws EnvironmentForbiddenException
     * @throws EnvironmentMisconfigurationException
     */
    public static function loadPreset( $presetIdentifier )
    {
        if ( self::ini()->hasVariable( 'EnvironmentSettingsPresets', 'AvailablePresets' ) )
        {
            if ( in_array(
                $presetIdentifier,
                (array)self::ini()->variable( 'EnvironmentSettingsPresets', 'AvailablePresets' )
            ) )
            {
                if ( self::needAccess( $presetIdentifier ) && !self::checkAccess( $presetIdentifier ) )
            {
                throw new EnvironmentForbiddenException( $presetIdentifier );
            }
                if ( self::phpClass( $presetIdentifier ) )
                {
                    return self::instanceEnvironmentSettings( $presetIdentifier );
                }
            }
        }

        throw new EnvironmentMisconfigurationException( $presetIdentifier );
    }

    public static function getAvailablePresetIdentifiers()
    {
        $data = array();
        if ( self::ini()->hasVariable( 'EnvironmentSettingsPresets', 'AvailablePresets' ) )
        {
            $data = (array)self::ini()->variable( 'EnvironmentSettingsPresets', 'AvailablePresets' );
        }
        return $data;
    }

    /**
     * @param $presetIdentifier
     *
     * @return EnvironmentSettings
     * @throws EnvironmentMisconfigurationException
     */
    protected static function instanceEnvironmentSettings( $presetIdentifier )
    {
        $presetClassName = self::phpClass( $presetIdentifier );
        $settings = new $presetClassName();
        if ( $settings instanceof EnvironmentSettings )
        {
            $settings->__set( 'identifier', $presetIdentifier );
            if ( self::ini()->hasVariable( 'EnvironmentSettingsPresets_' . $presetIdentifier, 'Debug' ) )
            {
                $settings->__set( 'debug', (bool) self::ini()->variable( 'EnvironmentSettingsPresets_' . $presetIdentifier, 'Debug' ) == 'enabled' );
            }
            return $settings;
        }
        throw new EnvironmentMisconfigurationException( $presetIdentifier );
    }

    public static function phpClass( $presetIdentifier )
    {
        if ( self::ini()->hasGroup( 'EnvironmentSettingsPresets_' . $presetIdentifier ) )
        {
            $settings = (array)self::ini()->group( 'EnvironmentSettingsPresets_' . $presetIdentifier );
            if ( isset( $settings['PHPClass'] ) )
                return $settings['PHPClass'];
        }
        return false;
    }

    public static function needAccess( $presetIdentifier )
    {
        if ( self::ini()->hasGroup( 'EnvironmentSettingsPresets_' . $presetIdentifier ) )
        {
            $settings = (array)self::ini()->group( 'EnvironmentSettingsPresets_' . $presetIdentifier );
            return $settings['CheckAccess'] == 'true';
        }
        return true;
    }

    protected static function checkAccess( $presetIdentifier )
    {
        $access = eZUser::currentUser()->hasAccessTo( 'opendata', 'environment');
        if ( $access['accessWord'] == 'limited' )
        {
            foreach( $access['policies'] as $pLimitation => $limitations )
            {
                foreach( $limitations as $identifier => $limitationList )
                {
                    if ( $identifier == 'PresetList' )
                    {
                        if ( in_array( $presetIdentifier, $limitationList ) )
                        {
                            return true;
                        }
                    }
                }
            }
        }
        return $access['accessWord'] == 'yes';
    }

}