<?php

$Module = array( 'name' => 'OpenData' );

$ViewList = array();

$ViewList['import'] = array(
    'functions' => array( 'import' ),
    'script' => 'import.php',
    'params' => array(),
    'unordered_params' => array()
);
$ViewList['api'] = array(
    'functions' => array( 'api' ),
    'script' => 'api.php',
    'params' => array( 'Environment', 'Action', 'Param' ),
    'unordered_params' => array()
);
$ViewList['console'] = array(
    'functions' => array( 'console' ),
    'script' => 'console.php',
    'params' => array( 'UseCurrentUser' ),
    'unordered_params' => array()
);
$ViewList['analyzer'] = array(
    'functions' => array( 'analyzer' ),
    'script' => 'analyzer.php',
    'params' => array(),
    'unordered_params' => array()
);
$ViewList['datatable'] = array(
    'functions' => array( 'datatable' ),
    'script' => 'datatable.php',
    'params' => array(),
    'unordered_params' => array()
);
$ViewList['help'] = array(
    'functions' => array( 'console' ),
    'script' => 'help.php',
    'params' => array( 'Section', 'Identifier' ),
    'unordered_params' => array()
);
$ViewList['check_queries'] = array(
    'functions' => array( 'queries' ),
    'script' => 'check_queries.php',
    'params' => array(),
    'unordered_params' => array()
);

$FunctionList = array();
$FunctionList['api'] = array();
$FunctionList['console'] = array();
$FunctionList['analyzer'] = array();
$FunctionList['datatable'] = array();
$FunctionList['import'] = array();
$FunctionList['queries'] = array();

$presetList = array();
foreach( \Opencontent\Opendata\Api\EnvironmentLoader::getAvailablePresetIdentifiers() as $preset )
{
    $presetList[$preset] = array( 'Name' => $preset, 'value' => $preset );
}
$FunctionList['environment'] = array(
    'PresetList' => array(
        'name' => 'PresetList',
        'values' => $presetList
    )
);

