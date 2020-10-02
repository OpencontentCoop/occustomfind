<?php

$Module = $Params['Module'];
$http = eZHTTPTool::instance();
$Debug = isset($_GET['debug']);
$repositoryIdentifier = $Params['Repository'];
$id = $Params['Id'];
$item = null;
try {

    $repository = OCCustomSearchableRepositoryProvider::instance()->provideRepository($repositoryIdentifier);
    $item = $repository->fetchSearchableObject($id);
    if ($item instanceof OCCustomSearchableObjectInterface) {
        $data = $repository->index($item);
    } else {
        $data = false;
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

echo '<pre>';
var_dump($data);
if ($item instanceof OCCustomSearchableObjectInterface) {
    echo $item->getGuid() . "\n";
    print_r($item->toArray());
} else {
    print_r($item);
}
echo '</pre>';
eZDisplayDebug();
eZExecution::cleanExit();
