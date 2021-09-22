<?php

namespace Opencontent\Opendata\Api\Exception;

use Opencontent\Opendata\Api\Exception\BaseException;

class PublicationException extends BaseException
{
    public function getServerErrorCode()
    {
        return 400;
    }
}
