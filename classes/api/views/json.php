<?php


class OCOpenDataJsonView extends ezpRestJsonView
{
    public function __construct( ezcMvcRequest $request, ezcMvcResult $result )
    {
        parent::__construct( $request, $result );

        $result->content = new ezcMvcResultContent();
        $result->content->type = "application/json";
        $result->content->charset = "UTF-8";

        if (eZINI::instance('ocopendata.ini')
            ->variable('ApiSettings', 'Cors') === 'enabled') {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            header(
                'Access-Control-Allow-Headers: DNT,User-Agent,X-Requested-With,If-Modified-Since,Cache-Control,Content-Type,Range'
            );
            header('Access-Control-Expose-Headers: Content-Length,Content-Range,Content-Type');
        }

        $cacheControl = eZINI::instance('site.ini')->variable('HTTPHeaderSettings', 'Cache-Control');
        if (!empty($cacheControl['/api/opendata/v2'])) {
            header('Cache-Control: ' . $cacheControl['/api/opendata/v2']);
        } elseif (!empty($cacheControl['/opendata/api'])) {
            header('Cache-Control: ' . $cacheControl['/opendata/api']);
        }

        $vary = eZINI::instance('site.ini')->variable('HTTPHeaderSettings', 'Vary');
        if (!empty($vary['/api/opendata/v2'])) {
            header('Vary: ' . $vary['/api/opendata/v2']);
        } elseif (!empty($vary['/opendata/api'])) {
            header('Vary: ' . $vary['/opendata/api']);
        }


    }

    public function createZones( $layout )
    {
        $zones = array();
        $zones[] = new ezcMvcJsonViewHandler( 'content' );
        return $zones;
    }

}