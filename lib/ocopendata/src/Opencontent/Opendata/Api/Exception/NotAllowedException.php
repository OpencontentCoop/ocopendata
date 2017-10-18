<?php

namespace Opencontent\Opendata\Api\Exception;

use Opencontent\Opendata\Api\Exception\BaseException;

class NotAllowedException extends BaseException
{
    public function __construct( $contentObjectIdentifier, $method )
    {
        parent::__construct( "User can not {$method} content {$contentObjectIdentifier}" );
    }

    public function getServerErrorCode()
    {
        return 405;
    }
}