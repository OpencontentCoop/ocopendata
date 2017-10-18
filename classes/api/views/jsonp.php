<?php

class OCOpenDataJsonPView extends ezcMvcView
{
    protected $variables = array();

    public function __construct( ezcMvcRequest $request, ezcMvcResult $result )
    {
        $this->variables = $result->variables;
        $result = new ezcMvcResult();
        $result->variables = array(
            'callback' => $request->get['callback'],
            'content' => json_encode( $this->variables )
        );

        parent::__construct( $request, $result );

        $result->content = new ezcMvcResultContent();
        $result->content->type = "application/javascript";
        $result->content->charset = "UTF-8";
    }

    public function createZones( $layout )
    {
        $zones = array();
        $zones[] = new OCOpenDataJsonPViewHandler( 'content' );
        return $zones;
    }

}