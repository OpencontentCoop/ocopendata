<?php

class InvalidProviderFilter implements ezpRestPreRoutingFilterInterface
{
    public function __construct(ezcMvcRequest $request)
    {
    }

    public function filter()
    {
        $provider = ezpRestPrefixFilterInterface::getApiProviderName();
        if ($provider) {
            $providerOptions = new ezpExtensionOptions();
            $providerOptions->iniFile = 'rest.ini';
            $providerOptions->iniSection = 'ApiProvider';
            $providerOptions->iniVariable = 'ProviderClass';
            $providerOptions->handlerIndex = $provider;
            $providerInstance = eZExtension::getHandlerClass($providerOptions);
            if (!$providerInstance instanceof ezpRestProviderInterface) {
                header('HTTP/1.1 404 Not Found');
                header('Content-Type: application/json');
                echo json_encode(['error_message' => 'Not Found']);
                eZExecution::cleanExit();
            }
        }
    }
}