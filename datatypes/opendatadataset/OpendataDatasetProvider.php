<?php

trait OpendataDatasetProvider
{
    private $datasetAttributeList;

    /**
     * @return array(attribute => eZContentObjectAttribute, node => eZContentObjectTreeNode)
     */
    public function provideDatasetAttributes()
    {
        if ($this->datasetAttributeList === null) {
            $this->datasetAttributeList = [];
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
                'Limitation' => array()
            ], 1);

            foreach ($nodes as $node) {
                $dataMap = $node->dataMap();
                foreach ($dataMap as $attribute) {
                    if ($attribute->attribute('data_type_string') == OpendataDatasetType::DATA_TYPE_STRING) {
                        $this->datasetAttributeList[] = [
                            'attribute' => $attribute,
                            'node' => $node,
                        ];
                    }
                }
            }
        }

        return $this->datasetAttributeList;
    }
}