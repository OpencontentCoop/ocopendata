<?php

$module = $Params['Module'];
$tpl = eZTemplate::factory();
$http = eZHTTPTool::instance();
$useCurrentUser = $Params['UseCurrentUser'];

$query = null;
$error = null;
$tokens = array();
$classRepository = new \Opencontent\Opendata\Api\ClassRepository();
$classes = $classRepository->listClassIdentifiers();
sort($classes);

if ( $http->hasGetVariable( 'query' ) )
{
    $query = $http->getVariable( 'query' );
}

try
{
    $factory = new \Opencontent\Opendata\Api\QueryLanguage\EzFind\QueryBuilder();
    $tokenFactory = $factory->getTokenFactory();
    $fields = $factory->fields;
    $metaFields = $factory->metaFields;
    $operators = $factory->operators;
    $parameters = $factory->parameters;
    $tokens = array_unique( array_merge( $fields, $parameters, $operators ) );
    sort( $tokens );

}
catch ( Exception $e )
{
    $error = $e->getMessage();
}

$tpl->setVariable( 'use_current_user', $useCurrentUser );
$tpl->setVariable( 'error', $error );
$tpl->setVariable( 'query', $query );
$tpl->setVariable( 'tokens', $tokens );
$tpl->setVariable( 'classes', $classes );

echo $tpl->fetch( 'design:opendata/console.tpl' );
eZDisplayDebug();
eZExecution::cleanExit();