<?php

interface OCCustomSearchableRepositoryProviderInterface
{
    /**
     * @return OCCustomSearchableRepositoryInterface[]
     */
    public function provideRepositories();
}