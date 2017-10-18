<?php
require 'autoload.php';

$script = eZScript::instance(array(
    'description' => ( "Ckan dataset tools\n\n" ),
    'use-session' => false,
    'use-modules' => true,
    'use-extensions' => true
));

$script->startup();

$options = $script->getOptions(
    '[id:][show][push][delete][purge][unlink][generate_from_class:][generate_all][dry-run][push_all][delete_all][purge_all][unlink_all][archive_all][link_all]',
    '',
    array(
        'dry-run' => "Verifica l'azione ma non la esegue",

        'id' => 'eZ Content Object id',
        'show' => 'Mostra le info sul dataset',
        'push' => 'Salva o aggiorna il dataset in Ckan',
        'delete' => "Marca il dataset come 'deleted' in Ckan",
        'purge' => 'Elimina il dataset da Ckan',
        'unlink' => 'Elimina il riferimento al Ckan remoto',

        'push_all' => 'Invia tutti i dataset locali in Ckan',
        'delete_all' => 'Elimina da Ckan tutti i dataset locali',
        'purge_all' => 'Elimina da Ckan tutti i dataset locali',
        'unlink_all' => 'Elimina i riferimenti a Ckan remoto da tutti i dataset locali',
        'link_all' => 'Reimposta i riferimenti dal Ckan remoto a tutti i dataset locali',
        'archive_all' => 'Salva i riferimenti al Ckan remoto di tutti i dataset locali',
        'generate_from_class' => "Genera un oggetto dataset in eZ dato l'identificativo di classe specificato",
        'generate_all' => "Genera un oggetto dataset in eZ per ciascuna classe specificata nel file ocopendata_datasetgenerator.ini"
    )
);
$script->initialize();
$script->setUseDebugAccumulators(true);

$cli = eZCLI::instance();

