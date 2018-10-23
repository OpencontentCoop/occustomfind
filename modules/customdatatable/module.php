<?php

$Module = array( 'name' => 'OC Custom Datatable',
                 'function' => array(
                     'functions' => array('datatable'),
                     'script' => 'datatable.php',
                     'params' => array('Repository'),
                     'unordered_params' => array()
                 )
);

$ViewList = array();

$FunctionList = array();
$FunctionList['datatable'] = array();

$allRepository = eZINI::instance('occustomfind.ini')->variable('Settings', 'AvailableRepositories');
$repositoryList = array();
foreach ($allRepository as $repositoryIdentifier => $repositoryName) {
    $presetList[$repositoryIdentifier] = array('Name' => $repositoryName, 'value' => $repositoryIdentifier);
}
$FunctionList['datatable'] = array(
    'RepositoryList' => array(
        'name' => 'RepositoryList',
        'values' => $repositoryList
    )
);

