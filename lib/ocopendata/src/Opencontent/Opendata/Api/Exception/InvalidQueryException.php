<?php

namespace Opencontent\Opendata\Api\Exception;

class InvalidQueryException extends BaseException
{
    public function getServerErrorCode()
    {
        return 400;
    }
}