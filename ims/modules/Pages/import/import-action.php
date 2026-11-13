<?php
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/functions.php';
require_once ROOT_PATH . '/modules/Pages/subscription/subscription-common.php';
require_once ROOT_PATH . '/modules/Pages/borrowed/borrowed-common.php';
require_once ROOT_PATH . '/modules/Pages/bidding/bidding-common.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_login();

function ims_import_response(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

function ims_import_clean($value): ?string
{
    $value = trim((string) ($value ?? ''));
    return $value === '' ? null : $value;
}

function ims_import_int($value): ?int
{
    if ($value === null || $value === '') return null;
    return is_numeric($value) ? (int) $value : null;
}

function ims_import_date($value): ?string
{
    $value = ims_import_clean($value);
    if ($value === null) return null;
    $time = strtotime($value);
    return $time === false ? null : date('Y-m-d', $time);
}

function ims_import_modules(): array
{
    return [
        'equipment' => [
            'permission' => 'ictd.equipment.import',
            'template' => ['supplier', 'date_delivered', 'description', 'brand', 'serialno', 'computer_name', 'os', 'processor', 'memory', 'hdd', 'status', 'delivery_stat', 'price', 'property_no', 'old_control_no'],
            'sample' => ['ABC Supplier', date('Y-m-d'), 'Desktop Computer', 'Dell', 'SN-001', 'ICT-PC-001', 'Windows 11', 'Intel i5', '8GB', '512GB SSD', 'Working', 'Delivered', '35000', 'PROP-001', ''],
        ],
        'supplies' => [
            'permission' => 'ictd.supplies.view',
            'template' => ['code', 'description', 'brand', 'serialno', 'status', 'delivered_date', 'warranty_date', 'delivery_status', 'remarks', 'old_control_no'],
            'sample' => ['', 'Mouse', 'Logitech', 'SUP-SN-001', 'Working', date('Y-m-d'), '', 'Delivered', 'Imported sample row', ''],
        ],
        'assign' => [
            'permission' => 'ictd.assign.view',
            'template' => ['end_user', 'department', 'item_type', 'control_no', 'assigned_date', 'connection_ip', 'internet', 'wifi', 'program_installed', 'equipment_analysis'],
            'sample' => ['Juan Dela Cruz', 'ICTD', 'equipment', 'IT-26-0001', date('Y-m-d'), '192.168.1.10', 'Yes', 'Yes', 'MS Office', 'Good condition'],
        ],
        'subscription' => [
            'permission' => 'ictd.subscription.view',
            'template' => ['software_description', 'purchase_date', 'expiry_date', 'end_user', 'department'],
            'sample' => ['Microsoft 365', date('Y-m-d'), date('Y-m-d', strtotime('+1 year')), 'Juan Dela Cruz', 'ICTD'],
        ],
        'borrowed' => [
            'permission' => 'ictd.borrowed.view',
            'template' => ['end_user', 'department', 'item_borrowed', 'serialno', 'date_borrowed', 'date_returned'],
            'sample' => ['Juan Dela Cruz', 'ICTD', 'Laptop Charger', 'CHG-001', date('Y-m-d'), ''],
        ],
        'bidding' => [
            'permission' => 'ictd.bidding.view',
            'template' => ['date_requested', 'fund', 'department', 'department_head', 'item_description', 'qty'],
            'sample' => [date('Y-m-d'), 'MOOE', 'ICTD', 'Department Head', 'Printer Ink', '3'],
        ],
        'title' => [
            'permission' => 'ictd.title.view',
            'template' => ['title', 'description'],
            'sample' => ['Sample Title', 'Optional description'],
        ],
    ];
}

function ims_import_safe_ident(string $identifier): string
{
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function ims_import_insert(PDO $pdo, string $table, array $data): int
{
    if (!db_table_exists($pdo, $table)) {
        throw new RuntimeException("Table {$table} is missing.");
    }

    $filtered = [];
    foreach ($data as $column => $value) {
        if (db_column_exists($pdo, $table, $column)) {
            $filtered[$column] = $value;
        }
    }
    if (!$filtered) {
        throw new RuntimeException("No matching columns found for {$table}.");
    }

    $columns = array_keys($filtered);
    $sql = 'INSERT INTO ' . ims_import_safe_ident($table)
        . ' (' . implode(', ', array_map('ims_import_safe_ident', $columns)) . ') VALUES ('
        . implode(', ', array_fill(0, count($columns), '?')) . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_values($filtered));
    return (int) $pdo->lastInsertId();
}

function ims_import_find_or_create(PDO $pdo, string $table, string $pk, string $label, $value, array $extra = []): ?int
{
    $value = ims_import_clean($value);
    if ($value === null) return null;
    if (is_numeric($value)) return (int) $value;

    ims_import_ensure_lookup_table($pdo, $table);
    if (!db_table_exists($pdo, $table)) {
        throw new RuntimeException("Masterlist table {$table} is missing.");
    }

    $stmt = $pdo->prepare('SELECT ' . ims_import_safe_ident($pk) . ' FROM ' . ims_import_safe_ident($table) . ' WHERE LOWER(TRIM(' . ims_import_safe_ident($label) . ')) = LOWER(TRIM(?)) LIMIT 1');
    $stmt->execute([$value]);
    $id = $stmt->fetchColumn();
    if ($id) return (int) $id;

    $data = array_merge([$label => $value], $extra);
    return ims_import_insert($pdo, $table, $data);
}

function ims_import_ensure_lookup_table(PDO $pdo, string $table): void
{
    $schemas = [
        'list_suppliers' => "CREATE TABLE IF NOT EXISTS list_suppliers (id INT AUTO_INCREMENT PRIMARY KEY, supplier_name VARCHAR(255) NOT NULL UNIQUE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'list_description' => "CREATE TABLE IF NOT EXISTS list_description (id INT AUTO_INCREMENT PRIMARY KEY, description_name VARCHAR(255) NOT NULL UNIQUE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'list_brand' => "CREATE TABLE IF NOT EXISTS list_brand (id INT AUTO_INCREMENT PRIMARY KEY, brand_name VARCHAR(255) NOT NULL UNIQUE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'list_equipment_status' => "CREATE TABLE IF NOT EXISTS list_equipment_status (id INT AUTO_INCREMENT PRIMARY KEY, equipment_status VARCHAR(255) NOT NULL UNIQUE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'list_department' => "CREATE TABLE IF NOT EXISTS list_department (department_id INT AUTO_INCREMENT PRIMARY KEY, department_name VARCHAR(255) NOT NULL, department_head VARCHAR(255) NULL, department_office VARCHAR(255) NULL, department_division VARCHAR(255) NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'list_end_user' => "CREATE TABLE IF NOT EXISTS list_end_user (end_user_id INT AUTO_INCREMENT PRIMARY KEY, end_user_name VARCHAR(255) NOT NULL, department_id INT NULL, position_id INT NULL, empstat_id INT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'list_funds' => "CREATE TABLE IF NOT EXISTS list_funds (id INT AUTO_INCREMENT PRIMARY KEY, fund_name VARCHAR(100) NOT NULL UNIQUE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];
    if (isset($schemas[$table])) {
        $pdo->exec($schemas[$table]);
    }
}

function ims_import_department(PDO $pdo, $value, ?string $head = null): ?int
{
    return ims_import_find_or_create($pdo, 'list_department', 'department_id', 'department_name', $value, ['department_head' => $head]);
}

function ims_import_end_user(PDO $pdo, $value, ?int $departmentId = null): ?int
{
    return ims_import_find_or_create($pdo, 'list_end_user', 'end_user_id', 'end_user_name', $value, ['department_id' => $departmentId]);
}

function ims_import_next_control_numbers(PDO $pdo, string $dateDelivered, int $count): array
{
    $year = date('y', strtotime($dateDelivered));
    $prefix = 'IT-' . $year . '-';
    $values = [];
    if (db_table_exists($pdo, 'equipment')) {
        $stmt = $pdo->prepare('SELECT control_no FROM equipment WHERE control_no LIKE ?');
        $stmt->execute([$prefix . '%']);
        $values = array_merge($values, $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }
    if (db_table_exists($pdo, 'archive_equipment')) {
        $stmt = $pdo->prepare('SELECT control_no FROM archive_equipment WHERE control_no LIKE ?');
        $stmt->execute([$prefix . '%']);
        $values = array_merge($values, $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }
    $max = 0;
    foreach ($values as $controlNo) {
        if (preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', (string) $controlNo, $m)) {
            $max = max($max, (int) $m[1]);
        }
    }
    $next = [];
    for ($i = 1; $i <= $count; $i++) {
        $next[] = $prefix . str_pad((string) ($max + $i), 4, '0', STR_PAD_LEFT);
    }
    return $next;
}

function ims_import_next_supply_codes(PDO $pdo, int $count): array
{
    $prefix = 'SUP-' . date('y') . '-';
    $values = [];
    if (db_table_exists($pdo, 'other_equipment')) {
        $stmt = $pdo->prepare('SELECT code FROM other_equipment WHERE code LIKE ?');
        $stmt->execute([$prefix . '%']);
        $values = array_merge($values, $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }
    $max = 0;
    foreach ($values as $code) {
        if (preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', (string) $code, $m)) {
            $max = max($max, (int) $m[1]);
        }
    }
    $next = [];
    for ($i = 1; $i <= $count; $i++) {
        $next[] = $prefix . str_pad((string) ($max + $i), 4, '0', STR_PAD_LEFT);
    }
    return $next;
}

function ims_import_csv_rows(string $path): array
{
    $handle = fopen($path, 'rb');
    if (!$handle) {
        throw new RuntimeException('Unable to open uploaded CSV file.');
    }

    $headers = fgetcsv($handle);
    if (!$headers) {
        fclose($handle);
        throw new RuntimeException('CSV file is empty.');
    }
    if (isset($headers[0])) {
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);
    }
    $headers = array_map(static fn($h): string => strtolower(trim((string) $h)), $headers);
    $rows = [];
    while (($data = fgetcsv($handle)) !== false) {
        if (count(array_filter($data, static fn($v): bool => trim((string) $v) !== '')) === 0) {
            continue;
        }
        $row = [];
        foreach ($headers as $index => $header) {
            $row[$header] = $data[$index] ?? null;
        }
        $rows[] = $row;
    }
    fclose($handle);
    return $rows;
}

function ims_import_template(array $config, string $module): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="ims-' . $module . '-import-template.csv"');
    $out = fopen('php://output', 'wb');
    fputcsv($out, $config['template']);
    fputcsv($out, $config['sample']);
    fclose($out);
    exit;
}

function ims_import_insert_equipment(PDO $pdo, array $row, ?string $controlNo): void
{
    $dateDelivered = ims_import_date($row['date_delivered'] ?? null);
    if (!$dateDelivered) throw new RuntimeException('date_delivered is required.');
    $supplierId = ims_import_find_or_create($pdo, 'list_suppliers', 'id', 'supplier_name', $row['supplier'] ?? null);
    if (!$supplierId) throw new RuntimeException('supplier is required.');

    ims_import_insert($pdo, 'equipment', [
        'control_no' => $controlNo,
        'supplier_id' => $supplierId,
        'date_delivered' => $dateDelivered,
        'description_id' => ims_import_find_or_create($pdo, 'list_description', 'id', 'description_name', $row['description'] ?? null),
        'brand_id' => ims_import_find_or_create($pdo, 'list_brand', 'id', 'brand_name', $row['brand'] ?? null),
        'serialno' => ims_import_clean($row['serialno'] ?? null),
        'computer_name' => ims_import_clean($row['computer_name'] ?? null),
        'os' => ims_import_clean($row['os'] ?? null),
        'processor' => ims_import_clean($row['processor'] ?? null),
        'memory' => ims_import_clean($row['memory'] ?? null),
        'hdd' => ims_import_clean($row['hdd'] ?? null),
        'equipment_status_id' => ims_import_find_or_create($pdo, 'list_equipment_status', 'id', 'equipment_status', $row['status'] ?? null),
        'delivery_stat' => ims_import_clean($row['delivery_stat'] ?? null) ?: 'Delivered',
        'price' => is_numeric($row['price'] ?? null) ? (float) $row['price'] : null,
        'property_no' => ims_import_clean($row['property_no'] ?? null),
        'old_control_no' => ims_import_clean($row['old_control_no'] ?? null),
        'batch_id' => 'IMPORT-' . date('Ymd-His'),
        'created_by' => current_user()['id'] ?? null,
    ]);
}

function ims_import_insert_supply(PDO $pdo, array $row, ?string $code): void
{
    ims_import_insert($pdo, 'other_equipment', [
        'code' => $code,
        'description_id' => ims_import_find_or_create($pdo, 'list_description', 'id', 'description_name', $row['description'] ?? null),
        'brand_id' => ims_import_find_or_create($pdo, 'list_brand', 'id', 'brand_name', $row['brand'] ?? null),
        'serialno' => ims_import_clean($row['serialno'] ?? null),
        'status' => ims_import_clean($row['status'] ?? null),
        'delivered_date' => ims_import_date($row['delivered_date'] ?? null),
        'warranty_date' => ims_import_date($row['warranty_date'] ?? null),
        'delivery_status' => ims_import_clean($row['delivery_status'] ?? null),
        'remarks' => ims_import_clean($row['remarks'] ?? null),
        'old_control_no' => ims_import_clean($row['old_control_no'] ?? null),
        'created_by' => current_user()['id'] ?? null,
    ]);
}

function ims_import_find_item(PDO $pdo, string $type, string $controlNo): array
{
    if ($type === 'equipment') {
        $stmt = $pdo->prepare('SELECT id, control_no, serialno, assigned_id, department_id FROM equipment WHERE control_no = ? OR serialno = ? LIMIT 1');
        $stmt->execute([$controlNo, $controlNo]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new RuntimeException('Equipment item not found: ' . $controlNo);
        if (!empty($row['assigned_id']) || !empty($row['department_id'])) throw new RuntimeException('Equipment already assigned: ' . $controlNo);
        return ['type' => 'equipment', 'id' => (int) $row['id'], 'control_no' => $row['control_no'] ?: $controlNo, 'serialno' => $row['serialno'] ?? null];
    }

    $stmt = $pdo->prepare('SELECT id, code, serialno, assigned_id, end_user_id, department_id, status FROM other_equipment WHERE code = ? OR serialno = ? LIMIT 1');
    $stmt->execute([$controlNo, $controlNo]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new RuntimeException('Supply item not found: ' . $controlNo);
    if (!empty($row['assigned_id']) || !empty($row['end_user_id']) || !empty($row['department_id'])) throw new RuntimeException('Supply already assigned: ' . $controlNo);
    return ['type' => 'supply', 'id' => (int) $row['id'], 'control_no' => $row['code'] ?: $controlNo, 'serialno' => $row['serialno'] ?? null, 'status' => $row['status'] ?? null];
}

function ims_import_insert_assignment(PDO $pdo, array $row): void
{
    if (!db_table_exists($pdo, 'assigned_record_items')) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS assigned_record_items (id INT AUTO_INCREMENT PRIMARY KEY, assigned_id INT NOT NULL, item_type VARCHAR(30) NOT NULL, source_id INT NULL, control_no VARCHAR(100) NULL, serialno VARCHAR(100) NULL, item_status VARCHAR(100) NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
    $departmentId = ims_import_department($pdo, $row['department'] ?? null);
    $endUserId = ims_import_end_user($pdo, $row['end_user'] ?? null, $departmentId);
    if (!$departmentId || !$endUserId) throw new RuntimeException('end_user and department are required.');
    $type = strtolower((string) ($row['item_type'] ?? 'equipment'));
    if (!in_array($type, ['equipment', 'supply'], true)) throw new RuntimeException('item_type must be equipment or supply.');
    $item = ims_import_find_item($pdo, $type, (string) ($row['control_no'] ?? ''));
    $assignedDate = ims_import_date($row['assigned_date'] ?? null) ?: date('Y-m-d');

    $pdo->beginTransaction();
    $assignedId = ims_import_insert($pdo, 'assigned_record', [
        'end_user_id' => $endUserId,
        'department_id' => $departmentId,
        'equipment_id' => $type === 'equipment' ? $item['id'] : null,
        'control_no' => $item['control_no'],
        'connection_ip' => ims_import_clean($row['connection_ip'] ?? null),
        'internet' => ims_import_clean($row['internet'] ?? null),
        'wifi' => ims_import_clean($row['wifi'] ?? null),
        'program_installed' => ims_import_clean($row['program_installed'] ?? null),
        'assigned_date' => $assignedDate,
        'equipment_analysis' => ims_import_clean($row['equipment_analysis'] ?? null),
        'created_by' => current_user()['id'] ?? null,
    ]);
    ims_import_insert($pdo, 'assigned_record_items', [
        'assigned_id' => $assignedId,
        'item_type' => $type,
        'source_id' => $item['id'],
        'control_no' => $item['control_no'],
        'serialno' => $item['serialno'] ?? null,
        'item_status' => $item['status'] ?? null,
    ]);
    if ($type === 'equipment') {
        $pdo->prepare('UPDATE equipment SET assigned_id = ?, department_id = ? WHERE id = ?')->execute([$assignedId, $departmentId, $item['id']]);
    } else {
        $pdo->prepare('UPDATE other_equipment SET assigned_id = ?, end_user_id = ?, department_id = ? WHERE id = ?')->execute([$assignedId, $endUserId, $departmentId, $item['id']]);
    }
    $pdo->commit();
}

function ims_import_insert_simple(PDO $pdo, string $module, array $row): void
{
    if ($module === 'subscription') {
        subscription_ensure_schema($pdo);
        $departmentId = ims_import_department($pdo, $row['department'] ?? null);
        $endUserId = ims_import_end_user($pdo, $row['end_user'] ?? null, $departmentId);
        if (!$departmentId || !$endUserId || !ims_import_clean($row['software_description'] ?? null)) throw new RuntimeException('software_description, end_user, and department are required.');
        ims_import_insert($pdo, 'subscription', [
            'software_description' => ims_import_clean($row['software_description'] ?? null),
            'purchase_date' => ims_import_date($row['purchase_date'] ?? null),
            'expiry_date' => ims_import_date($row['expiry_date'] ?? null),
            'end_user_id' => $endUserId,
            'department_id' => $departmentId,
            'created_by' => current_user()['id'] ?? null,
        ]);
        return;
    }
    if ($module === 'borrowed') {
        borrowed_ensure_schema($pdo);
        $departmentId = ims_import_department($pdo, $row['department'] ?? null);
        $endUserId = ims_import_end_user($pdo, $row['end_user'] ?? null, $departmentId);
        if (!$departmentId || !$endUserId || !ims_import_clean($row['item_borrowed'] ?? null)) throw new RuntimeException('end_user, department, and item_borrowed are required.');
        ims_import_insert($pdo, 'borrowed', [
            'end_user_id' => $endUserId,
            'department_id' => $departmentId,
            'item_borrowed' => ims_import_clean($row['item_borrowed'] ?? null),
            'serialno' => ims_import_clean($row['serialno'] ?? null),
            'date_borrowed' => ims_import_date($row['date_borrowed'] ?? null) ?: date('Y-m-d'),
            'date_returned' => ims_import_date($row['date_returned'] ?? null),
            'created_by' => current_user()['id'] ?? null,
        ]);
        return;
    }
    if ($module === 'bidding') {
        bidding_ensure_schema($pdo);
        $departmentId = ims_import_department($pdo, $row['department'] ?? null, ims_import_clean($row['department_head'] ?? null));
        $fundId = ims_import_find_or_create($pdo, 'list_funds', 'id', 'fund_name', $row['fund'] ?? null);
        if (!$departmentId || !$fundId || !ims_import_clean($row['item_description'] ?? null)) throw new RuntimeException('fund, department, and item_description are required.');
        $pdo->beginTransaction();
        $bidId = ims_import_insert($pdo, 'bidding', [
            'date_requested' => ims_import_date($row['date_requested'] ?? null) ?: date('Y-m-d'),
            'fund_id' => $fundId,
            'department_id' => $departmentId,
            'department_head' => ims_import_clean($row['department_head'] ?? null),
            'created_by' => current_user()['id'] ?? null,
        ]);
        ims_import_insert($pdo, 'bidding_items', [
            'bidding_id' => $bidId,
            'description' => ims_import_clean($row['item_description'] ?? null),
            'qty' => max(1, (int) ($row['qty'] ?? 1)),
        ]);
        $pdo->commit();
        return;
    }
    if ($module === 'title') {
        $pdo->exec("CREATE TABLE IF NOT EXISTS titles (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(255) NOT NULL, description TEXT NULL, is_archived TINYINT(1) NOT NULL DEFAULT 0, created_by INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        if (!ims_import_clean($row['title'] ?? null)) throw new RuntimeException('title is required.');
        ims_import_insert($pdo, 'titles', [
            'title' => ims_import_clean($row['title'] ?? null),
            'description' => ims_import_clean($row['description'] ?? null),
            'created_by' => current_user()['id'] ?? null,
        ]);
    }
}

$modules = ims_import_modules();
$module = strtolower(trim((string) ($_POST['module'] ?? $_GET['module'] ?? '')));
if (!isset($modules[$module])) {
    ims_import_response(['success' => false, 'message' => 'Unsupported import module.'], 404);
}
$config = $modules[$module];
require_permission($config['permission']);

$action = trim((string) ($_POST['action'] ?? $_GET['action'] ?? ''));
if ($action === 'template') {
    ims_import_template($config, $module);
}
if ($action !== 'import') {
    ims_import_response(['success' => false, 'message' => 'Invalid import action.'], 400);
}
if (empty($_FILES['import_file']['tmp_name']) || !is_uploaded_file($_FILES['import_file']['tmp_name'])) {
    ims_import_response(['success' => false, 'message' => 'Please upload a CSV file.'], 422);
}

try {
    $rows = ims_import_csv_rows($_FILES['import_file']['tmp_name']);
    $dryRun = !empty($_POST['dry_run']);
    $inserted = 0;
    $errors = [];

    $equipmentControls = $module === 'equipment'
        ? ims_import_next_control_numbers($pdo, ims_import_date($rows[0]['date_delivered'] ?? null) ?: date('Y-m-d'), count($rows))
        : [];
    $supplyCodes = $module === 'supplies'
        ? ims_import_next_supply_codes($pdo, count(array_filter($rows, static fn($row): bool => ims_import_clean($row['code'] ?? null) === null)))
        : [];
    $supplyCodeIndex = 0;

    foreach ($rows as $index => $row) {
        try {
            if (!$dryRun) {
                if ($module === 'equipment') {
                    ims_import_insert_equipment($pdo, $row, ims_import_clean($row['control_no'] ?? null) ?: ($equipmentControls[$index] ?? null));
                } elseif ($module === 'supplies') {
                    $code = ims_import_clean($row['code'] ?? null) ?: ($supplyCodes[$supplyCodeIndex++] ?? null);
                    ims_import_insert_supply($pdo, $row, $code);
                } elseif ($module === 'assign') {
                    ims_import_insert_assignment($pdo, $row);
                } else {
                    ims_import_insert_simple($pdo, $module, $row);
                }
            } else {
                if ($module === 'equipment' && (!ims_import_clean($row['supplier'] ?? null) || !ims_import_date($row['date_delivered'] ?? null))) {
                    throw new RuntimeException('supplier and date_delivered are required.');
                }
                if ($module === 'supplies' && !ims_import_clean($row['description'] ?? null)) {
                    throw new RuntimeException('description is recommended and should not be blank.');
                }
            }
            $inserted++;
        } catch (Throwable $rowError) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = 'Row ' . ($index + 2) . ': ' . $rowError->getMessage();
        }
    }

    if (!$dryRun && $inserted > 0) {
        log_activity('IMPORT_' . strtoupper($module), 'Imported ' . $inserted . ' row(s) into ' . $module . '.');
    }

    ims_import_response([
        'success' => empty($errors) || $inserted > 0,
        'message' => $dryRun ? 'Validation finished.' : 'Import finished.',
        'rows_read' => count($rows),
        'inserted' => $dryRun ? 0 : $inserted,
        'skipped' => count($errors),
        'errors' => $errors,
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    ims_import_response(['success' => false, 'message' => $e->getMessage(), 'inserted' => 0, 'skipped' => 0, 'rows_read' => 0, 'errors' => []], 500);
}
