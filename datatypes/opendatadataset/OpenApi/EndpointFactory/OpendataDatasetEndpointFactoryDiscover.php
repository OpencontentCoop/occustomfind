<?php

use Opencontent\OpenApi\EndpointFactory;
use Opencontent\OpenApi\EndpointFactoryCollection;
use Opencontent\OpenApi\EndpointFactoryProvider;
use Opencontent\OpenApi\OperationFactoryCollection;
use Opencontent\OpenApi\StringTools;

class OpendataDatasetEndpointFactoryDiscover extends EndpointFactoryProvider
{
    use OpendataDatasetProvider;

    /**
     * @var OpendataDatasetEndpointFactory[]
     */
    private $endpoints;

    public function getEndpointFactoryCollection()
    {
        if ($this->endpoints === null) {
            $this->endpoints = [];
            $avoidPathDuplication = [];
            foreach ($this->provideDatasetAttributes() as $attributeAndNode){
                $node = $attributeAndNode['node'];
                $attribute = $attributeAndNode['attribute'];

                $pathArray = strtolower($node->urlAlias());
                $pathParts = explode('/', $pathArray);
                $path = '/dataset/' . array_pop($pathParts);

                if (isset($avoidPathDuplication[$path])){
                    $path .= '-' . $node->attribute('node_id');
                }

                /** @var OpendataDatasetDefinition $definition */
                $definition = $attribute->content();
                if ($definition instanceof OpendataDatasetDefinition && $definition->isApiEnabled()) {
                    $itemName = $definition->getItemName();
                    $itemSlug = StringTools::toCamelCase(
                        eZCharTransform::instance()->transformByGroup($itemName, 'identifier'),
                    false
                    );

                    $this->endpoints[] = (new OpendataDatasetEndpointFactory($attribute))
                        ->setPath($path)
                        ->addTag('datasets')
                        ->setOperationFactoryCollection(new OperationFactoryCollection([
                            (new OpendataDatasetCreateOperationFactory()),
                            (new OpendataDatasetSearchOperationFactory()),
                        ]));

                    $this->endpoints[] = (new OpendataDatasetEndpointFactory($attribute))
                        ->setPath($path . '/{' . $itemSlug . 'DatasetItemGuid}')
                        ->addTag('datasets')
                        ->setOperationFactoryCollection(new OperationFactoryCollection([
                            (new OpendataDatasetReadOperationFactory()),
                            (new OpendataDatasetUpdateOperationFactory()),
                            (new OpendataDatasetDeleteOperationFactory()),
                        ]));
                }
            }
        }

        return new EndpointFactoryCollection($this->endpoints);
    }

}