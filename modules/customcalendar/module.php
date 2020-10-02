<?php

$Module = array( 'name' => 'OC Custom Calendar',
                 'function' => array(
                     'functions' => array('calendar'),
                     'script' => 'calendar.php',
                     'params' => array('Repository'),
                     'unordered_params' => array()
                 )
);

$ViewList = array();

$FunctionList = array();
$FunctionList['calendar'] = array();

$allRepository = OCCustomSearchableRepositoryProvider::instance()->provideRepositories();
$repositoryList = array();
foreach ($allRepository as $repository) {
    $presetList[$repository->getIdentifier()] = array('Name' => get_class($repository), 'value' => $repository->getIdentifier());
}
$FunctionList['find'] = array(
    'RepositoryList' => array(
        'name' => 'RepositoryList',
        'values' => $repositoryList
    )
);

