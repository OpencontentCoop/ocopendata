<?php

use Opencontent\Opendata\Api\ContentSearch;
use Opencontent\Opendata\Api\ClassRepository;
use Opencontent\Opendata\Api\AttributeConverterLoader;
use Opencontent\Opendata\Api\QueryLanguage\EzFind\ArrayQueryBuilder;

class SearchQueryCSVExporter extends AbstarctExporter
{

    const MAX_DIRECT_DOWNLOAD_ITEMS = 1000;

    /**
     * @var ContentSearch
     */
    protected $contentSearch;

    /**
     * @var ClassRepository
     */
    protected $classRepository;

    protected $queryString;

    protected $CSVheaders;

    protected $uniqueClassIdentifier;

    protected $language;

    protected $classFields = array();

    protected $download;

    protected $downloadId;

    protected $iteration;

    protected $count;

    protected $maxSearchLimit;

    public function __construct($parentNodeId, $queryString)
    {
        $this->functionName = 'csv';

        $this->ini = eZINI::instance('exportas.ini');
        $this->setOptions($this->ini->group('Settings'));

        $currentEnvironment = new CsvEnvironmentSettings;
        $currentEnvironment->__set('identifier', 'csv');
        $currentEnvironment->__set('debug', false);
        $this->maxSearchLimit = $currentEnvironment->getMaxSearchLimit();
        $this->contentSearch = new ContentSearch();
        $this->contentSearch->setEnvironment($currentEnvironment);
        $this->queryString = $queryString;

        if (!$this->queryString){
            $arrayQueryBuilder = new ArrayQueryBuilder();
            $this->queryString = (string)$arrayQueryBuilder->instanceQuery($_GET);
        }

        $this->filename = uniqid('export_');

        $this->classRepository = new ClassRepository();

        $this->language = eZLocale::currentLocaleCode();

        $http = eZHTTPTool::instance();
        if ($http->hasGetVariable('download_id')){
            $this->downloadId = $http->getVariable('download_id');
            $this->filename = $this->downloadId;
            $this->iteration = intval( $http->getVariable('iteration') );
            if ($http->hasGetVariable('download')){
                $this->download = true;
            }
        }
    }

    function transformNode(eZContentObjectTreeNode $node)
    {
        return null;
    }

    public function fetch()
    {
        return $this->contentSearch->search($this->queryString);
    }

    public function fetchCount()
    {
        if ($this->count === null) {
            $result = $this->contentSearch->search($this->queryString);

            $this->count = $result->totalCount;
        }
        return $this->count;
    }

    protected function csvHeaders($item)
    {
        $this->uniqueClassIdentifier = $item['metadata']['classIdentifier'];
        $class = $this->classRepository->load($this->uniqueClassIdentifier);
        $this->CSVheaders = $this->csvMetaHeaders();
        foreach ($class->fields as $field) {

            $header = $this->csvHeader($field);

            if (is_string($header)) {
                $this->CSVheaders[] = $header;
                $this->classFields[$field['identifier']] = $field['identifier'];
            } else {
                $this->CSVheaders = array_merge($this->CSVheaders, $header);
            }
        }

        return $this->CSVheaders;
    }

    protected function csvMetaHeaders()
    {
        return array(
            'remoteId',
            'published',
            'modified'
        );
    }

    protected function csvHeader($field)
    {
        $header = $field['name'][$this->language];
        switch ($field['dataType']) {

            case 'ezmatrix': {
                $baseHeader = $header;
                $header = array();
                $fieldIdentifiers = array();
                foreach ($field['template']['format'][0][0] as $key => $value) {
                    $columnHeader = str_replace('string (', ' (', $value);
                    $header[] = $baseHeader . $columnHeader;
                    $fieldIdentifiers[] = $key;
                }
                $this->classFields[$field['identifier']] = $fieldIdentifiers;
            }
                break;

            case 'ezgmaplocation': {
                $header = array('lat','lon','address');
                $this->classFields[$field['identifier']] = $header;
            }
                break;
        }

        return $header;
    }