try {

    $tools = new OCOpenDataTools();

    $cli->warning('Dump connector settings: ');
    print_r($tools->getSettings());
    $cli->notice();

    if ($options['push_all']) {

        function recursivePush(OCOpenDataTools $tools, eZContentObject $object, eZCLI $cli, $level = 0)
        {
            try {
                $tools->pushObject($object);
                $cli->warning('OK');
            } catch (Exception $e) {
                if ($e instanceof OCOpenDataRequestException && $e->getResponseCode() == '404' && $level == 0) {
                    $tools->getConverter()->markObjectDeleted($object, null);
                    recursivePush($tools, $object, $cli, 1);
                } else {
                    $cli->error('KO ' . $e->getMessage());
                }
            }
        }

        foreach ($tools->getDatasetObjects() as $object) {
            $cli->notice($object->attribute('id') . ' ' . $object->attribute('name') . ' ', false);
            if (!$options['dry-run']) {
                recursivePush($tools, $object, $cli, 1);
            } else {
                $cli->notice();
            }
        }

    } elseif ($options['delete_all'] || $options['purge_all']) {

        foreach ($tools->getDatasetObjects() as $object) {
            $cli->notice($object->attribute('id') . ' ' . $object->attribute('name') . ' ', false);
            if (!$options['dry-run']) {
                try {
                    $tools->deleteObject($object, $options['purge_all']);
                    $cli->warning('OK');
                } catch (Exception $e) {
                    if ($e instanceof OCOpenDataRequestException && $e->getResponseCode() == '404') {
                        $tools->getConverter()->markObjectDeleted($object, null);
                        $cli->warning('OK');
                    } else {
                        $cli->error('KO ' . $e->getMessage());
                    }
                }
            } else {
                $cli->notice();
            }
        }

    } elseif ($options['archive_all']) {

        $tools->archiveDatasetIdList();

    } elseif ($options['unlink_all']) {

        foreach ($tools->getDatasetObjects() as $object) {
            $cli->notice($object->attribute('id') . ' ' . $object->attribute('name') . ' ', false);
            if (!$options['dry-run']) {
                try {
                    $tools->getConverter()->markObjectDeleted($object, null);
                    $cli->warning('OK');
                } catch (Exception $e) {
                    $cli->error('KO ' . $e->getMessage());
                }
            } else {
                $cli->notice();
            }
        }

        $tools->archiveDatasetIdList();

    } elseif ($options['generate_from_class']) {

        $generator = $tools->getDatasetGenerator();
        if ($generator instanceof OcOpendataDatasetGeneratorInterface) {
            $object = $generator->createFromClassIdentifier(
                $options['generate_from_class'],
                array(),
                $options['dry-run'] !== null
            );
            if (!$options['dry-run']) {
                $cli->warning("Generato/aggiornato oggetto " . $object->attribute('id'));
            } else {
                $cli->warning('Ok');
            }
        } else {
            throw new Exception('Generator not found');
        }

    } elseif ($options['generate_all']) {

        $generator = $tools->getDatasetGenerator();
        if ($generator instanceof OcOpendataDatasetGeneratorInterface) {
            $groups = eZINI::instance('ocopendata_datasetgenerator.ini')->groups();
            foreach (array_keys($groups) as $classIdentifier) {
                try {
                    $object = $generator->createFromClassIdentifier(
                        $classIdentifier,
                        array(),
                        $options['dry-run'] !== null
                    );
                    if (!$options['dry-run']) {
                        $cli->warning("Generato/aggiornato $classIdentifier " . $object->attribute('id'));
                    } else {
                        $cli->warning('Ok');
                    }
                } catch (Exception $e) {
                    $cli->error($e->getMessage());
                }
            }
        } else {
            throw new Exception('Generator not found');
        }

    } elseif ($options['link_all']) {

        $currentSettingsId = $tools->getCurrentEndpointIdentifier();
        $cli->warning($currentSettingsId);

        $organizationId = $tools->getOrganizationBuilder()->getStoresOrganizationId();
        $data = array(
            'org' => $organizationId
        );

        $settings = $tools->getSettings();
        $baseUrl = $settings['BaseUrl'];
        $apiVersion = $settings['ApiVersion'];
        $baseUrl = sprintf( $baseUrl, $apiVersion );

        $remoteData = file_get_contents($baseUrl . "action/organization_show?id={$organizationId}&include_datasets=1");
        $remoteDataDecoded = $remoteData ? json_decode($remoteData,1) : array();

        $suggestions = array();
        if (isset($remoteDataDecoded["result"]["packages"])){
            foreach($remoteDataDecoded["result"]["packages"] as $package){
                $suggestions[$package['title']] = $package['id'];
            }
        }

        foreach( $tools->getDatasetObjects() as $object) {
            $suggest = isset($suggestions[$object->attribute('name')]) ? $suggestions[$object->attribute('name')] : 'empty';
            $output = new ezcConsoleOutput();
            $opts = new ezcConsoleQuestionDialogOptions();
            $opts->text = "Inserisci id dataset per " . $object->attribute('name');
            $opts->showResults = true;
            $opts->validator = new ezcConsoleQuestionDialogTypeValidator(ezcConsoleQuestionDialogTypeValidator::TYPE_STRING, $suggest);
            $question = new ezcConsoleQuestionDialog( $output, $opts );
            $datasetId = ezcConsoleDialogViewer::displayDialog( $question );
            if ($datasetId !== 'empty'){
                $data[$object->attribute('id')] = $datasetId;
            }
        }

        print_r($data);

        $output = new ezcConsoleOutput();
        $question = ezcConsoleQuestionDialog::YesNoQuestion(
            $output,
            "Salvo i dati?",
            "y"
        );
        if ( ezcConsoleDialogViewer::displayDialog( $question ) == "y" ){
            $jsonStoreData = json_encode($data);
            $data = eZSiteData::fetchByName('ckan_' . $currentSettingsId);
            if (!$data instanceof eZSiteData) {
                eZDB::instance()->query("INSERT INTO ezsite_data ( name, value ) values( 'ckan_$currentSettingsId', '$jsonStoreData' )");
            }else{
                $data->setAttribute('value', $jsonStoreData);
                $data->store();
            }

            $tools->restoreArchivedDatasetIdList();
        }

        print_r($tools->getArchivedDatasetIdList($currentSettingsId));

    } else {


        if (!$options['id']) {
            throw new Exception('Specifica un object id');
        }
        $object = $tools->validateObject($options['id']);

        if ($options['show'] || ( !$options['push'] && !$options['delete'] && !$options['purge'] )) {

            $cli->warning('Dump local dataset data:');
            print_r($tools->getConverter()->getDatasetFromObject($object));
            $cli->notice();

            $cli->warning('Dump remote dataset data:');
            $remote = null;
            $datasetId = $tools->getConverter()->getDatasetId($object);
            if ($datasetId) {
                try {
                    $remote = $tools->getClient()->getDataset($datasetId);
                } catch (Exception $e) {
                    $remote = $e->getMessage();
                }

            }
            print_r($remote);
            $cli->notice();
        }

        if ($options['push']) {
            if (!$options['dry-run']) {
                $tools->pushObject($object);
                $cli->warning('Push OK');
            } else {
                $cli->warning('Ok');
            }
        }

        if ($options['delete']) {
            if (!$options['dry-run']) {
                $tools->deleteObject($object);
                $cli->warning('Delete OK');
            } else {
                $cli->warning('Ok');
            }
        }

        if ($options['purge']) {
            if (!$options['dry-run']) {
                $tools->deleteObject($object, true);
                $cli->warning('Purge OK');
            } else {
                $cli->warning('Ok');
            }
        }

        if ($options['unlink']) {
            if (!$options['dry-run']) {
                $tools->getConverter()->markObjectDeleted($object, null);
                $cli->warning('Purge OK');
            } else {
                $cli->warning('Ok');
            }
        }
    }


    $script->shutdown();
} catch (Exception $e) {
    $errCode = $e->getCode();
    $errCode = $errCode != 0 ? $errCode : 1;
    $script->shutdown($errCode, $e->getMessage());
}
