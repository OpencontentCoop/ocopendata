<?php

namespace Opencontent\Opendata\Api\Exception;

use Opencontent\Opendata\Api\Exception\BaseException;

class OutOfRangeException extends BaseException
{
    public function __construct($field, $code = 0, \Exception $previous = null) {
        $trace = $this->getTrace();
        $message = "Field '$field' is out of range in " . $trace[0]['class'];
        return parent::__construct($message, $code = 0, $previous );
    }
}