<?php

use Opencontent\Opendata\Api\StateRepository;

class OCOpenDataClassRepositoryCache
{
    public static function clearCache()
    {
        $repository = new \Opencontent\Opendata\Api\ClassRepository();
        $repository->clearAllCache();
    }
}
