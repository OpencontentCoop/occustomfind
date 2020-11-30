<?php

/** @var eZModule $Module */
$Module = $Params['Module'];
$id = (int)$Params['Attribute'];

header('Content-Type: application/json');
header( 'HTTP/1.1 200 OK' );
echo json_encode(OpendataDatasetCsvImporter::hasPendingImport($id));
eZExecution::cleanExit();
