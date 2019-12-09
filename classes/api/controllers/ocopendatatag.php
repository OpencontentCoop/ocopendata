<?php

use Opencontent\Opendata\Api\Exception\BaseException;
use Opencontent\Opendata\Api\TagRepository;

class OCOpenDataTagController extends ezpRestContentController
{
    /**
     * @var ezpRestRequest
     */
    protected $request;

    protected function doExceptionResult( Exception $exception )
    {
        $result = new ezcMvcResult;
        $result->variables['message'] = $exception->getMessage();

        $serverErrorCode = ezpHttpResponseCodes::SERVER_ERROR;
        $errorType = BaseException::cleanErrorCode( get_class( $exception ) );
        if ( $exception instanceof BaseException )
        {
            $serverErrorCode = $exception->getServerErrorCode();
            $errorType = $exception->getErrorType();
        }

        $result->status = new OcOpenDataErrorResponse(
            $serverErrorCode,
            $exception->getMessage(),
            $errorType
        );

        return $result;
    }

    public function doTagsTree()
    {
        try
        {
            $result = new ezpRestMvcResult();
            $tagRepository = new TagRepository();
            $requestTag = null;
            if (isset($this->request->variables['Tag'])){
                $requestTag = $this->request->variables['Tag'];
            }
            $offset = isset($this->request->get['offset']) ? (int)$this->request->get['offset'] : $this->offset;
            $limit = isset($this->request->get['limit']) ? (int)$this->request->get['limit'] : $this->limit;
            $result->variables = (array)$tagRepository->read($requestTag, $offset, $limit);
        }
        catch ( Exception $e )
        {
            $result = $this->doExceptionResult( $e );
        }

        return $result;
    }

}
