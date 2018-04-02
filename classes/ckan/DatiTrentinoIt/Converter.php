<?php

namespace Opencontent\Ckan\DatiTrentinoIt;

use eZContentObject;
use eZContentObjectAttribute;
use eZCharTransform;

class Converter implements \OcOpenDataConverterInterface
{

    /**
     * @var eZContentObject
     */
    protected $object;

    /**
     * @var eZContentObjectAttribute[]
     */
    protected $dataMap;

    /**
     * @var \OcOpendataOrganizationBuilderInterface
     */
    public $organizationBuilder;

    public function setOrganizationBuilder(\OcOpendataOrganizationBuilderInterface $builder)
    {
        $this->organizationBuilder = $builder;
    }

    public function getDatasetFromObject(eZContentObject $object)
    {
        $this->object = $object;
        $this->dataMap = $object->dataMap();

        $dataset = new Dataset();

        $id = $this->getDatasetId($this->object);
        if ($id) {
            $dataset->setData('id', $id);
        }

        $dataset->name = $this->getName();
        $dataset->title = $this->fixText($this->getTitle());
        $dataset->author = $this->fixText($this->getAuthorName());
        $dataset->author_email = $this->getAuthorEmail();
        $dataset->maintainer = $this->fixText($this->getMaintainerName());
        $dataset->maintainer_email = $this->getMaintainerEmail();
        $dataset->license_id = $this->getLicenseId();
        $dataset->notes = $this->fixText($this->getNotes());
        $dataset->url = $this->getUrl();
        $dataset->version = $this->getVersion();
        $dataset->state = $this->getState();
        $dataset->type = $this->getType();
        $dataset->resources = $this->getResources();
        $dataset->tags = $this->getTags();
        $dataset->extras = $this->getExtras();
        $dataset->relationships_as_object = $this->getRelationshipsAsObject();
        $dataset->relationships_as_subject = $this->getRelationshipsAsSubject();
        $dataset->groups = $this->getGroups();
        $dataset->owner_org = $this->getOwnerOrg();
        $dataset->identifier = $this->organizationBuilder->build()->getCodiceIpa() . '-' . $this->object->attribute('id');
        $dataset->publisher_name = $this->organizationBuilder->build()->title;
        $dataset->publisher_identifier = $this->organizationBuilder->build()->getCodiceIpa();
        $dataset->geographical_geonames_url = $this->getGeoNamesUrl();
        $dataset->temporal_start = $this->getTemporalStart();
        $dataset->temporal_end = $this->getTemporalEnd();
        $dataset->holder_name = $this->organizationBuilder->build()->title;
        $dataset->holder_identifier = $this->organizationBuilder->build()->getCodiceIpa();
        $dataset->creator_name = $this->organizationBuilder->build()->title;
        $dataset->creator_identifier = $this->organizationBuilder->build()->getCodiceIpa();

        $dataset->issued = date("Y-m-d", $this->object->attribute('published'));
        $dataset->creation_date = date("Y-m-d", $this->object->attribute('published'));
        $dataset->modified = date("Y-m-d", time());

        $dataset->site_url = $this->getSiterUrl();

        return $dataset;
    }

    protected function fixText($string)
    {
        return str_replace(';', '', $string);
    }

    protected function fixApiUrl($url)
    {
        return $this->fixUrl($url);
    }

    protected function fixUrl($url)
    {
        $url = ltrim($url, '/');
        if (strpos($url, 'http') === false) {
            $siteUrl = rtrim(\eZINI::instance()->variable('SiteSettings', 'SiteURL'), '/') . '/';
            if (strpos($url, $siteUrl) === false) {
                $url = $siteUrl . $url;
            }
            if (strpos($url, 'http') === false) {
                $url = 'http://' . $url;
            }
        }

        return $url;
    }

    public function getDatasetId(eZContentObject $object)
    {
        if (strpos($object->attribute('remote_id'), $this->getRemotePrefix()) !== false) {
            return str_replace($this->getRemotePrefix(), '', $object->attribute('remote_id'));
        }

        return null;
    }

