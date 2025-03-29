<?php

namespace Opencontent\Opendata\Api\Exception;

use Opencontent\Opendata\Api\Exception\BaseException;

class EnvironmentMisconfigurationException extends BaseException
{
    public function getServerErrorCode()
    {
        return 400;
    }

    public function __construct( $presetIdentifier, $message = null )
    {
        if ( $message !== null )
            $message = ': ' . $message;
        parent::__construct( "Environment '{$presetIdentifier}' bad configuration{$message}" );
    }
}