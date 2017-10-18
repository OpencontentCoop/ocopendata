<?php

use Opencontent\Opendata\Rest\Client\HttpClient;
use Opencontent\Opendata\Api\ContentRepository;
use Opencontent\Opendata\Rest\Client\PayloadBuilder;

$host = 'http://www.comune.rivadelgarda.tn.it/';
$client = new HttpClient($host);

try {

    $repository = new ContentRepository();
    $repository->setEnvironment(new DefaultEnvironmentSettings());

    $remoteId = '2fbe8fe4b789abeb4da3587c317322b6';
    $data = $client->import($remoteId, $repository);

    if ( $data['message'] == 'success' ){
        echo $data['message'] . ' ' . $data['method'] . ' ' . $data['content']['metadata']['id'];
    }else{
        echo $data['message'];
    }


} catch (Exception $e) {
    echo $e->getMessage();
}