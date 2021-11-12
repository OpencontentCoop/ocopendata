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
        \eZModule::setGlobalPathList(\eZModule::activeModuleRepositories());
    }

    public function createUpdate($payload, $ignorePolicies = false)
    {
        try {
            $result = $this->create($payload, $ignorePolicies);
        } catch (DuplicateRemoteIdException $e) {
            $result = $this->update($payload, $ignorePolicies);
        }

        return $result;
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

    public function delete($content, $moveToTrash = false)
    {
        if (!$content instanceof Content) {
            $content = $this->gateway->loadContent($content);
        }
        $objectId = (int)$content->metadata->id;
        $object = \eZContentObject::fetch($objectId);
        if (!$object instanceof \eZContentObject){
            throw new NotFoundException($content);
        }
        if (!$object->canRemove()) {
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

    public function move($content, $newParentNodeIdentifier, $asUniqueLocation = false)
    {
        if (!$content instanceof Content) {
            $content = $this->gateway->loadContent($content);
        }

        $objectId = (int)$content->metadata->id;
        $object = \eZContentObject::fetch($objectId);
        if (!$object instanceof \eZContentObject){
            throw new NotFoundException($content);
        }
        if (!$object->canMoveFrom()) {
            throw new ForbiddenException($content, 'move');
        }

        $newParentNode = is_numeric($newParentNodeIdentifier) ?
            \eZContentObjectTreeNode::fetch($newParentNodeIdentifier) :
            \eZContentObjectTreeNode::fetchByRemoteID($newParentNodeIdentifier);

        if (!$newParentNode instanceof \eZContentObjectTreeNode) {
            throw new NotFoundException($newParentNodeIdentifier, 'Node');
        }
        if (!$newParentNode->canCreate()){
            throw new ForbiddenException('in node #' . $newParentNode->attribute('node_id'), 'create');
        }

        if ($asUniqueLocation) {
            foreach ($object->assignedNodes() as $assignedNode) {
                if ($assignedNode->attribute('parent_node_id') != $newParentNode->attribute('node_id')) {
                    if (!$assignedNode->canRemove() || !$assignedNode->canRemoveLocation()) {
                        throw new ForbiddenException($assignedNode->attribute('node_id'), 'remove location');
                    }
                }
            }
        }

        \eZContentObjectTreeNodeOperations::move($object->attribute('main_node_id'), $newParentNode->attribute('node_id'));

        if ($asUniqueLocation) {
            $removeList = [];
            foreach ($object->assignedNodes() as $assignedNode) {
                if ($assignedNode->attribute('parent_node_id') != $newParentNode->attribute('node_id')
                    && $assignedNode->canRemove()
                    && $assignedNode->canRemoveLocation()
                ) {
                    $removeList[] = $assignedNode->attribute('node_id');
                }
            }
            if (!empty($removeList)) {
                if (\eZOperationHandler::operationIsAvailable('content_removelocation')) {
                    \eZOperationHandler::execute('content',
                        'removelocation', array('node_list' => $removeList),
                        null,
                        true);
                } else {
                    \eZContentOperationCollection::removeNodes($removeList);
                }
            }
        }

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
     * Alias of setEnvironment method
     *
     * @return ContentRepository
     */
    public function setCurrentEnvironmentSettings(EnvironmentSettings $environmentSettings)
    {
        return $this->setEnvironment($environmentSettings);
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
