<?php

namespace Opencontent\Opendata\Api\AttributeConverter;

use eZContentObjectAttribute;
use eZContentClassAttribute;
use Opencontent\Opendata\Api\PublicationProcess;

class FloatNumber extends Base
{
    public function get( eZContentObjectAttribute $attribute )
    {
        $content = parent::get( $attribute );
        $content['content'] = (float)$content['content'];

        return $content;
    }

    public function toCSVString($content, $params = null)
    {
        if (is_string($content)) {            
            $locale = \eZLocale::instance();
            return (float)$locale->formatNumber($content);
        }

        return '';
    }
}
