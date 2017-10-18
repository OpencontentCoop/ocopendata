<?php

namespace Opencontent\Opendata\Api\Structs;

use Opencontent\Opendata\Api\ClassRepository;
use Opencontent\Opendata\Api\Exception\CreateContentException;
use Opencontent\Opendata\Api\Exception\ForbiddenException;
use Opencontent\Opendata\Api\Exception\UpdateContentException;
use Opencontent\Opendata\Api\Exception\DuplicateRemoteIdException;
use Opencontent\Opendata\Api\StateRepository;
use Opencontent\Opendata\Api\SectionRepository;
use eZContentObject;
use eZContentObjectTreeNode;
use eZContentLanguage;
use Opencontent\Opendata\Api\Exception\OutOfRangeException;
use Opencontent\Opendata\Api\Values\ContentClass;
use Opencontent\Opendata\Api\Values\ContentSection;
use Opencontent\Opendata\Api\Values\ContentState;


class MetadataStruct implements \ArrayAccess
{
    const METHOD_CREATE = 1;
    const METHOD_UPDATE = 2;

    public $id;

    public $remoteId;

    public $classIdentifier;

    public $sectionIdentifier;

    public $stateIdentifiers;

    public $parentNodes;

    public $languages;

    public $creatorId;

    public $published;

    public $modified;

    /**
     * @var ContentClass
     */
    protected $class;

    /**
     * @var ContentSection
     */
    protected $section;

    /**
     * @var ContentState[]
     */
    protected $states = array();


    /**
     * @var eZContentObjectTreeNode[]
     */
    protected $parentTreeNodes = array();


    /**
     * @var StateRepository
     */
    protected $stateRepository;

    /**
     * @var SectionRepository
     */
    protected $sectionRepository;

    /**
     * @var ClassRepository
     */
    protected $classRepository;

    /**
     * @var eZContentObject
     */
    protected $contentObject;

    protected $useDefaultLanguage = false;

    protected $method;

    public function __construct(array $properties)
    {
        foreach ($properties as $property => $value) {
            if (property_exists($this, $property)) {
                $this->$property = $value;
            } else {
                throw new OutOfRangeException($property);
            }
        }

        if ($this->creatorId == null) {
            $this->creatorId = (int)\eZUser::currentUserID();
        }

        $this->stateRepository = new StateRepository();
        $this->sectionRepository = new SectionRepository();
        $this->classRepository = new ClassRepository();

    }

    /**
     * @return StateRepository
     */
    public function getStateRepository()
    {
        return $this->stateRepository;
    }

    /**
     * @param StateRepository $stateRepository
     */
    public function setStateRepository($stateRepository)
    {
        $this->stateRepository = $stateRepository;
    }

    /**
     * @return SectionRepository
     */
    public function getSectionRepository()
    {
        return $this->sectionRepository;
    }

    /**
     * @param SectionRepository $sectionRepository
     */
    public function setSectionRepository($sectionRepository)
    {
        $this->sectionRepository = $sectionRepository;
    }

    /**
     * @return ClassRepository
     */
    public function getClassRepository()
    {
        return $this->classRepository;
    }

    /**
     * @param ClassRepository $classRepository
     */
    public function setClassRepository($classRepository)
    {
        $this->classRepository = $classRepository;
    }

    /**
     * @return ContentClass
     */
    public function getClass()
    {
        return $this->class;
    }

    /**
     * @param ContentClass $class
     */
    public function setClass($class)
    {
        $this->class = $class;
    }

    /**
     * @return ContentSection
     */
    public function getSection()
    {
        return $this->section;
    }

    /**
     * @param ContentSection $section
     */
    public function setSection($section)
    {
        $this->section = $section;
    }

    /**
     * @return ContentState[]
     */
    public function getStates()
    {
        return $this->states;
    }

    /**
     * @param ContentState[] $states
     */
    public function setStates($states)
    {
        $this->states = $states;
    }

    /**
     * @return \eZContentObjectTreeNode[]
     */
    public function getParentTreeNodes()
    {
        return $this->parentTreeNodes;
    }

    /**
     * @return eZContentObject
     */
    public function getContentObject()
    {
        return $this->contentObject;
    }

    public function useDefaultLanguage()
    {
        return $this->useDefaultLanguage;
    }

    protected function throwException( $message )
    {
        if ( $this->method == self::METHOD_UPDATE)
            throw new UpdateContentException( $message );
        elseif ( $this->method == self::METHOD_CREATE)
            throw new CreateContentException( $message );
        else
            throw new \Exception( $message );
    }

