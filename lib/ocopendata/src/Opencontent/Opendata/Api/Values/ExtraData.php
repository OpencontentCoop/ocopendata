<?php

namespace Opencontent\Opendata\Api\Values;

use eZContentObject;
use eZINI;
use eZSolrDoc;
use ezfIndexPlugin;
use SimpleXMLElement;
use Opencontent\Opendata\Api\Gateway\SolrStorage;

class ExtraData extends ContentData
{
    public static function createFromEzContentObject(eZContentObject $contentObject)
    {
        $extraData = new ExtraData();

        $opendataIni = eZINI::instance('ocopendata.ini');
        $generalExtraDataProviders = $opendataIni->hasVariable('ExtraDataProviders', 'General') ?
            (array)$opendataIni->variable('ExtraDataProviders', 'General') : array();

        $classExtraDataProviders = $opendataIni->hasVariable('ExtraDataProviders', 'Class') ?
            (array)$opendataIni->variable('ExtraDataProviders', 'Class') : array();

        $extraDataProviders = array();
        if ($generalExtraDataProviders) {
            $extraDataProviders = $generalExtraDataProviders;
        }
        if (array_key_exists($contentObject->attribute('class_identifier'), $classExtraDataProviders)) {
            $extraDataProviders[] = $classExtraDataProviders[$contentObject->attribute('class_identifier')];
        }

        foreach ($extraDataProviders as $extraDataProviderClassString) {
            if (class_exists($extraDataProviderClassString)) {
                $provider = new $extraDataProviderClassString;

                if ($provider instanceof ExtraDataProviderInterface) {
                    $provider->setExtraDataFromContentObject($contentObject, $extraData);
                }
            }
        }


        return $extraData;
    }

    public function set($field, $value, $languageCode = null)
    {
        if (!$languageCode){
            $languageCode = \eZLocale::currentLocaleCode();
        }
        $this->data[$languageCode][$field] = $value;
    }

}
