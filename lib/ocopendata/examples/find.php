<?php

use Opencontent\Opendata\Rest\Client\HttpClient;

try
{
    $client = new HttpClient( 'http://openpa.opencontent.it', 'user', 'password' );
    $data = array(
        'metadata' => array(
            'section_identifier' => 'restricted',
            'status_identifier' => array( 'foo.bar' ),
            'visibility' => 'hide'
        ),
        'data' => array(
            'title' => 'Hello universe',
            'description' => '<p>Hi everybody again!</p>',
            'image' => array(
                'url' => 'https://www.microsoft.com/maps/images/branding/Bing%20logo%20gray_150px-57px.png',
                'filename' => 'logo.png',
                'alt' => 'Logo di Bing'
            )
        )
    );
    $response = $client->update( $data );
    echo $response->result;
}
catch( Exception $e )
{
    echo $e->getMessage();
}