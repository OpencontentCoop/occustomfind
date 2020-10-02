<?php


class OpendataDatasetSearchableRepositoryProvider implements OCCustomSearchableRepositoryProviderInterface
{
    private $repositories;

    public function provideRepositories()
    {
        if ($this->repositories === null) {
            $this->repositories = [];
            $datasetClasses = array_column(
                eZDB::instance()->arrayQuery("SELECT ezcontentclass.identifier
                                         FROM ezcontentclass, ezcontentclass_attribute
                                         WHERE ezcontentclass.id = ezcontentclass_attribute.contentclass_id AND
                                               ezcontentclass.version = " . eZContentClass::VERSION_STATUS_DEFINED . " AND
                                               ezcontentclass_attribute.version = 0 AND
                                               ezcontentclass_attribute.data_type_string = '" . OpendataDatasetType::DATA_TYPE_STRING . "'"),
                'identifier'
            );

            /** @var eZContentObjectTreeNode[] $nodes */
            $nodes = eZContentObjectTreeNode::subTreeByNodeID([
                'ClassFilterType' => 'include',
                'ClassFilterArray' => $datasetClasses,
            ], 1);

            foreach ($nodes as $node){
                $dataMap = $node->dataMap();
                foreach ($dataMap as $attribute){
                    if ($attribute->attribute('data_type_string') == OpendataDatasetType::DATA_TYPE_STRING){
                        /** @var OpendataDatasetDefinition $definition */
                        $definition = $attribute->content();
                        if ($definition instanceof OpendataDatasetDefinition && $definition->canRead()) {
                            $repository = new OpendataDatasetSearchableRepository($attribute);
                            $this->repositories[$repository->getIdentifier()] = $repository;
                        }
                    }
                }
            }
        }

        return $this->repositories;
    }

}