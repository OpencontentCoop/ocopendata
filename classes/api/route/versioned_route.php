<?php


class OcOpenDataVersionedRoute extends ezpRestVersionedRoute
{
    /**
     * @var ezcMvcReversibleRoute
     */
    protected $route;

    /**
     * @return ezcMvcRoute|ezcMvcReversibleRoute
     */
    public function getRoute()
    {
        return $this->route;
    }

    /**
     * @return int
     */
    public function getVersion()
    {
        return $this->version;
    }

    public function generateDocUrl()
    {
        if ($this->route instanceof OcOpenDataRouteInterface) {
            $apiPrefix = ezpRestPrefixFilterInterface::getApiPrefix() . '/';
            $apiProviderName = ezpRestPrefixFilterInterface::getApiProviderName();

            return $apiPrefix . (!$apiProviderName ? '' : $apiProviderName . '/') . 'v' . $this->version . '/' . str_replace($apiPrefix, '', $this->route->generateDocUrl());
        }

        return '';
    }

}