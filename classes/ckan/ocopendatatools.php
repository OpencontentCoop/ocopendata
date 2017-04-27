<?php

/**
 * Classe OCOpenDataTools
 * Tool per la gestione dei dataset e la loro esposizione
 * Estende la libreria Ckan_client
 */
class OCOpenDataTools
{
    /**
     * Separatore per il remote id
     */
    const SEPARATOR = ':';

    /**
     * Id di installazione
     *
     * @see OCOpenDataTools::installationID()
     * @var string
     */
    static $InstallationID;

    /**
     * Configurazioni ini
     *
     * @var eZINI
     */
    public $openDataIni;

    /**
     * @var OcOpenDataClientInterface
     */
    public $client;

    /**
     * @var OcOpenDataConverterInterface
     */
    public $converter;

    /**
     * @var OcOpendataOrganizationBuilderInterface
     */
    public $organizationBuilder;

    /**
     * @var OcOpendataDatasetGeneratorInterface
     */
    protected $datasetGenerator;

    protected $settings;

    protected $currentEndpointIdentifier;

    public function __construct($alias = null)
    {
        $this->openDataIni = eZINI::instance('ocopendata.ini');
        if ($alias === null) {
            $alias = $this->openDataIni->variable('CkanSettings', 'Alias');
        }
        try{
            $this->setCurrentEndpointIdentifier($alias);
        }catch(Exception $e){
            eZDebug::writeError($e->getMessage(), __METHOD__);
        }

    }

    /**
     * @return string
     */
    public function getCurrentEndpointIdentifier()
    {
        return $this->currentEndpointIdentifier;
    }

    public function setCurrentEndpointIdentifier($alias)
    {
        $this->currentEndpointIdentifier = $alias;

        if (!$this->openDataIni->hasGroup($alias)) {
            throw new Exception("Setting $alias not found in ocopendata.ini");
        }

        $this->settings = $this->openDataIni->group($alias);

        $baseUrl = $this->settings['BaseUrl'];

        $apiVersion = $this->settings['ApiVersion'];

        $apiKey = $this->settings['ApiKey'];
        if (empty( $apiKey )) {
            throw new Exception("Api key not found");
        }
        $clientClassName = $this->settings['Client'];
        if (!class_exists($clientClassName)) {
            throw new Exception("Class $clientClassName not found");
        }
        $converterClassName = $this->settings['Converter'];
        if (!class_exists($converterClassName)) {
            throw new Exception("Class $converterClassName not found");
        }
        $organizationBuilderClassName = $this->settings['OrganizationBuilder'];
        if (!class_exists($organizationBuilderClassName)) {
            throw new Exception("Class $organizationBuilderClassName not found");
        }
        $datasetGeneratorClassName = isset( $this->settings['DatasetGenerator'] ) ? $this->settings['DatasetGenerator'] : null;
        if ($datasetGeneratorClassName ) {
            if(!class_exists($datasetGeneratorClassName)){
                throw new Exception("Class $datasetGeneratorClassName not found");
            }else{
                $this->datasetGenerator = new $datasetGeneratorClassName();
            }
        }

        $this->organizationBuilder = new $organizationBuilderClassName();
        $this->client = new $clientClassName($apiKey, $baseUrl, $apiVersion);
        $this->converter = new $converterClassName();
        $this->converter->setOrganizationBuilder($this->organizationBuilder);
    }

    public function getClient()
    {
        return $this->client;
    }

    public function getConverter()
    {
        return $this->converter;
    }

    public function getOrganizationBuilder()
    {
        return $this->organizationBuilder;
    }

    public function getSettings()
    {
        return $this->settings;
    }

    public function getDatasetGenerator()
    {
        return $this->datasetGenerator;
    }

    /**
     * @return eZINI
     */
    public function getIni()
    {
        return $this->openDataIni;
    }

    /**
     * Push di un oggetto (creazione o aggiornamento) in CKAN
     *
     * @param eZContentObject|integer $object
     *
     * @throws Exception
     * @return stdClass
     */
    public function pushObject($object)
    {
        try {
            $object = $this->validateObject($object);
            $data = $this->converter->getDatasetFromObject($object);
            $returnData = $this->client->pushDataset($data);
            $this->converter->markObjectPushed($object, $returnData);

            return $returnData;
        } catch (Exception $e) {
            eZDebug::writeError($e->getMessage() . ' on object id #' . $object->attribute('id'), __METHOD__);
            throw $e;
        }
    }

    public function deleteObject($object, $purge = false)
    {
        try {
            $object = $this->validateObject($object);
            $data = $this->converter->getDatasetFromObject($object);
            $returnData = $this->client->deleteDataset($data, $purge);
            if ($purge) {
                $this->converter->markObjectDeleted($object, $returnData);
            }

            return $returnData;
        } catch (Exception $e) {
            eZDebug::writeError($e->getMessage() . ' on object id #' . $object->attribute('id'), __METHOD__);
            throw $e;
        }
    }

