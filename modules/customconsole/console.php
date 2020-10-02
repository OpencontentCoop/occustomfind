<?php

$Module = $Params['Module'];
$http = eZHTTPTool::instance();
$Debug = isset( $_GET['debug'] );
$repositoryIdentifier = $Params['Repository'];

$error = false;
$fields = array();

try {
    $repository = OCCustomSearchableRepositoryProvider::instance()->provideRepository($repositoryIdentifier);
} catch (Exception $e) {
    $error = $e->getMessage();
}
