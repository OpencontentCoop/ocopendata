<?php

namespace Opencontent\Opendata\Api\AttributeConverter;

use eZContentObjectAttribute;
use eZContentClassAttribute;
use Opencontent\Opendata\Api\PublicationProcess;

class FloatNumber extends Base
{    
    public function toCSVString($content, $params = null)
    {
        if (is_string($content)) {            
            $locale = \eZLocale::instance();
            return (float)$locale->formatNumber($content);
        }

        return '';
    }
}
