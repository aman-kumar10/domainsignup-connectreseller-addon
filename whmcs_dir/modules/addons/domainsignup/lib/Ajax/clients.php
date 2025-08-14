<?php

require_once __DIR__ . '/../../../../../init.php';

use WHMCS\Module\Addon\DomainSignup\Helper;

// Check if the request method is GET
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    header('Content-Type: application/json');

    $helper = new Helper();

    $request = $_GET;

    // Get DataTable parameters with fallbacks
    $draw = isset($request['draw']) ? (int) $request['draw'] : 0;
    $start = isset($request['start']) ? (int) $request['start'] : 0;
    $length = isset($request['length']) ? (int) $request['length'] : 10;
    $search = isset($request['search']['value']) ? $request['search']['value'] : '';

    $data = $helper->getClientsDataTable($start, $length, $search);
    $totalRecords = $helper->getClientsCount();
    $filteredRecords = $helper->getClientsCount($search);

    echo json_encode([
        "draw" => $draw,
        "recordsTotal" => $totalRecords,
        "recordsFiltered" => $filteredRecords,
        "data" => $data
    ]);
    exit;
}
