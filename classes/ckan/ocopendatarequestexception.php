<?php

class OCOpenDataRequestException extends Exception
{
    private $responseCode;

    private $responseCodeMessage;

    private $response;

    /**
     * @return mixed
     */
    public function getResponseCode()
    {
        return $this->responseCode;
    }

    /**
     * @param mixed $responseCode
     *
     * @return Exception
     */
    public function setResponseCode($responseCode)
    {
        $this->responseCode = $responseCode;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getResponseCodeMessage()
    {
        return $this->responseCodeMessage;
    }

    /**
     * @param mixed $responseCodeMessage
     *
     * @return OCOpenDataRequestException
     */
    public function setResponseCodeMessage($responseCodeMessage)
    {
        $this->responseCodeMessage = $responseCodeMessage;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getResponseError()
    {
        $data = json_decode($this->response, true);
        return isset($data['error']) ? $data['error'] : null;
    }

    /**
     * @param mixed $responseError
     *
     * @return Exception
     */
    public function setResponse($response)
    {
        $this->response = $response;

        return $this;
    }
}
