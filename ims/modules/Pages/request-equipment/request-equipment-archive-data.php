<?php
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_login();
require_permission('ictd.request.view');
header('Content-Type: application/json');

function reqeq_archive_response(array $payload): void
{
    echo json_encode($payload);
    exit;
}

function reqeq_archive_ensure_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS list_funds (
            id INT AUTO_INCREMENT PRIMARY KEY,
            fund_name VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

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
            item_type VARCHAR(30) NOT NULL DEFAULT 'equipment',
            quantity INT NOT NULL DEFAULT 1,
            description TEXT NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'Pending',
            rejection_reason TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
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
        'archived_at' => 'DATETIME NULL',
        'updated_at' => 'DATETIME NULL',
    ] as $column => $definition) {
        if (db_table_exists($pdo, 'equipment_requests') && !db_column_exists($pdo, 'equipment_requests', $column)) {
            $pdo->exec("ALTER TABLE equipment_requests ADD COLUMN {$column} {$definition}");
        }
    }
}

try {
    reqeq_archive_ensure_schema($pdo);
    ensure_record_scope_schema($pdo);

    $draw = (int) ($_REQUEST['draw'] ?? 1);
    $start = max(0, (int) ($_REQUEST['start'] ?? 0));
    $length = (int) ($_REQUEST['length'] ?? 12);
    if ($length <= 0 || $length > 100) {
        $length = 12;
    }

    $search = trim((string) ($_REQUEST['search']['value'] ?? ''));
    $orderColumnIndex = (int) ($_REQUEST['order'][0]['column'] ?? 9);
    $orderDir = strtolower((string) ($_REQUEST['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
    $columns = [
        1 => 'er.date_requested',
        2 => 'er.control_no',
        3 => 'lf.fund_name',
        4 => 'd.department_name',
        5 => 'er.department_head',
        6 => 'item_count',
        7 => 'er.status',
        8 => 'er.created_at',
        9 => 'er.archived_at',
    ];
    $orderColumn = $columns[$orderColumnIndex] ?? 'er.archived_at';

    $where = ['COALESCE(er.is_archived, 0) = 1'];
    $params = [];
    apply_record_scope($pdo, $where, $params, record_scope_column($pdo, 'equipment_requests', 'created_by', 'er'), record_scope_column($pdo, 'equipment_requests', 'department_id', 'er'), 'request_equipment_archive');

    if ($search !== '') {
        $where[] = '(er.date_requested LIKE :search OR er.control_no LIKE :search OR lf.fund_name LIKE :search OR d.department_name LIKE :search OR er.department_head LIKE :search OR er.status LIKE :search OR er.cancel_reason LIKE :search OR er.created_at LIKE :search OR er.archived_at LIKE :search)';
        $params['search'] = '%' . $search . '%';
    }
    $whereSql = 'WHERE ' . implode(' AND ', $where);

    $totalWhere = ['COALESCE(er.is_archived, 0) = 1'];
    $totalParams = [];
    apply_record_scope($pdo, $totalWhere, $totalParams, record_scope_column($pdo, 'equipment_requests', 'created_by', 'er'), record_scope_column($pdo, 'equipment_requests', 'department_id', 'er'), 'request_equipment_archive_total');
    $totalStmt = $pdo->prepare('SELECT COUNT(*) FROM equipment_requests er WHERE ' . implode(' AND ', $totalWhere));
    foreach ($totalParams as $key => $value) {
        $totalStmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $totalStmt->execute();
    $total = (int) $totalStmt->fetchColumn();

    $countStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM equipment_requests er
        LEFT JOIN list_funds lf ON lf.id = er.fund_id
        LEFT JOIN list_department d ON d.department_id = er.department_id
        {$whereSql}
    ");
    foreach ($params as $key => $value) {
        $countStmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $countStmt->execute();
    $filtered = (int) $countStmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT er.*, lf.fund_name, d.department_name, COUNT(eri.id) AS item_count
        FROM equipment_requests er
        LEFT JOIN list_funds lf ON lf.id = er.fund_id
        LEFT JOIN list_department d ON d.department_id = er.department_id
        LEFT JOIN equipment_request_items eri ON eri.request_id = er.id
        {$whereSql}
        GROUP BY er.id
        ORDER BY {$orderColumn} {$orderDir}
        LIMIT :start, :length
    ");
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':start', $start, PDO::PARAM_INT);
    $stmt->bindValue(':length', $length, PDO::PARAM_INT);
    $stmt->execute();

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $id = (int) $row['id'];
        $status = (string) ($row['status'] ?? 'Pending');
        $badge = match (strtolower($status)) {
            'approved' => 'success',
            'rejected' => 'danger',
            'cancelled' => 'dark',
            'in review' => 'info',
            default => 'secondary',
        };
        $reason = strtolower($status) === 'cancelled' && trim((string) ($row['cancel_reason'] ?? '')) !== ''
            ? '<div class="text-muted small mt-1">' . e($row['cancel_reason']) . '</div>'
            : '';
        $actions = '<div class="request-actions">'
            . '<button type="button" class="request-action-btn request-action-view view-request-btn" data-id="' . $id . '" title="View Items"><i class="bi bi-eye"></i> View</button>'
            . '<button type="button" class="request-action-btn request-action-restore restore-request-btn" data-id="' . $id . '" title="Restore"><i class="bi bi-arrow-counterclockwise"></i> Restore</button>'
            . (is_admin() ? '<button type="button" class="request-action-btn request-action-delete delete-request-btn" data-id="' . $id . '" title="Delete Permanently"><i class="bi bi-trash3"></i> Delete</button>' : '')
            . '</div>';

        $rows[] = [
            $actions,
            e($row['date_requested'] ?? ''),
            e($row['control_no'] ?? ''),
            e($row['fund_name'] ?? ''),
            e($row['department_name'] ?? ''),
            e($row['department_head'] ?? ''),
            '<span class="badge bg-primary-subtle text-primary">' . (int) ($row['item_count'] ?? 0) . ' item(s)</span>',
            '<span class="badge text-bg-' . $badge . '">' . e($status) . '</span>' . $reason,
            e($row['created_at'] ?? ''),
            e($row['archived_at'] ?? ''),
        ];
    }

    reqeq_archive_response([
        'draw' => $draw,
        'recordsTotal' => $total,
        'recordsFiltered' => $filtered,
        'data' => $rows,
    ]);
} catch (Throwable $e) {
    reqeq_archive_response([
        'draw' => (int) ($_REQUEST['draw'] ?? 1),
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => $e->getMessage(),
    ]);
}
