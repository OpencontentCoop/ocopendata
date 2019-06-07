<?php

use Opencontent\Opendata\Rest\Client\HttpClient;

try
{
    $client = new HttpClient( 'http://www.example.com', 'user', 'password' );
    $data = array(
        'metadata' => array(
            'remote_id' => 'my_own_unique_id',
            'parent_node_id' => 2,
            'section_identifier' => 'standard',
            'status_identifier' => array( 'bar.foo' ),
            'visibility' => 'show'
        ),
        'data' => array(
            'title' => 'Hello world',
            'description' => '<p>Hi everybody!</p>'
        )
    );
    $response = $client->create( $data );
    echo $response->result;
}
catch( Exception $e )
{
    echo $e->getMessage();
}