    protected function validate()
    {
        if (empty($this)){
            $this->throwException("No data found");
        }

        // classe
        $this->class = $this->classRepository->load($this->classIdentifier);
        if (!$this->class instanceof ContentClass) {
            $this->throwException("Class '{$this->classIdentifier}' not found");
        }

        // sezione
        if ($this->sectionIdentifier !== null) {
            if (!is_string($this->sectionIdentifier)) {
                $this->throwException("sectionIdentifier must be a string: a single section for a single content");
            }
            $this->section = $this->sectionRepository->load($this->sectionIdentifier);
            if (!$this->section instanceof ContentSection) {
                $this->throwException("Section '{$this->sectionIdentifier}' not found");
            }
        }

        // stati
        if ($this->stateIdentifiers !== null) {
            if (is_string($this->stateIdentifiers)) {
                $this->stateIdentifiers = array($this->stateIdentifiers);
            }

            foreach ($this->stateIdentifiers as $stateIdentifier) {
                $this->states[$stateIdentifier] = $this->stateRepository->load($stateIdentifier);
                if (!$this->states[$stateIdentifier] instanceof ContentState) {
                    $this->throwException("Section '{$stateIdentifier}' not found");
                }
            }
        } else {
            $this->stateIdentifiers = array();
        }

        // lingue
        if ($this->languages !== null) {
            $languages = eZContentLanguage::fetchLocaleList();
            foreach ($this->languages as $language) {
                if (!in_array($language, $languages)) {
                    $this->throwException("Language '{$language}' not found");
                }
            }
        } else {
            $prioritizedLanguageCodes = eZContentLanguage::prioritizedLanguageCodes();
            $this->languages = array($prioritizedLanguageCodes[0]);
            $this->useDefaultLanguage = true;
        }

        //parent nodes
        $normalizedParentNodes = array();
        foreach ((array)$this->parentNodes as $nodeId) {
            $node = eZContentObjectTreeNode::fetch((int)$nodeId);
            if ($node instanceof eZContentObjectTreeNode) {
                $this->parentTreeNodes[] = $node;
                $normalizedParentNodes[] = (int)$nodeId;
            } else {
                $node = eZContentObjectTreeNode::fetchByRemoteID($nodeId);
                if ($node instanceof eZContentObjectTreeNode) {
                    $this->parentTreeNodes[] = $node;
                    $normalizedParentNodes[] = (int)$node->attribute( 'node_id' );
                } else {
                    $this->throwException("Node '{$nodeId}' not found");
                }
            }
        }

        //publish date if is passed or nowdate
        if ($this->published == null){
            $this->published = time();
        }

        //modified date if is passed or nowdate
        if ($this->modified == null){
            $this->modified = time();
        }

        $this->parentNodes = $normalizedParentNodes;

    }

    public function validateOnCreate()
    {
        $this->method = self::METHOD_CREATE;

        // remoteId univoco
        if ($this->remoteId !== null && eZContentObject::fetchByRemoteID($this->remoteId)) {
            throw new DuplicateRemoteIdException("Remote '{$this->remoteId}' already exists");
        }

        if ($this->id !== null){
            $this->throwException("Invalid parameter 'id' in create method");
        }

        // classe
        if ($this->classIdentifier === null) {
            $this->throwException("Missing parameter 'classIdentifier'");
        }

        //parent nodes
        if ($this->parentNodes == null) {
            $this->throwException("Missing parameter 'parentNodes'");
        }
        if ($this->parentNodes && !is_array($this->parentNodes)) {
            $this->parentNodes = array($this->parentNodes);
        }

        $this->validate();
    }

    public function validateOnUpdate()
    {
        $this->method = self::METHOD_UPDATE;

        if ($this->remoteId !== null) {
            $this->contentObject = eZContentObject::fetchByRemoteID($this->remoteId);
            if (!$this->contentObject instanceof eZContentObject) {
                $this->throwException("Object with remoteId '{$this->remoteId}' not found");
            }
        }elseif ($this->id !== null) {
            $this->contentObject = eZContentObject::fetch((int)$this->id);
            if (!$this->contentObject instanceof eZContentObject) {
                $this->throwException("Object with id '{$this->id}' not found");
            }
        }else{
            $this->throwException("Parameter remoteId or id is required. Which content should you modify?");
        }

        //parent nodes
        if ($this->parentNodes == null) {
            $this->parentNodes = array();
        }

        if ($this->classIdentifier === null) {
            $this->classIdentifier = $this->contentObject->attribute('class_identifier');
        } elseif( $this->classIdentifier != $this->contentObject->attribute('class_identifier') ) {
            $this->throwException("Can not switch class in existing content");
        }

        $this->validate();
    }

