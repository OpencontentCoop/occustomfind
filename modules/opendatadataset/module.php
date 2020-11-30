<?php

$Module = array('name' => 'OpenDataDataset');

$ViewList = [];
$ViewList['addrole'] = array(
    'functions' => array('addrole'),
    'script' => 'addrole.php',
    'params' => array()
);
$ViewList['has_pending_action'] = array(
    'functions' => array('has_pending_action'),
    'script' => 'has_pending_action.php',
    'params' => array('Attribute')
);
$FunctionList = [
    'addrole' => [],
    'edit' => [],
    'has_pending_action' => [],
];
