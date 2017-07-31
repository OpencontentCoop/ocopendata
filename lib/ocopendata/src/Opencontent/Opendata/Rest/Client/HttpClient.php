<?php

namespace Opencontent\Opendata\Rest\Client;


use Opencontent\Opendata\Api\ClassRepository;
use Opencontent\Opendata\Api\ContentRepository;

class HttpClient
{
    protected $server;

    protected $login;

    protected $password;

    protected $proxy;

    protected $proxyPort;

    protected $proxyLogin;

    protected $proxyPassword;

    protected $proxyAuthType;

    protected $apiEnvironmentPreset;

    protected $apiEndPointBase;

    public static $connectionTimeout = 60;

    public static $processTimeout = 60;

    public $logger;

    public function __construct(
        $server,
        $login = null,
        $password = null,
        $apiEnvironmentPreset = 'content',
        $apiEndPointBase = '/api/opendata/v2'
    ) {
        $this->server = rtrim($server, '/');
        $this->login = $login;
        $this->password = $password;
        $this->apiEnvironmentPreset = $apiEnvironmentPreset;
        $this->apiEndPointBase = rtrim($apiEndPointBase, '/');
    }

    public function getServer()
    {
        return $this->server;
    }

    public function setProxy(
        $proxy,
        $proxyPort,
        $proxyLogin = null,
        $proxyPassword = null,
        $proxyAuthType = 1
    ) {
        $this->proxy = $proxy;
        $this->proxyPort = $proxyPort;
        $this->proxyLogin = $proxyLogin;
        $this->proxyPassword = $proxyPassword;
        $this->proxyAuthType = $proxyAuthType;
    }

    public function create($data)
    {
        return $this->request('POST', $this->buildUrl('/create'), json_encode($data));
    }

    public function createUpdate($data)
    {
        try {
            $result = $this->create($data);
        } catch (\Exception $e) {
            $result = $this->update($data);
        }

        return $result;
    }

    public function getPayload($data)
    {
        if (is_numeric($data) || is_string($data)) {
            $data = $this->read($data);
        }
        unset( $data['metadata']['id'] );
        unset( $data['metadata']['class'] );
        unset( $data['metadata']['sectionIdentifier'] );
        unset( $data['metadata']['ownerId'] );
        unset( $data['metadata']['ownerName'] );
        unset( $data['metadata']['mainNodeId'] );
        unset( $data['metadata']['published'] );
        unset( $data['metadata']['modified'] );
        unset( $data['metadata']['name'] );
        unset( $data['metadata']['link'] );
        unset( $data['metadata']['stateIdentifiers'] );

        return new PayloadBuilder($data);
    }

    public function read($id)
    {
        return $this->request('GET', $this->buildUrl('/read/' . $id));
    }

    public function browse($nodeId, $limit = 10, $offset = 0)
    {
        return $this->request('GET', $this->buildUrl("/browse/$nodeId?limit=$limit&offset=$offset"));
    }

    public function update($data)
    {
        return $this->request('POST', $this->buildUrl('/update'), json_encode($data));
    }

    public function delete($data)
    {
        return $this->request('POST', $this->buildUrl('/delete'), json_encode($data));
    }

    public function findAll($query, \Closure $paginationCallback = null)
    {
        $collectData = array();
        $nextPageQuery = $this->buildUrl('/search/' . urlencode($query));
        while($nextPageQuery){
            $data = $this->request('GET', $nextPageQuery);
            $collectData = array_merge($collectData, $data['searchHits']);
            $nextPageQuery = $data['nextPageQuery'];
            if ($paginationCallback instanceof \Closure){
                $paginationCallback($data);
            }
        }
        return $collectData;
    }

    public function find($query, \Closure $callback = null)
    {
        $result = $this->request('GET', $this->buildUrl('/search/' . urlencode($query)));
        if ($callback instanceof \Closure){
            $result = $callback($result);
        }
        return $result;
    }

    protected function buildUrl($path)
    {
        return $this->server . $this->apiEndPointBase . '/' . $this->apiEnvironmentPreset . $path;
    }

    public function request($method, $url, $data = null)
    {
        $headers = array();

        if ($this->login && $this->password) {
            $credentials = "{$this->login}:{$this->password}";
            $headers[] = "Authorization: Basic " . base64_encode($credentials);
        }

        $ch = curl_init();
        if ($method == "POST") {
            curl_setopt($ch, CURLOPT_POST, 1);
        }
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            $headers[] = 'Content-Type: application/json';
            $headers[] = 'Content-Length: ' . strlen($data);
        }
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::$connectionTimeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::$processTimeout);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);

        if ($this->proxy !== null) {
            curl_setopt($ch, CURLOPT_PROXY, $this->proxy . ':' . $this->proxyPort);
            if ($this->proxyLogin !== null) {
                curl_setopt(
                    $ch,
                    CURLOPT_PROXYUSERPWD,
                    $this->proxyLogin . ':' . $this->proxyPassword
                );
                curl_setopt($ch, CURLOPT_PROXYAUTH, $this->proxyAuthType);
            }
        }

        $data = curl_exec($ch);

        if ($data === false) {
            $errorCode = curl_errno($ch) * -1;
            $errorMessage = curl_error($ch);
            curl_close($ch);
            throw new \Exception($errorMessage, $errorCode);
        }

        $info = curl_getinfo($ch);
        if (class_exists('\eZDebug')){
            \eZDebug::writeDebug($info['request_header'], __METHOD__);
        }

        curl_close($ch);

        $headers = substr($data, 0, $info['header_size']);
        if ($info['download_content_length'] > 0) {
            $body = substr($data, -$info['download_content_length']);
        } else {
            $body = substr($data, $info['header_size']);
        }

        return $this->parseResponse($info, $headers, $body);
    }

    protected function parseResponse($info, $headers, $body)
    {
        $data = json_decode($body);

        if (isset( $data->error_message )) {
            $errorMessage = '';
            if (isset( $data->error_type )) {
                $errorMessage = $data->error_type . ': ';
            }
            $errorMessage .= $data->error_message;
            throw new \Exception($errorMessage);
        }

        if ($info['http_code'] == 401) {
            throw new \Exception("Authorization Required");
        }

        if (!in_array($info['http_code'], array(100, 200, 201, 202))) {
            throw new \Exception("Unknown error");
        }

        $data = json_decode($body, true);

        return $data;
    }

    public function import($data, ContentRepository $repository, \Closure $payloadFilterClosure = null)
    {
        $payload = $this->getPayload($data);
        if ($payloadFilterClosure instanceof \Closure){
            $payload = $payloadFilterClosure($payload, $this, $repository);
        }

        return $repository->createUpdate((array)$payload);
    }
}
