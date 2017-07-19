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
    private static $recursionProtect = array();

    public static function createFromEzContentObject(eZContentObject $contentObject)
    {
        $extraData = new ExtraData();
        //@todo creare una sorta di ExtraDataRepository per permettere ad altre classi di settare extra data
        self::setDataFromIndexPlugins($contentObject, $extraData);

        return $extraData;
    }

    private static function setDataFromIndexPlugins(eZContentObject $contentObject, ExtraData $extraData)
    {
        if (!isset( self::$recursionProtect[$contentObject->attribute('id')] )) {

            self::$recursionProtect[$contentObject->attribute('id')] = true;

            $availableLanguages = array_keys($contentObject->allLanguages());
            $ezFindIni = eZINI::instance('ezfind.ini');

            $generalPlugins = $ezFindIni->hasVariable('IndexPlugins', 'General') ?
                (array)$ezFindIni->variable('IndexPlugins', 'General') : array();

            $classPlugins = $ezFindIni->hasVariable('IndexPlugins', 'Class') ?
                (array)$ezFindIni->variable('IndexPlugins', 'Class') : array();

            $indexPlugins = array();
            if ($generalPlugins) {
                $indexPlugins = $generalPlugins;
            }
            if (array_key_exists($contentObject->attribute('class_identifier'), $classPlugins)) {
                $indexPlugins[] = $classPlugins[$contentObject->attribute('class_identifier')];
            }

            /** @var eZSolrDoc[] $docList */
            $docList = array();
            foreach ($availableLanguages as $language) {
                $doc = new eZSolrDoc();
                $docList[$language] = $doc;
            }
            foreach ($indexPlugins as $pluginClassString) {
                if (class_exists($pluginClassString)) {
                    $plugin = new $pluginClassString;

                    if ($plugin instanceof ezfIndexPlugin) {
                        $plugin->modify($contentObject, $docList);
                    }
                }
            }

            $extra = array();
            foreach ($docList as $language => $doc) {
                $xml = simplexml_load_string($doc->docToXML());
                if ($xml) {
                    /** @var SimpleXMLElement $field */
                    foreach ($xml->children() as $field) {
                        if ((string)$field['name'] != SolrStorage::getSolrIdentifier()) {
                            $extra[$language][(string)$field['name']] = (string)$field;
                        }
                    }
                }
            }

            foreach($extra as $key => $value) {
                $extraData[$key] = $value;
            }
        }

    }

}
