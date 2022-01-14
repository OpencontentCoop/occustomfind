<?php

$Module = $Params['Module'];
$http = eZHTTPTool::instance();
$repositoryIdentifier = $Params['Repository'];
$Debug = isset($_GET['debug']);

try {

    $repository = OCCustomSearchableRepositoryProvider::instance()->provideRepository($repositoryIdentifier);

    if ($http->hasVariable('q')) {
        $builder = new OCCustomSearchableQueryBuilder($repository);
        $query = urldecode($http->variable('q'));
        $queryBuilded = $builder->instanceQuery($query);
        $parameters = $queryBuilded->convert();
    } else {
        $parameters = OCCustomSearchParameters::instance();
        if ($http->hasGetVariable('query')) {
            $parameters->setQuery($http->getVariable('query'));
        }
        if ($http->hasGetVariable('filters')) {
            $parameters->setFilters($http->variable('filters'));
        }
    }

    if ($http->hasGetVariable('startDateField')) {
        $startDateField = $http->getVariable('startDateField');
    }
    if ($http->hasGetVariable('endDateField')) {
        $endDateField = $http->getVariable('endDateField');
    }
    if ($http->hasGetVariable('start')) {
        $start = new \DateTime($http->getVariable('start'), new \DateTimeZone('UTC'));
        if (!$start instanceof \DateTime) {
            throw new Exception("Problem with date format");
        }
        $start = '"' . ezfSolrDocumentFieldBase::convertTimestampToDate($start->format('U')) . '"';
    }
    if ($http->hasGetVariable('end')) {
        $end = new \DateTime($http->getVariable('end'), new \DateTimeZone('UTC'));
        if (!$end instanceof \DateTime) {
            throw new Exception("Problem with date format");
        }
        $end = '"' . ezfSolrDocumentFieldBase::convertTimestampToDate($end->format('U')) . '"';
    }

    $data = [];
    if (isset($startDateField, $start, $end)) {
        $startDateFormat = false;
        if ($http->hasGetVariable('startDateFormat')) {
            $startDateFormat = OCCustomSearchableField::convertMomentFormatToPhp($http->getVariable('startDateFormat'));
        }

        $endDateFormat = false;
        if (isset($endDateField) && $http->hasGetVariable('endDateFormat')){
            $endDateFormat = OCCustomSearchableField::convertMomentFormatToPhp($http->getVariable('endDateFormat'));
        }

        if (isset($endDateField)) {
            $filters = $parameters->getFilters();
            $filters[100] = [
                'or',
                $startDateField => ['range', [$start, $end]],
                $endDateField => ['range', [$start, $end]],
            ];
            $parameters->setFilters($filters);
        }else {
            $parameters->addFilter($startDateField, ['range', [$start, $end]]);
        }

        $titleField = false;
        if ($http->hasGetVariable('titleField')) {
            $titleField = $http->getVariable('titleField');
        }

        $parameters->setLimit(300);
        $searchResults = $repository->find($parameters);

        /** @var OCCustomSearchableObjectInterface $hit */
        foreach ($searchResults['searchHits'] as $hit) {
            $hitArray = $hit->toArray();
            $start = $hitArray[$startDateField];
            if ($startDateFormat){
                $startDateTime = DateTime::createFromFormat($startDateFormat, trim($hitArray[$startDateField]));
                if ($startDateTime instanceof DateTime){
                    $start = $startDateTime->format('c');
                }
            }
            $allDay = true;
            $item = [
                'id' => $hit->getGuid(),
                'content' => $hit,
                'allDay' => $allDay,
                'title' => $titleField ? $hitArray[$titleField] : $hit->getGuid(),
                'start' => $start,
            ];
            if (isset($endDateField)){
                $end = $hitArray[$endDateField];
                if ($endDateFormat){
                    $endDateTime = DateTime::createFromFormat($endDateFormat, trim($hitArray[$endDateField]));
                    if ($endDateTime instanceof DateTime){
                        $end = $endDateTime->format('c');
                    }
                }
                $item['end'] = $end;
                $item['allDay'] = false;
            }
            $data[] = $item;
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
