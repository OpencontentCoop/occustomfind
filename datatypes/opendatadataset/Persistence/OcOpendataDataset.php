<?php

class OcOpendataDataset extends eZPersistentObject
{
    public static function definition()
    {
        return array(
            'fields' => array(
                'repository' => array(
                    'name' => 'repository',
                    'datatype' => 'string',
                    'default' => null,
                    'required' => true
                ),
                'guid' => array(
                    'name' => 'guid',
                    'datatype' => 'string',
                    'default' => null,
                    'required' => true
                ),
                'created_at' => array(
                    'name' => 'created_at',
                    'datatype' => 'integer',
                    'default' => time(),
                    'required' => false
                ),
                'modified_at' => array(
                    'name' => 'modified_at',
                    'datatype' => 'integer',
                    'default' => time(),
                    'required' => false
                ),
                'creator' => array(
                    'name' => 'creator',
                    'datatype' => 'integer',
                    'default' => 0,
                    'required' => false
                ),
                'data' => array(
                    'name' => 'data',
                    'datatype' => 'string',
                    'default' => null,
                    'required' => false
                ),
            ),
            'keys' => array('repository', 'guid'),
            'class_name' => 'OcOpendataDataset',
            'name' => 'ocopendatadataset',
            'function_attributes' => array()
        );
    }
}