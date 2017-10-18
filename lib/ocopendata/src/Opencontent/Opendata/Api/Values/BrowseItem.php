<?php

namespace Opencontent\Opendata\Api\Values;


class BrowseItem
{
    public $id;

    public $remoteId;

    public $nodeId;

    public $nodeRemoteId;

    public $isMainNode;

    public $mainNodeId;

    public $parentNodeId;

    public $name;

    public $isHidden;

    public $isInvisible;

    public $depth;

    public $modified;

    public $path;

    public $classIdentifier;

    /**
     * @var integer
     */
    public $childrenCount = 0;

}