    public function checkAccess(\eZUser $user)
    {
        foreach ($this->parentTreeNodes as $parentNode) {
            $parentObject = $parentNode->object();
            foreach ($this->languages as $languageCode) {
                if ($parentObject->checkAccess(
                        'create',
                        $this->class->getClassId(),
                        false,
                        false,
                        $languageCode) != '1'
                ) {
                    throw new ForbiddenException("'{$this->class->identifier}' in language '$languageCode' in node #{$parentNode->attribute( 'node_id' )}", 'create');
                }
            }
        }

        $allowedAssignStateList = $this->getAllowedAssignStateList();
        foreach ($this->stateIdentifiers as $stateIdentifier) {
            if (!in_array($stateIdentifier, $allowedAssignStateList)) {
                throw new ForbiddenException( "'{$this->class->identifier}' in state '$stateIdentifier'", 'create');
            }
        }

    }

    public function offsetExists($property)
    {
        return isset( $this->{$property} );
    }

    public function offsetGet($property)
    {
        return $this->{$property};
    }

    public function offsetSet($property, $value)
    {
        if (property_exists($this, $property)) {
            $this->{$property} = $value;
        } else {
            throw new OutOfRangeException($property);
        }
    }

    public function offsetUnset($property)
    {
        $this->{$property} = null;
    }

    protected function getAllowedAssignStateList()
    {
        $user = \eZUser::currentUser();
        $access = $user->hasAccessTo('state', 'assign');

        $db = \eZDB::instance();
        $sql = 'SELECT ezcobj_state.id
                FROM   ezcobj_state, ezcobj_state_group
                WHERE  ezcobj_state.group_id = ezcobj_state_group.id
                    AND ezcobj_state_group.identifier NOT LIKE \'ez%\'';
        if ($access['accessWord'] == 'yes') {
            $allowedStateIDList = $db->arrayQuery($sql, array('column' => 'id'));
        } else if ($access['accessWord'] == 'limited') {
            $userID = $user->attribute('contentobject_id');
            $classID = $this->class->getClassId();
            $ownerID = $userID;
            $sectionID = $this->section['id'];

            $stateIDArray = array();
            foreach ($this->stateRepository->defaultStates() as $state) {
                $stateIDArray[] = $state['id'];
            }

            $allowedStateIDList = array();
            foreach ($access['policies'] as $policy) {
                foreach ($policy as $ident => $values) {
                    $allowed = true;

                    switch ($ident) {
                        case 'Class': {
                            $allowed = in_array($classID, $values);
                        }
                            break;

                        case 'Owner': {
                            $allowed = in_array(1, $values) and $userID != $ownerID;
                        }
                            break;

                        case 'Section':
                        case 'User_Section': {
                            $allowed = in_array($sectionID, $values);
                        }
                            break;

                        //This case is based on the similar if statement in the method : classListFromPolicy
                        case 'User_Subtree': {
                            $allowed = false;
                            foreach ($this->parentTreeNodes as $assignedNode) {
                                $path = $assignedNode->attribute('path_string');
                                foreach ($policy['User_Subtree'] as $subtreeString) {
                                    if (strpos($path, $subtreeString) !== false) {
                                        $allowed = true;
                                        break;
                                    }
                                }
                            }
                        }
                            break;

                        default: {
                            if (strncmp($ident, 'StateGroup_', 11) === 0) {
                                $allowed = count(array_intersect($values, $stateIDArray)) > 0;
                            }
                        }
                    }

                    if (!$allowed) {
                        continue 2;
                    }
                }

                if (isset( $policy['NewState'] ) and count($policy['NewState']) > 0) {
                    $allowedStateIDList = array_merge($allowedStateIDList, $policy['NewState']);
                } else {
                    $allowedStateIDList = $db->arrayQuery($sql, array('column' => 'id'));
                    break;
                }
            }

            $allowedStateIDList = array_merge($allowedStateIDList, $stateIDArray);
        } else {
            $stateIDArray = array();
            foreach ($this->stateRepository->defaultStates() as $state) {
                $stateIDArray[] = $state['id'];
            }
            $allowedStateIDList = $stateIDArray;
        }

        $allowedStateIDList = array_unique($allowedStateIDList);

        $allowedStateList = array();
        foreach ($allowedStateIDList as $allowedStateID) {
            $allowedState = $this->stateRepository->load($allowedStateID);
            $allowedStateList[] = $allowedState['identifier'];
        }
        sort($allowedStateList);

        return $allowedStateList;
    }


}