    function transformItem($item)
    {
        $data = $item['data'][$this->language];

        if ($this->uniqueClassIdentifier === null) {
            $this->uniqueClassIdentifier = $item['metadata']['classIdentifier'];
        } else {
            if ($this->uniqueClassIdentifier != $item['metadata']['classIdentifier']) {
                throw new Exception("Multiple class export not allowed");
            }
        }

        $stringData = array();

        foreach ($this->csvMetaHeaders() as $meta) {
            $stringData[$meta] = $item['metadata'][$meta];
        }

        foreach (array_keys($this->classFields) as $key) {
            $field = $data[$key];
            list( $classIdentifier, $identifier ) = explode('/', $field['identifier']);
            $converter = AttributeConverterLoader::load(
                $classIdentifier,
                $identifier,
                $field['datatype']
            );
            switch ($field['datatype']) {

                case 'ezmatrix': {
                    foreach ($this->classFields[$identifier] as $columnIdentifier) {
                        $stringData[$key . '.' . $columnIdentifier] = $converter->toCSVString(
                            $field['content'],
                            $columnIdentifier
                        );
                    }
                }
                    break;

                case 'ezgmaplocation': {
                    foreach ($this->classFields[$identifier] as $columnIdentifier) {
                        if ($columnIdentifier == 'lat')
                            $stringData[$key . '.' . $columnIdentifier] = $field['content']['latitude'];
                        elseif ($columnIdentifier == 'lon')
                            $stringData[$key . '.' . $columnIdentifier] = $field['content']['longitude'];
                        elseif ($columnIdentifier == 'address')
                            $stringData[$key . '.' . $columnIdentifier] = $field['content']['address'];
                    }
                }
                    break;

                case 'ezobjectrelation':
                case 'ezobjectrelationlist': {
                    $stringData[$key] = $converter->toCSVString($field['content'], $this->language);
                }
                    break;

                default: {
                    $stringData[$key] = $converter->toCSVString($field['content']);
                }
                    break;
            }
        }
        return $stringData;
    }

    function handleDownload()
    {
        if ($this->download == true) {
            $fileHandler = $this->tempFile($this->filename);
            $this->downloadTempFile($fileHandler, $this->tempFileName($this->filename));
        }else {

            $this->fetchCount();

            if ($this->downloadId !== null) {

                $this->handlePaginateDownload();

            } elseif ($this->count > self::MAX_DIRECT_DOWNLOAD_ITEMS ||
                      eZINI::instance('ocopendata.ini')->variable('GeneralSettings', 'ForcePaginateDownload') == 'enabled') {

                $this->startPaginateDownload();

            } else {

                try {

                    $filename = $this->filename . '.csv';
                    header('X-Powered-By: eZ Publish');
                    header('Content-Description: File Transfer');
                    header('Content-Type: text/csv; charset=utf-8');
                    header("Content-Disposition: attachment; filename=$filename");
                    header("Pragma: no-cache");
                    header("Expires: 0");

                    $output = fopen('php://output', 'w');
                    $runOnce = false;

                    do {
                        $result = $this->fetch();
                        foreach ($result->searchHits as $item) {
                            if (!$runOnce) {
                                fputcsv(
                                    $output,
                                    $this->csvHeaders($item),
                                    $this->options['CSVDelimiter'],
                                    $this->options['CSVEnclosure']
                                );
                                $runOnce = true;
                            }
                            $values = $this->transformItem($item);
                            fputcsv($output, array_values($values), $this->options['CSVDelimiter'], $this->options['CSVEnclosure']);
                            flush();
                        }
                        $this->queryString = $result->nextPageQuery;

                    } while ($this->queryString != null);

                } catch (Exception $e) {
                    echo $e->getMessage();
                }
            }
        }
    }

