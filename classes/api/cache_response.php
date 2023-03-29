<?php

class ApiCacheHeadersResponseFilter implements ezpRestResponseFilterInterface
{
    private $response;

    public function __construct(
        ezcMvcRoutingInformation $routeInfo,
        ezcMvcRequest $request,
        ezcMvcResult $result,
        ezcMvcResponse $response
    ) {
        $this->response = $response;
    }

    public function filter()
    {
        $currentCache = $this->response->cache ?? new ezcMvcResultCache();
        $currentCache->controls = [
            'public',
            'must-revalidate',
            'max-age=60',
            's-maxage=600'
        ];
        $this->response->cache = $currentCache;
    }

    public static function printHeaders(eZModule $module = null)
    {
        header('Cache-Control: public, must-revalidate, max-age=60, s-maxage=600');
    }
}