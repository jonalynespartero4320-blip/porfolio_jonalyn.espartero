<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
send_security_headers(true);
require_once dirname(__DIR__) . '/config/db-rtc.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['status' => 'method_not_allowed'], 405);
$id = strtoupper(trim((string) ($_POST['unique_id'] ?? '')));
if (!preg_match('/^FOOD-ID-\d{5}$/', $id)) {
    json_response(['status' => 'invalid_id', 'message' => 'Enter a Food ID in the format FOOD-ID-00001.'], 400);
}
$stmt = $conn->prepare('SELECT food_id, display_name, verification_status FROM demo_food_ids WHERE food_id = ?');
$stmt->bind_param('s', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
if (!$row) json_response(['status' => 'not_found', 'message' => 'Food ID not found in the demo records.'], 404);
json_response(['status' => 'success', 'rtc_code' => $row['food_id'], 'verification_status' => $row['verification_status'], 'principal_fullname' => 'S***** M*****', 'hcp_fullname' => 'D*** S******']);
