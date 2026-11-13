<?php
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_login();
require_permission('ictd.assign.view');

header('Content-Type: application/json');

function assign_history_response(array $payload): void
{
    echo json_encode($payload);
    exit;
}

if (!db_table_exists($pdo, 'assignment_history')) {
    assign_history_response([
        'draw' => (int) ($_GET['draw'] ?? 1),
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
    ]);
}

$draw = (int) ($_GET['draw'] ?? 1);
$start = max(0, (int) ($_GET['start'] ?? 0));
$length = (int) ($_GET['length'] ?? 10);
if ($length <= 0 || $length > 100) {
    $length = 10;
}
$search = trim((string) ($_GET['search']['value'] ?? ''));
$orderDir = strtolower((string) ($_GET['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';

$params = [];
$whereClauses = [];
ensure_record_scope_schema($pdo);
apply_record_scope(
    $pdo,
    $whereClauses,
    $params,
    record_scope_column($pdo, 'assignment_history', 'performed_by', 'ah'),
    record_scope_column($pdo, 'assignment_history', 'new_department_id', 'ah'),
    'assign_history_scope'
);
if ($search !== '') {
    $whereClauses[] = "(
        ah.action LIKE :search
        OR ah.item_type LIKE :search
        OR ah.control_no LIKE :search
        OR e.control_no LIKE :search
        OR e.serialno LIKE :search
        OR oe.control_no LIKE :search
        OR oe.serialno LIKE :search
        OR oe.code LIKE :search
        OR old_user.end_user_name LIKE :search
        OR new_user.end_user_name LIKE :search
        OR ah.performed_username LIKE :search
        OR ah.details LIKE :search
    )";
    $params['search'] = '%' . $search . '%';
}
$where = empty($whereClauses) ? '' : 'WHERE ' . implode(' AND ', $whereClauses);

$total = (int) $pdo->query('SELECT COUNT(*) FROM assignment_history')->fetchColumn();

$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM assignment_history ah
    LEFT JOIN list_end_user old_user ON old_user.end_user_id = ah.old_end_user_id
    LEFT JOIN list_end_user new_user ON new_user.end_user_id = ah.new_end_user_id
    LEFT JOIN equipment e ON e.id = ah.equipment_id OR e.control_no = ah.control_no OR e.serialno = ah.control_no
    LEFT JOIN other_equipment oe ON oe.control_no = ah.control_no OR oe.serialno = ah.control_no OR oe.code = ah.control_no
    {$where}
");
foreach ($params as $key => $value) {
    $countStmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
}
$countStmt->execute();
$filtered = (int) $countStmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT
        ah.*,
        old_user.end_user_name AS old_user_name,
        new_user.end_user_name AS new_user_name,
        old_dep.department_name AS old_department_name,
        new_dep.department_name AS new_department_name
    FROM assignment_history ah
    LEFT JOIN list_end_user old_user ON old_user.end_user_id = ah.old_end_user_id
    LEFT JOIN list_end_user new_user ON new_user.end_user_id = ah.new_end_user_id
    LEFT JOIN list_department old_dep ON old_dep.department_id = ah.old_department_id
    LEFT JOIN list_department new_dep ON new_dep.department_id = ah.new_department_id
    LEFT JOIN equipment e ON e.id = ah.equipment_id OR e.control_no = ah.control_no OR e.serialno = ah.control_no
    LEFT JOIN other_equipment oe ON oe.control_no = ah.control_no OR oe.serialno = ah.control_no OR oe.code = ah.control_no
    {$where}
    ORDER BY ah.performed_at {$orderDir}, ah.id {$orderDir}
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
        '<span class="assign-type-badge">' . e(ucfirst((string) ($row['action'] ?? ''))) . '</span>',
        e($row['performed_at'] ?? ''),
        e($row['item_type'] ?? ''),
        e($row['control_no'] ?? ''),
        e($row['old_user_name'] ?? 'None'),
        e($row['new_user_name'] ?? 'None'),
        e($row['old_department_name'] ?? 'None'),
        e($row['new_department_name'] ?? 'None'),
        e($row['performed_username'] ?? ''),
        e($row['details'] ?? ''),
    ];
}

assign_history_response([
    'draw' => $draw,
    'recordsTotal' => $total,
    'recordsFiltered' => $filtered,
    'data' => $rows,
]);
