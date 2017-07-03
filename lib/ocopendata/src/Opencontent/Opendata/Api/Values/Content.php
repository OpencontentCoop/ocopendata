<?php

namespace Opencontent\Opendata\Api\Values;

use eZContentObject;
use eZContentObjectAttribute;
use eZSection;
use eZContentLanguage;
use Opencontent\Opendata\Api\ClassRepository;
use Opencontent\Opendata\Api\Exception\OutOfRangeException;
use Opencontent\Opendata\Api\AttributeConverterLoader;
use eZUser;
use eZPreferences;
use eZContentObjectTreeNode;
use Opencontent\Opendata\Api\Gateway;
use Opencontent\Opendata\GeoJson\Feature;
use Opencontent\Opendata\GeoJson\Geometry;
use Opencontent\Opendata\GeoJson\Properties;

class Content
{
    /**
     * @var Metadata
     */
    public $metadata;

    /**
     * @var ContentData
     */
    public $data;

    /**
     * @var array
     */
    private static $limitationsNodeIds = array();

    public function __construct(array $properties = array())
    {
        foreach ($properties as $property => $value) {
            if (property_exists($this, $property)) {
                if ($property == 'metadata' && is_array($value)){
                    $this->$property = new Metadata($value);
                }else{
                    $this->$property = $value;
                }
            } else {
                throw new OutOfRangeException($property);
            }
        }
    }

    public static function __set_state(array $array)
    {
        return new static($array);
    }

    public function jsonSerialize()
    {
        if (is_object($this->metadata) && method_exists($this->metadata, 'jsonSerialize')) {
            $metadata = $this->metadata->jsonSerialize();
        } elseif (is_array($this->metadata)) {
            $metadata = $this->metadata;
        } else {
            $metadata = array();
        }

        if (is_object($this->data) && method_exists($this->data, 'jsonSerialize')) {
            $data = $this->data->jsonSerialize();
        } elseif (is_array($this->data)) {
            $data = $this->data;
        } else {
            $data = array();
        }

        return array(
            'metadata' => $metadata,
            'data' => $data
        );
    }

    /**
     * @param eZContentObject $contentObject
     *
     * @return Content
     */
    public static function createFromEzContentObject(eZContentObject $contentObject)
    {
        $languages = eZContentLanguage::fetchLocaleList();
        /** @var eZContentLanguage[] $availableLanguages */
        $availableLanguages = array_keys($contentObject->allLanguages());
        $content = new Content();
        $content->metadata = Metadata::createFromEzContentObject($contentObject);

        $attributes = array();
        foreach ($languages as $language) {
            if (in_array($language, $availableLanguages)) {
                $localeAttributes = array();
                /** @var eZContentObjectAttribute[] $dataMap */
                $dataMap = $contentObject->fetchDataMap(false, $language);
                foreach ($dataMap as $identifier => $attribute) {
                    $converter = AttributeConverterLoader::load(
                        $contentObject->attribute('class_identifier'),
                        $identifier,
                        $attribute->attribute('data_type_string')
                    );
                    $localeAttributes[$converter->getIdentifier()] = $converter->get($attribute);
                }
                $attributes[$language] = $localeAttributes;
            }
        }
        $content->data = new ContentData($attributes);

        return $content;
    }

    public function geoJsonSerialize($defaultLanguage = null)
    {
        $defaultLanguage = !$defaultLanguage ? eZContentObject::defaultLanguage() : $defaultLanguage;
        $geometry = new Geometry();
        $properties = array();

        if (isset( $this->data[$defaultLanguage] )) {
            $data = $this->data[$defaultLanguage];
            $name = $this->metadata->name[$defaultLanguage];
        } else {
            $dataArray = $this->data->jsonSerialize();
            $nameArray = $this->metadata->name;
            $data = array_shift($dataArray);
            $name = array_shift($nameArray);
        }

        $properties['id'] = $this->metadata->id;
        $properties['name'] = $name;
        $properties['class_identifier'] = $this->metadata->classIdentifier;
        $properties['mainNodeId'] = $this->metadata->mainNodeId;

        foreach ($data as $identifier => $attribute) {
            if ($attribute['datatype'] == 'ezgmaplocation') {
                $geometry->type = 'Point';
                $geometry->coordinates = array(
                    isset( $attribute['content']['longitude'] ) ? $attribute['content']['longitude'] : 0,
                    isset( $attribute['content']['latitude'] ) ? $attribute['content']['latitude'] : 0
                );
                if (!empty( $attribute['content']['address'] )) {
                    $properties[$identifier] = $attribute['content']['address'];
                }
            } else {
                //                if ( $attribute['content'] && !isset( $properties[$identifier] ))
                //                {
                //                    if ( is_scalar( $attribute['content'] ) )
                //                    {
                //                        $content = $attribute['content'];
                //                    }
                //                    elseif ( is_array( $attribute['content'] ) && isset( $attribute['content']['url'] ) )
                //                    {
                //                        $content = $attribute['content']['url'];
                //                    }
                //                    else
                //                    {
                //                        $content = $attribute['content']; //@todo
                //                    }
                //                    $properties[$identifier] = $content;
                //                }
            }
        }

        return new Feature($this->metadata->id, $geometry, new Properties($properties));
    }

