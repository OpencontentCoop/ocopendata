<?php

class OCOpenDataViewController implements ezpRestViewControllerInterface
{
    /**
     * Creates a view required by controller's result
     *
     * @param ezcMvcRoutingInformation $routeInfo
     * @param ezcMvcRequest $request
     * @param ezcMvcResult $result
     * @return ezcMvcView
     */
    public function loadView( ezcMvcRoutingInformation $routeInfo, ezcMvcRequest $request, ezcMvcResult $result )
    {
        if ( isset( $request->variables['EnvironmentSettings'] )
             && $request->variables['EnvironmentSettings'] instanceof \Opencontent\Opendata\Api\EnvironmentSettings )
            return $this->loadOpenData2View( $routeInfo, $request, $result );
        return new ezpRestJsonView( $request, $result );
    }

    protected function loadOpenData2View( ezcMvcRoutingInformation $routeInfo, ezcMvcRequest $request, ezcMvcResult $result )
    {
        if ( isset( $request->get['callback'] ) )
        {
            return new OCOpenDataJsonPView( $request, $result );
        }
        return new OCOpenDataJsonView( $request, $result );
    }

}