    public function getRemotePrefix()
    {
        return 'ckan_';
    }

    /**
     * @param eZContentObject $object
     * @param Dataset|string $dataset
     */
    public function markObjectPushed(eZContentObject $object, $dataset)
    {
        $id = null;
        if ($dataset instanceof Dataset && $dataset->getData('id')){
            $id = $dataset->getData('id');
        }elseif (is_string($dataset)){
            $id = (string)$dataset;
        }
        if ($id) {
            $object->setAttribute('remote_id', $this->getRemotePrefix() . $id);
            $object->store();
        }
    }

    public function markObjectDeleted(eZContentObject $object, $response)
    {
        $object->setAttribute('remote_id', \eZRemoteIdUtility::generate());
        $object->store();
    }

    protected function getCustomField($key)
    {
        switch ($key) {
            case 'Codifica Caratteri':
                return 'UTF-8';
                break;

            case 'Copertura Temporale (Data di inizio)':
                if (isset( $this->dataMap['from_time'] ) && $this->dataMap['from_time']->hasContent()) {
                    return date(DATE_ISO8601, $this->dataMap['from_time']->toString());
                }
                break;

            case 'Copertura Temporale (Data di fine)':
                if (isset( $this->dataMap['to_time'] ) && $this->dataMap['to_time']->hasContent()) {
                    return date(DATE_ISO8601, $this->dataMap['to_time']->toString());
                }
                break;

            case 'Aggiornamento':
                if (isset( $this->dataMap['frequency'] ) && $this->dataMap['frequency']->hasContent()) {
                    return $this->dataMap['frequency']->toString();
                }
                break;

            case 'Copertura Geografica':
                if (isset( $this->dataMap['geo'] ) && $this->dataMap['geo']->hasContent()) {
                    return $this->dataMap['geo']->toString();
                }
                break;

            case 'Titolare':
                return $this->getMaintainerName();
                break;

            case 'Descrizione campi':
                if (isset( $this->dataMap['fields_description'] ) && $this->dataMap['fields_description']->attribute('has_content')) {
                    if ($this->dataMap['fields_description']->attribute('data_type_string') == 'ezmatrix') {

                        $fields = $keys = array();
                        $columns = $this->dataMap['fields_description']->content()->attribute(
                            'columns'
                        );
                        foreach ($columns['sequential'] as $column) {
                            $keys[] = $column['identifier'];
                        }
                        $rows = $this->dataMap['fields_description']->content()->attribute(
                            'rows'
                        );
                        foreach ($rows['sequential'] as $row) {
                            $fields[] = array_combine($keys, $row['columns']);
                        }

                        $tpl = \eZTemplate::factory();
                        $tpl->setVariable('fields', $fields);

                        return $tpl->fetch("design:push/dataset_fields_description.tpl");
                    } elseif ($this->dataMap['fields_description']->attribute('data_type_string') == 'ezurl') {
                        $url = explode('|', $this->dataMap['fields_description']->toString());
                        $url = $url[0];

                        return $this->fixUrl($url);
                    } else {
                        return $this->dataMap['fields_description']->toString();
                    }
                } elseif (isset( $this->dataMap['fields_description_text'] ) && $this->dataMap['fields_description_text']->attribute('has_content')) {
                    return $this->dataMap['fields_description_text']->toString();
                }
                break;

            case 'Data di creazione':
            case 'Data di pubblicazione':
                return date(DATE_ISO8601, $this->object->attribute('published'));
                break;

            case 'Data di aggiornamento':
                return date(DATE_ISO8601, $this->object->attribute('modified'));
                break;

            case 'URL sito':
                if (isset( $this->dataMap['url_website'] ) && $this->dataMap['url_website']->hasContent()) {
                    $url = explode('|', $this->dataMap['url_website']->toString());
                    $url = $url[0];

                    return $this->fixUrl($url);
                }
                break;

        }

        return null;
    }

