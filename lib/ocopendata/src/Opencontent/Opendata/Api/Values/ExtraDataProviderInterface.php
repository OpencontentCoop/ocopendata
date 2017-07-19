<?php

namespace Opencontent\Opendata\Api\Values;

use eZContentObject;

interface ExtraDataProviderInterface
{
    public function setExtraDataFromContentObject(eZContentObject $object, ExtraData $extraData);
}
