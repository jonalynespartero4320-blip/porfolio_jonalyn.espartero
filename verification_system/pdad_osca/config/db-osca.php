<?php
mysqli_report(MYSQLI_REPORT_OFF);

$servername = "localhost";
$username = "root";
$password = "";
$database = "senior_verification";

$conn = @new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    error_log('Home ID database connection failed: ' . $conn->connect_error);
    json_response([
        'status' => 'server_error',
        'message' => 'Unable to process the request right now.'
    ], 500);
}

$conn->set_charset('utf8mb4');
