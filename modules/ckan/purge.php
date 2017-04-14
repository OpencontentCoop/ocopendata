<?php
/** @var eZModule $Module */
$Module = $Params['Module'];
$ObjectID = $Params['ObjectID'];
$Alias = $Params['Alias'];

$html = '<html>';
$html .= '<head>';
$html .= '<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" crossorigin="anonymous">';
$html .= '</head>';
$html .= '<body>';
$html .= '<div class="container">';


try {
    if ($ObjectID == 'org') {
        $tools = new OCOpenDataTools($Alias);
        $tools->deleteOrganization(true);
    } else {
        $object = eZContentObject::fetch($ObjectID);
        if (!$object instanceof eZContentObject){
            throw new Exception("Object $ObjectID not found");
        }
        $tools = new OCOpenDataTools($Alias);
        $tools->deleteObject($ObjectID, true);
    }

    $json = array('result' => 'success');

    $html .= "<h1>OK</h1>";

} catch (Exception $e) {

    $json = array(
        'result' => 'error',
        'error' => $e->getMessage()
    );

    $html .= "<h1>" . $e->getMessage() . "</h1>";

    if ($e instanceof OCOpenDataRequestException) {

        $html .= "<h3>" . $e->getResponseCode() . ' ' . $e->getResponseCodeMessage() . "</h3>";

        $html .= '<pre>' . print_r($e->getResponseError(), 1) . '</pre>';
    }

    try {
        if (is_numeric($ObjectID)) {
            $object = eZContentObject::fetch($ObjectID);
            $tools = new OCOpenDataTools($Alias);

            if ($e instanceof OCOpenDataRequestException && $e->getResponseCode() == 404) {
                $tools->getConverter()->markObjectDeleted($object, null);
            }

            $data = $tools->getDatasetFromObject($object);

        } else {
            $data = $tools->organizationBuilder->build();
        }

        $html .= '<pre>' . print_r($data, 1) . '</pre>';

    } catch (Exception $error) {
        $html .= "<em>Conversione non riuscita</em> ({$error->getMessage()})";
    }
}

$html .= '</div>';
$html .= '</body>';
$html .= '</html>';

$format = 'json';
if (eZHTTPTool::instance()->hasGetVariable('format')){
    $format = eZHTTPTool::instance()->getVariable('format');
}

if ($format == 'json'){
    header('Content-Type: application/json');
    echo json_encode( $json );
}elseif ($format == 'html'){
    echo $html;
    eZDisplayDebug();
}

eZExecution::cleanExit();

