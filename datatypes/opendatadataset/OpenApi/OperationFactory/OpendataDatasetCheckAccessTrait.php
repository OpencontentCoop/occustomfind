<?php


use Opencontent\Opendata\Api\Exception\ForbiddenException;

trait OpendataDatasetCheckAccessTrait
{
    protected function checkAccess(OpendataDatasetDefinition $definition, $access, $dataset = null)
    {
        switch ($access){
            case 'read':
                if (!$definition->canRead()){
                    throw new ForbiddenException($definition->getItemName(), 'read');
                }
                break;


            case 'edit':
                if (($dataset instanceof OpendataDataset && !$definition->canEditDataset($dataset)) || !$definition->canEdit()){
                    throw new ForbiddenException($definition->getItemName(),'read');
                }
                break;

            case 'delete':
                if ($dataset == null || !$definition->canDeleteDataset($dataset)){
                    throw new ForbiddenException($definition->getItemName(),'read');
                }
                break;
        }
    }
}