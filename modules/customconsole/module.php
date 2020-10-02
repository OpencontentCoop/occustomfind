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

$allRepository = OCCustomSearchableRepositoryProvider::instance()->provideRepositories();
$repositoryList = array();
foreach ($allRepository as $repository) {
    $presetList[$repository->getIdentifier()] = array('Name' => get_class($repository), 'value' => $repository->getIdentifier());
}
$FunctionList['console'] = array(
    'RepositoryList' => array(
        'name' => 'RepositoryList',
        'values' => $repositoryList
    )
);