    protected function getName()
    {
        $trans = eZCharTransform::instance();
        $title = $this->object->attribute('name');
        $name = $trans->transformByGroup($title, 'urlalias');

        return strtolower($name);
    }

    protected function getTitle()
    {
        return $this->object->attribute('name');
    }

    protected function getAuthorName()
    {
        if (isset( $this->dataMap['author'] )
            && $this->dataMap['author']->hasContent()
        ) {
            $author = explode('|', $this->dataMap['author']->toString());

            return $author[0];
        }

        return null;
    }

    protected function getAuthorEmail()
    {
        if (isset( $this->dataMap['author'] )
            && $this->dataMap['author']->hasContent()
        ) {
            $author = explode('|', $this->dataMap['author']->toString());

            return $author[1];
        }

        return null;
    }

    protected function getMaintainerName()
    {
        if (isset( $this->dataMap['maintainer'] )
            && $this->dataMap['maintainer']->hasContent()
        ) {
            $maintainer = explode('|', $this->dataMap['maintainer']->toString());

            return $maintainer[0];
        }

        return null;
    }

    protected function getMaintainerEmail()
    {
        if (isset( $this->dataMap['maintainer'] )
            && $this->dataMap['maintainer']->hasContent()
        ) {
            $maintainer = explode('|', $this->dataMap['maintainer']->toString());

            return $maintainer[1];
        }

        return null;
    }

    protected function getLicenseId()
    {
        if (isset( $this->dataMap['license_id'] ) && $this->dataMap['license_id']->hasContent()) {
            return strtolower($this->dataMap['license_id']->toString());
        }

        return null;
    }

    protected function getNotes()
    {
        if (isset( $this->dataMap['notes'] ) && $this->dataMap['notes']->hasContent()) {
            return $this->dataMap['notes']->toString();
        }

        return null;
    }

    protected function getUrl()
    {
        $url = $this->object->attribute('main_node')->attribute('url_alias');

        return $this->fixUrl($url);
    }

    protected function getVersion()
    {
        if (isset( $this->dataMap['versione'] )) {
            if ($this->dataMap['versione']->hasContent()) {
                return $this->dataMap['versione']->toString();
            }

            /** @var \eZContentClassAttribute $classAttribute */
            $classAttribute = $this->dataMap['versione']->contentClassAttribute();
            if ($classAttribute->hasAttribute(\eZStringType::DEFAULT_STRING_FIELD)
                && !empty( $classAttribute->attribute(\eZStringType::DEFAULT_STRING_FIELD) )
            ) {
                return $classAttribute->attribute(\eZStringType::DEFAULT_STRING_FIELD);
            }
        }

        return $this->object->attribute('current_version');
    }

    protected function getState()
    {
        return 'active';
    }

    protected function getType()
    {
        return 'dataset';
    }

