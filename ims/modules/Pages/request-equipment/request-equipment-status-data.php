<?php
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_login();
require_any_permission(['ictd.request.manage', 'ictd.request.view']);
header('Content-Type: application/json');

if (!is_admin() && !can('ictd.request.manage')) {
    http_response_code(403);
    echo json_encode([
        'draw' => (int) ($_REQUEST['draw'] ?? 1),
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'counts' => ['all' => 0, 'Pending' => 0, 'Approved' => 0, 'Rejected' => 0, 'Cancelled' => 0, 'In Review' => 0],
        'error' => 'Access denied.',
    ]);
    exit;
}

function reqstat_data_response(array $payload): void
{
    echo json_encode($payload);
    exit;
}

function reqstat_data_ensure_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS equipment_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            date_requested DATE NOT NULL,
            fund_id INT NULL,
            department_id INT NULL,
            department_head VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS equipment_request_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            request_id INT NOT NULL,
            quantity INT NOT NULL DEFAULT 1,
            description TEXT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    foreach ([
        'control_no' => 'VARCHAR(50) NULL',
        'budget_amount' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
        'total_amount' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
        'quarter' => 'VARCHAR(20) NULL',
        'budget_balance' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
        'status' => "VARCHAR(30) NOT NULL DEFAULT 'Pending'",
        'cancel_reason' => 'TEXT NULL',
        'cancelled_at' => 'DATETIME NULL',
        'created_by' => 'INT NULL',
        'is_archived' => 'TINYINT(1) NOT NULL DEFAULT 0',
    ] as $column => $definition) {
        if (db_table_exists($pdo, 'equipment_requests') && !db_column_exists($pdo, 'equipment_requests', $column)) {
            $pdo->exec("ALTER TABLE equipment_requests ADD COLUMN {$column} {$definition}");
        }
    }
    foreach ([
        'item_type' => "VARCHAR(30) NOT NULL DEFAULT 'equipment'",
        'estimated_amount' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
        'total_amount' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
        'status' => "VARCHAR(30) NOT NULL DEFAULT 'Pending'",
        'rejection_reason' => 'TEXT NULL',
    ] as $column => $definition) {
        if (db_table_exists($pdo, 'equipment_request_items') && !db_column_exists($pdo, 'equipment_request_items', $column)) {
            $pdo->exec("ALTER TABLE equipment_request_items ADD COLUMN {$column} {$definition}");
        }
    }
}

reqstat_data_ensure_schema($pdo);

$draw = (int) ($_REQUEST['draw'] ?? 1);
$status = trim((string) ($_REQUEST['status'] ?? ''));
$where = ['COALESCE(er.is_archived, 0) = 0'];
$params = [];
if ($status !== '') {
    $where[] = 'er.status = :status';
    $params['status'] = $status;
}
$whereSql = 'WHERE ' . implode(' AND ', $where);
$search = trim((string) ($_REQUEST['search']['value'] ?? ''));
if ($search !== '') {
    $whereSql .= ' AND (er.control_no LIKE :search OR er.date_requested LIKE :search OR er.department_head LIKE :search OR d.department_name LIKE :search OR er.status LIKE :search OR er.cancel_reason LIKE :search)';
    $params['search'] = '%' . $search . '%';
}

$stmt = $pdo->prepare("
    SELECT er.*, d.department_name
    FROM equipment_requests er
    LEFT JOIN list_department d ON d.department_id = er.department_id
    {$whereSql}
    ORDER BY er.date_requested DESC, er.id DESC
");
foreach ($params as $key => $value) {
    $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$allRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$counts = ['all' => 0, 'Pending' => 0, 'Approved' => 0, 'Rejected' => 0, 'Cancelled' => 0, 'In Review' => 0];
$countWhere = ['COALESCE(er.is_archived, 0) = 0'];
$countParams = [];
$countStmt = $pdo->prepare('SELECT er.status, COUNT(*) AS total FROM equipment_requests er WHERE ' . implode(' AND ', $countWhere) . ' GROUP BY er.status');
foreach ($countParams as $key => $value) {
    $countStmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$countStmt->execute();
foreach ($countStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
    $key = (string) ($row['status'] ?: 'Pending');
    if (!isset($counts[$key])) {
        $counts[$key] = 0;
    }
    $counts[$key] += (int) $row['total'];
    $counts['all'] += (int) $row['total'];
}

$rows = [];
foreach ($allRows as $row) {
    $badge = match (strtolower((string) ($row['status'] ?? 'pending'))) {
        'approved' => 'success',
        'rejected' => 'danger',
        'cancelled' => 'dark',
        'in review' => 'info',
        default => 'secondary',
    };
    $reason = strtolower((string) ($row['status'] ?? '')) === 'cancelled' ? (string) ($row['cancel_reason'] ?? '') : '';
    $rows[] = [
        e($row['control_no'] ?? ''),
        e($row['date_requested'] ?? ''),
        e($row['department_head'] ?? ''),
        e($row['department_name'] ?? ''),
        number_format((float) ($row['budget_amount'] ?? 0), 2),
        number_format((float) ($row['total_amount'] ?? 0), 2),
        e($row['quarter'] ?? ''),
        number_format((float) ($row['budget_balance'] ?? 0), 2),
        '<span class="badge text-bg-' . $badge . '">' . e($row['status'] ?? 'Pending') . '</span>',
        e($reason),
        '<button type="button" class="request-action-btn request-action-view review-request-btn" data-id="' . (int) $row['id'] . '" data-balance="' . e($row['budget_balance'] ?? 0) . '" data-status="' . e($row['status'] ?? 'Pending') . '"><i class="bi bi-list-check"></i> Review</button>',
    ];
}

reqstat_data_response([
    'draw' => $draw,
    'recordsTotal' => count($allRows),
    'recordsFiltered' => count($allRows),
    'data' => $rows,
    'counts' => $counts,
]);
