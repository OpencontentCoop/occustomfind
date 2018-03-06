<?php

$Module = array( 'name' => 'OC Custom Console',
                 'function' => array(
                     'functions' => array('console'),
                     'script' => 'console.php',
                     'params' => array('Repository'),
                     'unordered_params' => array()
                 )
);

$ViewList = array();

$FunctionList = array();
$FunctionList['console'] = array();

$allRepository = eZINI::instance('occustomfind.ini')->variable('Settings', 'AvailableRepositories');
$repositoryList = array();
foreach ($allRepository as $repositoryIdentifier => $repositoryName) {
    $presetList[$repositoryIdentifier] = array('Name' => $repositoryName, 'value' => $repositoryIdentifier);
}
$FunctionList['console'] = array(
    'RepositoryList' => array(
        'name' => 'RepositoryList',
        'values' => $repositoryList
    )
);

