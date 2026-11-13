<?php
require_once dirname(__DIR__, 2) . '/demo/config.php';
mysqli_report(MYSQLI_REPORT_OFF);

$servername = getenv('DEMO_DB_HOST') ?: '127.0.0.1';
$username = getenv('DEMO_DB_USER') ?: 'root';
$password = getenv('DEMO_DB_PASSWORD') ?: '';
$database = demo_database_name('rtc');

$conn = @new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    error_log('Food ID database connection failed: ' . $conn->connect_error);
    json_response([
        'status' => 'server_error',
        'message' => 'Unable to process the request right now.'
    ], 500);
}

$conn->set_charset('utf8mb4');
