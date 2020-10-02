<?php

$Module = $Params['Module'];
$http = eZHTTPTool::instance();
$repositoryIdentifier = $Params['Repository'];
$Debug = isset($_GET['debug']);

try {

    $repository = OCCustomSearchableRepositoryProvider::instance()->provideRepository($repositoryIdentifier);

    if ($http->hasGetVariable('q')) {

        $builder = new OCCustomSearchableQueryBuilder($repository);
        $queryBuilded = $builder->instanceQuery(urldecode($http->getVariable('q')));
        $parameters = $queryBuilded->convert();


    } else {
        $parameters = OCCustomSearchParameters::instance();

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
    }

    $data = $repository->find($parameters);
    if ($http->hasGetVariable('q')) {
        $data['query'] = (string)$queryBuilded;
    }
} catch (Exception $e) {
    $data = array(
        'error_code' => $e->getCode(),
        'error_message' => $e->getMessage()
    );
    if ($http->hasGetVariable('q')) {
        $data['query'] = urldecode($http->getVariable('q'));
    }
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
