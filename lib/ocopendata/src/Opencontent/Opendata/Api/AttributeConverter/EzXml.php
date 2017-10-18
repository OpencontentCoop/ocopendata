<?php

namespace Opencontent\Opendata\Api\AttributeConverter;

use eZContentObjectAttribute;
use eZContentClassAttribute;
use Opencontent\Opendata\Api\PublicationProcess;
use SQLIContentUtils;

class EzXml extends Base
{
    public function get( eZContentObjectAttribute $attribute )
    {
        // avoid php notice in kernel/common/ezmoduleparamsoperator.php on line 71
        if ( !isset( $GLOBALS['eZRequestedModuleParams'] ) )
            $GLOBALS['eZRequestedModuleParams'] = array( 'module_name' => null,
                                                         'function_name' => null,
                                                         'parameters' => null );
        $content = parent::get( $attribute );
        $content['content'] = str_replace( '&nbsp;', ' ', $attribute->content()->attribute( 'output' )->attribute( 'output_text' ) );
        return $content;
    }

    public function set( $data, PublicationProcess $process )
    {
        return SQLIContentUtils::getRichContent( $data );
    }

    public function type( eZContentClassAttribute $attribute )
    {
        return array( 'identifier' => 'html' );
    }

    public function toCSVString($content, $params = null)
    {
        return is_string($content) ? strip_tags($content) : '';
    }
}
