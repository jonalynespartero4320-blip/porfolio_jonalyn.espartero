<?php
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_login();
require_permission('reports.view');
header('Content-Type: application/json');

ensure_activity_logs_schema($pdo);

function logs_data_response(array $payload): void
{
    echo json_encode($payload);
    exit;
}

if (!db_table_exists($pdo, 'activity_logs')) {
    logs_data_response([
        'draw' => (int) ($_REQUEST['draw'] ?? 1),
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
    ]);
}

$draw = (int) ($_REQUEST['draw'] ?? 1);
$start = max(0, (int) ($_REQUEST['start'] ?? 0));
$length = (int) ($_REQUEST['length'] ?? 12);
if ($length <= 0 || $length > 100) {
    $length = 12;
}

$userFilter = trim((string) ($_REQUEST['user'] ?? ''));
$actionFilter = trim((string) ($_REQUEST['action_filter'] ?? ''));
$dateFrom = trim((string) ($_REQUEST['date_from'] ?? ''));
$dateTo = trim((string) ($_REQUEST['date_to'] ?? ''));
$search = trim((string) ($_REQUEST['search']['value'] ?? ''));
$orderColumnIndex = (int) ($_REQUEST['order'][0]['column'] ?? 5);
$orderDir = strtolower((string) ($_REQUEST['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';

$columns = [
    0 => 'action',
    1 => 'id',
    2 => 'username',
    3 => 'description',
    4 => 'ip_address',
    5 => 'created_at',
];
$orderColumn = $columns[$orderColumnIndex] ?? 'created_at';

$where = [];
$params = [];
if ($userFilter !== '') {
    $where[] = 'username = :user_filter';
    $params['user_filter'] = $userFilter;
}
if ($actionFilter !== '') {
    $where[] = 'action LIKE :action_filter';
    $params['action_filter'] = '%' . $actionFilter . '%';
}
if ($dateFrom !== '') {
    $where[] = 'DATE(created_at) >= :date_from';
    $params['date_from'] = $dateFrom;
}
if ($dateTo !== '') {
    $where[] = 'DATE(created_at) <= :date_to';
    $params['date_to'] = $dateTo;
}
if ($search !== '') {
    $where[] = '(username LIKE :search OR action LIKE :search OR description LIKE :search OR ip_address LIKE :search OR created_at LIKE :search)';
    $params['search'] = '%' . $search . '%';
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = (int) $pdo->query('SELECT COUNT(*) FROM activity_logs')->fetchColumn();

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM activity_logs ' . $whereSql);
foreach ($params as $key => $value) {
    $countStmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
}
$countStmt->execute();
$filtered = (int) $countStmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT id, username, action, description, ip_address, created_at
    FROM activity_logs
    {$whereSql}
    ORDER BY {$orderColumn} {$orderDir}
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
        '<span class="log-action-badge">' . e($row['action'] ?? '') . '</span>',
        '<span class="fw-semibold">' . (int) ($row['id'] ?? 0) . '</span>',
        e($row['username'] ?? ''),
        e($row['description'] ?? ''),
        e($row['ip_address'] ?? ''),
        e($row['created_at'] ?? ''),
    ];
}

logs_data_response([
    'draw' => $draw,
    'recordsTotal' => $total,
    'recordsFiltered' => $filtered,
    'data' => $rows,
]);
