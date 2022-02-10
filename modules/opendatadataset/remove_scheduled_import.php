<?php

/** @var eZModule $Module */
$Module = $Params['Module'];
$id = (int)$Params['Attribute'];

$import = OpendataDatasetImporterRegistry::fetchScheduledImport($id);
if ($import instanceof SQLIScheduledImport) {
    $options = $import->getOptions();
    $objectId = (int)$options->object_id;
    $object = eZContentObject::fetch($objectId);
    if ($object instanceof eZContentObject && $object->canEdit()) {
        OpendataDatasetImporterRegistry::removeScheduledImport($id);
        $Module->redirectTo('/openpa/object/' . $objectId);
        return;
    } else {
        return $Module->handleError(eZError::KERNEL_ACCESS_DENIED, 'kernel');
    }
} else {
    return $Module->handleError(eZError::KERNEL_NOT_FOUND, 'kernel');
}
