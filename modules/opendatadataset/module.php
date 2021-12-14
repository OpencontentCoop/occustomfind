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
$ViewList['has_scheduled_action'] = array(
    'functions' => array('edit'),
    'script' => 'has_scheduled_action.php',
    'params' => array('Attribute')
);
$ViewList['remove_scheduled_import'] = array(
    'functions' => array('edit'),
    'script' => 'remove_scheduled_import.php',
    'params' => array('Attribute')
);
$FunctionList = [
    'addrole' => [],
    'edit' => [],
    'has_pending_action' => [],
];
