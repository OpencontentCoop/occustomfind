<?php

$Module = array( 'name' => 'OC Custom Count',
                 'function' => array(
                     'functions' => array('count'),
                     'script' => 'count.php',
                     'params' => array('Repository'),
                     'unordered_params' => array()
                 )
);

$ViewList = array();

$FunctionList = array();
$FunctionList['count'] = array();

$allRepository = OCCustomSearchableRepositoryProvider::instance()->provideRepositories();
$repositoryList = array();
foreach ($allRepository as $repository) {
    $presetList[$repository->getIdentifier()] = array('Name' => get_class($repository), 'value' => $repository->getIdentifier());
}
$FunctionList['count'] = array(
    'RepositoryList' => array(
        'name' => 'RepositoryList',
        'values' => $repositoryList
    )
);
