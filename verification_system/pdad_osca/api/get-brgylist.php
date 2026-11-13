<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';
send_security_headers(true);

require_once dirname(__DIR__) . '/config/db-pdad.php';

$response = [];

$sql = "SELECT DISTINCT brgy_code, brgy_name FROM brgyList WHERE brgy_code IS NOT NULL AND brgy_name IS NOT NULL ORDER BY brgy_name ASC";
$result = mysqli_query($conn, $sql);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $response[] = [
            'brgy_code' => $row['brgy_code'],
            'brgy_name' => $row['brgy_name']
        ];
    }
    json_response($response);
} else {
    error_log('Barangay list query failed: ' . mysqli_error($conn));
    json_response([
        'status' => 'error',
        'message' => 'Unable to load the barangay list right now.'
    ], 500);
}