    public function canRead(eZUser $user = null)
    {
        if ($user == null) {
            $user = eZUser::currentUser();
        }
        $userID = $user->attribute('contentobject_id');
        $accessResult = $user->hasAccessTo('content', 'read');
        $accessWord = $accessResult['accessWord'];

        if ($accessWord == 'yes') {
            return true;
        }

        if ($accessWord == 'no') {
            return false;
        }

        $access = 'denied';
        $policies =& $accessResult['policies'];
        foreach (array_keys($policies) as $pkey) {
            $limitationArray =& $policies[$pkey];
            if ($access == 'allowed') {
                break;
            }

            $limitationList = array();
            if (isset( $limitationArray['Subtree'] )) {
                $checkedSubtree = false;
            } else {
                $checkedSubtree = true;
                $accessSubtree = false;
            }
            if (isset( $limitationArray['Node'] )) {
                $checkedNode = false;
            } else {
                $checkedNode = true;
                $accessNode = false;
            }
            foreach (array_keys($limitationArray) as $key) {
                $access = 'denied';
                switch ($key) {
                    case 'Class': {
                        if (in_array($this->metadata->classId, $limitationArray[$key])) {
                            $access = 'allowed';
                        } else {
                            $access = 'denied';
                            $limitationList = array(
                                'Limitation' => $key,
                                'Required' => $limitationArray[$key]
                            );
                        }
                    }
                        break;

                    case 'ParentClass': {

                        if (in_array($this->metadata->classId, $limitationArray[$key])) {
                            $access = 'allowed';
                        } else {
                            $access = 'denied';
                            $limitationList = array(
                                'Limitation' => $key,
                                'Required' => $limitationArray[$key]
                            );
                        }
                    }
                        break;

                    case 'ParentDepth': {
                        $assignedNodes = $this->metadata->assignedNodes;
                        if (count($assignedNodes) > 0) {
                            foreach ($assignedNodes as $assignedNode) {
                                $depth = $assignedNode['depth'];
                                if (in_array($depth, $limitationArray[$key])) {
                                    $access = 'allowed';
                                    break;
                                }
                            }
                        }

                        if ($access != 'allowed') {
                            $access = 'denied';
                            $limitationList = array(
                                'Limitation' => $key,
                                'Required' => $limitationArray[$key]
                            );
                        }
                    }
                        break;

                    case 'Section':
                    case 'User_Section': {
                        if (in_array($this->metadata->sectionId, $limitationArray[$key])) {
                            $access = 'allowed';
                        } else {
                            $access = 'denied';
                            $limitationList = array(
                                'Limitation' => $key,
                                'Required' => $limitationArray[$key]
                            );
                        }
                    }
                        break;

                    case 'Language': {
                        //@todo
                    }
                        break;

                    case 'Owner':
                    case 'ParentOwner': {
                        // if limitation value == 2, anonymous limited to current session.
                        if (in_array(2, $limitationArray[$key]) && $user->isAnonymous()) {
                            $createdObjectIDList = eZPreferences::value('ObjectCreationIDList');
                            if ($createdObjectIDList
                                && in_array($this->metadata->id, unserialize($createdObjectIDList))
                            ) {
                                $access = 'allowed';
                            }
                        } else if ($this->metadata->ownerId == $userID || $this->metadata->id == $userID) {
                            $access = 'allowed';
                        }
                        if ($access != 'allowed') {
                            $access = 'denied';
                            $limitationList = array(
                                'Limitation' => $key,
                                'Required' => $limitationArray[$key]
                            );
                        }
                    }
                        break;

                    case 'Group':
                    case 'ParentGroup': {
                        $access = $this->checkGroupLimitationAccess(
                            $limitationArray[$key],
                            $userID
                        );

                        if ($access != 'allowed') {
                            $access = 'denied';
                            $limitationList = array(
                                'Limitation' => $key,
                                'Required' => $limitationArray[$key]
                            );
                        }
                    }
                        break;

                    case 'State': {
                        if (count(array_intersect($limitationArray[$key], $this->metadata->stateIds)) == 0
                        ) {
                            $access = 'denied';
                            $limitationList = array(
                                'Limitation' => $key,
                                'Required' => $limitationArray[$key]
                            );
                        } else {
                            $access = 'allowed';
                        }
                    }
                        break;

                    case 'Node': {
                        $accessNode = false;
                        $mainNodeID = $this->metadata->mainNodeId;
                        foreach ($limitationArray[$key] as $nodeID) {
                            $limitationNodeID = $this->getMainNodeIdFromNodeId($nodeID);
                            if ($mainNodeID == $limitationNodeID) {
                                $access = 'allowed';
                                $accessNode = true;
                                break;
                            }
                        }
                        if ($access != 'allowed' && $checkedSubtree && ( !isset( $accessSubtree ) || !$accessSubtree )) {
                            $access = 'denied';
                            // ??? TODO: if there is a limitation on Subtree, return two limitations?
                            $limitationList = array(
                                'Limitation' => $key,
                                'Required' => $limitationArray[$key]
                            );
                        } else {
                            $access = 'allowed';
                        }
                        $checkedNode = true;
                    }
                        break;

                    case 'Subtree': {
                        $accessSubtree = false;
                        $assignedNodes = $this->metadata->assignedNodes;
                        if (count($assignedNodes) != 0) {
                            foreach ($assignedNodes as $assignedNode) {
                                $path = $assignedNode['path_string'];
                                $subtreeArray = $limitationArray[$key];
                                foreach ($subtreeArray as $subtreeString) {
                                    if (strstr($path, $subtreeString)) {
                                        $access = 'allowed';
                                        $accessSubtree = true;
                                        break;
                                    }
                                }
                            }
                        } else {
                            $parentNodes = $this->metadata->parentNodes;
                            if (count($parentNodes) == 0) {
                                if ($this->metadata->id == $userID || $this->metadata->id == $userID) {
                                    $access = 'allowed';
                                    $accessSubtree = true;
                                }
                            } else {
                                foreach ($parentNodes as $parentNode) {
                                    $path = $parentNode['path_string'];

                                    $subtreeArray = $limitationArray[$key];
                                    foreach ($subtreeArray as $subtreeString) {
                                        if (strstr($path, $subtreeString)) {
                                            $access = 'allowed';
                                            $accessSubtree = true;
                                            break;
                                        }
                                    }
                                }
                            }
                        }
                        if ($access != 'allowed' && $checkedNode && ( !isset( $accessNode ) || !$accessNode )) {
                            $access = 'denied';
                            // ??? TODO: if there is a limitation on Node, return two limitations?
                            $limitationList = array(
                                'Limitation' => $key,
                                'Required' => $limitationArray[$key]
                            );
                        } else {
                            $access = 'allowed';
                        }
                        $checkedSubtree = true;
                    }
                        break;

                    case 'User_Subtree': {
                        $assignedNodes = $this->metadata->assignedNodes;
                        if (count($assignedNodes) != 0) {
                            foreach ($assignedNodes as $assignedNode) {
                                $path = $assignedNode['path_string'];
                                $subtreeArray = $limitationArray[$key];
                                foreach ($subtreeArray as $subtreeString) {
                                    if (strstr($path, $subtreeString)) {
                                        $access = 'allowed';
                                    }
                                }
                            }
                        } else {
                            $parentNodes = $this->metadata->parentNodes;
                            if (count($parentNodes) == 0) {
                                if ($this->metadata->id == $userID || $this->metadata->id == $userID) {
                                    $access = 'allowed';
                                }
                            } else {
                                foreach ($parentNodes as $parentNode) {
                                    $path = $parentNode['path_string'];

                                    $subtreeArray = $limitationArray[$key];
                                    foreach ($subtreeArray as $subtreeString) {
                                        if (strstr($path, $subtreeString)) {
                                            $access = 'allowed';
                                            break;
                                        }
                                    }
                                }
                            }
                        }
                        if ($access != 'allowed') {
                            $access = 'denied';
                            $limitationList = array(
                                'Limitation' => $key,
                                'Required' => $limitationArray[$key]
                            );
                        }
                    }
                        break;

                    default: {
                        if (strncmp($key, 'StateGroup_', 11) === 0) {
                            if (count(array_intersect($limitationArray[$key], $this->metadata->stateIds)) == 0) {
                                $access = 'denied';
                                $limitationList = array(
                                    'Limitation' => $key,
                                    'Required' => $limitationArray[$key]
                                );
                            } else {
                                $access = 'allowed';
                            }
                        }
                    }
                }
                if ($access == 'denied') {
                    break;
                }
            }

            $policyList[] = array(
                'PolicyID' => $pkey,
                'LimitationList' => $limitationList
            );
        }

        return !( $access == 'denied' );
    }

