<?php
/** @var array $Params */
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

        $parameters->setLimit(0);
        $parameters->setOffset(0);
    }

    $statFields = $statFacets = [];
    if ($http->hasGetVariable('fields')) {
        $statFields = (array)$http->getVariable('fields');
        $statFacets = $http->hasGetVariable('facets') ? (array)$http->getVariable('facets') : [];
    }
    $calcField = false;
    if ($http->hasGetVariable('field')) {
        $statFields[] = $http->getVariable('field');
        $calcField = $http->getVariable('field');
    }
    if (!empty($statFields)) {
        $parameters->setStats(['fields' => $statFields, 'facets' => $statFacets]);
    }

    $searchResults = $repository->find($parameters);
    $total = (int)$searchResults['totalCount'];
    $stats = isset($searchResults['stats']) ? $searchResults['stats'] : [];

    $data['count'] = '?';
    if ($calcField){
        $calcValue = $http->hasGetVariable('stat') ? $http->getVariable('stat') : 'count';
        if (isset($stats[$calcField][$calcValue])){
            $statValue = $stats[$calcField][$calcValue];
            if (is_float($statValue)){
                $statValue = number_format($statValue, 2, ',', '.');
            }
            $data['count'] = $statValue;
        }
    }else{
        $data['count'] = $total;
    }

    if ($http->hasGetVariable('show_stats')) {
        $data['stats'] = $stats;
    }

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
