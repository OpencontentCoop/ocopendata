<?php

namespace Opencontent\Opendata\Api;

use Opencontent\Opendata\Api\Exception\DuplicateRemoteIdException;
use Opencontent\Opendata\Api\Exception\NotFoundException;
use Opencontent\Opendata\Api\Gateway\FileSystem;
use Opencontent\Opendata\Api\Exception\ForbiddenException;
use Opencontent\Opendata\Api\Values\Content;

class ContentRepository
{
    /**
     * @var EnvironmentSettings
     */
    protected $currentEnvironmentSettings;

    /**
     * @var Gateway
     */
    protected $gateway;

    public function __construct()
    {
        //        $this->gateway = new Database();      // fallback per tutti
        //        $this->gateway = new SolrStorage();   // usa solr storage per restituire oggetti (sembra lento...)
        $this->gateway = new FileSystem();      // scrive cache sul filesystem (cluster safe)
    }

    /**
     * @param $content
     * @param bool $ignorePolicies
     *
     * @return array
     * @throws ForbiddenException
     */
    public function read($content, $ignorePolicies = false)
    {
        if (!$content instanceof Content) {
            $content = $this->gateway->loadContent($content);
        }

        if (!$ignorePolicies && !$content->canRead()) {
            throw new ForbiddenException($content, 'read');
        }

        return $this->currentEnvironmentSettings->filterContent($content);
    }

    public function create($payload, $ignorePolicies = false)
    {
        $createStruct = $this->currentEnvironmentSettings->instanceCreateStruct($payload);
        $createStruct->validate($ignorePolicies);
        $publicationProcess = new PublicationProcess($createStruct);
        $contentId = $publicationProcess->publish();

        $this->currentEnvironmentSettings->afterCreate($contentId, $createStruct);

        return array(
            'message' => 'success',
            'method' => 'create',
            'content' => (array)$this->read($contentId, $ignorePolicies)
        );
    }

    public function update($payload, $ignorePolicies = false)
    {
        $updateStruct = $this->currentEnvironmentSettings->instanceUpdateStruct($payload);
        $updateStruct->validate($ignorePolicies);
        $publicationProcess = new PublicationProcess($updateStruct);
        $contentId = $publicationProcess->publish();

        $this->currentEnvironmentSettings->afterUpdate($contentId, $updateStruct);

        return array(
            'message' => 'success',
            'method' => 'update',
            'content' => (array)$this->read($contentId, $ignorePolicies)
        );
    }

    public function createUpdate($payload, $ignorePolicies = false){
        try {
            $result = $this->create($payload, $ignorePolicies);
        } catch (DuplicateRemoteIdException $e) {
            $result = $this->update($payload, $ignorePolicies);
        }

        return $result;
    }

    public function delete($content, $moveToTrash = false)
    {
        if (!$content instanceof Content) {
            $content = $this->gateway->loadContent($content);
        }
        $objectId = (int)$content->metadata->id;
        $object = \eZContentObject::fetch($objectId);
        if (!$object instanceof \eZContentObject || !$object->canRemove()) {
            throw new ForbiddenException($content, 'remove');
        }

        $deleteIDArray = array();
        foreach ($object->assignedNodes() as $node) {
            $deleteIDArray[] = $node->attribute('node_id');
        }
        if (!empty($deleteIDArray)) {
            if (\eZOperationHandler::operationIsAvailable('content_delete')) {
                \eZOperationHandler::execute('content',
                    'delete',
                    array(
                        'node_id_list' => $deleteIDArray,
                        'move_to_trash' => $moveToTrash
                    ),
                    null, true);
            } else {
                \eZContentOperationCollection::deleteObject($deleteIDArray, $moveToTrash);
            }
        }

        return array(
            'message' => 'success',
            'method' => 'delete',
            'content' => $objectId
        );
    }

    public function move($content, $newParentNodeIdentifier)
    {
        if (!$content instanceof Content) {
            $content = $this->gateway->loadContent($content);
        }

        $objectId = (int)$content->metadata->id;
        $object = \eZContentObject::fetch($objectId);
        if (!$object instanceof \eZContentObject || !$object->canEdit()) {
            throw new ForbiddenException($content, 'move');
        }

        $newParentNode = is_numeric($newParentNodeIdentifier) ?
            \eZContentObjectTreeNode::fetch($newParentNodeIdentifier) :
            \eZContentObjectTreeNode::fetchByRemoteID($newParentNodeIdentifier);

        if (!$newParentNode instanceof \eZContentObjectTreeNode){
            throw new NotFoundException($newParentNodeIdentifier, 'Node');
        }

        \eZContentObjectTreeNodeOperations::move($object->attribute('main_node_id'), $newParentNode->attribute('node_id'));

        return array(
            'message' => 'success',
            'method' => 'move',
            'content' => $objectId
        );
    }

    /**
     * @return EnvironmentSettings
     */
    public function getCurrentEnvironmentSettings()
    {
        return $this->currentEnvironmentSettings;
    }

    /**
     * @param EnvironmentSettings $currentEnvironmentSettings
     *
     * @return $this
     */
    public function setEnvironment(EnvironmentSettings $currentEnvironmentSettings)
    {
        $this->currentEnvironmentSettings = $currentEnvironmentSettings;
        return $this;
    }

    /**
     * Alias of setEnvironment method
     *
     * @return ContentRepository
     */
    public function setCurrentEnvironmentSettings(EnvironmentSettings $environmentSettings)
    {
        return $this->setEnvironment($environmentSettings);
    }

    /**
     * @return Gateway
     */
    public function getGateway()
    {
        return $this->gateway;
    }

    /**
     * @param Gateway $gateway
     */
    public function setGateway(Gateway $gateway)
    {
        $this->gateway = $gateway;
    }


}
