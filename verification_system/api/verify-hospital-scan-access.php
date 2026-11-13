<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';
send_security_headers(true);

require_once dirname(__DIR__) . '/config/db-rtc.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['status' => 'method_not_allowed'], 405);
}

$hospitalPassword = trim($_POST['hospital_password'] ?? '');

if ($hospitalPassword === '') {
    json_response([
        'status' => 'missing_fields',
        'message' => 'Please enter the assigned hospital password.'
    ], 400);
}

$stmt = $conn->prepare(
    "SELECT id, hospital_name
     FROM `hospital_credentials`
     WHERE (`password_one` = ? OR `password_two` = ?)
       AND ? <> ''
     LIMIT 1"
);

if (!$stmt) {
    error_log('Hospital scanner access query prepare failed: ' . $conn->error);
    json_response(['status' => 'query_error'], 500);
}

$stmt->bind_param('sss', $hospitalPassword, $hospitalPassword, $hospitalPassword);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if (!$row) {
    json_response([
        'status' => 'invalid_credentials',
        'message' => 'Invalid assigned hospital password.'
    ], 401);
}

json_response([
    'status' => 'success',
    'message' => 'Hospital scanner access approved.',
    'hospital_id' => (string) ($row['id'] ?? ''),
    'hospital' => (string) ($row['hospital_name'] ?? '')
]);
