<?php

class OpendataDatasetDBStorage implements OpendataDatasetStorageInterface
{
    public function __construct()
    {
        eZDB::setErrorHandling(eZDB::ERROR_HANDLING_EXCEPTIONS);
    }

    public function updateDataset(OpendataDataset $dataset)
    {
        return $this->createDataset($dataset);
    }

    public function createDataset(OpendataDataset $dataset)
    {
        $row = new OcOpendataDataset([
            'repository' => $this->getRepositoryIdentifier($dataset->getContext()),
            'guid' => $dataset->getGuid(),
            'created_at' => $dataset->getCreatedAt(),
            'modified_at' => $dataset->getModifiedAt(),
            'creator' => $dataset->getCreator(),
            'data' => json_encode($dataset->getData()),
        ]);
        $row->store();

        return $dataset;
    }

    private function getRepositoryIdentifier(eZContentObjectAttribute $attribute)
    {
        return 'dataset-' . $attribute->attribute('contentclass_attribute_identifier') . '-' . $attribute->attribute('contentobject_id');
    }

    public function deleteDataset(OpendataDataset $dataset)
    {
        $row = OcOpendataDataset::fetchObject(OcOpendataDataset::definition(), null, ['repository' => $this->getRepositoryIdentifier($dataset->getContext()), 'guid' => $dataset->getGuid()], true);
        if ($row instanceof OcOpendataDataset) {
            $row->remove();
        }
    }

    public function truncate(eZContentObjectAttribute $context)
    {
        OcOpendataDataset::removeObject(OcOpendataDataset::definition(), ['repository' => $this->getRepositoryIdentifier($context)]);
    }

    public function getDataset($guid, eZContentObjectAttribute $context)
    {
        $row = OcOpendataDataset::fetchObject(OcOpendataDataset::definition(), null, ['repository' => $this->getRepositoryIdentifier($context), 'guid' => $guid], true);
        if ($row instanceof OcOpendataDataset) {
            $data = json_decode($row->attribute('data'), true);
            $dataset = new OpendataDataset($data, $context, $context->content());
            $dataset->setGuid($row->attribute('guid'));
            $dataset->setCreatedAt($row->attribute('created_at'));
            $dataset->setModifiedAt($row->attribute('modified_at'));
            $dataset->setCreator($row->attribute('creator'));

            return $dataset;
        }

        throw new Exception("Dataset $guid not found");
    }

    public function deleteByCreator($creatorId, eZContentObjectAttribute $context)
    {
        OcOpendataDataset::removeObject(OcOpendataDataset::definition(), [
            'repository' => $this->getRepositoryIdentifier($context),
            'creator' => $creatorId
        ]);

        return true;
    }
}