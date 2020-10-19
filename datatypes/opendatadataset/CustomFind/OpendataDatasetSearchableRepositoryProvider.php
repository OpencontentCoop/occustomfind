<?php


class OpendataDatasetSearchableRepositoryProvider implements OCCustomSearchableRepositoryProviderInterface
{
    use OpendataDatasetProvider;

    private $repositories;

    public function provideRepositories()
    {
        if ($this->repositories === null) {
            $this->repositories = [];

            foreach ($this->provideDatasetAttributes() as $attributeAndNode) {
                $attribute = $attributeAndNode['attribute'];
                /** @var OpendataDatasetDefinition $definition */
                $definition = $attribute->content();
                if ($definition instanceof OpendataDatasetDefinition && $definition->canRead()) {
                    $repository = new OpendataDatasetSearchableRepository($attribute);
                    $this->repositories[$repository->getIdentifier()] = $repository;
                }
            }
        }

        return $this->repositories;
    }

}