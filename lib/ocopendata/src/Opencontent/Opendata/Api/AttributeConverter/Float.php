<?php

namespace Opencontent\Opendata\Api\AttributeConverter;

use eZContentObjectAttribute;
use eZContentClassAttribute;
use Opencontent\Opendata\Api\PublicationProcess;

class Float extends Base
{    
    public function toCSVString($content, $params = null)
    {
        if (is_string($content)) {            
            $locale = \eZLocale::instance();
            return $locale->formatNumber($content);
        }

        return '';
    }
}