<?php
require 'autoload.php';

$script = eZScript::instance(array(
    'description' => ( "Custom reindex\n\n" ),
    'use-session' => false,
    'use-modules' => true,
    'use-extensions' => true
));

$script->startup();

$options = $script->getOptions(
    '[repository:][list]',
    '',
    array(
        'repository' => "Esegue il reindex per i repository indicati (separati da virgola)",
        'list' => "Mostra l'elenco dei repository",
    )
);
$script->initialize();
$script->setUseDebugAccumulators(true);

$cli = eZCLI::instance();
$output = new ezcConsoleOutput();

try {

    $allRepository = eZINI::instance('occustomfind.ini')->variable('Settings', 'AvailableRepositories');

    if ($options['list']) {
        foreach ($allRepository as $repositoryIdentifier => $repositoryName) {
            $cli->notice($repositoryIdentifier . ' ', false);
            if (!class_exists($repositoryName)) {
                $cli->error("Class $repositoryName not found");
            }else{
                $cli->output($repositoryName);
            }
        }
    }else {
        $selectedRepository = $options['repository'] ? explode(',', $options['repository']) : array();

        foreach ($allRepository as $repositoryName) {

            if (!class_exists($repositoryName)) {
                $cli->error("Class $repositoryName not found");
                continue;
            }

            /** @var OCCustomSearchableRepositoryInterface $repository */
            $repository = new $repositoryName;
            $reindex = true;
            if (!empty( $selectedRepository ) && !in_array($repository->getIdentifier(), $selectedRepository)) {
                $reindex = false;
            }

            if ($reindex) {
                $cli->warning('Truncate repository ' . $repository->getIdentifier());
                $repository->truncate();
            }
        }
    }

    $script->shutdown();
} catch (Exception $e) {
    $errCode = $e->getCode();
    $errCode = $errCode != 0 ? $errCode : 1;
    $script->shutdown($errCode, $e->getMessage());
}
