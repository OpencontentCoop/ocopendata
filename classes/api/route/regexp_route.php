<?php


class OcOpenDataRegexpRoute extends ezpMvcRegexpRoute implements OcOpenDataRouteInterface
{
    protected $docUrl;

    protected $apiDescription;

    public function __construct($pattern, $controllerClassName, $protocolActionMap, array $defaultValues = array(), $docUrl = null, $apiDescription = null)
    {
        $this->docUrl = $docUrl;
        $this->apiDescription = $apiDescription;
        parent::__construct($pattern, $controllerClassName, $protocolActionMap, $defaultValues);
    }

    /**
     * @return array
     */
    public function getProtocolActionMap()
    {
        return $this->protocolActionMap;
    }

    /**
     * @return string
     */
    public function getPattern()
    {
        return $this->pattern;
    }

    public function getParams()
    {
        $parts = explode('?P', $this->pattern);
        $params = array();

        foreach ($parts as $part) {
            if (strpos($part, '<') === 0) {
                $subParts = explode('>', $part);
                $params[] = ltrim($subParts[0], '<');
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