<?php

namespace Opencontent\Ckan\DatiTrentinoIt\OrganizationBuilder;

use Opencontent\Ckan\DatiTrentinoIt\Organization;
use OcOpendataOrganizationBuilderInterface;
use eZDB;
use eZSiteData;

class OpenPA implements OcOpendataOrganizationBuilderInterface
{

    private $instance;

    /**
     * @return Organization
     * @throws \Exception
     */
    public function build()
    {
        if ($this->instance === null) {
            $instance = \OpenPAInstance::current();

            if ( !$instance->isLive() ){
                throw new \Exception( "L'istanza corrente non è in produzione" );
            }

            if ($instance->getType() != 'comune_standard' && $instance->getType() != 'comune_new_design') {
                throw new \Exception("L'istanza corrente non è un comune");
            }

            $pagedata = new \OpenPAPageData();
            $contacts = $pagedata->getContactsData();
            $title = \eZINI::instance()->variable('SiteSettings', 'SiteName');

            $trans = \eZCharTransform::instance();
            $name = $trans->transformByGroup($title, 'urlalias_dataset');
            $description = "Dati di cui è titolare il " . $title;

            //$imageUrl = 'http://' . rtrim( \eZINI::instance()->variable( 'SiteSettings', 'SiteURL' ), '/' ) . '/extension/ocopendata/design/standard/images/comunweb-cloud.png';

            $stemma = \OpenPaFunctionCollection::fetchStemma();
            if (is_array($stemma)) {
                $imageUrl = 'http://' . rtrim(\eZINI::instance()->variable('SiteSettings', 'SiteURL'),
                        '/') . '/' . $stemma['full_path'];
            } else {
                $imageUrl = 'http://dati.trentino.it/images/logo.png';
            }

            if (!isset( $contacts['web'] )) {
                $contacts['web'] = 'http://' . rtrim(\eZINI::instance()->variable('SiteSettings', 'SiteURL'), '/');
            }

            if (!isset( $contacts['codice_ipa'] )) {
                throw new \Exception("Il codice IPA è un valore obbligatorio");
            }

            $org = new Organization();

            $org->setCodiceIpa($contacts['codice_ipa']);

            $extras = array();
            foreach ($contacts as $key => $value) {

                switch ($key) {
                    case 'email':
                        $org->email = $value;
                        break;

                    case 'telefono':
                        $org->telephone = $value;
                        break;

                    case 'web':
                        $org->site = $value;
                        break;

                    default:
                        $extras[] = array(
                            'key' => $key,
                            'value' => $value
                        );
                }
            }

            $org->name = $name;
            $org->title = $title;
            $org->description = $description;
            $org->image_url = $imageUrl;
            $org->extras = $extras;

            $this->instance = $org;
        }
        return $this->instance;
    }

    public function storeOrganizationPushedId( $returnData )
    {
        if (is_array($returnData)){
            $id = $returnData['id'];
        }elseif (is_object($returnData) && property_exists($returnData, 'id')) {
            $id = $returnData->id;
        }else{
            $id = (string)$returnData;
        }
        if ( $id ){
            $data = eZSiteData::fetchByName('ckan_organization_id');
            if (!$data instanceof eZSiteData) {
                eZDB::instance()->query("INSERT INTO ezsite_data ( name, value ) values( 'ckan_organization_id', '$id' )");
            }else{
                $data->setAttribute('value', $id);
                $data->store();
            }
        }
    }

    public function getStoresOrganizationId()
    {
        $data = eZSiteData::fetchByName( 'ckan_organization_id' );
        if ( $data instanceof eZSiteData )
            return $data->attribute( 'value' );
        return null;
    }

    public function removeStoresOrganizationId()
    {
        $data = eZSiteData::fetchByName('ckan_organization_id');
        if ($data instanceof eZSiteData) {
            $data->remove();
        }
    }
    
}
