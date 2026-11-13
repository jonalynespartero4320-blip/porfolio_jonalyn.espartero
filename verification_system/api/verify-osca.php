<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';
send_security_headers(true);

require_once dirname(__DIR__) . '/config/db-osca.php';

// Allow only POST requests
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    json_response(["status" => "method_not_allowed"], 405);
}

// Sanitize and validate input
$senior_citizen_id = strtoupper(trim($_POST['senior_citizen_id'] ?? ''));
if (empty($senior_citizen_id)) {
    json_response([
        "status" => "missing_id",
        "message" => "Please enter a Home ID number."
    ], 400);
}

if (!preg_match('/^\d{6}(-[A-Z])?$/', $senior_citizen_id)) {
    json_response([
        "status" => "invalid_id",
        "message" => "Use a 6-digit ID, with an optional suffix like 123456-T."
    ], 400);
}

// Prepare the SQL query
$stmt = $conn->prepare("SELECT * FROM verification_info WHERE senior_citizen_id = ?");
if (!$stmt) {
    error_log('Home ID query prepare failed: ' . $conn->error);
    json_response(["status" => "query_error"], 500);
}

$stmt->bind_param("s", $senior_citizen_id);
$stmt->execute();
$result = $stmt->get_result();

// Helper function to mask names
function maskName($name) {
    $length = strlen($name);
    if ($length === 0) return '';
    if ($length === 1) return strtoupper($name[0]);
    if ($length === 2) return strtoupper($name[0]) . '*';

    $first = strtoupper($name[0]);
    $last = strtoupper($name[$length - 1]);
    $maskedMiddle = str_repeat('*', $length - 2);

    return $first . $maskedMiddle . $last;
}

// Helper function to mask birthdate in format YYYY-MM-DD → 1990-*1-*2
function maskBirthdate($birthdate) {
    $parts = explode('-', $birthdate);
    if (count($parts) === 3) {
        $year = $parts[0];
        $month = $parts[1];
        $day = $parts[2];

        // Mask first digit of month and day
        $maskedMonth = (strlen($month) === 2) ? '*' . $month[1] : '*';
        $maskedDay   = (strlen($day) === 2) ? '*' . $day[1] : '*';

        return "$year-$maskedMonth-$maskedDay";
    }
    return ''; // Return empty string if format is incorrect
}

// Process result
if ($row = $result->fetch_assoc()) {

    $firstMasked  = maskName($row['firstname']);
    $middleMasked = !empty($row['middlename']) ? maskName($row['middlename']) : '';
    $lastMasked   = maskName($row['lastname']);
    $maskedBirthdate = maskBirthdate($row['birthdate']);

    // Return JSON response
    json_response([
        "status"            => "success",
        "message"           => "Home ID verified successfully.",
        "senior_citizen_id" => $row['senior_citizen_id'],
        "firstname"         => $firstMasked,
        "middlename"        => $middleMasked,
        "lastname"          => $lastMasked,
        "sex"               => $row['sex'],
        "birthdate"         => $maskedBirthdate,
        "acc_status"        => $row['acc_status'],
        // "age"            => (new DateTime())->diff(new DateTime($row['birthdate']))->y, // Optional
    ]);

} else {
    json_response([
        "status" => "not_found",
        "message" => "The Home ID was not found in the verification database."
    ], 404);
}
