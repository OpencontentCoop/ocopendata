<?php

namespace Opencontent\Opendata\Api\Exception;


class CreateContentException extends BaseException
{
    public function getServerErrorCode()
    {
        return 400;
    }
}
