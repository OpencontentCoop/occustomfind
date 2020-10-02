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

$allRepository = OCCustomSearchableRepositoryProvider::instance()->provideRepositories();
$repositoryList = array();
foreach ($allRepository as $repository) {
    $presetList[$repository->getIdentifier()] = array('Name' => get_class($repository), 'value' => $repository->getIdentifier());
}
$FunctionList['datatable'] = array(
    'RepositoryList' => array(
        'name' => 'RepositoryList',
        'values' => $repositoryList
    )
);