    private function checkGroupLimitationAccess($limitationValueList, $userID)
    {
        $access = 'denied';

        if (is_array($limitationValueList) && is_numeric($userID)) {
            // limitation value == 1, means "self group"
            if (in_array(1, $limitationValueList)) {
                // no need to check groups if user ownes this object
                $ownerID = $this->metadata->ownerId;
                if ($ownerID == $userID || $this->metadata->id == $userID) {
                    $access = 'allowed';
                } else {
                    // get parent node ids for 'user' and 'owner'
                    $groupList = eZContentObjectTreeNode::getParentNodeIdListByContentObjectID(array($userID, $ownerID),
                        true);

                    // find group(s) which is common for 'user' and 'owner'
                    $commonGroup = array_intersect($groupList[$userID], $groupList[$ownerID]);

                    if (count($commonGroup) > 0) {
                        // ok, we have at least 1 common group
                        $access = 'allowed';
                    }
                }
            }
        }

        return $access;
    }

    private function getMainNodeIdFromNodeId($nodeId)
    {
        if (!isset( self::$limitationsNodeIds[$nodeId] )) {
            $node = eZContentObjectTreeNode::fetchObject(
                eZContentObjectTreeNode::definition(),
                array('main_node_id'),
                array('node_id' => $nodeId),
                false
            );
            $mainNodeId = $node['main_node_id'];
            self::$limitationsNodeIds[$nodeId] = $mainNodeId;
        }

        return self::$limitationsNodeIds[$nodeId];
    }

