<?php
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_login();
require_permission('ictd.equipment.archive');

header('Content-Type: application/json');

function equipment_archive_response(array $payload): void
{
    echo json_encode($payload);
    exit;
}

function equipment_archive_ensure_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS archive_equipment (
            id INT AUTO_INCREMENT PRIMARY KEY,
            original_id INT NULL,
            control_no VARCHAR(255) NULL,
            computer_name VARCHAR(255) NULL,
            description_id INT NULL,
            brand_id INT NULL,
            serialno VARCHAR(50) NULL,
            os VARCHAR(255) NULL,
            os_prokey VARCHAR(100) NULL,
            ms_software VARCHAR(255) NULL,
            ms_uname VARCHAR(255) NULL,
            ms_pass VARCHAR(255) NULL,
            processor VARCHAR(255) NULL,
            memory VARCHAR(255) NULL,
            hdd VARCHAR(255) NULL,
            video_card VARCHAR(255) NULL,
            warranty_date VARCHAR(255) NULL,
            no_yrs_warranty INT NULL,
            delivery_stat VARCHAR(25) NULL,
            eta_date VARCHAR(20) NULL,
            price INT NULL,
            property_no VARCHAR(255) NULL,
            old_control_no VARCHAR(255) NULL,
            batch_id VARCHAR(255) NULL,
            supplier_id INT NULL,
            date_delivered VARCHAR(20) NULL,
            assigned_id INT NULL,
            transmittal_id VARCHAR(255) NULL,
            created_at TIMESTAMP NULL,
            department_id INT NULL,
            assigned_end_user_id INT NULL,
            assigned_end_user_name VARCHAR(255) NULL,
            assigned_department_name VARCHAR(255) NULL,
            assignment_snapshot LONGTEXT NULL,
            equipment_status_id INT NULL,
            control_no_reused TINYINT(1) NOT NULL DEFAULT 0,
            reused_at DATETIME NULL,
            reused_by INT NULL,
            reused_equipment_id INT NULL,
            archive_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            archived_by INT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $columns = [
        'original_id' => 'INT NULL AFTER id',
        'computer_name' => 'VARCHAR(255) NULL AFTER control_no',
        'assigned_id' => 'INT NULL AFTER date_delivered',
        'transmittal_id' => 'VARCHAR(255) NULL AFTER assigned_id',
        'department_id' => 'INT NULL AFTER created_at',
        'assigned_end_user_id' => 'INT NULL AFTER department_id',
        'assigned_end_user_name' => 'VARCHAR(255) NULL AFTER assigned_end_user_id',
        'assigned_department_name' => 'VARCHAR(255) NULL AFTER assigned_end_user_name',
        'assignment_snapshot' => 'LONGTEXT NULL AFTER assigned_department_name',
        'equipment_status_id' => 'INT NULL AFTER department_id',
        'control_no_reused' => 'TINYINT(1) NOT NULL DEFAULT 0 AFTER equipment_status_id',
        'reused_at' => 'DATETIME NULL AFTER control_no_reused',
        'reused_by' => 'INT NULL AFTER reused_at',
        'reused_equipment_id' => 'INT NULL AFTER reused_by',
        'archived_by' => 'INT NULL AFTER archive_at',
    ];

    foreach ($columns as $column => $definition) {
        if (!db_column_exists($pdo, 'archive_equipment', $column)) {
            $pdo->exec("ALTER TABLE archive_equipment ADD COLUMN {$column} {$definition}");
        }
    }

    try {
        $pdo->exec('ALTER TABLE archive_equipment MODIFY control_no VARCHAR(255) NULL');
        $pdo->exec('ALTER TABLE archive_equipment MODIFY description_id INT NULL');
    } catch (Throwable $e) {
    }
}

equipment_archive_ensure_schema($pdo);

$draw = (int) ($_GET['draw'] ?? 1);
$start = max(0, (int) ($_GET['start'] ?? 0));
$length = (int) ($_GET['length'] ?? 10);
if ($length <= 0 || $length > 100) {
    $length = 10;
}

