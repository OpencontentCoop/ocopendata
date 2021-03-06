<?php


class OcOpenDataRoute extends ezpMvcRailsRoute implements OcOpenDataRouteInterface
{
    protected $docUrl;

    protected $apiDescription;

    public function __construct($pattern, $controllerClassName, $protocolActionMap, array $defaultValues = array(), $protocol = null, $docUrl = null, $apiDescription = null)
    {
        $this->docUrl = $docUrl;
        $this->apiDescription = $apiDescription;
        parent::__construct($pattern, $controllerClassName, $protocolActionMap, $defaultValues, $protocol);
    }

    /**
     * @return array
     */
    public function getProtocolActionMap()
    {
        return $this->protocolActionMap;
    }

    public function getPattern()
    {
        return $this->pattern;
    }

    public function getParams()
    {
        $params = array();
        $patternParts = explode('/', $this->pattern);
        foreach ($patternParts as &$part) {
            if (strlen($part) > 1 && $part[0] === ':') {
                $params[] = substr($part, 1);

            }
        }
        return $params;
    }

    public function generateDocUrl()
    {
        if ($this->docUrl) {
            return $this->docUrl;
        }

        $arguments = array();
        foreach ($this->getParams() as $param) {
            $arguments[$param] = ':' . $param;
        }
        $arguments = array_merge($arguments, $this->defaultValues);

        return $this->generateUrl($arguments);
    }

    public function getApiDescription()
    {
        return $this->apiDescription;
    }
}
