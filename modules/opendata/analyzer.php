<?php

use Opencontent\Opendata\Api\QueryLanguage\EzFind\QueryBuilder;
use Opencontent\Opendata\Api\QueryLanguage\EzFind\SearchGateway;

$module = $Params['Module'];
$tpl = eZTemplate::factory();
$http = eZHTTPTool::instance();
$data = null;

if ( $http->hasGetVariable( 'query' ) )
{
    $query = urldecode( $http->getVariable( 'query' ) );

    try
    {
        $builder = new QueryBuilder();
        $builder->getTokenFactory()->setSubQueryResolver(function ($query){
            $searchGateway = new SearchGateway();
            $searchGateway->setEnvironmentSettings(new DefaultEnvironmentSettings());
            $searchGateway->setQueryBuilder(new QueryBuilder());
            return array_filter((array)$searchGateway->search($query, []));
        });
        $ezFindQuery = null;
        try
        {
            $queryObject = $builder->instanceQuery( $query );
            $ezFindQuery = $queryObject->convert();
        }
        catch ( Exception $e )
        {
            $ezFindQuery = array(
                'error_message' => $e->getMessage(),
                'error_code' => \Opencontent\Opendata\Api\Exception\BaseException::cleanErrorCode( get_class( $e ) ),
                'error_trace' => explode( "\n", $e->getTraceAsString() )
            );
        }

        $tokenFactory = $builder->getTokenFactory();
        $parser = new \Opencontent\QueryLanguage\Parser( new \Opencontent\QueryLanguage\Query( $query ) );
        $query = $parser->setTokenFactory( $tokenFactory )->parse();

        $converter = new \Opencontent\QueryLanguage\Converter\AnalyzerQueryConverter();
        $converter->setQuery( $query );

        $data = array(
            'analysis' => $converter->convert(),
            'ezfind' => $ezFindQuery
        );


    }
    catch ( Exception $e )
    {
        $data = array(
            'error_message' => $e->getMessage(),
            'error_code' => \Opencontent\Opendata\Api\Exception\BaseException::cleanErrorCode( get_class( $e ) ),
            'error_trace' => explode( "\n", $e->getTraceAsString() )
        );
    }
}
header('Content-Type: application/json');
echo json_encode( $data );
eZExecution::cleanExit();