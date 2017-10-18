<?php

use Opencontent\Opendata\Rest\Client\HttpClient;

try
{
    $client = new HttpClient( 'http://openpa.opencontent.it', 'user', 'password' );

    $data = $client->find('classes [article]');
    foreach( $data['searchHits'] as $content ){
        echo $content['metadata']['id'] . "\n";
    }

    $client->find('classes [article]', function($result){
        foreach( $result['searchHits'] as $content ){
            echo $content['metadata']['id'] . "\n";
        }
    });

    $allContents = $client->findAll('classes [article]');
    foreach( $allContents as $content ){
        echo $content['metadata']['id'] . "\n";
    }

    $client->findAll('classes [article]', function($result){
        echo $result['query'] . "\n";
        foreach( $result['searchHits'] as $content ){
            echo $content['metadata']['id'] . "\n";
        }
    });
}
catch( Exception $e )
{
    echo $e->getMessage();
}