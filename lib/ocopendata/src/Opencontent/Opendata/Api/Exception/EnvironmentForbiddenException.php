<?php

namespace Opencontent\Opendata\Api\Exception;

use Opencontent\Opendata\Api\Exception\BaseException;

class EnvironmentForbiddenException extends BaseException
{
    public function __construct( $presetIdentifier )
    {
        parent::__construct( "Access forbidden to environment {$presetIdentifier}" );
    }

    public function getServerErrorCode()
    {
        return 403;
    }
}