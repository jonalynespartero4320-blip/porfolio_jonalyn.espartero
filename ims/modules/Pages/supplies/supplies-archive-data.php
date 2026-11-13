<?php
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_login();
require_permission('ictd.supplies.view');

header('Content-Type: application/json');

function supplies_archive_response(array $payload): void
{
    echo json_encode($payload);
    exit;
}

function supplies_archive_ensure_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS archive_other_equipment (
            id INT AUTO_INCREMENT PRIMARY KEY,
            original_id INT NULL,
            code VARCHAR(100) NULL,
            control_no VARCHAR(50) NULL,
            old_control_no VARCHAR(100) NULL,
            description_id INT NULL,
            brand_id INT NULL,
            serialno VARCHAR(100) NULL,
            remarks TEXT NULL,
            status VARCHAR(100) NULL,
            delivered_date VARCHAR(100) NULL,
            warranty_date VARCHAR(100) NULL,
            delivery_status VARCHAR(100) NULL,
            eta_date VARCHAR(20) NULL,
            batch_id VARCHAR(255) NULL,
            supplier_id INT NULL,
            end_user_id INT NULL,
            department_id INT NULL,
            assigned_end_user_id INT NULL,
            assigned_end_user_name VARCHAR(255) NULL,
            assigned_department_name VARCHAR(255) NULL,
            assignment_snapshot LONGTEXT NULL,
            control_no_reused TINYINT(1) NOT NULL DEFAULT 0,
            reused_at DATETIME NULL,
            reused_by INT NULL,
            reused_supply_id INT NULL,
            created_at TIMESTAMP NULL,
            archive_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            archived_by INT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    if (!db_column_exists($pdo, 'archive_other_equipment', 'original_id')) {
        $pdo->exec('ALTER TABLE archive_other_equipment ADD COLUMN original_id INT NULL AFTER id');
    }
    if (!db_column_exists($pdo, 'archive_other_equipment', 'archived_by')) {
        $pdo->exec('ALTER TABLE archive_other_equipment ADD COLUMN archived_by INT NULL AFTER archive_at');
    }
    $columns = [
        'assigned_end_user_id' => 'INT NULL AFTER department_id',
        'assigned_end_user_name' => 'VARCHAR(255) NULL AFTER assigned_end_user_id',
        'assigned_department_name' => 'VARCHAR(255) NULL AFTER assigned_end_user_name',
        'assignment_snapshot' => 'LONGTEXT NULL AFTER assigned_department_name',
        'control_no_reused' => 'TINYINT(1) NOT NULL DEFAULT 0 AFTER assignment_snapshot',
        'reused_at' => 'DATETIME NULL AFTER control_no_reused',
        'reused_by' => 'INT NULL AFTER reused_at',
        'reused_supply_id' => 'INT NULL AFTER reused_by',
        'eta_date' => 'VARCHAR(20) NULL AFTER delivery_status',
        'batch_id' => 'VARCHAR(255) NULL AFTER eta_date',
        'supplier_id' => 'INT NULL AFTER batch_id',
    ];
    foreach ($columns as $column => $definition) {
        if (!db_column_exists($pdo, 'archive_other_equipment', $column)) {
            $pdo->exec("ALTER TABLE archive_other_equipment ADD COLUMN {$column} {$definition}");
        }
    }
}

function supplies_archive_badge(string $value, string $type = 'secondary'): string
{
    $value = trim($value) ?: 'No Info';
    return '<span class="supplies-badge bg-' . e($type) . '-subtle text-' . e($type) . '">' . e($value) . '</span>';
}

function supplies_archive_warranty_markup($value): string
{
    $raw = trim((string) ($value ?? ''));
    if ($raw === '' || $raw === '0' || strtolower($raw) === 'null') {
        return supplies_archive_badge('No Info');
    }

    try {
        $warrantyEnd = DateTimeImmutable::createFromFormat('Y-m-d', substr($raw, 0, 10))
            ?: new DateTimeImmutable($raw);
    } catch (Throwable $e) {
        return e($raw) . '<br>' . supplies_archive_badge('No Info');
    }

    $date = $warrantyEnd->format('Y-m-d');
    if ($warrantyEnd >= new DateTimeImmutable('today')) {
        return e($date) . '<br>' . supplies_archive_badge('Under Warranty', 'success');
    }

    return e($date) . '<br>' . supplies_archive_badge('Expired', 'danger');
}

supplies_archive_ensure_schema($pdo);

$draw = (int) ($_GET['draw'] ?? 1);
$start = max(0, (int) ($_GET['start'] ?? 0));
$length = (int) ($_GET['length'] ?? 10);
if ($length <= 0 || $length > 100) {
    $length = 10;
}

