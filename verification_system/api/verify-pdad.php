<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';
send_security_headers(true);

require_once dirname(__DIR__) . '/config/db-pdad.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    json_response(["status" => "method_not_allowed"], 405);
}

$brgy_code = trim($_POST['brgy_code'] ?? '');
$unique_id = preg_replace('/\D+/', '', trim($_POST['unique_id'] ?? ''));

if (empty($brgy_code) || empty($unique_id)) {
    json_response(["status" => "missing_fields"], 400);
}

if (!preg_match('/^\d{3}$/', $brgy_code)) {
    json_response([
        "status" => "invalid_brgy_code",
        "message" => "Please select a valid barangay before verifying."
    ], 400);
}

if (!preg_match('/^\d{7}$/', $unique_id)) {
    json_response([
        "status" => "invalid_unique_id",
        "message" => "Please enter the last 7 digits of the Professional ID."
    ], 400);
}

// Step 1: Check if ID exists at all
$stmt = $conn->prepare("SELECT * FROM verification_info WHERE unique_id = ?");
if (!$stmt) {
    error_log('Professional ID query prepare failed: ' . $conn->error);
    json_response(["status" => "query_error"], 500);
}
$stmt->bind_param("s", $unique_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    // Step 2: Check if barangay code matches
    if ($row['brgy_code'] !== $brgy_code) {
        json_response([
            "status" => "mismatch_brgy_code",
            "message" => "The selected barangay does not match this ID number."
        ], 409);
    } else {
        // --- Masking functions ---
        function maskName($name) {
            $len = strlen($name);
            if ($len <= 2) {
                return strtoupper($name); // not enough characters to mask
            }

            $first = strtoupper($name[0]);
            $last = strtoupper($name[$len - 1]);
            $maskedMiddle = str_repeat('*', $len - 2);

            return $first . $maskedMiddle . $last;
        }

        function maskBirthdate($birthdate) {
            if (!$birthdate) return '';
            $date = explode('-', $birthdate); // Expected: YYYY-MM-DD
            if (count($date) !== 3) return $birthdate;

            $year = $date[0];
            $month = $date[1];
            $day = $date[2];

            return substr($year, 0, 2) . '** - ' .
                   '*' . substr($month, 1, 1) . ' - ' .
                   '*' . substr($day, 1, 1);
        }

        // --- Apply masking ---
        $firstname  = maskName($row['firstname'] ?? '');
        $middlename = maskName($row['middlename'] ?? '');
        $lastname   = maskName($row['lastname'] ?? ''); // Fixed: maskName used consistently
        $birthdate  = maskBirthdate($row['birthdate'] ?? '');

        // --- Calculate validity ---
        $validUntil = 'N/A';
        if (!empty($row['date_issued']) && strtotime($row['date_issued']) !== false) {
            $issuedDate = new DateTime($row['date_issued']);
            $issuedDate->modify('+5 years');
            $validUntil = $issuedDate->format('Y-m-d');
        }

        // --- Format sex ---
        $sex = strtoupper($row['sex'] ?? '');
        $sex = $sex === 'M' ? 'Male' : ($sex === 'F' ? 'Female' : 'N/A');

        // --- Return JSON ---
        json_response([
            "status"      => "success",
            "message"     => "Professional ID verified successfully.",
            "pwd_id"      => $row['pwd_id'],
            "firstname"   => $firstname,
            "middlename"  => $middlename,
            "lastname"    => $lastname,
            "birthdate"   => $birthdate,
            "sex"         => $sex,
            "valid_until" => $validUntil,
            "brgy_code"   => $row['brgy_code']
        ]);
    }
} else {
    // ID does not exist
    json_response([
        "status" => "not_found",
        "message" => "The Professional ID was not found in the verification database."
    ], 404);
}
