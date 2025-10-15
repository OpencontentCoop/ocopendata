<?php


class OCOpenDataJsonViewV1 extends ezpRestJsonView
{
    public function __construct( ezcMvcRequest $request, ezcMvcResult $result )
    {
        parent::__construct( $request, $result );

        $result->content = new ezcMvcResultContent();
        $result->content->type = "application/json";
        $result->content->charset = "UTF-8";

        $cacheControl = eZINI::instance('site.ini')->variable('HTTPHeaderSettings', 'Cache-Control');
        if (!empty($cacheControl['/api/opendata/v1'])) {
            header('Cache-Control: ' . $cacheControl['/api/opendata/v1']);
        } elseif (!empty($cacheControl['/opendata/api'])) {
            header('Cache-Control: ' . $cacheControl['/opendata/api']);
        }

        $vary = eZINI::instance('site.ini')->variable('HTTPHeaderSettings', 'Vary');
        if (!empty($vary['/api/opendata/v1'])) {
            header('Vary: ' . $vary['/api/opendata/v1']);
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