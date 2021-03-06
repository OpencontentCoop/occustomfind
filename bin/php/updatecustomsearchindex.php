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
    '[repository:][clean][list]',
    '',
    array(
        'repository' => "Esegue il reindex per i repository indicati (separati da virgola)",
        'clean' => "Esegue il truncate del repository",
        'list' => "Mostra l'elenco dei repository",
    )
);
$script->initialize();
$script->setUseDebugAccumulators(true);

$cli = eZCLI::instance();
$output = new ezcConsoleOutput();

try {

    $allRepository = eZINI::instance('occustomfind.ini')->variable('Settings', 'AvailableRepositories');
    $errors = array();
    
    if ($options['list']) {
        foreach ($allRepository as $repositoryIdentifier => $repositoryName) {
            $cli->notice($repositoryIdentifier . ' ', false);
            if (!class_exists($repositoryName)) {
                $cli->error("Class $repositoryName not found");
            }else{
                $cli->notice($repositoryName);
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
                if ($options['clean']) {
                    $cli->warning('Truncate repository ' . $repository->getIdentifier());
                    $repository->truncate();
                }

                $processLength = $repository->countSearchableObjects();
                $cli->warning('Reindex ' . $processLength . '  object of repository ' . $repository->getIdentifier());

                if ($processLength > 0) {
                    $percentageAdvancementStep = 100 / $processLength;
                } else {
                    $percentageAdvancementStep = 0;
                }

                $progressBarOptions = array(
                    'emptyChar' => ' ',
                    'barChar' => '='
                );
                $progressBar = new ezcConsoleProgressbar($output, $processLength, $progressBarOptions);
                $progressBar->start();
                $length = 50;
                $offset = 0;
                do {
                    $items = $repository->fetchSearchableObjectList($length, $offset);

                    foreach ($items as $item) {
                        $progressBar->advance();
                        if (!$repository->index($item)){
                            $errors[$repository->getIdentifier()][] = $item;
                        }
                    }

                    $offset += $length;
                } while (count($items) == $length);

                $progressBar->finish();
                $cli->notice();
            }
        }
    }

    foreach($errors as $identifier => $items){
        $cli->error("Errors indexing $identifier:");
        /** @var OCCustomSearchableObjectInterface $item */
        foreach($items as $item){
            $cli->error("  " . $item->getGuid());
        }

    }

    $script->shutdown();
} catch (Exception $e) {
    $errCode = $e->getCode();
    $errCode = $errCode != 0 ? $errCode : 1;
    $script->shutdown($errCode, $e->getMessage());
}