    /**
     * @param $object
     *
     * @return eZContentObject
     * @throws Exception
     */
    public function validateObject($object)
    {
        if (is_numeric($object)) {
            $object = eZContentObject::fetch((int)$object);
        }
        if (!$object instanceof eZContentObject) {
            throw new Exception('Object not found');
        }
        if ($object->attribute('class_identifier') != $this->openDataIni->variable('GeneralSettings','DatasetClassIdentifier')) {
            throw new Exception('Object invalid');
        }
        if ( strpos($object->attribute('remote_id'), 'nockan' ) !== false ){
            throw new Exception('Can not validate object: the remote id contains \'nockan\'');
        }
        return $object;
    }

    public function pushOrganization()
    {
        $data = $this->organizationBuilder->build();
        try {
            $returnData = $this->client->pushOrganization($data);
            $this->organizationBuilder->storeOrganizationPushedId($returnData);

            return $returnData;
        } catch (Exception $e) {
            eZDebug::writeError($e->getMessage() . ' on organization', __METHOD__);
            throw $e;
        }
    }

    public function deleteOrganization($purge = false)
    {
        //$data = $this->organizationBuilder->getStoresOrganizationId();
        $data = $this->organizationBuilder->build();
        try {
            $returnData = $this->client->deleteOrganization($data->name, $purge);
            $this->organizationBuilder->removeStoresOrganizationId();

            return $returnData;
        } catch (Exception $e) {
            eZDebug::writeError($e->getMessage() . ' on organization', __METHOD__);
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Costruisce il datatset a partire da un oggetto
     *
     * @param eZContentObject $object
     *
     * @throws Exception
     * @return array
     */
    public function getDatasetFromObject(eZContentObject $object)
    {
        if (!$object instanceof eZContentObject) {
            throw new Exception("Oggetto non trovato");
        }
        if ($object->attribute('class_identifier') == $this->openDataIni->variable('GeneralSettings',
                'DatasetClassIdentifier')
        ) {
            return $this->converter->getDatasetFromObject($object);
        }
        throw new Exception("L'oggetto {$object->attribute( 'id' )} non è di classe {$this->openDataIni->variable( 'GeneralSettings', 'DatasetClassIdentifier' )}");
    }

    /**
     * Genera id unico
     *
     * @see self::installationID()
     *
     * @param integer $objectId
     *
     * @return string
     */
    public static function generateUniqueId($objectId)
    {
        return self::installationID() . self::SEPARATOR . $objectId;
    }

    /**
     * Restituisce un oggetto, dato il suo id unico
     *
     * @param string $id
     *
     * @return eZContentObject or false
     */
    public function getObjectFromUniqueId($id)
    {
        $fromRemote = eZContentObject::fetchByRemoteID($id);
        if ($fromRemote instanceof eZContentObject) {
            return $fromRemote;
        }
        $parts = explode(self::SEPARATOR, $id);
        if ($parts[0] == self::installationID()) {
            $object = eZContentObject::fetch($parts[1]);
            if ($object instanceof eZContentObject) {
                if ($object->attribute('class_identifier') == $this->openDataIni->variable('GeneralSettings',
                        'DatasetClassIdentifier')
                ) {
                    return $object;
                }
            }
        }

        return false;
    }

    /**
     * Restiuisce la lista delle classi filtrate sulla BlackList
     *
     * @return eZContentClass[]
     */
    public function getClassList()
    {
        $return = array();
        /** @var eZContentClass[] $classes */
        $classes = eZContentClass::fetchList();
        $classBlacklist = self::getClassBlacklist();
        foreach ($classes as $class) {
            if (!isset( $classBlacklist[$class->attribute('identifier')] )) {
                $return[$class->attribute('identifier')] = $class;
            }
        }
        ksort($return);

        return $return;
    }

    /**
     * Restiuisce la lista delle classi effettivamente utilizzatr filtrate sulla BlackList
     *
     * @return eZContentClass[]
     */
    public function getUsedClassList()
    {
        $return = array();
        /** @var eZContentClass[] $classes */
        $classes = eZContentClass::fetchList();
        $classBlacklist = self::getClassBlacklist();
        foreach ($classes as $class) {
            if (!isset( $classBlacklist[$class->attribute('identifier')] ) && $class->attribute('object_count') > 0) {
                $return[$class->attribute('identifier')] = $class;
            }
        }
        ksort($return);

        return $return;
    }

    /**
     * Restituisce la classe filtrando su balck list
     *
     * @param string $classIdentifier
     *
     * @return eZContentClass or false
     */
    public function getClass($classIdentifier)
    {
        $classBlacklist = self::getClassBlacklist();
        if (isset( $classBlacklist[$classIdentifier] )) {
            return false;
        }
        $class = eZContentClass::fetchByIdentifier($classIdentifier);

        return $class;
    }

    /**
     * Restituisce i nodi dataset nel sottoalbero dei contenuti
     *
     * @return eZContentObjectTreeNode[]
     */
    public function getDatasetNodes()
    {
        $nodes = array();
        $classIdentifier = $this->openDataIni->variable('GeneralSettings', 'DatasetClassIdentifier');
        $class = eZContentClass::fetchByIdentifier($classIdentifier);
        if ($class instanceof eZContentClass) {
            $params = array(
                'ClassFilterType' => 'include',
                'ClassFilterArray' => array($classIdentifier),
                'Depth' => 1,
                'DepthOperator' => 'ge'
            );
            $nodes = eZContentObjectTreeNode::subTreeByNodeID($params,
                eZINI::instance('content.ini')->variable('NodeSettings', 'RootNode'));
        }

        return $nodes;
    }

    /**
     * Restituisce l'array degli id dataset nel sottoalbero dei contenuti
     *
     * @return array
     */
    public function getDatasetIdArray()
    {
        $dataset = array();
        $classIdentifier = $this->openDataIni->variable('GeneralSettings', 'DatasetClassIdentifier');
        $class = eZContentClass::fetchByIdentifier($classIdentifier);
        if ($class instanceof eZContentClass) {
            $params = array(
                'ClassFilterType' => 'include',
                'ClassFilterArray' => array($classIdentifier),
                'Depth' => 1,
                'DepthOperator' => 'ge'
            );
            /** @var eZContentObjectTreeNode[] $nodes */
            $nodes = eZContentObjectTreeNode::subTreeByNodeID($params,
                eZINI::instance('content.ini')->variable('NodeSettings', 'RootNode'));
            foreach ($nodes as $node) {
                $dataset[] = self::generateUniqueId($node->attribute('contentobject_id'));
            }
        }

        return $dataset;
    }

    /**
     * Restituisce il dataset
     *
     * @param string $datasetId
     *
     * @return array
     */
    public function getDataset($datasetId)
    {
        $dataset = array();
        $object = $this->getObjectFromUniqueId($datasetId);
        if ($object) {
            $dataset = $this->getDatasetFromObject($object);
        }

        return $dataset;
    }

    /**
     * Genera l'id di installazione
     *
     * @see eZSolr::installationID()
     * @return string
     */
    public static function installationID()
    {
        if (class_exists('eZSolr') && method_exists('eZSolr', 'installationID')) {
            return eZSolr::installationID();
        }

        if (!empty( self::$InstallationID )) {
            return self::$InstallationID;
        }
        $db = eZDB::instance();

        $resultSet = $db->arrayQuery('SELECT value FROM ezsite_data WHERE name=\'ezfind_site_id\'');

        if (count($resultSet) >= 1) {
            self::$InstallationID = $resultSet[0]['value'];
        } else {
            self::$InstallationID = md5(time() . '-' . mt_rand());
            $db->query('INSERT INTO ezsite_data ( name, value ) values( \'ezfind_site_id\', \'' . self::$InstallationID . '\' )');
        }

        return self::$InstallationID;
    }

    /**
     * Restituice la lista dei datattype in black list
     *
     * @return array
     */
    public static function getDatatypeBlackList()
    {
        $datatypeBlacklist = array();
        if (eZINI::instance('ocopendata.ini')->hasVariable('ContentSettings', 'DatatypeBlackListForExternal')) {
            $datatypeBlacklist = array_fill_keys(
                (array)eZINI::instance('ocopendata.ini')->variable('ContentSettings', 'DatatypeBlackListForExternal'),
                true
            );
        }

        return $datatypeBlacklist;
    }

    /**
     * Restituice la lista delle classi in black list
     *
     * @return array
     */
    public static function getClassBlacklist()
    {
        $classBlacklist = array();
        if (eZINI::instance('ocopendata.ini')->hasVariable('ContentSettings', 'ClassIdentifierBlackListForExternal')) {
            $classBlacklist = array_fill_keys(
                (array)eZINI::instance('ocopendata.ini')->variable('ContentSettings',
                    'ClassIdentifierBlackListForExternal'),
                true
            );
        }

        return $classBlacklist;
    }

    /**
     * Restituice la lista degli identificatori di attributo in black list
     *
     * @return array
     */
    public static function getFieldBlacklist()
    {
        $fieldBlacklist = array();
        if (eZINI::instance('ocopendata.ini')->hasVariable('ContentSettings', 'IdentifierBlackListForExternal')) {
            $fieldBlacklist = array_fill_keys(
                (array)eZINI::instance('ocopendata.ini')->variable('ContentSettings', 'IdentifierBlackListForExternal'),
                true
            );
        }

        return $fieldBlacklist;
    }

    /**
     * Restituice la lista degli ovveride degli identificatori di attributo
     *
     * @param $fieldName
     * @param $classIdentifier
     *
     * @return array
     */
    public static function getOverrideFieldIdentifier($fieldName, $classIdentifier)
    {
        if (eZINI::instance('ocopendata.ini')->hasVariable('ContentSettings', 'OverrideFieldIdentifierList')) {
            $list = eZINI::instance('ocopendata.ini')->variableArray('ContentSettings', 'OverrideFieldIdentifierList');
            foreach ($list as $nameArray) {
                if (( $nameArray[0] == $fieldName || $nameArray[0] == $classIdentifier . '/' . $fieldName )
                    && isset( $nameArray[1] )
                ) {
                    return $nameArray[1];
                }
            }
        }

        return $fieldName;
    }

    /**
     * Restituice l'identificatore di attributo sulla base del loto override di identificatore
     *
     * @param string $fieldName
     * @param string $classIdentifier
     *
     * @return string
     */
    public static function getRealFieldIdentifier($fieldName, $classIdentifier)
    {
        if (eZINI::instance('ocopendata.ini')->hasVariable('ContentSettings', 'OverrideFieldIdentifierList')) {
            $list = eZINI::instance('ocopendata.ini')->variableArray('ContentSettings', 'OverrideFieldIdentifierList');
            foreach ($list as $nameArray) {
                if (isset( $nameArray[1] ) && $nameArray[1] == $fieldName) {
                    $realField = explode('/', $nameArray[0]);
                    if (count($realField) == 1) {
                        return $realField;
                    } elseif (count($realField) == 2) {
                        if ($realField[0] == $classIdentifier) {
                            return $realField[1];
                        }
                    }
                }
            }
        }

        return $fieldName;
    }

    public function getLicenseList()
    {
        return $this->client->getLicenseList();
    }

    /**
     * @return eZContentObject[]
     */
    public static function getDatasetObjects(){
        $objects = array();
        /** @var eZContentObjectTreeNode[] $nodes */
        $nodes = eZContentObjectTreeNode::subTreeByNodeID(array(
            'ClassFilterType' => 'include',
            'ClassFilterArray' => array( eZINI::instance('ocopendata.ini')->variable('GeneralSettings','DatasetClassIdentifier') ),
            'Limitation' => array()
        ),1);
        foreach( $nodes as $node ){
            $objects[] = $node->object();
        }
        return $objects;
    }

    public function archiveDatasetIdList()
    {
        $organizationId = $this->getOrganizationBuilder()->getStoresOrganizationId();
        $data = array(
            'org' => $organizationId
        );
        $currentSettingsId = $this->getCurrentEndpointIdentifier();
        foreach ($this->getDatasetObjects() as $object) {
            try {
                $datasetId = $this->getConverter()->getDatasetId($object);
                $data[$object->attribute('id')] = $datasetId;
            } catch (Exception $e) {
                eZDebug::writeError($object->attribute('id') . ':  ' . $e->getMessage(), __METHOD__);
            }
        }

        $jsonStoreData = json_encode($data);
        $data = eZSiteData::fetchByName('ckan_' . $currentSettingsId);
        if (!$data instanceof eZSiteData) {
            eZDB::instance()->query("INSERT INTO ezsite_data ( name, value ) values( 'ckan_$currentSettingsId', '$jsonStoreData' )");
        }else{
            $data->setAttribute('value', $jsonStoreData);
            $data->store();
        }
    }

    public function getArchivedDatasetIdList($currentSettingsId)
    {
        $idList = array();
        $data = eZSiteData::fetchByName('ckan_' . $currentSettingsId);
        if ($data instanceof eZSiteData){
            $idList = json_decode($data->attribute('value'), true);
        };
        return $idList;
    }

    public function restoreArchivedDatasetIdList()
    {
        $currentSettingsId = $this->getCurrentEndpointIdentifier();
        $idList = $this->getArchivedDatasetIdList($currentSettingsId);
        foreach($idList as $objectId => $datasetId){
            if ($objectId == 'org') {
                $this->organizationBuilder->storeOrganizationPushedId($datasetId);
            }else {
                $object = eZContentObject::fetch((int)$objectId);
                if ($object instanceof eZContentObject) {
                    $this->converter->markObjectPushed($object, $datasetId);
                }
            }
        }
    }

    public function getAllArchivedDatasetIdList()
    {
        $data = array();
        $aliasList = (array)$this->openDataIni->variable('CkanSettings', 'AliasList');
        foreach($aliasList as $alias){
            $data[$alias] = $this->getArchivedDatasetIdList($alias);
        }
        return $data;
    }



}
