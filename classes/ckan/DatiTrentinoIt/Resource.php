<?php

namespace Opencontent\Ckan\DatiTrentinoIt;


class Resource extends Base
{
    /**
     * package_id (string) – id of package that the resource should be added to.
     * var string
     */
    public $package_id;

    /**
     * url (string) – url of resource
     *
     * @var string
     */
    public $url;
    /**
     * revision_id (string) – (optional)
     * var string
     */
    public $revision_id;

    /**
     * description (string) – (optional)
     *
     * @var string
     */
    public $description;

    /**
     * format (string) – (optional)
     *
     * @var string
     */
    public $format;

    /**
     * hash (string) – (optional)
     *
     * @var string
     */
    public $hash;

    /**
     * name (string) – (optional)
     *
     * @var string
     */
    public $name;

    /**
     * resource_type (string) – (optional)
     *
     * @var string
     */
    public $resource_type;

    /**
     * mimetype (string) – (optional)
     *
     * @var string
     */
    public $mimetype;

    /**
     * mimetype_inner (string) – (optional)
     *
     * @var string
     */
    public $mimetype_inner;

    /**
     * cache_url (string) – (optional)
     *
     * @var string
     */
    public $cache_url;

    /**
     * size (int) – (optional)
     *
     * @var string
     */
    public $size;

    /**
     * created (iso date string) – (optional)
     *
     * @var string
     */
    public $created;

    /**
     * last_modified (iso date string) – (optional)
     *
     * @var string
     */
    public $last_modified;

    /**
     * cache_last_updated (iso date string) – (optional)
     *
     * @var string
     */
    public $cache_last_updated;

    /**
     * upload (FieldStorage (optional) needs multipart/form-data) – (optional)
     *
     * @var string
     */
    public $upload;

}