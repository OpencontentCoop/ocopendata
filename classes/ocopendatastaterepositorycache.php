<?php

class OCOpenDataStateRepositoryCache
{
    public static function clearCache()
    {
        $repository = new \Opencontent\Opendata\Api\StateRepository();
        $repository->clearCache();
    }
}
