<?php

/** @var eZModule $Module */
$Module = $Params['Module'];

OpendataDatasetDefinition::getRole();
$readRole = OpendataDatasetDefinition::getReadRole();
$defaultUserPlacement = eZContentObjectTreeNode::fetch((int)eZINI::instance()->variable("UserSettings", "DefaultUserPlacement"));
if ($defaultUserPlacement instanceof eZContentObjectTreeNode) {
    $readRole->assignToUser($defaultUserPlacement->attribute('contentobject_id'));
}
$readRole->assignToUser((int)eZINI::instance()->variable("UserSettings", "AnonymousUserID"));

$Module->redirectTo('/');
return;