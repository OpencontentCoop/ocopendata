<?php

namespace Opencontent\Opendata\Api;

use Opencontent\Opendata\Api\Exception\ForbiddenException;
use Opencontent\Opendata\Api\Values\BrowseItem;
use Opencontent\Opendata\Api\Exception\BaseException;
use eZContentObjectTreeNode;
use eZContentLanguage;

class ContentBrowser
{
    /**
     * @var EnvironmentSettings
     */
    protected $currentEnvironmentSettings;

    public function setEnvironment(EnvironmentSettings $environmentSettings)
    {
        $this->currentEnvironmentSettings = $environmentSettings;
    }

    /**
     * @param string $nodeIdentifier
     * @param int $childrenOffset
     * @param int $childrenLimit
     *
     * @return BrowseItem
     * @throws BaseException
     * @throws ForbiddenException
     */
    public function browse($nodeIdentifier, $childrenOffset = 0, $childrenLimit = 10)
    {
        if (is_numeric($nodeIdentifier)) {
            $node = eZContentObjectTreeNode::fetch($nodeIdentifier);
        } else {
            $node = eZContentObjectTreeNode::fetchByRemoteID($nodeIdentifier);
        }
        if (!$node instanceof eZContentObjectTreeNode) {
            throw new BaseException("Node {$nodeIdentifier} not found");
        }
        if (!$node->canRead()) {
            throw new ForbiddenException("with node {$nodeIdentifier}", 'read');
        }

        $item = $this->buildBrowseItem($node);
        $item->children = $this->getChildren($node, $childrenOffset, $childrenLimit);
        return $item;
    }

    /**
     * @param eZContentObjectTreeNode $node
     *
     * @return BrowseItem
     */
    private function buildBrowseItem(eZContentObjectTreeNode $node)
    {
        $contentObject = $node->object();
        $languages = eZContentLanguage::fetchLocaleList();
        /** @var eZContentLanguage[] $availableLanguages */
        $availableLanguages = array_keys($contentObject->allLanguages());

        $item = new BrowseItem();
        $item->id = (int)$contentObject->attribute('id');
        $item->remoteId = $contentObject->attribute('remote_id');
        $item->nodeId = (int)$node->attribute('node_id');
        $item->nodeRemoteId = $node->attribute('remote_id');
        $item->isMainNode = (bool)$node->attribute('is_main');
        $item->mainNodeId = (int)$node->attribute('main_node_id');
        $item->parentNodeId = (int)$node->attribute('parent_node_id');

        $names = array();
        foreach ($languages as $language) {
            if (in_array($language, $availableLanguages)) {
                $names[$language] = $contentObject->name(false, $language);
            }
        }
        $item->name = $names;
        $item->isHidden = (bool)$node->attribute('is_hidden');
        $item->isInvisible = (bool)$node->attribute('is_invisible');
        $item->depth = (int)$node->attribute('depth');
        $item->modified = date('c', $node->attribute('modified_subnode'));
        $item->path = $node->attribute('path_string');
        $item->classIdentifier = $node->attribute('class_identifier');
        $item->childrenCount = (int)$node->attribute('children_count');
        $item->priority = (int)$node->attribute('priority');
        $item->sortField = $node->attribute('sort_field');
        $item->sortOrder = $node->attribute('sort_order');

        return $item;
    }

    private function getChildren(eZContentObjectTreeNode $node, $offset, $limit)
    {
        $children = array();
        /** @var eZContentObjectTreeNode[] $childrenNodes */
        $childrenNodes = $node->subTree(array(
            'Depth' => 1,
            'DepthOperator' => 'eq',
            'SortBy' => $node->sortArray(),
            'Limit' => $limit,
            'Offset' => $offset
        ));
        foreach($childrenNodes as $node){
            $children[] = $this->buildBrowseItem($node);
        }
        return $children;
    }
}
