<?php

namespace Opencontent\Opendata\Api;

use Opencontent\Opendata\Api\Values\Content;
use Opencontent\Opendata\Api\Exception\NotFoundException;

interface Gateway
{
    /**
     * @param $contentObjectIdentifier
     *
     * @return Content
     * @throws NotFoundException
     */
    public function loadContent( $contentObjectIdentifier );
}