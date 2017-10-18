<?php
$Module = $Params['Module'];
$ObjectID = $Params['ObjectID'];

$html = '<html>';
$html .= '<head>';
$html .= '<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" crossorigin="anonymous">';
$html .= '</head>';
$html .= '<body>';
$html .= '<div class="container">';

try {
    $object = eZContentObject::fetch($ObjectID);
    if (!$object instanceof eZContentObject){
        throw new Exception("Object $ObjectID not found");
    }
    $tools = new OCOpenDataTools();
    $data = $tools->getDatasetFromObject($object);

    $json = array(
        'result' => 'success',
        'data' => $data
    );

    $html .= '<pre>' . print_r($data, 1) . '</pre>';
} catch (Exception $e) {
    $json = array(
        'result' => 'error',
        'error' => $e->getMessage()
    );

    $html .= "<h1>Conversione in dataset non riuscita</h1> ({$e->getMessage()})";
}

$html .= '</div>';
$html .= '</body>';
$html .= '</html>';

$format = 'json';
if (eZHTTPTool::instance()->hasGetVariable('format')) {
    $format = eZHTTPTool::instance()->getVariable('format');
}

if ($format == 'json') {
    header('Content-Type: application/json');
    echo json_encode($json);
} elseif ($format == 'html') {
    echo $html;
    eZDisplayDebug();
}

eZExecution::cleanExit();
