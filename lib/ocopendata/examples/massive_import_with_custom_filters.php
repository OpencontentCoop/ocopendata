<?php

use Opencontent\Opendata\Rest\Client\HttpClient;
use Opencontent\Opendata\Rest\Client\PayloadBuilder;
use Opencontent\Opendata\Api\ContentRepository;


$data = array(); // collect import data
$errors = array(); //log errors in simple array

try {

    $client = new HttpClient($host);

    // set custom filter for relations
    $relationFilters = array(
        'autore' => function (PayloadBuilder $payload) {
            $payload->setParentNodes(array(180545));
            $payload->unSetData('star_rating');
            $payload->unSetData('comments');
            $payload->unSetData('credits');
            $payload->unSetData('keywords');

            return $payload;

        },
        'audio' => function (PayloadBuilder $payload) {
            $payload->setParentNodes(array(180545));
            $payload->unSetData('star_rating');
            $payload->unSetData('comments');
            $payload->unSetData('credits');
            $payload->unSetData('keywords');

            return $payload;

        },
        'video' => function (PayloadBuilder $payload) {
            $payload->setParentNodes(array(180545));
            $payload->unSetData('star_rating');
            $payload->unSetData('comments');
            $payload->unSetData('credits');
            $payload->unSetData('keywords');

            return $payload;

        },
        'immagini' => function (PayloadBuilder $payload) {
            $payload->setParentNodes(array(180545));
            $payload->unSetData('star_rating');
            $payload->unSetData('comments');
            $payload->unSetData('credits');
            $payload->unSetData('keywords');

            return $payload;

        },
        'allegati' => function (PayloadBuilder $payload) {
            $payload->setParentNodes(array(180545));
            $payload->unSetData('star_rating');
            $payload->unSetData('comments');
            $payload->unSetData('credits');
            $payload->unSetData('keywords');

            return $payload;

        }
    );

    // set main custom filter
    $mainFilter = function (
        PayloadBuilder $payload,
        HttpClient $client,
        ContentRepository $repository
    ) use (
        $relationFilters,
        &$errors
    ) {

        $currentRemoteId = $payload->getMetadaData('remoteId');
        $errors[$currentRemoteId] = array();

        $payload->setParentNodes(array(180544));
        $payload->unSetData('internal_comments');
        $payload->unSetData('persone');
        $payload->unSetData('tematica');
        $payload->unSetData('argomento');
        $payload->unSetData('fonte');

        foreach ($relationFilters as $identifier => $relatedClosure) {
            $relationData = array();
            $relationList = $payload->getData($identifier);
            foreach ($relationList as $language => $relations) {
                foreach ($relations as $relation) {
                    try {
                        $newRelation = $client->import(
                            $relation['id'],
                            $repository,
                            $relatedClosure
                        );
                        if ($newRelation['message'] == 'success') {
                            $relationData[] = $newRelation['content']['metadata']['remoteId'];
                        } else {
                            $errors[$currentRemoteId]['fields'][$identifier]['errorImport'] = $newRelation['message'];
                        }
                    } catch (Exception $e) {
                        $errors[$currentRemoteId]['fields'][$identifier]['errorImport'] = $e->getMessage();
                    }
                }
            }

            if (empty( $relationData )) {
                if (!empty( $relationList )) {
                    $errors[$currentRemoteId]['fields'][$identifier]['remoteData'] = $relationList;
                }
                $payload->unSetData($identifier);
            } else {
                $payload->setData(null, $identifier, $relationData);
            }
        }

        return $payload;
    };

    try {

        $repository = new ContentRepository();
        $repository->setEnvironment(new DefaultEnvironmentSettings());

        $host = 'http://www.ufficiostampa.provincia.tn.it/';
        $query = "classes 'comunicato' and persone.id = '11351' and published range [2016-01-01, today] sort [published=>desc] limit 3";

        $client = new HttpClient($host);

        // find in remotes
        $client->find($query,
            // run on each search hit with custom filter callback
            function ($response) use (&$data, &$errors, $client, $repository, $mainFilter) {
                foreach ($response['searchHits'] as $item) {
                    $import = $client->import(
                        $item,
                        $repository,
                        $mainFilter // use the custom payload filter to remove wrong data
                    );
                    if ($import['message'] == 'success') {
                        $data[] = $import['content']['metadata'];
                    } else {
                        $errors[$item['metadata']['remoteId']]['errorImport'] = $import['message'];
                    }
                }
            }
        );

    } catch (Exception $e) {
        $data = array($e->getMessage());
    }

    print_r($errors);
    print_r($data);

} catch (Exception $e) {
    echo $e->getMessage();
}