<?php

namespace Opencontent\Opendata\Api;

use Opencontent\Opendata\Api\Structs\ContentCreateStruct;
use Opencontent\Opendata\Api\Values\ContentSection;

class PublicationProcess
{
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

        $section = $this->currentStruct->metadata->getSection();

        if ($this->currentStruct->metadata->getContentObject() instanceof \eZContentObject) {
            $content = \SQLIContent::fromContentObject($this->currentStruct->metadata->getContentObject());
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

        try {
            $prioritizedLanguageCodes = \eZContentLanguage::prioritizedLanguageCodes();
            $mainLanguage = $prioritizedLanguageCodes[0];
            foreach ($this->currentStruct->data as $language => $values) {
                if ($language == $mainLanguage) {
                    foreach ($values as $identifier => $data) {
                        $content->fields->{$identifier} = $converters[$identifier]->set($data, $this);
                    }
                } else {
                    $content->addTranslation($language);
                    foreach ($values as $identifier => $data) {
                        $content->fields[$language]->{$identifier} = $converters[$identifier]->set($data, $this);
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
            $content->getRawContentObject()->setAttribute('published', $this->currentStruct->metadata->published);
            $content->getRawContentObject()->setAttribute('modified', $this->currentStruct->metadata->modified);

            // force change section (in update mode)
            if ($section instanceof ContentSection && $content->getRawContentObject()->attribute('section_id') !== $section['id']) {
                $content->getRawContentObject()->setAttribute('section_id', $section['id']);
            }

            $content->getRawContentObject()->store();

            // publish
            $publisher = \SQLIContentPublisher::getInstance();
            $publisher->publish($content);

            // handle error
            $id = (int)$content->id;

            // cleanup
            foreach ($converters as $converter) {
                $converter->clean();
            }

            // flush content
            unset( $content );

            return $id;
        } catch (\Exception $e) {
            if ($content->getRawContentObject()->attribute('current_version') == 1) {
                $content->getRawContentObject()->remove();
            } else {
                $content->getDraft()->removeThis();
            }
            throw new \Exception($e->getMessage());
        }
    }

}
