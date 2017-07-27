<?php

$Module = $Params['Module'];
$http = eZHTTPTool::instance();
$repositoryIdentifier = $Params['Repository'];
$Debug = isset( $_GET['debug'] );
$parameters = OCCustomSearchParameters::instance();
try {

    $allRepository = eZINI::instance('occustomfind.ini')->variable('Settings', 'AvailableRepositories');
    if (isset( $allRepository[$repositoryIdentifier] )) {
        $repositoryClass = $allRepository[$repositoryIdentifier];
        /** @var OCCustomSearchableRepositoryInterface $repository */
        $repository = new $repositoryClass;

        if ($http->hasGetVariable('query')) {
            $parameters->setQuery($http->getVariable('query'));
        }

        if ($http->hasGetVariable('filters')) {
            $parameters->setFilters($http->getVariable('filters'));
        }

        if ($http->hasGetVariable('facets')) {
            $facets = array();
            $requestFacets = $http->getVariable('facets');
            foreach ($requestFacets as $facet) {
                $facets[] = array('field' => $facet);
            }
            $parameters->setFacets($facets);
        }

        if ($http->hasGetVariable('sort')) {
            $parameters->setSort($http->getVariable('sort'));
        }

        if ($http->hasGetVariable('limit')) {
            $parameters->setLimit($http->getVariable('limit'));
        }

        if ($http->hasGetVariable('offset')) {
            $parameters->setOffset($http->getVariable('offset'));
        }

        $data = $repository->find($parameters);

    } else {
        throw new Exception("Repository $repositoryIdentifier non found");
    }
} catch (Exception $e) {
    $data = array(
        'error_code' => $e->getCode(),
        'error_message' => $e->getMessage()
    );
    if ($Debug) {
        $data['file'] = $e->getFile();
        $data['line'] = $e->getLine();
        $data['trace'] = $e->getTraceAsString();
    }
}

if ($Debug) {
    echo '<pre>';
    print_r($parameters);
    print_r($data);
    echo '</pre>';
    eZDisplayDebug();
} else {
    header('Content-Type: application/json');
    echo json_encode($data);
}

eZExecution::cleanExit();
