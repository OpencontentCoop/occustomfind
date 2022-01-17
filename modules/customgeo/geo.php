<?php

use Opencontent\Opendata\GeoJson\FeatureCollection;
use Opencontent\Opendata\GeoJson\Feature;
use Opencontent\Opendata\GeoJson\Geometry;
use Opencontent\Opendata\GeoJson\Properties;

$Module = $Params['Module'];
$http = eZHTTPTool::instance();
$Debug = isset($_GET['debug']);
$repositoryIdentifier = $Params['Repository'];
$data = [];
try {
    $repository = OCCustomSearchableRepositoryProvider::instance()->provideRepository($repositoryIdentifier);

    $geoPointField = false;
    foreach ($repository->getFields() as $field){
        if ($field->getType() === 'geopoint'){
            $geoPointField = $field;
            break;
        }
    }
    if ($geoPointField) {
        if ($http->hasVariable('q') && !empty($http->variable('q'))) {

            $builder = new OCCustomSearchableQueryBuilder($repository);
            $query = urldecode($http->variable('q'));
            $queryBuilt = $builder->instanceQuery($query);
            $parameters = $queryBuilt->convert();

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
        $parameters->addRawFilter($geoPointField->getSolrName() . ':[-90,-90 TO 90,90]');
        $parameters->setLimit(100000);

        $searchResults = $repository->find($parameters);
        $data = new FeatureCollection();
        /** @var OpendataDatasetSearchableObject $result */
        foreach ($searchResults['searchHits'] as $result){
            $properties = [];
            $geometry = new Geometry();
            $geometry->type = 'Point';
            list($longitude, $latitude) = explode(',', $result->getFieldValue($geoPointField));
            $geometry->coordinates = [$longitude, $latitude];
            $data->add(
                new Feature($result->getGuid(), $geometry, new Properties($result->toArray()))
            );
        }
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
