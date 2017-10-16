<?php

namespace Opencontent\Opendata\Api\Exception;

use Opencontent\Opendata\Api\Exception\BaseException;

class ForbiddenException extends BaseException
{
    public function __construct( $contentObjectIdentifier, $permission )
    {
        parent::__construct( "User can not {$permission} content {$contentObjectIdentifier}" );
    }

    public function getServerErrorCode()
    {
        return 403;
    }
}
