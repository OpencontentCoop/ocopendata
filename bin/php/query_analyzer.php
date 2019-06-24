<?php
require 'autoload.php';

$script = eZScript::instance(array(
    'description' => ("OCQL Query analysis\n\n"),
    'use-session' => false,
    'use-modules' => true,
    'use-extensions' => true
));

$script->startup();

$options = $script->getOptions();
$script->initialize();
$script->setUseDebugAccumulators(true);

$cli = eZCLI::instance();
$output = new ezcConsoleOutput();
try {
    while (true) {
        $query = ezcConsoleDialogViewer::displayDialog(
            new ezcConsoleQuestionDialog($output, new ezcConsoleQuestionDialogOptions(array(
                'text' => "Query:",
                'showResults' => true
            ))));

        $builder = new \Opencontent\Opendata\Api\QueryLanguage\EzFind\QueryBuilder();
        $ezFindQuery = null;
        try {
            $queryObject = $builder->instanceQuery($query);
            /** @var ArrayObject $ezFindQuery */
            $ezFindQuery = $queryObject->convert();
        } catch (Exception $e) {
            $ezFindQuery = new ArrayObject(array(
                'error_message' => $e->getMessage(),
                'error_code' => \Opencontent\Opendata\Api\Exception\BaseException::cleanErrorCode(get_class($e)),
                'error_trace' => explode("\n", $e->getTraceAsString())
            ));
        }

        $tokenFactory = $builder->getTokenFactory();
        $parser = new \Opencontent\QueryLanguage\Parser(new \Opencontent\QueryLanguage\Query($query));
        $query = $parser->setTokenFactory($tokenFactory)->parse();

        $converter = new \Opencontent\QueryLanguage\Converter\AnalyzerQueryConverter();
        $converter->setQuery($query);

        $analysis = $converter->convert();

        print_r($analysis);
        print_r($ezFindQuery->getArrayCopy());
    }
} catch (Exception $e) {
    $cli->error($e->getMessage());
}
$script->shutdown();