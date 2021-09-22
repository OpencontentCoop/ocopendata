<?php

namespace Opencontent\Opendata\Api\Exception;


class UpdateContentException extends BaseException
{
    public function getServerErrorCode()
    {
        return 400;
    }
}
