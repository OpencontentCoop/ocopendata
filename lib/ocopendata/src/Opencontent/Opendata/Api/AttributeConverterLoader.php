<?php

namespace Opencontent\Opendata\Api;

use eZContentObjectAttribute;
use eZContentClassAttribute;
use Opencontent\Opendata\Api\AttributeConverter\Base;
use Opencontent\Opendata\Api\EnvironmentLoader;

class AttributeConverterLoader
{

    /**
     * @param string $classIdentifier
     * @param string $identifier
     * @param string $dataTypeString
     *
     * @return \Opencontent\Opendata\Api\AttributeConverter\Base
     * @throws \Exception
     */
    final public static function load(
        $classIdentifier,
        $identifier,
        $dataTypeString
    )
    {
        $className = null;
        $converters = (array)self::attributeConverters();
        if ( isset( $converters["{$classIdentifier}/{$identifier}"] ) )
        {
            $className = $converters["{$classIdentifier}/{$identifier}"];
        }
        elseif ( isset( $converters[$identifier] ) )
        {
            $className = $converters[$identifier];
        }
        elseif ( isset( $converters[$dataTypeString] ) )
        {
            $className = $converters[$dataTypeString];
        }
        elseif ( isset( $converters['*'] ) )
        {
            $className = $converters['*'];
        }
        if ( class_exists( $className ) )
        {
            return new $className(
                $classIdentifier,
                $identifier
            );
        }
        else
        {
            if ( $className !== null )
                \eZDebug::writeError( "$className not found", __METHOD__ );
            return new Base(
                $classIdentifier,
                $identifier
            );
        }
    }

    /**
     * @return array
     */
    public static function attributeConverters()
    {
        $converters = array(
            'ezselection' => '\Opencontent\Opendata\Api\AttributeConverter\Selection',
            'ezprice' => '\Opencontent\Opendata\Api\AttributeConverter\Price',
            'ezkeyword' => '\Opencontent\Opendata\Api\AttributeConverter\Keywords',
            'eztags' => '\Opencontent\Opendata\Api\AttributeConverter\Tags',
            'ezgmaplocation' => '\Opencontent\Opendata\Api\AttributeConverter\Geo',
            'ezdate' => '\Opencontent\Opendata\Api\AttributeConverter\Date',
            'ezdatetime' => '\Opencontent\Opendata\Api\AttributeConverter\Date',
            'eztime' => '\Opencontent\Opendata\Api\AttributeConverter\Date',
            'ezmatrix' => '\Opencontent\Opendata\Api\AttributeConverter\Matrix',
            'ezxmltext' => '\Opencontent\Opendata\Api\AttributeConverter\EzXml',
            'ezauthor' => '\Opencontent\Opendata\Api\AttributeConverter\Author',
            'ezobjectrelation' => '\Opencontent\Opendata\Api\AttributeConverter\Relations',
            'ezobjectrelationlist' => '\Opencontent\Opendata\Api\AttributeConverter\Relations',
            'ezbinaryfile' => '\Opencontent\Opendata\Api\AttributeConverter\File',
            'ezimage' => '\Opencontent\Opendata\Api\AttributeConverter\Image',
            'ezpage' => '\Opencontent\Opendata\Api\AttributeConverter\Page',
            'ezboolean' => '\Opencontent\Opendata\Api\AttributeConverter\Boolean',
            'ezuser' => '\Opencontent\Opendata\Api\AttributeConverter\User',
            'ezfloat' => '\Opencontent\Opendata\Api\AttributeConverter\Float',
            'mugoobjectrelationlist' => '\Opencontent\Opendata\Api\AttributeConverter\Relations',
        );

        if ( EnvironmentLoader::ini()->hasVariable( 'AttributeConverters', 'Converters' ) )
        {
            $converters = array_merge(
                $converters,
                (array)EnvironmentLoader::ini()->variable( 'AttributeConverters', 'Converters' )
            );
        }

        return $converters;
    }

}
