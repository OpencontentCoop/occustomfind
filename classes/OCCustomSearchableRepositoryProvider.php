<?php

class OCCustomSearchableRepositoryProvider
{
    private static $instance;

    /**
     * @var OCCustomSearchableRepositoryInterface[]
     */
    private $repositories;

    final public static function instance()
    {
        if (self::$instance === null){
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
    }

    /**
     * @return OCCustomSearchableRepositoryInterface[]
     */
    public function provideRepositories()
    {
        if ($this->repositories === null){
            $repositoryIdentifierList = eZINI::instance('occustomfind.ini')->variable('Settings', 'AvailableRepositories');
            foreach ($repositoryIdentifierList as $repositoryIdentifier => $repositoryClass){
                $this->repositories[$repositoryIdentifier] = new $repositoryClass;
            }

            if (eZINI::instance('occustomfind.ini')->hasVariable('Settings', 'RepositoryProviders')) {
                $repositoryProviderList = eZINI::instance('occustomfind.ini')->variable('Settings', 'RepositoryProviders');
                foreach ($repositoryProviderList as $repositoryProviderClass){
                    if (class_exists($repositoryProviderClass)) {
                        $provider = new $repositoryProviderClass();
                        if ($provider instanceof OCCustomSearchableRepositoryProviderInterface) {
                            foreach ($provider->provideRepositories() as $repository) {
                                $this->repositories[$repository->getIdentifier()] = $repository;
                            }
                        }
                    }
                }
            }
        }

        return $this->repositories;
    }

    public function provideRepository($repositoryIdentifier)
    {
        $this->provideRepositories();
        if (isset($this->repositories[$repositoryIdentifier])){
            return $this->repositories[$repositoryIdentifier];
        }

        throw new Exception("Repository $repositoryIdentifier non found");
    }
}