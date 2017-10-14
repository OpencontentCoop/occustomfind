<?php

$Module = array( 'name' => 'OC Custom Index',
                 'function' => array(
                     'functions' => array('index'),
                     'script' => 'index.php',
                     'params' => array('Repository', 'Id'),
                     'unordered_params' => array()
                 )
);

$ViewList = array();

$FunctionList = array();
$FunctionList['index'] = array();

$allRepository = eZINI::instance('occustomfind.ini')->variable('Settings', 'AvailableRepositories');
$repositoryList = array();
foreach ($allRepository as $repositoryIdentifier => $repositoryName) {
    $presetList[$repositoryIdentifier] = array('Name' => $repositoryName, 'value' => $repositoryIdentifier);
}
$FunctionList['index'] = array(
    'RepositoryList' => array(
        'name' => 'RepositoryList',
        'values' => $repositoryList
    )
);