$search = trim((string) ($_GET['search']['value'] ?? ''));
$orderColumnIndex = (int) ($_GET['order'][0]['column'] ?? 12);
$orderDir = strtolower((string) ($_GET['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
$hasEquipmentStatusColumn = db_column_exists($pdo, 'archive_equipment', 'equipment_status_id') && db_table_exists($pdo, 'list_equipment_status');

$columns = [
    1 => 'ae.control_no',
    2 => 'd.description_name',
    3 => 'b.brand_name',
    4 => 'ae.serialno',
    5 => 'ae.delivery_stat',
    6 => $hasEquipmentStatusColumn ? 'es.equipment_status' : 'ae.id',
    7 => 'ae.price',
    8 => 'ae.property_no',
    9 => 'ae.assigned_end_user_name',
    10 => 'ae.assigned_department_name',
    11 => 'ae.control_no_reused',
    12 => 'ae.created_at',
    13 => 'ae.archive_at',
];
$orderColumn = $columns[$orderColumnIndex] ?? 'ae.archive_at';

$whereClauses = [];
$params = [];
ensure_record_scope_schema($pdo);
apply_record_scope(
    $pdo,
    $whereClauses,
    $params,
    record_scope_column($pdo, 'archive_equipment', 'archived_by', 'ae'),
    record_scope_column($pdo, 'archive_equipment', 'department_id', 'ae'),
    'equipment_archive_scope'
);
if ($search !== '') {
    $whereClauses[] = "(
        ae.control_no LIKE :search
        OR ae.old_control_no LIKE :search
        OR ae.computer_name LIKE :search
        OR ae.serialno LIKE :search
        OR ae.property_no LIKE :search
        OR ae.assigned_end_user_name LIKE :search
        OR ae.assigned_department_name LIKE :search
        OR d.description_name LIKE :search
        OR b.brand_name LIKE :search
        " . ($hasEquipmentStatusColumn ? "OR es.equipment_status LIKE :search" : "") . "
    )";
    $params['search'] = '%' . $search . '%';
}
$whereSql = empty($whereClauses) ? '' : 'WHERE ' . implode(' AND ', $whereClauses);

$totalRecords = (int) $pdo->query('SELECT COUNT(*) FROM archive_equipment')->fetchColumn();

$filteredStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM archive_equipment ae
    LEFT JOIN list_description d ON d.id = ae.description_id
    LEFT JOIN list_brand b ON b.id = ae.brand_id
    " . ($hasEquipmentStatusColumn ? "LEFT JOIN list_equipment_status es ON es.id = ae.equipment_status_id" : "") . "
    {$whereSql}
");
foreach ($params as $key => $value) {
    $filteredStmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
}
$filteredStmt->execute();
$totalFiltered = (int) $filteredStmt->fetchColumn();

$dataStmt = $pdo->prepare("
    SELECT
        ae.*,
        d.description_name,
        b.brand_name,
        " . ($hasEquipmentStatusColumn ? "es.equipment_status" : "NULL AS equipment_status") . "
    FROM archive_equipment ae
    LEFT JOIN list_description d ON d.id = ae.description_id
    LEFT JOIN list_brand b ON b.id = ae.brand_id
    " . ($hasEquipmentStatusColumn ? "LEFT JOIN list_equipment_status es ON es.id = ae.equipment_status_id" : "") . "
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

$canRestore = can('ictd.equipment.archive');
$canPermanentDelete = can('ictd.equipment.delete');
$canReuseControlNo = can('ictd.equipment.create');
$rows = [];
foreach ($dataStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $id = (int) ($row['id'] ?? 0);
    $actions = [];
    if ($canReuseControlNo && (int) ($row['control_no_reused'] ?? 0) === 0 && empty($row['reused_equipment_id']) && !empty($row['control_no'])) {
        $actions[] = '<button type="button" class="equipment-action-btn equipment-action-reuse reuse-control-btn" data-id="' . $id . '" data-control="' . e($row['control_no'] ?? '') . '" title="Reuse Control No"><i class="bi bi-arrow-repeat"></i> Reuse</button>';
    }
    if ($canRestore) {
        $actions[] = '<button type="button" class="equipment-action-btn equipment-action-restore restore-equipment-btn" data-id="' . $id . '" title="Restore"><i class="bi bi-arrow-counterclockwise"></i> Restore</button>';
    }
    if ($canPermanentDelete) {
        $actions[] = '<button type="button" class="equipment-action-btn equipment-action-delete permanent-delete-equipment-btn" data-id="' . $id . '" title="Permanently Delete"><i class="bi bi-trash3"></i> Delete</button>';
    }

    $rows[] = [
        '<span class="equipment-actions">' . implode('', $actions) . '</span>',
        '<span class="equipment-control">' . e($row['control_no'] ?? '') . '</span>',
        '<span class="fw-semibold">' . e($row['description_name'] ?? '') . '</span>',
        e($row['brand_name'] ?? ''),
        e($row['serialno'] ?? ''),
        e($row['delivery_stat'] ?? ''),
        '<span class="equipment-badge bg-secondary-subtle text-secondary">' . e($row['equipment_status'] ?? 'No Status') . '</span>',
        number_format((float) ($row['price'] ?? 0), 2),
        e($row['property_no'] ?? ''),
        e($row['assigned_end_user_name'] ?? ''),
        e($row['assigned_department_name'] ?? ''),
        !empty($row['reused_equipment_id'])
            ? '<span class="equipment-badge bg-success-subtle text-success">Used</span>'
            : ((int) ($row['control_no_reused'] ?? 0) === 1
                ? '<span class="equipment-badge bg-primary-subtle text-primary">Ready to Reuse</span>'
                : '<span class="equipment-badge bg-warning-subtle text-warning">Reserved</span>'),
        e($row['created_at'] ?? ''),
        e($row['archive_at'] ?? ''),
    ];
}

equipment_archive_response([
    'draw' => $draw,
    'recordsTotal' => $totalRecords,
    'recordsFiltered' => $totalFiltered,
    'data' => $rows,
]);
