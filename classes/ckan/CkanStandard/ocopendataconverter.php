<?php

class OCOpenDataConverter implements OcOpenDataConverterInterface
{

    /**
     * I dati del dataset in formato array compatibile con CKAN
     *
     * @var array
     */
    private $data = array();

    public static $remotePrefix = 'ckan_';

    /**
     * @var eZContentObject
     */
    protected $object;

    /**
     * @var eZContentObjectAttribute[]
     */
    protected $dataMap;

    /**
     * @var eZContentObjectAttribute[][]
     */
    protected $resources;

    /**
     * @var OcOpendataOrganizationBuilderInterface
     */
    public $organizationBuilder;

    /**
     * Parametri del dataset ricavati dalle proprietà dell'oggetto
     */
    public $datasetMetaAttributes = array(
        "id",
        "metadata_created",
        "metadata_modified",
        "name",
        "url",
        "version"
    );

    /**
     * Parametri del dataset ricavati dagli attributi dell'oggetto
     */
    public $datasetAttributes = array(
        "title",
        "author",
        "author_email",
        "maintainer",
        "maintainer_email",
        "license_id",
        "categories",
        "notes",
        "tags",
        "state",
        "from_time",
        "to_time",
        "url_website",
        "extras",
        "categories",
        "fields_description"
    );

    /**
     * Parametri del dataset ricavati dagli attributi delle risorse
     */
    public $resourceAttributes = array(
        "url",
        "name",
        "description",
        "visualization",
        "code",
        "documentation",
        "format",
        "mimetype",
        "mimetype-inner",
        "size",
        "last_modified",
        "hash",
        "resource_type",
        "package_id",
    );

    /**
     * Paramteri custom
     */
    public $extraData = array(
        "Codifica Caratteri" => array('string' => "UTF-8"),
        "Copertura Temporale (Data di inizio)" => array('attribute' => "from_time"),
        "Copertura Temporale (Data di fine)" => array('attribute' => "to_time"),
        "Aggiornamento" => array('attribute' => "frequency"),
        "Copertura Geografica" => array('attribute' => "geo"),
        "Titolare" => array('attribute' => "author"),
        "Data di pubblicazione" => array('property' => "metadata_modified"),
        "Data di creazione" => array('property' => "metadata_created"),
        "Data di aggiornamento" => array('property' => "metadata_modified"),
        "Descrizione campi" => array('attribute' => "fields_description"),
        "URL sito" => array('property' => "url")
    );

    public function setObject(eZContentObject $object)
    {
        $this->object = $object;
        $this->dataMap = $object->attribute('data_map');
    }

    public function setResources(array $resources)
    {
        $this->resources = $resources;
    }

    protected function convertProperty($field, &$data, $identifier = null)
    {
        switch ($field) {
            case 'id':
                $id = $this->getDatasetId($this->object);
                if ($id) {
                    $data['id'] = $id;
                }
                $data['package_unique_id'] = OCOpenDataTools::generateUniqueId($this->object->attribute('id'));
                break;

            case 'name':
                $trans = eZCharTransform::instance();
                $original = $this->object->attribute('name');
                $name = $trans->transformByGroup($original, 'urlalias');
                if ($identifier) {
                    $data[$identifier] = strtolower($name);
                } else {
                    $data[$field] = strtolower($name);
                }
                break;

            case 'metadata_created':
                if ($identifier) {
                    $data[$identifier] = date(DATE_ATOM, $this->object->attribute('published'));
                } else {
                    $data[$field] = date(DATE_ATOM, $this->object->attribute('published'));
                }
                break;

            case 'metadata_modified':
                if ($identifier) {
                    $data[$identifier] = date(DATE_ATOM, $this->object->attribute('modified'));
                } else {
                    $data[$field] = date(DATE_ATOM, $this->object->attribute('modified'));
                }
                break;

            case 'version':
                if ($identifier) {
                    $data[$identifier] = $this->object->attribute('current_version');
                } else {
                    $data[$field] = $this->object->attribute('current_version');
                }
                break;

            case 'url':
                //$url = 'content/view/full/' . $this->object->attribute( 'main_node_id' );
                $url = $this->object->attribute('main_node')->attribute('url_alias');
                eZURI::transformURI($url, false, 'full');
                if ($identifier) {
                    $data[$identifier] = $url;
                } else {
                    $data[$field] = $url;
                }
                break;
        }
    }

