<?php

class OcOpenDataErrorResponse implements ezcMvcResultStatusObject
{
    public $code;
    public $message;
    public $errorType;
    public $errorDetails;

    public function __construct(
        $code = null,
        $message = null,
        $errorType = null
    )
    {
        $this->code = $code;
        $this->message = $message;
        $this->errorType = $errorType;
    }

    public function process( ezcMvcResponseWriter $writer )
    {
        if ( $writer instanceof ezcMvcHttpResponseWriter )
        {
            header("HTTP/1.1 " . trim($this->code) . " " . ezpRestStatusResponse::$statusCodes[$this->code]);
            $writer->headers['X-Api-Error-Type'] = $this->errorType;
            $writer->headers['X-Api-Error-Message'] = $this->message;
        }

        if ( $this->message !== null && $writer instanceof ezpRestHttpResponseWriter)
        {
            $writer->headers['Content-Type'] = 'application/json; charset=UTF-8';
            $writer->response->body = json_encode(
                array(
                    'error_type' => $this->errorType,
                    'error_message' => $this->message
                )
            );
        }
    }
}