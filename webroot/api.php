<?php

try {
    $data = file_get_contents('php://input');

    $data['meta']['request_ts'] = new MongoDB\BSON\UTCDateTime(DateTime::createFromFormat('U', $data['meta']['request_ts']));
    $data['meta']['request_ts_micro'] = new MongoDB\BSON\UTCDateTime(preg_replace('/\D/', '',  $data['meta']['request_ts_micro']));
    $data['meta']['simple_url'] = preg_replace('/\=\d+/', '', $data['meta']['simple_url']);

    $manager = new MongoDB\Driver\Manager('mongodb://mongodb:27017');
    $bulk = new MongoDB\Driver\BulkWrite;
    $bulk->insert($data);
    $cursor = $manager->executeBulkWrite('xhprof.results', $bulk);
} catch (Exception $e) {
    header("HTTP/1.1 500 Internal Server Error");
    echo 'xhgui - ' . $e->getMessage();
}
