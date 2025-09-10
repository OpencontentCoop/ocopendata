<?php

namespace Opencontent\Opendata\Api;

use Opencontent\Opendata\Api\Exception\PublicationException;
use Opencontent\Opendata\Api\Structs\ContentCreateStruct;
use Opencontent\Opendata\Api\Values\ContentSection;

class PublicationProcess
{
    private $currentStruct;

    private $isUpdate;

    /**
     * PublicationProcess constructor.
     *
     * @param ContentCreateStruct $currentStruct
     */
    public function __construct($currentStruct)
    {
        $this->currentStruct = $currentStruct;

        $moduleINI = \eZINI::instance('module.ini');
        $globalModuleRepositories = $moduleINI->variable('ModuleSettings', 'ModuleRepositories');
        \eZModule::setGlobalPathList($globalModuleRepositories);

        // avoid php notice in kernel/common/ezmoduleparamsoperator.php on line 71
        if ( !isset( $GLOBALS['eZRequestedModuleParams'] ) )
            $GLOBALS['eZRequestedModuleParams'] = array( 'module_name' => null,
                                                         'function_name' => null,
                                                         'parameters' => null );

    }

    public function publish()
    {
        /** @var \Opencontent\Opendata\Api\AttributeConverter\Base[] $converters */
        $converters = array();
        foreach ($this->currentStruct->metadata->getClass()->fields as $field) {
            $converters[$field['identifier']] = AttributeConverterLoader::load(
                $this->currentStruct->metadata->classIdentifier,
                $field['identifier'],
                $field['dataType']
            );
        }

        $languages = array_keys($this->currentStruct->data->getArrayCopy());
        $invalidLanguages = array_diff($languages, \eZContentLanguage::fetchLocaleList());
        if (!empty($invalidLanguages)){
            throw new PublicationException('Invalid languages ' . implode(',', $invalidLanguages));
        }

        $section = $this->currentStruct->metadata->getSection();

        $prioritizedLanguageCodes = \eZContentLanguage::prioritizedLanguageCodes();
        $mainLanguage = $prioritizedLanguageCodes[0];
        $addMissingTranslations = array();
        if ($this->currentStruct->metadata->getContentObject() instanceof \eZContentObject) {
            $this->isUpdate = true;
            $content = \SQLIContent::fromContentObject($this->currentStruct->metadata->getContentObject());
            $currentObjectLanguages = array_keys(
                $this->currentStruct->metadata->getContentObject()->allLanguages()
            );
            if (!in_array($mainLanguage, $currentObjectLanguages)){
                $content->addTranslation($mainLanguage);
            }
            $addMissingTranslations = array_diff($currentObjectLanguages, $this->currentStruct->metadata->languages);
        } else {
            $contentOptions = new \SQLIContentOptions(array(
                'class_identifier' => $this->currentStruct->metadata->classIdentifier,
                'remote_id' => $this->currentStruct->metadata->remoteId,
                'creator_id' => $this->currentStruct->metadata->creatorId
            ));
            if ($section instanceof ContentSection) {
                $contentOptions['section_id'] = $section['id'];
            }
            $content = \SQLIContent::create($contentOptions);
        }

        $nullUpdates = [];
        
        try {
            foreach ($this->currentStruct->data as $language => $values) {
                if ($language == $mainLanguage) {
                    foreach ($values as $identifier => $data) {
                        if (is_null($data)){
                            if ($this->currentStruct->options->isCopyPrevVersionField($identifier)) {
                                $content->fields->{$identifier} = (string)$content->fields->{$identifier};
                            }elseif ($this->currentStruct->options->isUpdateNullFields() == true){
                                $content->fields->{$identifier} = null;
                                $nullUpdates[$language][] = $identifier;
                            }
                        }else{
                            $content->fields->{$identifier} = $converters[$identifier]->set($data, $this);
                        }

                    }
                } else {
                    $content->addTranslation($language);
                    foreach ($values as $identifier => $data) {
                        if (is_null($data)){
                            if ($this->currentStruct->options->isCopyPrevVersionField($identifier)) {
                                $content->fields[$language]->{$identifier} = (string)$content->fields[$language]->{$identifier};
                            }elseif ($this->currentStruct->options->isUpdateNullFields() == true) {
                                $content->fields[$language]->{$identifier} = null;
                                $nullUpdates[$language][] = $identifier;
                            }
                        }else {
                            $content->fields[$language]->{$identifier} = $converters[$identifier]->set($data, $this);
                        }
                    }
                }
            }

            if (!empty($addMissingTranslations)) {
                $fieldList = $this->currentStruct->metadata->getClass()->fields;
                foreach ($addMissingTranslations as $language) {
                    $content->addTranslation($language);
                    foreach ($fieldList as $field){
                        $identifier = $field['identifier'];
                        if (isset($content->fields[$language]->{$identifier})){
                            if (in_array(
                                $content->fields[$language]->{$identifier}->data_type_string,
                                [
                                    \eZBinaryFileType::DATA_TYPE_STRING,
                                    \OCMultiBinaryType::DATA_TYPE_STRING,
                                    \eZUserType::DATA_TYPE_STRING,
                                ]
                            ) || !$content->fields[$language]->{$identifier}->getRawAttribute()->hasContent()) {
                                $content->fields[$language]->{$identifier} = '';
                            } elseif ($content->fields[$language]->{$identifier}->data_type_string === \eZBooleanType::DATA_TYPE_STRING) {
                                $content->fields[$language]->{$identifier} = (string)$content->fields[$language]->{$identifier}->data_int;
                            } else {
                                $content->fields[$language]->{$identifier} = (string)$content->fields[$language]->{$identifier};
                            }
                        }
                    }
                }
            }

            //add locations
            foreach ((array)$this->currentStruct->metadata->parentNodes as $node) {
                $content->addLocation(\SQLILocation::fromNodeID($node)); // Will be main location
            }

            //change state
            foreach ($this->currentStruct->metadata->getStates() as $state) {
                $content->getRawContentObject()->assignState($state->getStateObject());
            }

            //publish date
            if ($this->currentStruct->metadata->published) {
                $content->getRawContentObject()->setAttribute('published', $this->currentStruct->metadata->published);
            }
            if ($this->currentStruct->metadata->modified) {
                $content->getRawContentObject()->setAttribute('modified', $this->currentStruct->metadata->modified);
            }

            // force change section (in update mode)
            if ($section instanceof ContentSection && $content->getRawContentObject()->attribute('section_id') !== $section['id']) {
                $content->getRawContentObject()->setAttribute('section_id', $section['id']);
            }

            $content->getRawContentObject()->store();

            // publish
            $publisher = \SQLIContentPublisher::getInstance();
            $publisher->setOptions($this->currentStruct->options->getSQLIContentPublishOptions());
            $publisher->publish($content);

            // handle error
            $id = (int)$content->id;

            // cleanup
            foreach ($converters as $converter) {
                $converter->clean();
            }

            // flush content
            unset( $content );

            // remove locations missing in payload
            if ($this->isUpdate && (!empty($this->currentStruct->metadata->parentNodes) || !empty($nullUpdates))){
                \eZContentObject::clearCache([$id]);
                $object = \eZContentObject::fetch($id);
                if ($object instanceof \eZContentObject){
                    $refresh = false;
                    if (!empty($nullUpdates)){
                        foreach ($nullUpdates as $language => $identifiers) {
                            $dataMap = $object->fetchDataMap(false, $language);
                            foreach ($identifiers as $identifier) {
                                if (isset($converters[$identifier]) && isset($dataMap[$identifier])) {
                                    $hasChanges = $converters[$identifier]->onPublishNullData(
                                        $dataMap[$identifier],
                                        $this
                                    );
                                    if ($hasChanges) {
                                        $refresh = true;
                                    }
                                }
                            }
                        }
                    }
                    if (!empty($this->currentStruct->metadata->parentNodes)) {
                        $assignedNodes = $object->assignedNodes();
                        if (count($assignedNodes) !== count($this->currentStruct->metadata->parentNodes)) {
                            $removeList = [];
                            foreach ($assignedNodes as $assignedNode) {
                                if (
                                    !in_array(
                                        $assignedNode->attribute('parent_node_id'),
                                        $this->currentStruct->metadata->parentNodes
                                    )
                                    && $assignedNode->canRemove()
                                    && $assignedNode->canRemoveLocation()
                                ) {
                                    $removeList[] = $assignedNode->attribute('node_id');
                                }
                            }
                            if (!empty($removeList)) {
                                $refresh = false;
                                if (\eZOperationHandler::operationIsAvailable('content_removelocation')) {
                                    \eZOperationHandler::execute(
                                        'content',
                                        'removelocation',
                                        ['node_list' => $removeList],
                                        null,
                                        true
                                    );
                                } else {
                                    \eZContentOperationCollection::removeNodes($removeList);
                                }
                            }
                        }
                    }
                    if ($refresh){
                        \eZContentOperationCollection::registerSearchObject($object->attribute('id'));
                    }
                }
            }

            return $id;
        } catch (\Exception $e) {
            if ($content->getRawContentObject()->attribute('current_version') == 1) {
                $content->getRawContentObject()->remove();
            } else {
                $content->getDraft()->removeThis();
            }
            throw new PublicationException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
