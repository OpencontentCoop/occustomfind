<?php

$Module = $Params['Module'];
$http = eZHTTPTool::instance();
$Debug = isset($_GET['debug']);
$repositoryIdentifier = $Params['Repository'];

try {
    $repository = OCCustomSearchableRepositoryProvider::instance()->provideRepository($repositoryIdentifier);

    if ($http->hasVariable('q')) {

        $builder = new OCCustomSearchableQueryBuilder($repository);
        $query = urldecode($http->variable('q'));
        $queryBuilded = $builder->instanceQuery($query);
        $parameters = $queryBuilded->convert();
    } else {

        $parameters = OCCustomSearchParameters::instance();

        if ($http->hasVariable('filters')) {
            $parameters->setFilters($http->variable('filters'));
        }
    }

    if ($http->hasVariable('search')) {
        $search = $http->variable('search');
        if (!empty($search['value'])) {
            $parameters->setQuery($search['value']);
        }
    }

    if ($http->hasGetVariable('sort')) {
        $parameters->setSort($http->getVariable('sort'));
    }

    $parameters->setLimit(100);
    $parameters->setOffset(0);

    $searchResults = $repository->find($parameters);

    if ($repository instanceof OCCustomSearchableRepositoryCSVExportCapable){
        $headers = $repository->getCsvHeaders();
    }else {
        $headers = [];
        foreach ($repository->getFields() as $field) {
            $headers[] = $field->getName();
        }
    }

    ob_end_clean();

    $filename = $repository->getIdentifier() . '.csv';
    header('X-Powered-By: eZ Publish');
    header('Content-Description: File Transfer');
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=$filename");
    header("Pragma: no-cache");
    header("Expires: 0");

    $count = $searchResults['totalCount'];
    $length = 50;

    $parameters->setLimit($length);
    $parameters->setOffset(0);

    $output = fopen('php://temp', 'w+');
    fputcsv($output, $headers);
    do {
        $items = $repository->find($parameters);
        foreach ($items['searchHits'] as $item) {
            if ($item instanceof OCCustomSearchableObjectCSVExportCapable){
                fputcsv($output, array_values($item->toCsv()));
            }else {
                fputcsv($output, array_values($item->toArray()));
            }
        }
        $parameters->setOffset($parameters->getOffset()+$length);
    } while ($items['totalCount'] == $length);

    $stat = fstat($output);
    ftruncate($output, $stat['size']-1);

    fseek($output, 0);
    echo stream_get_contents($output);

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
    header('Content-Type: application/json');
    echo json_encode($data);
}

eZExecution::cleanExit();
