<?php

namespace Opencontent\Ckan\DatiTrentinoIt;

class Organization extends Base
{

    /**
     * (string) the name of the organization, a string between 2 and 100 characters long, containing only lowercase alphanumeric characters, - and _
     *
     * @var string
     */
    public $name;

    /**
     * (string) – the id of the organization (optional)
     *
     * @var string
     */
    public $id;

    /**
     *  (string) – the title of the organization (optional)
     *
     * @var string
     */
    public $title;

    /**
     * (string) – the description of the organization (optional)
     *
     * @var string
     */
    public $description;

    /**
     *  (string) – the URL to an image to be displayed on the organization’s page (optional)
     *
     * @var string
     */
    public $image_url;

    /**
     *  (string) – the current state of the organization, e.g. 'active' or 'deleted', only active organizations show up in search results and other lists of organizations, this parameter will be ignored if you are not authorized to change the state of the organization (optional, default: 'active')
     *
     * @var string
     */
    public $state = 'active';

    /**
     *  (string) – (optional)
     *
     * @var string
     */
    public $approval_status;

    /**
     *  (list of dataset extra dictionaries) – the organization’s extras (optional), extras are arbitrary (key: value) metadata items that can be added to organizations, each extra dictionary should have keys 'key' (a string), 'value' (a string), and optionally 'deleted'
     *
     * @var array
     */
    public $extras;

    /**
     *  (list of dictionaries) – the datasets (packages) that belong to the organization, a list of dictionaries each with keys 'name' (string, the id or name of the dataset) and optionally 'title' (string, the title of the dataset)
     *
     * @var array
     */
    public $packages;

    /**
     *  (list of dictionaries) – the users that belong to the organization, a list of dictionaries each with key 'name' (string, the id or name of the user) and optionally 'capacity' (string, the capacity in which the user is a member of the organization)
     *
     * @var array
     */
    public $users;

    public $email;

    public $telephone;

    public $site;

    public function hasExtra($fieldKey, $returnValue = false)
    {
        if (is_array($this->extras)){
            foreach($this->extras as $extra){
                if ($extra['key'] == $fieldKey){
                    return $returnValue? $extra['value'] : true;
                }
            }
        }

        return false;
    }

    public function getExtra($fieldKey)
    {
        return $this->hasExtra($fieldKey, true);
    }

    public function setCodiceIpa($ipa)
    {
        $this->setData('codice_ipa', $ipa);

        return $this;
    }

    public function getCodiceIpa()
    {
        return $this->getData('codice_ipa');
    }
}
