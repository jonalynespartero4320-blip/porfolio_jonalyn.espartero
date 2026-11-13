<?php
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_login();
require_permission('ictd.assign.view');

header('Content-Type: application/json');

function assign_archive_response(array $payload): void
{
    echo json_encode($payload);
    exit;
}

function assign_archive_ensure_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS archive_assigned_record (
            id INT AUTO_INCREMENT PRIMARY KEY,
            original_assigned_id INT NULL,
            item_type VARCHAR(30) NOT NULL DEFAULT 'equipment',
            equipment_id INT NULL,
            control_no VARCHAR(255) NULL,
            end_user_id INT NULL,
            end_user_name VARCHAR(255) NULL,
            department_id INT NULL,
            department_name VARCHAR(255) NULL,
            connection_ip VARCHAR(255) NULL,
            internet VARCHAR(50) NULL,
            wifi VARCHAR(50) NULL,
            program_installed TEXT NULL,
            assigned_date DATE NULL,
            months_since_assigned VARCHAR(100) NULL,
            equipment_analysis TEXT NULL,
            attachment_pdf VARCHAR(255) NULL,
            assignment_snapshot LONGTEXT NULL,
            item_snapshot LONGTEXT NULL,
            archive_reason VARCHAR(255) NULL,
            archive_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            archived_by INT NULL,
            archived_username VARCHAR(255) NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

assign_archive_ensure_schema($pdo);

$draw = (int) ($_REQUEST['draw'] ?? 1);
$start = max(0, (int) ($_REQUEST['start'] ?? 0));
$length = (int) ($_REQUEST['length'] ?? 10);
if ($length <= 0 || $length > 100) {
    $length = 10;
}

$search = trim((string) ($_REQUEST['search']['value'] ?? ''));
$orderColumnIndex = (int) ($_REQUEST['order'][0]['column'] ?? 10);
$orderDir = strtolower((string) ($_REQUEST['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
$columns = [
    0 => 'aar.original_assigned_id',
    1 => 'aar.item_type',
    2 => 'aar.control_no',
    3 => 'aar.end_user_name',
    4 => 'aar.department_name',
    5 => 'aar.connection_ip',
    6 => 'aar.internet',
    7 => 'aar.wifi',
    8 => 'aar.assigned_date',
    9 => 'aar.archive_reason',
    10 => 'aar.archive_at',
    11 => 'aar.archived_username',
];
$orderColumn = $columns[$orderColumnIndex] ?? 'aar.archive_at';

$whereClauses = [];
$params = [];
ensure_record_scope_schema($pdo);
apply_record_scope(
    $pdo,
    $whereClauses,
    $params,
    record_scope_column($pdo, 'archive_assigned_record', 'archived_by', 'aar'),
    record_scope_column($pdo, 'archive_assigned_record', 'department_id', 'aar'),
    'assign_archive_scope'
);
if ($search !== '') {
    $whereClauses[] = "(
        aar.control_no LIKE :search
        OR aar.end_user_name LIKE :search
        OR aar.department_name LIKE :search
        OR aar.connection_ip LIKE :search
        OR aar.internet LIKE :search
        OR aar.wifi LIKE :search
        OR aar.program_installed LIKE :search
        OR aar.equipment_analysis LIKE :search
        OR aar.archive_reason LIKE :search
        OR aar.archived_username LIKE :search
    )";
    $params['search'] = '%' . $search . '%';
}
$whereSql = empty($whereClauses) ? '' : 'WHERE ' . implode(' AND ', $whereClauses);

$total = (int) $pdo->query('SELECT COUNT(*) FROM archive_assigned_record')->fetchColumn();

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM archive_assigned_record aar {$whereSql}");
foreach ($params as $key => $value) {
    $countStmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
}
$countStmt->execute();
$filtered = (int) $countStmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT aar.*
    FROM archive_assigned_record aar
    {$whereSql}
    ORDER BY {$orderColumn} {$orderDir}, aar.id {$orderDir}
    LIMIT :start, :length
");
foreach ($params as $key => $value) {
    $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
}
$stmt->bindValue(':start', $start, PDO::PARAM_INT);
$stmt->bindValue(':length', $length, PDO::PARAM_INT);
$stmt->execute();

$rows = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $rows[] = [
        e($row['original_assigned_id'] ?? ''),
        '<span class="assign-type-badge">' . e(ucfirst((string) ($row['item_type'] ?? 'equipment'))) . '</span>',
        e($row['control_no'] ?? ''),
        e($row['end_user_name'] ?? ''),
        e($row['department_name'] ?? ''),
        e($row['connection_ip'] ?? ''),
        e($row['internet'] ?? ''),
        e($row['wifi'] ?? ''),
        e($row['assigned_date'] ?? ''),
        e($row['archive_reason'] ?? ''),
        e($row['archive_at'] ?? ''),
        e($row['archived_username'] ?? ''),
    ];
}

assign_archive_response([
    'draw' => $draw,
    'recordsTotal' => $total,
    'recordsFiltered' => $filtered,
    'data' => $rows,
]);