    protected function getResources()
    {
        $resources = array();
        foreach (array_keys($this->dataMap) as $attributeIdentifier) {
            if (strpos($attributeIdentifier, 'resource') !== false) {
                list( $resource, $number, $resourceIdentifier ) = explode('_', $attributeIdentifier);
                $resources[intval($number)][$resourceIdentifier] = $this->dataMap[$attributeIdentifier];
            }
        }

        $unset = array();
        /** @var eZContentObjectAttribute[] $resource */
        foreach ($resources as $number => $resource) {
            if (isset( $resource['url'] ) && $resource['url']->attribute('content') != '') {
                unset( $resources[$number]['file'] );
                unset( $resources[$number]['api'] );
            } elseif (isset( $resource['file'] ) && $resource['file']->attribute('has_content')) {
                unset( $resources[$number]['url'] );
                unset( $resources[$number]['api'] );
            } elseif (isset( $resource['api'] ) && $resource['api']->attribute('content') != '') {
                unset( $resources[$number]['url'] );
                unset( $resources[$number]['file'] );
            } else {
                $unset[] = $number;
            }
        }
        foreach ($unset as $number) {
            unset( $resources[$number] );
        }

        $resourceAttributes = array(
            "url" => null,
            "name" => null,
            "description" => null,
            "format" => null,
            "mimetype" => null,
            "mimetype_inner" => null,
            "size" => null,
            "last_modified" => null,
            "hash" => null,
            "resource_type" => null
        );

        $resourceList = array();
        foreach ($resources as $number => $resource) {
            $data = $resourceAttributes;
            $data['hash'] = $this->getResourceGuid($number);
            foreach (array_keys($resourceAttributes) as $resourceAttribute) {
                switch ($resourceAttribute) {
                    case 'url':
                        if (isset( $resource['file'] )) {
                            $url = $resource['file']->content()->attribute('filepath');
                            $data["url"] = $this->fixUrl($url);
                            $data["resource_type"] = 'file';
                            $data["size"] = $resource['file']->content()->attribute('filesize');
                            $data["mimetype"] = $resource['file']->content()->attribute('mime_type');
                            $data["format"] = \eZFile::suffix($resource['file']->content()->attribute('filepath'));
                        } elseif (isset( $resource['api'] )) {
                            $url = $resource['api']->toString();
                            $data["url"] = $this->fixApiUrl($url);
                            $data["resource_type"] = 'api';
                        } elseif (isset( $resource['url'] )) {
                            $url = explode('|', $resource['url']->toString());
                            $url = $url[0];
                            $data["url"] = $this->fixUrl($url);
                            $data["resource_type"] = 'file';
                        }
                        break;

                    default:
                        if (isset( $resource[$resourceAttribute] ) && $resource[$resourceAttribute]->attribute('has_content')) {
                            $string = $resource[$resourceAttribute]->toString();
                            if ($resourceAttribute == 'description') {
                                $string = str_replace(";", "", $string);
                            }
                            $data[$resourceAttribute] = $string;
                        }
                        break;
                }
            }
            $resourceList[] = CkanResource::fromArray($data);
        }

        return $resourceList;
    }

    protected function getResourceGuid($suffix)
    {
        return md5(\eZSolr::installationID() . '-' . $this->object->attribute('id') . '-' . $suffix);
    }

    protected function getTags()
    {
        if (isset( $this->dataMap['tags'] ) && $this->dataMap['tags']->attribute('has_content')) {
            $tagList = array();
            $tags = explode(', ', $this->dataMap['tags']->toString());
            foreach ($tags as $tag) {
                $tagList[] = array(
                    'vocabulary_id' => null,
                    'name' => $tag
                );
            }

            return $tagList;
        }

        return null;
    }

    protected function getExtras()
    {
        $extras = array();
        foreach (Dataset::getCustomFieldKeys() as $key) {
            $value = $this->getCustomField($key);
            if ($value) {
                $extras[] = array(
                    'key' => $this->fixText($key),
                    'value' => $this->fixText($value)
                );
            }
        }

        return $extras;
    }

    protected function getRelationshipsAsObject()
    {
        return null;
    }

    protected function getRelationshipsAsSubject()
    {
        return null;
    }

    protected function getGroups()
    {
        return null;
    }

    protected function getOwnerOrg()
    {
        return $this->organizationBuilder->getStoresOrganizationId();
    }

    protected function getGeoNamesUrl()
    {
        return '';
    }

    protected function getTemporalEnd()
    {
        if (isset( $this->dataMap['from_time'] ) && $this->dataMap['from_time']->hasContent()) {
            return date(DATE_ISO8601, $this->dataMap['from_time']->toString());
        }

        return null;
    }

    protected function getTemporalStart()
    {
        if (isset( $this->dataMap['to_time'] ) && $this->dataMap['to_time']->hasContent()) {
            return date(DATE_ISO8601, $this->dataMap['to_time']->toString());
        }

        return null;
    }

    protected function getSiterUrl()
    {
        if (isset( $this->dataMap['url_website'] ) && $this->dataMap['url_website']->hasContent()) {
            $url = explode('|', $this->dataMap['url_website']->toString());
            $url = $url[0];

            return $this->fixUrl($url);
        }

        return null;
    }

}