$search = trim((string) ($_GET['search']['value'] ?? ''));
$orderColumnIndex = (int) ($_GET['order'][0]['column'] ?? 13);
$orderDir = strtolower((string) ($_GET['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';

$columns = [
    1 => 'aoe.code',
    2 => 'aoe.old_control_no',
    3 => 'd.description_name',
    4 => 'b.brand_name',
    5 => 'aoe.serialno',
    6 => 'aoe.status',
    7 => 'aoe.delivery_status',
    8 => 'aoe.eta_date',
    9 => 'aoe.delivered_date',
    10 => 'aoe.warranty_date',
    11 => 'aoe.assigned_end_user_name',
    12 => 'aoe.assigned_department_name',
    13 => 'aoe.created_at',
    14 => 'aoe.archive_at',
];
$orderColumn = $columns[$orderColumnIndex] ?? 'aoe.archive_at';

$whereClauses = [];
$params = [];
ensure_record_scope_schema($pdo);
apply_record_scope(
    $pdo,
    $whereClauses,
    $params,
    record_scope_column($pdo, 'archive_other_equipment', 'archived_by', 'aoe'),
    record_scope_column($pdo, 'archive_other_equipment', 'department_id', 'aoe'),
    'supplies_archive_scope'
);
if ($search !== '') {
    $whereClauses[] = "(
        aoe.code LIKE :search
        OR aoe.old_control_no LIKE :search
        OR aoe.serialno LIKE :search
        OR aoe.remarks LIKE :search
        OR aoe.status LIKE :search
        OR aoe.delivery_status LIKE :search
        OR aoe.eta_date LIKE :search
        OR aoe.assigned_end_user_name LIKE :search
        OR aoe.assigned_department_name LIKE :search
        OR d.description_name LIKE :search
        OR b.brand_name LIKE :search
        OR s.supplier_name LIKE :search
    )";
    $params['search'] = '%' . $search . '%';
}
$whereSql = empty($whereClauses) ? '' : 'WHERE ' . implode(' AND ', $whereClauses);

$totalRecords = (int) $pdo->query('SELECT COUNT(*) FROM archive_other_equipment')->fetchColumn();

$filteredStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM archive_other_equipment aoe
    LEFT JOIN list_description d ON d.id = aoe.description_id
    LEFT JOIN list_brand b ON b.id = aoe.brand_id
    LEFT JOIN list_suppliers s ON s.id = aoe.supplier_id
    {$whereSql}
");
foreach ($params as $key => $value) {
    $filteredStmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
}
$filteredStmt->execute();
$totalFiltered = (int) $filteredStmt->fetchColumn();

$dataStmt = $pdo->prepare("
    SELECT aoe.*, d.description_name, b.brand_name, s.supplier_name
    FROM archive_other_equipment aoe
    LEFT JOIN list_description d ON d.id = aoe.description_id
    LEFT JOIN list_brand b ON b.id = aoe.brand_id
    LEFT JOIN list_suppliers s ON s.id = aoe.supplier_id
    {$whereSql}
    ORDER BY {$orderColumn} {$orderDir}
    LIMIT :start, :length
");
foreach ($params as $key => $value) {
    $dataStmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
}
$dataStmt->bindValue(':start', $start, PDO::PARAM_INT);
$dataStmt->bindValue(':length', $length, PDO::PARAM_INT);
$dataStmt->execute();

$canRestore = can('ictd.supplies.edit');
$canPermanentDelete = can('ictd.supplies.delete');
$rows = [];
foreach ($dataStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $id = (int) ($row['id'] ?? 0);
    $actions = [];
    if ($canRestore) {
        $actions[] = '<button type="button" class="supplies-action-btn supplies-action-restore restore-supply-btn" data-id="' . $id . '" title="Restore"><i class="bi bi-arrow-counterclockwise"></i> Restore</button>';
    }
    if ($canPermanentDelete) {
        $actions[] = '<button type="button" class="supplies-action-btn supplies-action-delete permanent-delete-supply-btn" data-id="' . $id . '" title="Permanently Delete"><i class="bi bi-trash3"></i> Delete</button>';
    }

    $deliveryType = strtolower((string) ($row['delivery_status'] ?? '')) === 'delivered' ? 'success' : 'warning';
    $rows[] = [
        '<span class="supplies-actions">' . implode('', $actions) . '</span>',
        '<span class="supplies-code">' . e($row['code'] ?? '') . '</span>',
        e($row['old_control_no'] ?? ''),
        '<span class="fw-semibold">' . e($row['description_name'] ?? '') . '</span>',
        e($row['brand_name'] ?? ''),
        e($row['serialno'] ?? ''),
        supplies_archive_badge((string) ($row['status'] ?? '')),
        supplies_archive_badge((string) ($row['delivery_status'] ?? ''), $deliveryType),
        e($row['eta_date'] ?? ''),
        e($row['delivered_date'] ?? ''),
        supplies_archive_warranty_markup($row['warranty_date'] ?? null),
        e($row['assigned_end_user_name'] ?? ''),
        e($row['assigned_department_name'] ?? ''),
        e($row['created_at'] ?? ''),
        e($row['archive_at'] ?? ''),
    ];
}

supplies_archive_response([
    'draw' => $draw,
    'recordsTotal' => $totalRecords,
    'recordsFiltered' => $totalFiltered,
    'data' => $rows,
]);
