<?php

$Module = $Params['Module'];
$http = eZHTTPTool::instance();
$Debug = isset( $_GET['debug'] );
$repositoryIdentifier = $Params['Repository'];

try {

    $allRepository = eZINI::instance('occustomfind.ini')->variable('Settings', 'AvailableRepositories');
    if (isset( $allRepository[$repositoryIdentifier] )) {
        $repositoryClass = $allRepository[$repositoryIdentifier];
        /** @var OCCustomSearchableRepositoryInterface $repository */
        $repository = new $repositoryClass;

        if ($http->hasVariable('q')) {

            $builder = new OCCustomSearchableQueryBuilder($repository);  
            $query = urldecode($http->variable('q'));            
            $queryBuilded = $builder->instanceQuery($query);
            $parameters = $queryBuilded->convert();
        }else{

            $parameters = OCCustomSearchParameters::instance();

            if ($http->hasVariable('filters')) {
                $parameters->setFilters($http->variable('filters'));
            }

            if ($http->hasVariable('facets')) {
                $facets = array();
                $requestFacets = $http->variable('facets');
                foreach ($requestFacets as $facet) {
                    $facets[] = array('field' => $facet);
                }
                $parameters->setFacets($facets);
            }
        }

        if ($http->hasVariable('columns')) {
            $columns = (array)$http->variable('columns');
        }

        if ($http->hasVariable('search')) {
            $search = $http->variable('search');
            if (!empty($search['value'])){
                $parameters->setQuery($search['value']);
            }
        }
        
        if ($http->hasVariable('length')) {
            $limit = (int)$http->variable('length');
            $parameters->setLimit($limit);
        }

        if ($http->hasVariable('start')) {
            $offset = (int)$http->variable('start');
            $parameters->setOffset($offset);
        }

        if ($http->hasVariable('order') && $http->hasVariable('columns')) {
            $order = (array)$http->variable('order');
            $sort = array();
            foreach( $order as $orderParam ){
                $column = $columns[$orderParam['column']];            
                if ( $column['orderable'] == 'true' || $column['orderable'] === true ){                
                    $sort[$column['name']] = $orderParam['dir'];
                }
            }
            $parameters->setSort($sort);
        }

        $searchResults = $repository->find($parameters);

        $facets = array();
        if (isset($searchResults['facets'])){
            foreach ($searchResults['facets'] as $key => $value) {
                $facets[] = array(
                    'name' => $key,
                    'data' => $value
                );
            }
        }

        $data = array(            
            'draw' => isset($_GET['draw']) ? ++$_GET['draw'] : 0,
            'recordsTotal' => (int)$searchResults['totalCount'],
            'recordsFiltered' => (int)$searchResults['totalCount'],
            'data' => $searchResults['searchHits'],
            'facets' => $facets,
            'params' => $parameters
        );
        if ($http->hasVariable('q')) {            
            $data['query'] = $query;
        }

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
