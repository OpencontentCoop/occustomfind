<?php

/** @var eZModule $Module */
$Module = $Params['Module'];
$id = (int)$Params['Attribute'];

header('Content-Type: application/json');
if (!eZUser::currentUser()->isAnonymous()) {
    header( 'HTTP/1.1 200 OK' );
    echo json_encode(OpendataDatasetImporterRegistry::hasPendingImport($id));
}else{
    header( 'HTTP/1.1 403 Forbidden' );
}
eZExecution::cleanExit();
