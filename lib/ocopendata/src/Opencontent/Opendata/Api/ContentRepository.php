<?php

namespace Opencontent\Opendata\Api;

use eZContentFunctions;
use eZContentObject;
use eZContentObjectTreeNode;
use eZContentObjectTreeNodeOperations;
use eZContentOperationCollection;
use eZDB;
use eZFlowBlock;
use eZFlowOperations;
use eZFlowPool;
use eZFlowPoolItem;
use eZModule;
use eZOperationHandler;
use eZPage;
use eZPersistentObject;
use Opencontent\Opendata\Api\Exception\DuplicateRemoteIdException;
use Opencontent\Opendata\Api\Exception\InvalidPayloadException;
use Opencontent\Opendata\Api\Exception\NotFoundException;
use Opencontent\Opendata\Api\Exception\PublicationException;
use Opencontent\Opendata\Api\Exception\ForbiddenException;
use Opencontent\Opendata\Api\Gateway\FileSystem;
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
        eZModule::setGlobalPathList(eZModule::activeModuleRepositories());
    }

    /**
     * @throws ForbiddenException
     * @throws PublicationException
     * @throws InvalidPayloadException
     * @throws NotFoundException
     */
    public function createUpdate($payload, $ignorePolicies = false)
    {
        try {
            $result = $this->create($payload, $ignorePolicies);
        } catch (DuplicateRemoteIdException $e) {
            $result = $this->update($payload, $ignorePolicies);
        }

        return $result;
    }

    /**
     * @throws ForbiddenException
     * @throws PublicationException
     * @throws InvalidPayloadException
     * @throws NotFoundException
     * @throws DuplicateRemoteIdException
     */
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
            'content' => (array)$this->read($contentId, $ignorePolicies),
        );
    }

    /**
     * @param $content
     * @param bool $ignorePolicies
     *
     * @return array
     * @throws ForbiddenException
     * @throws NotFoundException
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

    /**
     * @throws ForbiddenException
     * @throws PublicationException
     * @throws DuplicateRemoteIdException
     * @throws NotFoundException
     * @throws InvalidPayloadException
     */
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
            'content' => (array)$this->read($contentId, $ignorePolicies),
        );
    }

    /**
     * @throws ForbiddenException
     * @throws NotFoundException
     */
    public function delete($content, $moveToTrash = false)
    {
        if (!$content instanceof Content) {
            $content = $this->gateway->loadContent($content);
        }
        $objectId = (int)$content->metadata->id;
        $object = eZContentObject::fetch($objectId);
        if (!$object instanceof eZContentObject){
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
            if (eZOperationHandler::operationIsAvailable('content_delete')) {
                eZOperationHandler::execute('content',
                    'delete',
                    array(
                        'node_id_list' => $deleteIDArray,
                        'move_to_trash' => $moveToTrash,
                    ),
                    null, true);
            } else {
                eZContentOperationCollection::deleteObject($deleteIDArray, $moveToTrash);
            }
        }

        return array(
            'message' => 'success',
            'method' => 'delete',
            'content' => $objectId,
        );
    }

    /**
     * @throws ForbiddenException
     * @throws NotFoundException
     */
    public function move($content, $newParentNodeIdentifier, $asUniqueLocation = false)
    {
        if (!$content instanceof Content) {
            $content = $this->gateway->loadContent($content);
        }

        $objectId = (int)$content->metadata->id;
        $object = eZContentObject::fetch($objectId);
        if (!$object instanceof eZContentObject){
            throw new NotFoundException($content);
        }
        if (!$object->canMoveFrom()) {
            throw new ForbiddenException($content, 'move');
        }

        $newParentNode = is_numeric($newParentNodeIdentifier) ?
            eZContentObjectTreeNode::fetch($newParentNodeIdentifier) :
            eZContentObjectTreeNode::fetchByRemoteID($newParentNodeIdentifier);

        if (!$newParentNode instanceof eZContentObjectTreeNode) {
            throw new NotFoundException($newParentNodeIdentifier, 'Node');
        }
        if (!$newParentNode->canCreate()){
            throw new ForbiddenException('in node #' . $newParentNode->attribute('node_id'), 'create');
        }

        $currentParentNodes = [];
        foreach ($object->assignedNodes() as $assignedNode) {
            $currentParentNodes[$assignedNode->attribute('parent_node_id')] = $assignedNode->attribute('node_id');
            if ($assignedNode->attribute('parent_node_id') != $newParentNode->attribute('node_id')) {
                if ($asUniqueLocation) {
                    if (!$assignedNode->canRemove() || !$assignedNode->canRemoveLocation()) {
                        throw new ForbiddenException($assignedNode->attribute('node_id'), 'remove location');
                    }
                }
            }
        }

        if (!isset($currentParentNodes[$newParentNode->attribute('node_id')])) {
            eZContentObjectTreeNodeOperations::move($object->attribute('main_node_id'), $newParentNode->attribute('node_id'));
        } elseif ($object->attribute('main_parent_node_id') != $newParentNode->attribute('node_id')) {
            eZContentOperationCollection::updateMainAssignment(
                $currentParentNodes[$newParentNode->attribute('node_id')],
                $object->attribute('id'),
                $newParentNode->attribute('node_id')
            );
        }

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
                if (eZOperationHandler::operationIsAvailable('content_removelocation')) {
                    eZOperationHandler::execute('content',
                        'removelocation', array('node_list' => $removeList),
                        null,
                        true);
                } else {
                    eZContentOperationCollection::removeNodes($removeList);
                }
            }
        }

        return array(
            'message' => 'success',
            'method' => 'move',
            'content' => $objectId,
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

    /**
     * @throws ForbiddenException
     * @throws NotFoundException
     */
    public function readBlock($objectId, $language, $attribute, $zoneIdentifier, $blockId)
    {
        $selectedBlock = null;
        $content = $this->gateway->loadContent($objectId);
        if (!$content->canRead()) {
            throw new ForbiddenException($content, 'read');
        }

        $blocks = $content->data[$language][$attribute]['content'][$zoneIdentifier]['blocks'];
        if (!empty($blocks)){
            foreach ($blocks as $block){
                if ($block['block_id'] == $blockId){
                    $selectedBlock = $block;
                    break;
                }
            }
        }
        if (empty($selectedBlock)){
            throw new NotFoundException($blockId, 'Block');
        }

        return $selectedBlock;
    }

    /**
     * @throws ForbiddenException
     * @throws NotFoundException
     */
    public function updateBlock($objectId, $language, $attribute, $zoneIdentifier, $blockId, $payload)
    {
        $content = $this->gateway->loadContent($objectId);
        if (!$content->canRead()) {
            throw new ForbiddenException($content, 'read');
        }

        $object = $content->getContentObject($language);
        if (!$object->canEdit()) {
            throw new ForbiddenException($content, 'edit');
        }
        $dataMap = $object->dataMap();
        if (!isset($dataMap[$attribute])) {
            throw new NotFoundException($attribute, 'Attribute');
        }
        $page = $dataMap[$attribute]->content();
        if (!$page instanceof eZPage){
            throw new NotFoundException($attribute, 'Page attribute');
        }
        $db = eZDB::instance();

        $hasBlock = false;
        $zones = $page->attribute('zones');
        foreach ($zones as $zone) {
            if ($zone->attribute('zone_identifier') == $zoneIdentifier) {
                $blocks = $zone->attribute('blocks');
                foreach ($blocks as $index => $block) {
                    if ($block->attribute('id') == $blockId) {
                        $hasBlock = true;
                        if (isset($payload['name'])) {
                            $block->setAttribute('name', $payload['name']);
                        }
                        if (isset($payload['type'])) {
                            $block->setAttribute('type', $payload['type']);
                        }
                        if (isset($payload['view'])) {
                            $block->setAttribute('view', $payload['view']);
                        }
                        if (isset($payload['custom_attributes'])) {
                            $block->setAttribute('custom_attributes', $payload['custom_attributes']);
                        }

                        $flowBlock = eZFlowBlock::fetch($block->attribute('id'));
                        if (!$flowBlock) {
                            $flowBlock = new eZFlowBlock([
                                'id' => $block->attribute('id'),
                                'zone_id' => $block->attribute('id'),
                                'name' => $block->attribute('name'),
                                'node_id' => 0,
                                'block_type' => $block->attribute('type'),
                            ]);
                        } else {
                            $flowBlock->setAttribute('block_type', $block->attribute('type'));
                        }
                        $flowBlock->store();
                        eZPersistentObject::removeObject(
                            eZFlowPoolItem::definition(),
                            ['block_id' => $block->attribute('id')]
                        );
                        $flowPoolItems = [];
                        if (isset($payload['valid_items'])) {
                            $validItemsCount = count($payload['valid_items']);
                            foreach ($payload['valid_items'] as $i => $remoteId) {
                                $item = eZContentObject::fetchByRemoteID($remoteId);
                                if ($item instanceof eZContentObject) {
                                    $flowPoolItems[] = array(
                                        'blockID' => $block->attribute('id'),
                                        'nodeID' => $item->attribute('main_node_id'),
                                        'objectID' => $item->attribute('id'),
                                        'priority' => $validItemsCount - $i,
                                        'timestamp' => time() - 86400
                                    );
                                }
                            }
                        }
                        $db->query("DELETE from ezm_block WHERE zone_id = '" .
                            $db->escapeString($zone->attribute('id')) .
                            "' AND id NOT IN ('" . $db->escapeString($block->attribute('id'))
                            . "')");

                        if (!empty($flowPoolItems)) {
                            eZFlowPool::insertItems($flowPoolItems);
                        }
                        $blocks[$index] = $block;
                    }
                }
                $zone->setAttribute('blocks', $blocks);
            }
        }
        $page->setAttribute('zones', $zones);
        if (!$hasBlock){
            throw new NotFoundException($blockId, 'Page block');
        }
        $stringData = $page->toXML();
        if (!eZContentFunctions::updateAndPublishObject($object, [
            'attributes' => [
                $attribute => $stringData
            ]
        ])){
            throw new PublicationException();
        }
//        eZFlowOperations::update([$object->mainNodeID()]);

        return $this->readBlock($objectId, $language, $attribute, $zoneIdentifier, $blockId);
    }
}
