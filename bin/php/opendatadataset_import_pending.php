<?php
require 'autoload.php';

$script = eZScript::instance(array(
    'description' => ("Import csv dat in dataset\n\n"),
    'use-session' => false,
    'use-modules' => true,
    'use-extensions' => true
));

$script->startup();

$options = $script->getOptions( "[id:]", "", ['id' => 'Attribute id'] );
$script->initialize();
$script->setUseDebugAccumulators(true);

OpendataDatasetCsvImporter::executePendingImports($options['id']);

$script->shutdown();