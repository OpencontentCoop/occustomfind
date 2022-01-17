<?php

/** @var eZModule $Module */
$Module = $Params['Module'];
$id = (int)$Params['Attribute'];

$objectId = (int)OpendataDatasetImporterRegistry::removeScheduledImport($id);
if ($objectId > 0){
    $Module->redirectTo('/openpa/object/' . $objectId);
    return;
}else{
    return $Module->handleError( eZError::KERNEL_NOT_FOUND, 'kernel' );
}
