<?php

use Opencontent\OpenApi\EndpointFactory;

class OpendataDatasetEndpointFactory extends EndpointFactory
{
    /**
     * @var eZContentObjectAttribute
     */
    private $attribute;

    /**
     * @var OpendataDatasetDefinition
     */
    private $definition;

    public function __construct(eZContentObjectAttribute $attribute)
    {
        $this->attribute = $attribute;
        $this->definition = $this->attribute->content();
    }

    public function provideSchemaFactories()
    {
        return [new OpendataDatasetSchemaFactory($this->definition)];
    }

    /**
     * @return eZContentObjectAttribute
     */
    public function getAttribute()
    {
        return $this->attribute;
    }

    public function getDatasetDefinition()
    {
        return $this->definition;
    }

}