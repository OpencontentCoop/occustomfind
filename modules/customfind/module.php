<?php

$Module = array( 'name' => 'OC Custom Find',
                 'function' => array(
                     'functions' => array('find'),
                     'script' => 'find.php',
                     'params' => array('Repository'),
                     'unordered_params' => array()
                 )
);

$ViewList = array();

$FunctionList = array();
$FunctionList['find'] = array();

$allRepository = eZINI::instance('occustomfind.ini')->variable('Settings', 'AvailableRepositories');
$repositoryList = array();
foreach ($allRepository as $repositoryIdentifier => $repositoryName) {
    $presetList[$repositoryIdentifier] = array('Name' => $repositoryName, 'value' => $repositoryIdentifier);
}
$FunctionList['find'] = array(
    'RepositoryList' => array(
        'name' => 'RepositoryList',
        'values' => $repositoryList
    )
);

