<?php

namespace Opencontent\Ckan\DatiTrentinoIt;


class Dataset extends Base
{

    /**
     * name (string) – the name of the new dataset, must be between 2 and 100 characters long and contain only lowercase alphanumeric characters, - and _, e.g. 'warandpeace'
     *
     * @var string
     */
    public $name;

    /**
     * title (string) – the title of the dataset (optional, default: same as name)
     *
     * @var string
     */
    public $title;

    /**
     * author (string) – the name of the dataset’s author (optional)
     *
     * @var string
     */
    public $author;

    /**
     * author_email (string) – the email address of the dataset’s author (optional)
     *
     * @var string
     */
    public $author_email;

    /**
     * maintainer (string) – the name of the dataset’s maintainer (optional)
     *
     * @var string
     */
    public $maintainer;

    /**
     * maintainer_email (string) – the email address of the dataset’s maintainer (optional)
     *
     * @var string
     */
    public $maintainer_email;

    /**
     * license_id (license id string) – the id of the dataset’s license, see license_list() for available values (optional)
     *
     * @var string
     */
    public $license_id;

    /**
     * notes (string) – a description of the dataset (optional)
     *
     * @var string
     */
    public $notes;

    /**
     * url (string) – a URL for the dataset’s source (optional)
     *
     * @var string
     */
    public $url;

    /**
     * version (string, no longer than 100 characters) – (optional)
     *
     * @var string
     */
    public $version;

    /**
     * state (string) – the current state of the dataset, e.g. 'active' or 'deleted', only active datasets show up in search results and other lists of datasets, this parameter will be ignored if you are not authorized to change the state of the dataset (optional, default: 'active')
     *
     * @var string
     */
    public $state = 'active';

    /**
     * type (string) – the type of the dataset (optional), IDatasetForm plugins associate themselves with different dataset types and provide custom dataset handling behaviour for these types
     *
     * @var string
     */
    public $type;

    /**
     * resources (list of resource dictionaries) – the dataset’s resources, see resource_create() for the format of resource dictionaries (optional)
     *
     * @var Resource[]
     */
    public $resources = array();

    /**
     * tags (list of tag dictionaries) – the dataset’s tags, see tag_create() for the format of tag dictionaries (optional)
     *
     * @var string
     */
    public $tags;

    /**
     * extras (list of dataset extra dictionaries) – the dataset’s extras (optional), extras are arbitrary (key: value) metadata items that can be added to datasets, each extra dictionary should have keys 'key' (a string), 'value' (a string)
     *
     * @var string
     */
    public $extras;
    /**
     * relationships_as_object (list of relationship dictionaries) – see package_relationship_create() for the format of relationship dictionaries (optional)
     *
     * @var string
     */
    public $relationships_as_object;

    /**
     * relationships_as_subject (list of relationship dictionaries) – see package_relationship_create() for the format of relationship dictionaries (optional)
     *
     * @var string
     */
    public $relationships_as_subject;
    /**
     * groups (list of dictionaries) – the groups to which the dataset belongs (optional), each group dictionary should have one or more of the following keys which identify an existing group: 'id' (the id of the group, string), or 'name' (the name of the group, string), to see which groups exist call group_list()
     *
     * @var string
     */
    public $groups;

    /**
     * owner_org (string) – the id of the dataset’s owning organization, see organization_list() or organization_list_for_user() for available values (optional)
     *
     * @var string
     */
    public $owner_org;

    public $identifier;

    public $frequency = "UPDATE_CONT";

    public $theme = "REGI";

    public $geographical_name = "ITA-TRT";

    public $geographical_geonames_url;

    public $license_title = "Creative Commons Attribution 4.0";

    public $license_url = "http://creativecommons.org/licenses/by/4.0/it/";

    public $language = "ITA";

    public $publisher_name;

    public $publisher_identifier;

    public $modified;

    public $holder_name;

    public $holder_identifier;

    public $creator_name;

    public $creator_identifier;

    public $temporal_start;

    public $temporal_end;

    public $creation_date;

    public $site_url;

    public $encoding = 'UTF-8';

    public $issued;

    public $conforms_to = "conforme a REST/JSON";

    public static function getCustomFieldKeys()
    {
        return array(
//            "Codifica Caratteri",
//            "Copertura Temporale (Data di inizio)",
//            "Copertura Temporale (Data di fine)",
//            "Aggiornamento",
//            "Copertura Geografica",
//            "Titolare",
//            "Data di pubblicazione",
//            "Data di creazione",
//            "Data di aggiornamento",
            "Descrizione campi",
//            "URL sito",
        );
    }

    public static function fromArray( array $data )
    {

        $instance = new static();
        foreach ( $data as $key => $value )
        {
            if ( property_exists( $instance, $key ) )
            {
                if ( $key == 'resources' )
                {
                    $instance->resources = array();
                    foreach( $value as $resource )
                    {
                        $instance->resources[] = Resource::fromArray( $resource );
                    }
                }
                else
                {
                    $instance->{$key} = $value;
                }
            }
            else
            {
                $instance->data[$key] = $value;
            }
        }

        return $instance;
    }
}
