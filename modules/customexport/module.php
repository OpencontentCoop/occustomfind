<?php

$Module = array( 'name' => 'OC Custom Export',
                 'function' => array(
                     'functions' => array('export'),
                     'script' => 'export.php',
                     'params' => array('Repository'),
                     'unordered_params' => array()
                 )
);

$ViewList = array();

$FunctionList = array();
$FunctionList['export'] = array();

$allRepository = OCCustomSearchableRepositoryProvider::instance()->provideRepositories();
$repositoryList = array();
foreach ($allRepository as $repository) {
    $presetList[$repository->getIdentifier()] = array('Name' => get_class($repository), 'value' => $repository->getIdentifier());
}
$FunctionList['export'] = array(
    'RepositoryList' => array(
        'name' => 'RepositoryList',
        'values' => $repositoryList
    )
);