    protected function startPaginateDownload()
    {
        $this->tempFile($this->filename);

        echo $this->getPaginateTemplate(array(
            'query' => $this->queryString,
            'download_id' => $this->filename,
            'iteration' => 0,
            'count' => $this->count,
            'last' => 0,
            'limit' => $this->maxSearchLimit
        ));
    }

    protected function getPaginateTemplate($variables)
    {
        $tpl = eZTemplate::factory();
        foreach($variables as $key => $value){
            $tpl->setVariable($key, $value);
        }
        return $tpl->fetch('design:exportas/download_paginate.tpl');
    }

    protected function tempFile($filename)
    {
        $filename = $this->tempFileName($filename);
        $fileHandler = eZClusterFileHandler::instance($filename);
        if (!$fileHandler->exists()) {
            $fileHandler->storeContents('', 'exportas-temp', 'text/csv');
        }
        return $fileHandler;
    }

    protected function tempFileName($filename)
    {
        return eZSys::cacheDirectory() . '/tmp/' . $filename . '.csv';
    }

    protected function handlePaginateDownload()
    {
        $fileHandler = $this->tempFile($this->filename);

        $tempFilename = eZSys::cacheDirectory() . '/tmp/' . uniqid('exportaspaginate_') . '.temp';
        eZFile::create($tempFilename, false, $fileHandler->fetchContents());

        $output = fopen($tempFilename, 'a');
        $result = $this->fetch();

        $makeHeaders = $this->iteration == 0;

        foreach ($result->searchHits as $item) {
            $headers = $this->csvHeaders($item);
            if ($makeHeaders) {
                fputcsv(
                    $output,
                    $headers,
                    $this->options['CSVDelimiter'],
                    $this->options['CSVEnclosure']
                );
            }
            $values = $this->transformItem($item);
            fputcsv($output, array_values($values), $this->options['CSVDelimiter'], $this->options['CSVEnclosure']);
            $makeHeaders = false;
        }
        $this->queryString = $result->nextPageQuery;

        $fileHandler->storeContents( file_get_contents($tempFilename) );
        unlink($tempFilename);

        header('Content-Type: application/json');
        echo json_encode( array(
            'query' => $this->queryString,
            'download_id' => $this->filename,
            'iteration' => ++$this->iteration,
            'last' => count( $result->searchHits ),
            'count' => $this->count,
            'limit' => $this->maxSearchLimit
        ) );
        eZExecution::cleanExit();
    }

    /**
     * @param eZClusterFileHandlerInterface $file
     * @param string $filename
     */
    protected function downloadTempFile( $file, $filename )
    {
        if ( $file->exists() )
        {
            $fileSize = $file->size();
            if ( isset( $_SERVER['HTTP_RANGE'] ) && preg_match( "/^bytes=(\d+)-(\d+)?$/", trim( $_SERVER['HTTP_RANGE'] ), $matches ) )
            {
                $fileOffset = $matches[1];
                $contentLength = isset( $matches[2] ) ? $matches[2] - $matches[1] + 1 : $fileSize - $matches[1];
            }
            else
            {
                $fileOffset = 0;
                $contentLength = $fileSize;
            }

            eZFile::downloadHeaders(
                $filename,
                true,
                basename($filename),
                $fileOffset,
                $contentLength,
                $fileSize
            );
            header('Content-Type: text/csv; charset=utf-8');
            header("Pragma: no-cache");
            header("Expires: 0");

            try
            {
                $file->passthrough( $fileOffset, $contentLength );
            }
            catch ( Exception $e )
            {
                eZDebug::writeError( $e->getMessage, __METHOD__ );
                header( $_SERVER["SERVER_PROTOCOL"] . ' 404 Not Found' );
            }

            $file->purge();
            eZExecution::cleanExit();
        }else{
            eZDebug::writeError( $e->getMessage, __METHOD__ );
            header( $_SERVER["SERVER_PROTOCOL"] . ' 500 Internal Server Error' );
            eZExecution::cleanExit();
        }
    }

}