    public function __toString()
    {
        return (string)$this->metadata->id;
    }

    /**
     * @param $languageCode
     *
     * @return eZContentObject
     */
    public function getContentObject($languageCode)
    {
        global $eZContentObjectDataMapCache;
        global $eZContentObjectContentObjectCache;

        $classRepository = new ClassRepository();
        $class = $classRepository->load($this->metadata->classIdentifier);

        $object = new eZContentObject(array(
            'id' => $this->metadata->id,
            'section_id' => $this->metadata->sectionId,
            'owner_id' => $this->metadata->ownerId,
            'contentclass_id' => $this->metadata->classId,
            'name' => $this->metadata->name[$languageCode],
            'published' => strtotime($this->metadata->published),
            'modified' => strtotime($this->metadata->modified),
            'current_version' => $this->metadata->currentVersion,
            'status' => eZContentObject::STATUS_PUBLISHED,
            'remote_id' => $this->metadata->remoteId,
            'language_mask' => eZContentLanguage::idByLocale($languageCode),
            'initial_language_id' => eZContentLanguage::idByLocale($languageCode),
        ));
        $object->MainNodeID = (int)$this->metadata->mainNodeId;
        $object->ClassIdentifier = $this->metadata->classIdentifier;

        $version = $this->metadata->currentVersion;
        $dataMap = array(
            $version => array(
                $languageCode => array()
            )
        );
        $data = array();
        foreach ($this->data[$languageCode] as $identifier => $field) {

            $identifierExists = array_search($identifier, array_column($class->fields, 'identifier'));
            if (!$identifierExists){
                continue;
            }

            $attribute = new eZContentObjectAttribute(array(
                'id' => $field['id'],
                'contentobject_id' => $this->metadata->id,
                'version' => $field['version'],
                'language_code' => $languageCode,
                'language_id' => eZContentLanguage::idByLocale($languageCode),
                'contentclassattribute_id' => $field['contentclassattribute_id'],
                'attribute_original_id' => 0,
                'sort_key_int' => $field['sort_key_int'],
                'sort_key_string' => $field['sort_key_string'],
                'data_type_string' => $field['datatype'],
                'data_text' => $field['data_text'],
                'data_int' => $field['data_int'],
                'data_float' => $field['data_float'],
            ));
            $attribute->ContentClassAttributeIdentifier = $identifier;
            $attribute->ContentClassAttributeIsInformationCollector = $field['is_information_collector'];
            $dataMap[$version][$languageCode][$identifier] = $attribute;
            $data[] = $attribute;
        }
        $object->ContentObjectAttributeArray = $dataMap;
        $object->ContentObjectAttributes = $dataMap;
        $object->DataMap = $dataMap;
        $object->setCachedName($this->metadata->name[$languageCode]);

        $eZContentObjectDataMapCache[$this->metadata->id][$version][$languageCode] = $data;
        $eZContentObjectContentObjectCache[$this->metadata->id] = $object;

        return $object;
    }
}
