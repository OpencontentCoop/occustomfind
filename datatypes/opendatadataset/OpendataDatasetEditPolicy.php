<?php

/**
 * Chi può gestire le risorse (righe) di un dataset.
 *
 * Oltre ai due casi storici (content/edit sull'oggetto, ruolo "Dataset edit" sul subtree del nodo), un oggetto in stato
 * opencity_lock/locked, che non è più modificabile nella struttura, è gestibile nelle risorse da chi ha la policy
 * opendatadataset/edit.
 */
class OpendataDatasetEditPolicy
{
    const LOCKED_STATE = 'opencity_lock/locked';

    /**
     * Logica pura, senza accesso a eZ.
     *
     * @param bool $objectCanEdit l'utente ha content/edit sull'oggetto
     * @param bool $hasSubtreeRole l'utente ha il ruolo "Dataset edit" sul subtree del nodo
     * @param bool $isLocked l'oggetto è in stato opencity_lock/locked
     * @param bool $hasDatasetEditAccess l'utente ha la policy opendatadataset/edit (senza limitazioni)
     *
     * @return bool
     */
    public static function decide($objectCanEdit, $hasSubtreeRole, $isLocked, $hasDatasetEditAccess)
    {
        return (bool)($objectCanEdit || $hasSubtreeRole || ($isLocked && $hasDatasetEditAccess));
    }

    /**
     * @param eZContentObject $object
     *
     * @return bool
     */
    public static function canEditObject(eZContentObject $object)
    {
        $objectCanEdit = (bool)$object->canEdit();

        $hasSubtreeRole = false;
        if (!$objectCanEdit) {
            $hasSubtreeRole = self::hasSubtreeRole($object);
        }

        $isLocked = in_array(self::LOCKED_STATE, $object->stateIdentifierArray(), true);

        $hasDatasetEditAccess = false;
        if ($isLocked) {
            $access = eZUser::currentUser()->hasAccessTo('opendatadataset', 'edit');
            $hasDatasetEditAccess = isset($access['accessWord']) && $access['accessWord'] === 'yes';
        }

        return self::decide($objectCanEdit, $hasSubtreeRole, $isLocked, $hasDatasetEditAccess);
    }

    private static function hasSubtreeRole(eZContentObject $object)
    {
        $datasetNodeId = (int)$object->mainNodeID();
        if (!$datasetNodeId) {
            return false;
        }

        $role = OpendataDatasetDefinition::getRole();
        $userID = eZUser::currentUserID();
        $node = eZContentObjectTreeNode::fetch($datasetNodeId, false, false);
        $limitIdent = 'Subtree';
        $limitValue = $node['path_string'];
        $query = "SELECT * FROM ezuser_role WHERE role_id='$role->ID' AND contentobject_id='$userID' AND limit_identifier='$limitIdent' AND limit_value='$limitValue'";
        $rows = eZDB::instance()->arrayQuery($query);

        return count($rows) > 0;
    }
}
