<?php

interface OcOpenDataRouteInterface
{
    public function getProtocolActionMap();

    public function getPattern();

    public function getParams();

    public function generateDocUrl();

    public function getApiDescription();
}