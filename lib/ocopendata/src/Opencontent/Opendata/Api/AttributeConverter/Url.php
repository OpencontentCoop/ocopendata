<?php

namespace Opencontent\Opendata\Api\AttributeConverter;

use eZContentObjectAttribute;
use Opencontent\Opendata\Api\PublicationProcess;

class Url extends Base
{
    public function onPublishNullData(eZContentObjectAttribute $attribute, PublicationProcess $process): bool
    {
        $attribute->setAttribute('data_int', 0);
        $attribute->store();
        return true;
    }

}