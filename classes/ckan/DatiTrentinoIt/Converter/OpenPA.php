<?php

namespace Opencontent\Ckan\DatiTrentinoIt\Converter;

use Opencontent\Ckan\DatiTrentinoIt\Converter;
use Opencontent\Opendata\Api\QueryLanguage\EzFind\ArrayQueryConverter;
use Opencontent\Opendata\Api\QueryLanguage\EzFind\QueryBuilder;
use Exception;

class OpenPA extends Converter
{

    protected function getExtras()
    {
        $extras = parent::getExtras();
        $extras[] = array(
            'key' => 'Generator',
            'value' => 'http://www.comunweb.it'
        );

        return $extras;
    }

    protected function getGeoNamesUrl()
    {
        $geoNames = \eZINI::instance('geonames.ini')->hasGroup('GeoNamesId') ? \eZINI::instance('geonames.ini')->group('GeoNamesId') : array();
        $instanceId = \OpenPAInstance::current()->getIdentifier();
        if (isset( $geoNames[$instanceId] )) {
            return 'http://www.geonames.org/' . $geoNames[$instanceId];
        }

        return parent::getGeoNamesUrl();
    }

    /**
     * Converte la query dell'url della risorsa da string a parametri GET per evitare problemi di url encoding
     *
     * @param $url
     *
     * @return string
     */
    protected function fixApiUrl($url)
    {
        $data = parse_url($url);
        if (!isset( $data['query'] )
            && ( strpos($data['path'], 'api/opendata/v2') !== false
                 || strpos($data['path'], 'exportas/custom/csv_search') !== false )
        ) {

            $parts = explode('/', $data['path']);

            $query = array_pop($parts);
            $path = implode('/', $parts);
            $scheme = $data['scheme'];
            $host = $data['host'];

            $httpQuery = $this->httpBuildQuery($query);
            if ($httpQuery) {
                $url = "$scheme://$host$path?" . $httpQuery;
            }
        }

        return $this->fixUrl($url);
    }

    public static function httpBuildQuery($query)
    {
        try {
            $query = urldecode($query);
            $builder = new QueryBuilder();
            $converter = new ArrayQueryConverter();
            $queryObject = $builder->instanceQuery($query);
            $queryObject->setConverter($converter);

            $data = $queryObject->convert();

            return http_build_query($data);

        } catch (Exception $e) {

            \eZDebug::writeError($e->getMessage());

            return null;
        }
    }

}