    protected function convertAttribute($field, &$data, $identifier = null)
    {
        switch ($field) {
            case 'author':
                if (isset( $this->dataMap['author'] ) && $this->dataMap['author']->attribute('has_content')) {
                    $author = explode('|', $this->dataMap['author']->toString());
                    if ($identifier) {
                        $data[$identifier] = $author[0];
                    } else {
                        $data[$field] = $author[0];
                    }
                }
                break;

            case 'author_email':
                if (isset( $this->dataMap['author'] ) && $this->dataMap['author']->attribute('has_content')) {
                    $author = explode('|', $this->dataMap['author']->toString());
                    if ($identifier) {
                        $data[$identifier] = $author[1];
                    } else {
                        $data[$field] = $author[1];
                    }
                }
                break;

            case 'maintainer':
                if (isset( $this->dataMap['maintainer'] ) && $this->dataMap['maintainer']->attribute('has_content')) {
                    $maintainer = explode('|', $this->dataMap['maintainer']->toString());
                    if ($identifier) {
                        $data[$identifier] = $maintainer[0];
                    } else {
                        $data[$field] = $maintainer[0];
                    }
                }
                break;

            case 'maintainer_email':
                if (isset( $this->dataMap['maintainer'] ) && $this->dataMap['maintainer']->attribute('has_content')) {
                    $maintainer = explode('|', $this->dataMap['maintainer']->toString());
                    if ($identifier) {
                        $data[$identifier] = $maintainer[1];
                    } else {
                        $data[$field] = $maintainer[1];
                    }
                }
                break;

            case 'url_website':
                if (isset( $this->dataMap['url_website'] ) && $this->dataMap['url_website']->attribute('has_content')) {
                    $url = explode('|', $this->dataMap['url_website']->toString());
                    if ($identifier) {
                        $data[$identifier] = $url[0];
                    } else {
                        $data[$field] = $url[0];
                    }
                }
                break;

            case 'categories':
                if (isset( $this->dataMap['categories'] ) && $this->dataMap['categories']->attribute('has_content')) {
                    $categories = explode('|', $this->dataMap['categories']->toString());
                    if ($identifier) {
                        $data[$identifier] = implode(', ', $categories);
                    } else {
                        $data[$field] = implode(', ', $categories);
                    }
                }
                break;

            case 'fields_description':
                if (isset( $this->dataMap['fields_description'] ) && $this->dataMap['fields_description']->attribute('has_content')) {

                    $fields = $keys = array();
                    $columns = $this->dataMap['fields_description']->content()->attribute('columns');
                    foreach ($columns['sequential'] as $column) {
                        $keys[] = $column['identifier'];
                    }
                    $rows = $this->dataMap['fields_description']->content()->attribute('rows');
                    foreach ($rows['sequential'] as $row) {
                        $fields[] = array_combine($keys, $row['columns']);
                    }

                    //$tpl = eZTemplate::factory();
                    //$tpl->setVariable( 'attribute', $this->dataMap['fields_description'] );
                    //$value = $tpl->fetch( "design:content/datatype/view/" . $this->dataMap['fields_description']->attribute( 'data_type_string' ) . ".tpl" );

                    //try
                    //{
                    //    $document = new ezcDocumentXhtml();
                    //    $document->loadString( $html );
                    //    $docbook = $document->getAsDocbook();
                    //    $converter = new ezcDocumentDocbookToWikiConverter();
                    //    $wikiDocument = $converter->convert( $docbook );
                    //    $value = $wikiDocument->save();
                    //}
                    //catch( Exception $e )
                    //{
                    //    eZDebug::writeError( $e->getMessage(), __METHOD__ );
                    //}

                    $tpl = eZTemplate::factory();
                    $tpl->setVariable('fields', $fields);
                    $value = $tpl->fetch("design:push/dataset_fields_description.tpl");

                    if ($identifier) {
                        $data[$identifier] = $value;
                    } else {
                        $data[$field] = $value;
                    }
                }
                break;

            case 'tags':
                if (isset( $this->dataMap[$field] ) && $this->dataMap[$field]->attribute('has_content')) {
                    if ($identifier) {
                        $data[$identifier] = explode(', ', $this->dataMap[$field]->toString());
                    } else {
                        $data[$field] = explode(', ', $this->dataMap[$field]->toString());
                    }
                }
                break;

            case 'from_time':
            case 'to_time':
                if (isset( $this->dataMap[$field] ) && $this->dataMap[$field]->attribute('has_content')) {
                    if ($identifier) {
                        $data[$identifier] = date(DATE_ATOM, $this->dataMap[$field]->toString());
                    } else {
                        $data[$field] = date(DATE_ATOM, $this->dataMap[$field]->toString());
                    }
                }
                break;

            default:
                if (isset( $this->dataMap[$field] ) && $this->dataMap[$field]->attribute('has_content')) {
                    if ($identifier) {
                        $data[$identifier] = $this->dataMap[$field]->toString();
                    } else {
                        $data[$field] = $this->dataMap[$field]->toString();
                    }
                }
                break;
        }
    }

