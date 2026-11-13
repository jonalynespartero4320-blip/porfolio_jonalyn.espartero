<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/config.php';
// Only insert the named sample rows when missing; preserve visitor edits.
function seed_row(PDO $db, string $table, array $row, string $key): void {
    $check = $db->prepare("SELECT COUNT(*) FROM `$table` WHERE `$key` = ?");
    $check->execute([$row[$key]]);
    if ($check->fetchColumn()) return;
    $columns = '`' . implode('`,`', array_keys($row)) . '`';
    $params = implode(',', array_fill(0, count($row), '?'));
    $db->prepare("INSERT INTO `$table` ($columns) VALUES ($params)")->execute(array_values($row));
}
$db = demo_pdo('ims');
seed_row($db, 'list_description', ['description_name' => 'Demo computer'], 'description_name');
seed_row($db, 'list_brand', ['brand_name' => 'Demo Brand'], 'brand_name');
seed_row($db, 'list_department', ['department_name' => 'Demo Office'], 'department_name');
seed_row($db, 'list_suppliers', ['supplier_name' => 'Demo Supplier'], 'supplier_name');
seed_row($db, 'list_equipment_status', ['equipment_status' => 'Available'], 'equipment_status');
seed_row($db, 'equipment', ['control_no' => 'DEMO-001', 'computer_name' => 'Demo Workstation', 'serialno' => 'SAMPLE-001', 'processor' => 'Intel Core i5', 'memory' => '16 GB', 'hdd' => '512 GB SSD', 'price' => 35000, 'created_by' => 1], 'control_no');
seed_row($db, 'equipment', ['control_no' => 'DEMO-002', 'computer_name' => 'Demo Laptop', 'serialno' => 'SAMPLE-002', 'processor' => 'Intel Core i7', 'memory' => '16 GB', 'hdd' => '1 TB SSD', 'price' => 55000, 'created_by' => 1], 'control_no');
$db = demo_pdo('bjmp');
$type = $db->query("SHOW FULL TABLES LIKE 'v_queue_board'")->fetch(PDO::FETCH_NUM);
if ($type && $type[1] === 'BASE TABLE') $db->exec('DROP TABLE `v_queue_board`');
$db->exec("CREATE OR REPLACE VIEW v_queue_board AS SELECT id, visitor_id, display_name, position, status, display_time FROM queue_display ORDER BY (status = 'inside') DESC, position ASC, display_time ASC");
seed_row($db, 'dorm_masterlist', ['dorm_no' => '1', 'description' => 'Demo Dorm'], 'dorm_no');
seed_row($db, 'pdl_info', ['pdl_id' => 'DEMO-PDL-001', 'pdl_fname' => 'Sample', 'pdl_mname' => 'Demo', 'pdl_lname' => 'Resident', 'pdl_age' => 30, 'pdl_sex' => 'Male', 'dorm_no' => 1, 'case_no' => 'DEMO-CASE-001', 'case_type' => 'Fictional demo record', 'court' => 'Demo Court'], 'pdl_id');
$resident = (int) $db->query("SELECT id FROM pdl_info WHERE pdl_id = 'DEMO-PDL-001'")->fetchColumn();
seed_row($db, 'pdl_authorized_visitors', ['pdl_id' => $resident, 'visitor_fname' => 'Demo', 'visitor_lname' => 'Visitor', 'visitor_age' => 28, 'visitor_sex' => 'Female', 'visitor_relationship' => 'Sibling', 'visitor_address' => 'Sample Address', 'visitor_status' => 'Verified', 'created_by' => 1], 'visitor_fname');
$authorized = (int) $db->query("SELECT id FROM pdl_authorized_visitors WHERE visitor_fname = 'Demo' LIMIT 1")->fetchColumn();
seed_row($db, 'visitors_registration', ['v_id' => 'DEMO-VISITOR-001', 'pdl_id' => $resident, 'authorized_visitor_id' => $authorized, 'firstname' => 'Demo', 'lastname' => 'Visitor', 'age' => 28, 'sex' => 'Female', 'loc_add' => 'Sample Address', 'visitor_status' => 'Verified', 'valid_id_type' => 'Demo ID', 'valid_id_no' => 'SAMPLE-001', 'created_by_user' => 1], 'v_id');
echo "Demo equipment and fictional resident ready.\n";
$db = demo_pdo('rtc');
$db->exec('CREATE TABLE IF NOT EXISTS demo_food_ids (food_id VARCHAR(20) PRIMARY KEY, display_name VARCHAR(100) NOT NULL, verification_status VARCHAR(30) NOT NULL)');
seed_row($db, 'demo_food_ids', ['food_id' => 'FOOD-ID-00001', 'display_name' => 'Sample Member', 'verification_status' => 'VERIFIED'], 'food_id');
