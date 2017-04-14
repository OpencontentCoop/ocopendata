<?php

$Module = array( 'name' => 'OpenData CKAN' );

$ViewList = array();
$ViewList['push'] = array(
    'functions' => array( 'push' ),
    'script' => 'push.php',
    'params' => array( 'ObjectID', 'Alias' ),
    'unordered_params' => array()
);
$ViewList['list'] = array(
    'functions' => array( 'push' ),
    'script' => 'list.php',
    'params' => array('Action','Parameter'),
    'unordered_params' => array()
);
$ViewList['delete'] = array(
    'functions' => array( 'push' ),
    'script' => 'delete.php',
    'params' => array( 'ObjectID', 'Alias' ),
    'unordered_params' => array()
);
$ViewList['purge'] = array(
    'functions' => array( 'push' ),
    'script' => 'purge.php',
    'params' => array( 'ObjectID', 'Alias' ),
    'unordered_params' => array()
);
$ViewList['show'] = array(
    'functions' => array( 'view' ),
    'script' => 'show.php',
    'params' => array( 'ObjectID', 'Alias' ),
    'unordered_params' => array()
);
$ViewList['convert'] = array(
    'functions' => array( 'view' ),
    'script' => 'convert.php',
    'params' => array( 'ObjectID' ),
    'unordered_params' => array()
);

$FunctionList = array();
$FunctionList['push'] = array();
$FunctionList['view'] = array();

