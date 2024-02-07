<?php

namespace Opencontent\Opendata\Api\AttributeConverter;

use eZContentObjectAttribute;
use eZContentClassAttribute;
use Opencontent\Opendata\Api\Exception\InvalidInputException;
use Opencontent\Opendata\Api\PublicationProcess;

class Page extends Base
{
    public function get(eZContentObjectAttribute $attribute)
    {
        $content = parent::get($attribute);
        $data = array();
        if ($attribute->hasContent()) {
            /** @var \eZPage $ezPage */
            $source = $attribute->attribute('data_text');
            $page = \eZPage::createFromXML($source);
            $data['zone_layout'] = $page->attribute('zone_layout');
            /** @var \eZPageZone $zone */
            foreach ($page->attribute('zones') as $zone) {
                $blocksData = array();
                /** @var \eZPageBlock[] $blocks */
                $blocks = (array)$zone->attribute('blocks');
                foreach ($blocks as $block) {
                    $validItems = array();
                    /** @var \eZContentObjectTreeNode[] $validNodes */
                    $validNodes = $block->attribute('valid_nodes');
                    foreach ($validNodes as $node) {
                        $validItems[] = $node->object()->attribute('remote_id');
                    }
                    $blocksData[] = array(
                        'block_id' => $block->attribute('id'),
                        'name' => $block->attribute('name'),
                        'type' => $block->attribute('type'),
                        'view' => $block->hasAttribute('view') ? $block->attribute('view') : null,
                        'custom_attributes' => $block->attribute('custom_attributes'),
                        'valid_items' => $validItems
                    );
                }
                $data[$zone->attribute('zone_identifier')] = array(
                    'zone_id' => $zone->attribute('id'),
                    'blocks' => $blocksData
                );
            }
        }
        $content['content'] = $data;

        return $content;
    }

    public function set($data, PublicationProcess $process)
    {
        if (is_array($data) && !empty($data)) {
            return $this->createXML($data);
        }
        return null;
    }

    public static function validate($identifier, $data, eZContentClassAttribute $attribute)
    {
        if ($data !== null) {
            if (!is_array($data)) {
                throw new InvalidInputException('Invalid type', $identifier, $data);
            }

            if (!empty($data)) {
                if (!isset($data['zone_layout'])) {
                    throw new InvalidInputException('Missing field zone_layout', $identifier, $data);
                }
                foreach ($data as $key => $value) {
                    if ($key !== 'zone_layout') {
                        if (!is_array($value)) {
                            throw new InvalidInputException('Invalid type for zone ' . $key, $identifier, $data);
                        }
                        if (!isset($value['zone_id'])) {
                            throw new InvalidInputException('Missing field zone_id in zone ' . $key, $identifier, $data);
                        }
                        if (!isset($value['blocks'])) {
                            throw new InvalidInputException('Missing field blocks in zone ' . $key, $identifier, $data);
                        }
                        foreach ($value['blocks'] as $index => $block) {
                            if (!isset($block['view'])) {
                                throw new InvalidInputException('Missing field view in $block ' . $index, $identifier, $data);
                            }
                            if (!isset($block['type'])) {
                                throw new InvalidInputException('Missing field type in $block ' . $index, $identifier, $data);
                            }
                        }

                    }
                }
            }
        }
    }

    public function type(eZContentClassAttribute $attribute)
    {
        return array(
            'identifier' => 'json',
            'format' => [
                "zone_layout" => "layout identifier",
                "zone identifier" => [
                    "zone_id" => "zone id",
                    "blocks" => [
                        [
                            "block_id" => "block id",
                            "name" => "block name",
                            "type" => "block type",
                            "view" => "block view",
                            "custom_attributes" => [
                                "key" => "value"
                            ],
                            "valid_items" => [
                                "object remote id"
                            ]
                        ]
                    ]
                ]
            ]
        );
    }

    public function toCSVString($content, $params = null)
    {
        return ''; //todo
    }

    private function createXML($data)
    {
        $db = \eZDB::instance();
        $db->begin();
        $ezPage = new \eZPage();
        $ezPage->setAttribute('zone_layout', $data['zone_layout']);
        unset($data['zone_layout']);
        $zones = [];
        foreach ($data as $zoneIdentifier => $zone) {
            $ezPageZone = new \eZPageZone();
            $ezPageZone->setAttribute('id', $zone['zone_id']);
            $ezPageZone->setAttribute('zone_identifier', $zoneIdentifier);
            $blocks = [];
            $zoneBlocksIds = array();
            $flowPoolItems = array();

            foreach ($zone['blocks'] as $block) {
                $zoneBlocksIds[] = $block['block_id'];
                $ezPageBlock = new \eZPageBlock();
                $ezPageBlock->setAttribute('id', $block['block_id']);
                if (isset($block['name'])) {
                    $ezPageBlock->setAttribute('name', $block['name']);
                }
                $ezPageBlock->setAttribute('type', $block['type']);
                $ezPageBlock->setAttribute('view', $block['view']);
                if (isset($block['custom_attributes'])) {
                    $ezPageBlock->setAttribute('custom_attributes', $block['custom_attributes']);
                }

                // create missing blocks in the ezm_block table
                $flowBlock = \eZFlowBlock::fetch($ezPageBlock->attribute('id'));

                if (!$flowBlock) {
                    $flowBlock = new \eZFlowBlock(array(
                        'id' => $ezPageBlock->attribute('id'),
                        'zone_id' => $ezPageZone->attribute('id'),
                        'name' => $ezPageBlock->attribute('name'),
                        'node_id' => 0,
                        'block_type' => $ezPageBlock->attribute('type'),
                    ));
                } else {
                    $flowBlock->setAttribute('block_type', $ezPageBlock->attribute('type'));
                }

                $flowBlock->store();

                // reset block items: remove all existing (we assume block is manual)
                \eZPersistentObject::removeObject(\eZFlowPoolItem::definition(), array('block_id' => $block['block_id']));

                if (isset($block['valid_items'])) {
                    $validItemsCount = count($block['valid_items']);
                    foreach ($block['valid_items'] as $index => $remoteId) {
                        $object = \eZContentObject::fetchByRemoteID($remoteId);
                        if ($object instanceof \eZContentObject) {
                            $flowPoolItems[] = array(
                                'blockID' => $block['block_id'],
                                'nodeID' => $object->attribute('main_node_id'),
                                'objectID' => $object->attribute('id'),
                                'priority' => $validItemsCount - $index,
                                'timestamp' => time() - 86400
                            );
                        }
                    }
                }

                $blocks[] = $ezPageBlock;
            }

            // delete from ezm_block those that are in current zone but actually not there anymore
            if (!empty($zoneBlocksIds)) {
                foreach ($zoneBlocksIds as $i => $v) {
                    $zoneBlocksIds[$i] = $db->escapeString($v);
                }
                $db->query("DELETE from ezm_block WHERE zone_id = '" . $db->escapeString($ezPageZone->attribute('id')) . "' AND id NOT IN ('" . implode("', '", $zoneBlocksIds) . "')");
            } else {
                \eZPersistentObject::removeObject(\eZFlowBlock::definition(), array('zone_id' => $ezPageZone->attribute('id')));
            }

            \eZFlowPool::insertItems($flowPoolItems);

            $ezPageZone->setAttribute('blocks', $blocks);
            $zones[] = $ezPageZone;
        }
        $ezPage->setAttribute('zones', $zones);
        $db->commit();

        return $ezPage->toXML();
    }
}