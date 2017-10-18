<?php
require 'autoload.php';

$script = eZScript::instance(array(
    'description' => ( "Ckan organization tools\n\n" ),
    'use-session' => false,
    'use-modules' => true,
    'use-extensions' => true
));

$script->startup();

$options = $script->getOptions(
    '[show][push][delete][purge][refresh_internal_id][unlink]',
    '',
    array(
        'show' => 'Mostra le info sull\'organizzazione',
        'push' => 'Salva o marca come \'active\' l\'organizzazione in Ckan',
        'unlink' => 'Elimina il riferimento al Ckan remoto',
        'delete' => 'Marca l\'organizzazione come \'deleted\' in Ckan',
        'purge' => 'Elimina l\'organizzazione in Ckan',
        'refresh_internal_id' => "Risalva l'id remoto in locale cercando l'organizzazione in base al nome"
    )
);
$script->initialize();
$script->setUseDebugAccumulators(true);

$cli = eZCLI::instance();

try {

    $tools = new OCOpenDataTools();
    $data = $tools->getOrganizationBuilder()->build();

    if ($options['refresh_internal_id']) {

        try {
            $tools = new OCOpenDataTools();
            $data = $tools->getOrganizationBuilder()->build();
            $previousId = $tools->getOrganizationBuilder()->getStoresOrganizationId();
            $remote = $tools->getClient()->getOrganization($data->name);
            $tools->getOrganizationBuilder()->storeOrganizationPushedId($remote);
            $newId = is_array($remote) ? $remote['id'] : $remote->id;
            $cli->warning('OK: ' . $previousId . ' -> ' . $newId);

        } catch (Exception $e) {
            $cli->error($e->getMessage());
        }

    } else {

        if ($options['show'] || ( !$options['push'] && !$options['delete'] && !$options['purge'] )) {
            $cli->warning('Dump connector settings: ');
            print_r($tools->getSettings());
            $cli->notice();

            $cli->warning('Organization stored ID: ', false);
            $cli->notice($tools->getOrganizationBuilder()->getStoresOrganizationId());
            $cli->notice();

            $cli->warning('Dump local organization data:');
            print_r($data);
            $cli->notice();

            $cli->warning('Dump remote organization data:');
            $remote = null;
            if ($data->name !== null) {
                try {
                    $remote = $tools->getClient()->getOrganization($data->name);
                    $packageIdList = array();
                    foreach ($remote->packages as $package) {
                        $packageIdList[] = $package['id'];
                    }
                    $remote->packages = $packageIdList;
                } catch (Exception $e) {
                    $remote = $e->getMessage();
                }

            }
            print_r($remote);
            $cli->notice();
        }

        if ($options['push']) {
            $tools->pushOrganization();
            $cli->warning('Push OK');
        }

        if ($options['delete']) {
            $tools->deleteOrganization();
            $cli->warning('Delete OK');
        }

        if ($options['purge']) {
            $tools->deleteOrganization(true);
            $cli->warning('Purge OK');
        }

        if ($options['unlink']) {
            $tools->getOrganizationBuilder()->removeStoresOrganizationId();
            $cli->warning('Unlink OK');
        }
    }

    $script->shutdown();
} catch (Exception $e) {
    $errCode = $e->getCode();
    $errCode = $errCode != 0 ? $errCode : 1;
    $script->shutdown($errCode, $e->getMessage());
}
