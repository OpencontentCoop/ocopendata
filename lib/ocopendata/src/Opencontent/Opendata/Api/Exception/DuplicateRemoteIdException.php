<?php

namespace Opencontent\Opendata\Api\Exception;


class DuplicateRemoteIdException extends BaseException
{
    public function getServerErrorCode()
    {
        return 400;
    }
}