    /**
     * Converte l'oggetto popolando $data
     */
    public function convert()
    {
        foreach ($this->datasetMetaAttributes as $datasetMetaAttribute) {
            $this->convertProperty($datasetMetaAttribute, $this->data);
        }

        foreach ($this->datasetAttributes as $datasetAttribute) {
            $this->convertAttribute($datasetAttribute, $this->data);
        }

        $this->data['extras'] = array();
        foreach ($this->extraData as $identifier => $values) {
            foreach ($values as $type => $field) {
                if ($type == 'property') {
                    $this->convertProperty($field, $this->data['extras'], $identifier);
                } elseif ($type == 'attribute') {
                    $this->convertAttribute($field, $this->data['extras'], $identifier);
                } elseif ($type == 'string') {
                    $this->data['extras'][$identifier] = $field;
                }
            }
        }

        $this->data['resources'] = array();
        foreach ($this->resources as $number => $resource) {
            $this->data['resources'][] = $this->convertResource($resource);
        }

        return $this;
    }

    /**
     * Converte la singola risorsa
     *
     * @param eZContentObjectAttribute[] $resource
     *
     * @return array
     */
    public function convertResource(array $resource)
    {
        $data = array();
        $format = null;
        $resourceType = null;
        $url = null;
        foreach ($this->resourceAttributes as $resourceAttribute) {
            switch ($resourceAttribute) {
                case 'url':
                    if (isset( $resource['file'] )) {
                        $url = $resource['file']->content()->attribute('filepath');
                        eZURI::transformURI($url, false, 'full');
                        $resourceType = 'file';
                        //$data["hash"] = null;
                        $data["size"] = $resource['file']->content()->attribute('filesize');
                        $data["mimetype"] = $resource['file']->content()->attribute('mime_type');
                        $format = eZFile::suffix($resource['file']->content()->attribute('filepath'));
                    } elseif (isset( $resource['api'] )) {
                        $url = $resource['api']->toString();
                        $resourceType = 'api';
                    } elseif (isset( $resource['url'] )) {
                        $url = explode('|', $resource['url']->toString());
                        $url = $url[0];
                        eZURI::transformURI($url, false, 'full');
                        $resourceType = 'file';
                    }

                    $data[$resourceAttribute] = $url;
                    break;

                default:
                    if (isset( $resource[$resourceAttribute] ) && $resource[$resourceAttribute]->attribute('has_content')) {
                        $data[$resourceAttribute] = $resource[$resourceAttribute]->toString();
                    }
                    break;
            }
        }

        if (!isset( $data['format'] )) {
            $data['format'] = $format;
        }

        if (!isset( $data['resource_type'] )) {
            $data['resource_type'] = $resourceType;
        }

        if (!isset( $data['package_id'] )) {
            $data['package_unique_id'] = OCOpenDataTools::generateUniqueId($this->object->attribute('id'));
        }

        return $data;
    }

    /**
     * Metodo pubblico per convertire e restitire il dataset convertito
     *
     * @return array
     */
    public function getData()
    {
        $this->convert();

        return $this->data;
    }

    public function getRemotePrefix()
    {
        return self::$remotePrefix;
    }

    public function getDatasetFromObject(eZContentObject $object)
    {
        $resources = $this->getResourcesFromObject($object);
        $this->setObject($object);
        $this->setResources($resources);

        return $this->getData();
    }

    public function getDatasetId(eZContentObject $object)
    {
        if (strpos($object->attribute('remote_id'), $this->getRemotePrefix()) !== false) {
            return str_replace($this->getRemotePrefix(), '', $object->attribute('remote_id'));
        }

        return null;
    }

    protected function getResourcesFromObject(eZContentObject $object)
    {
        /** @var eZContentObjectAttribute[] $dataMap */
        $dataMap = $object->attribute('data_map');

        $resources = array();
        foreach (array_keys($dataMap) as $attributeIdentifier) {
            if (strpos($attributeIdentifier, 'resource') !== false) {
                list( $resource, $number, $resourceIdentifier ) = explode(
                    '_',
                    $attributeIdentifier
                );
                $resources[intval($number)][$resourceIdentifier] = $dataMap[$attributeIdentifier];
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

        return $resources;
    }

    public function markObjectPushed(eZContentObject $object, $response)
    {
        if (isset( $response->id )) {
            $object->setAttribute(
                'remote_id',
                OCOpenDataConverter::$remotePrefix . $response->id
            );
            $object->store();
        }
    }

    public function markObjectDeleted(eZContentObject $object, $response)
    {
        throw new Exception('Not implemented');
    }

    public function getOrganization(eZContentObject $object = null)
    {
        throw new Exception('Not implemented');
    }

    public function setOrganizationBuilder(OcOpendataOrganizationBuilderInterface $builder)
    {
        $this->organizationBuilder = $builder;
    }
}
