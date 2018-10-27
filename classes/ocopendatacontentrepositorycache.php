<?php

class OCOpenDataContentRepositoryCache
{
    public static function clearCache()
    {
        $repository = new \Opencontent\Opendata\Api\Gateway\FileSystem();
        $repository->clearAllCache();
    }
}
