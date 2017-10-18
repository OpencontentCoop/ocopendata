<?php

namespace Opencontent\Opendata\Api\Exception;

use Opencontent\Opendata\Api\Exception\BaseException;

class NotFoundException extends BaseException
{
    public function __construct( $identifier, $type = 'Content' )
    {
        parent::__construct( "{$type} {$identifier} not found" );
    }

    public function getServerErrorCode()
    {
        return 404;
    }
}