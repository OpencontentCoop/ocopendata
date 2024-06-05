<?php

namespace Opencontent\Opendata\Api\Exception;

use Opencontent\Opendata\Api\Exception\BaseException;

class InvalidPayloadException extends BaseException
{
    public function getServerErrorCode()
    {
        return 500;
    }
}