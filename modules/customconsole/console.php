<?php

$Module = $Params['Module'];
$http = eZHTTPTool::instance();
$Debug = isset( $_GET['debug'] );
$repositoryIdentifier = $Params['Repository'];

$error = false;
$fields = array();

try {
    $allRepository = eZINI::instance('occustomfind.ini')->variable('Settings', 'AvailableRepositories');
    if (isset( $allRepository[$repositoryIdentifier] )) {
        $repositoryClass = $allRepository[$repositoryIdentifier];
        /** @var OCCustomSearchableRepositoryInterface $repository */
        $repository = new $repositoryClass;


    } else {
        throw new Exception("Repository $repositoryIdentifier non found");
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}
