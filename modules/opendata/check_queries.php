<?php

$http = eZHTTPTool::instance();
$tpl = eZTemplate::factory();
$isAsync = false;
$data = [];

if ($http->hasGetVariable('load')) {
    $isAsync = true;
    try {
        header('HTTP/1.1 200 OK');
        $data = OCOpenDataQueries::getInstance()->getStaticQueries(
            $http->hasGetVariable('withField') && (bool)$http->getVariable('withField'),
            $http->hasGetVariable('withError') && (bool)$http->getVariable('withError')
        );
    } catch (Exception $e) {
        header('HTTP/1.1 500 Internal Server Error');
        $data = ['error_message' => $e->getMessage()];
    }
}

if ($http->hasGetVariable('optimize')) {
    $isAsync = true;
    try {
        header('HTTP/1.1 200 OK');
        $data = OCOpenDataQueries::getInstance()->optimize($http->getVariable('optimize'));
    } catch (Exception $e) {
        header('HTTP/1.1 500 Internal Server Error');
        $data = ['error_message' => $e->getMessage()];
    }
}

if ($http->hasGetVariable('tag')) {
    $isAsync = true;
    try {
        header('HTTP/1.1 200 OK');
        $data = 0;
    } catch (Exception $e) {
        header('HTTP/1.1 500 Internal Server Error');
        $data = ['error_message' => $e->getMessage()];
    }
}

if ($http->hasGetVariable('save')) {
    $isAsync = true;
    $data = $_POST;
    foreach ($data['blocks'] as &$modifyBlock) {
        $attribute = eZPersistentObject::fetchObject(
            eZContentObjectAttribute::definition(),
            null,
            [
                "id" => (int)$modifyBlock['attribute_id'],
                "version" => (int)$modifyBlock['attribute_version'],
                "language_code" => $modifyBlock['attribute_language'],
            ]
        );
        $modifyBlock['modified'] = false;
        $modifyBlock['error'] = false;
        if ($attribute instanceof eZContentObjectAttribute
            && $attribute->attribute('data_type_string') == eZPageType::DATA_TYPE_STRING) {
            $source = $attribute->attribute('data_text');
            /** @var \eZPage $page */
            $page = \eZPage::createFromXML($source);
            foreach ($page->attribute('zones') as $zone) {
                if ($zone->attribute('zone_identifier') == $modifyBlock['zone_identifier']) {
                    /** @var \eZPageBlock[] $blocks */
                    $blocks = (array)$zone->attribute('blocks');
                    foreach ($blocks as $block) {
                        if ($block->attribute('id') == $modifyBlock['block_id']) {
                            $custom = $block->attribute('custom_attributes');
                            if (isset($custom['query'])) {
                                $query = $modifyBlock['query'];
                                $custom['query'] = $query;
                                $block->setAttribute('custom_attributes', $custom);
                                $attribute->fromString($page->toXML());
                                $attribute->store();
                                $modifyBlock['modified'] = true;
                            }
                        }
                    }
                    if (!$modifyBlock['modified']) {
                        $modifyBlock['error'] = 'block not found';
                    }
                } else {
                    $modifyBlock['error'] = 'zone not found';
                }
            }
        } else {
            $modifyBlock['error'] = 'attribute not found';
        }
    }
}

if ($isAsync) {
    header('Content-Type: application/json');
    echo json_encode($data);
    eZExecution::cleanExit();
}


$Result = [];
$Result['content'] = $tpl->fetch('design:opendata/check_queries.tpl');
$Result['path'] = [
    [
        'text' => 'Controllo delle query nei blocchi opendata',
        'url' => false,
    ],
];
$contentInfoArray = [
    'node_id' => null,
    'class_identifier' => null,
];
$contentInfoArray['persistent_variable'] = [
    'show_path' => false,
];
if (is_array($tpl->variable('persistent_variable'))) {
    $contentInfoArray['persistent_variable'] = array_merge(
        $contentInfoArray['persistent_variable'],
        $tpl->variable('persistent_variable')
    );
}
$Result['content_info'] = $contentInfoArray;

