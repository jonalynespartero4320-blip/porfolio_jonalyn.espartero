<?php
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_login();
require_permission('ictd.supplies.view');

header('Content-Type: application/json');

function supplies_data_response(array $payload): void
{
    echo json_encode($payload);
    exit;
}

function supplies_badge(string $value, string $type = 'secondary'): string
{
    $value = trim($value);
    if ($value === '') {
        $value = 'No Info';
    }

    return '<span class="supplies-badge bg-' . e($type) . '-subtle text-' . e($type) . '">' . e($value) . '</span>';
}

function supplies_warranty_markup($value): string
{
    $raw = trim((string) ($value ?? ''));
    if ($raw === '' || $raw === '0' || strtolower($raw) === 'null') {
        return supplies_badge('No Info');
    }

    try {
        $warrantyEnd = DateTimeImmutable::createFromFormat('Y-m-d', substr($raw, 0, 10))
            ?: new DateTimeImmutable($raw);
    } catch (Throwable $e) {
        return e($raw) . '<br>' . supplies_badge('No Info');
    }

    $date = $warrantyEnd->format('Y-m-d');
    if ($warrantyEnd >= new DateTimeImmutable('today')) {
        return e($date) . '<br>' . supplies_badge('Under Warranty', 'success');
    }

    return e($date) . '<br>' . supplies_badge('Expired', 'danger');
}

function supplies_data_ensure_schema(PDO $pdo): void
{
    $columns = [
        'supplier_id' => 'INT NULL AFTER batch_id',
        'eta_date' => 'VARCHAR(20) NULL AFTER delivery_status',
        'batch_id' => 'VARCHAR(255) NULL AFTER eta_date',
    ];

    foreach ($columns as $column => $definition) {
        if (!db_column_exists($pdo, 'other_equipment', $column)) {
            $pdo->exec("ALTER TABLE other_equipment ADD COLUMN {$column} {$definition}");
        }
    }
}

if (!db_table_exists($pdo, 'other_equipment')) {
    supplies_data_response([
        'draw' => (int) ($_GET['draw'] ?? 1),
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'message' => 'The other_equipment table is missing.',
    ]);
}
if (!db_column_exists($pdo, 'other_equipment', 'assigned_id')) {
    $pdo->exec('ALTER TABLE other_equipment ADD COLUMN assigned_id INT NULL');
}
supplies_data_ensure_schema($pdo);

$draw = (int) ($_GET['draw'] ?? 1);
$start = max(0, (int) ($_GET['start'] ?? 0));
$length = (int) ($_GET['length'] ?? 10);
if ($length <= 0 || $length > 100) {
    $length = 10;
}

$search = trim((string) ($_GET['search']['value'] ?? ''));
$statusFilter = trim((string) ($_GET['status_filter'] ?? ''));
$deliveryFilter = trim((string) ($_GET['delivery_filter'] ?? ''));
$assignedFilter = trim((string) ($_GET['assigned_filter'] ?? ''));
$orderColumnIndex = (int) ($_GET['order'][0]['column'] ?? 0);
$orderDir = strtolower((string) ($_GET['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';

$columns = [
    1 => 'oe.code',
    2 => 'oe.old_control_no',
    3 => 'd.description_name',
    4 => 'b.brand_name',
    5 => 'oe.serialno',
    6 => 'oe.status',
    7 => 'oe.delivery_status',
    8 => 'oe.delivered_date',
    9 => 'oe.warranty_date',
    10 => 'oe.eta_date',
];
$orderColumn = $columns[$orderColumnIndex] ?? 'oe.created_at';

$assignmentExistsSql = db_table_exists($pdo, 'assigned_record')
    ? "(
        oe.assigned_id IS NOT NULL
        OR oe.department_id IS NOT NULL
        OR oe.end_user_id IS NOT NULL
        OR EXISTS (
        SELECT 1
        FROM assigned_record ae
        WHERE ae.assigned_id = oe.assigned_id
           OR (TRIM(COALESCE(ae.control_no, '')) <> '' AND (ae.control_no = oe.serialno OR ae.control_no = oe.code))
        )
    )"
    : '0 = 1';

$whereClauses = [];
$params = [];
ensure_record_scope_schema($pdo);
apply_record_scope(
    $pdo,
    $whereClauses,
    $params,
    record_scope_column($pdo, 'other_equipment', 'created_by', 'oe'),
    record_scope_column($pdo, 'other_equipment', 'department_id', 'oe'),
    'supplies_scope'
);
apply_mine_only_scope($whereClauses, $params, record_scope_column($pdo, 'other_equipment', 'created_by', 'oe'), 'supplies_mine');

if ($search !== '') {
    $whereClauses[] = "(
        oe.code LIKE :search
        OR oe.old_control_no LIKE :search
        OR d.description_name LIKE :search
        OR b.brand_name LIKE :search
        OR s.supplier_name LIKE :search
        OR oe.serialno LIKE :search
        OR oe.remarks LIKE :search
        OR oe.status LIKE :search
        OR oe.delivery_status LIKE :search
        OR oe.eta_date LIKE :search
        OR eu.end_user_name LIKE :search
        OR dep.department_name LIKE :search
    )";
    $params['search'] = '%' . $search . '%';
}

if ($statusFilter !== '') {
    $whereClauses[] = 'oe.status = :status_filter';
    $params['status_filter'] = $statusFilter;
}

if ($deliveryFilter !== '') {
    $whereClauses[] = 'oe.delivery_status = :delivery_filter';
    $params['delivery_filter'] = $deliveryFilter;
}

if ($assignedFilter === 'assigned') {
    $whereClauses[] = $assignmentExistsSql;
} elseif ($assignedFilter === 'unassigned') {
    $whereClauses[] = 'NOT ' . $assignmentExistsSql;
}

$whereSql = empty($whereClauses) ? '' : 'WHERE ' . implode(' AND ', $whereClauses);

$totalRecords = (int) $pdo->query('SELECT COUNT(*) FROM other_equipment')->fetchColumn();

$filteredStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM other_equipment oe
    LEFT JOIN list_description d ON d.id = oe.description_id
    LEFT JOIN list_brand b ON b.id = oe.brand_id
    LEFT JOIN list_suppliers s ON s.id = oe.supplier_id
    LEFT JOIN list_end_user eu ON eu.end_user_id = oe.end_user_id
    LEFT JOIN list_department dep ON dep.department_id = oe.department_id
    {$whereSql}
");
foreach ($params as $key => $value) {
    $filteredStmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
}
$filteredStmt->execute();
$totalFiltered = (int) $filteredStmt->fetchColumn();

$dataStmt = $pdo->prepare("
    SELECT
        oe.*,
        d.description_name,
        b.brand_name,
        s.supplier_name,
        eu.end_user_name,
        dep.department_name,
        CASE WHEN {$assignmentExistsSql} THEN 1 ELSE 0 END AS is_assigned
    FROM other_equipment oe
    LEFT JOIN list_description d ON d.id = oe.description_id
    LEFT JOIN list_brand b ON b.id = oe.brand_id
    LEFT JOIN list_suppliers s ON s.id = oe.supplier_id
    LEFT JOIN list_end_user eu ON eu.end_user_id = oe.end_user_id
    LEFT JOIN list_department dep ON dep.department_id = oe.department_id
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

$canEdit = can('ictd.supplies.edit');
$canDelete = can('ictd.supplies.delete');
$statusOptions = db_table_exists($pdo, 'list_equipment_status')
    ? ($pdo->query('SELECT equipment_status FROM list_equipment_status ORDER BY equipment_status ASC')->fetchAll(PDO::FETCH_COLUMN) ?: [])
    : [];
$rows = [];

foreach ($dataStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $id = (int) ($row['id'] ?? 0);
    $isAssigned = (int) ($row['is_assigned'] ?? 0) === 1;
    $actions = [
        '<button type="button" class="supplies-action-btn supplies-action-view view-supply-btn" data-id="' . $id . '" title="View"><i class="bi bi-eye"></i> View</button>',
    ];

    if ($canEdit) {
        $actions[] = '<button type="button" class="supplies-action-btn supplies-action-edit edit-supply-btn" data-id="' . $id . '" title="Edit"><i class="bi bi-pencil-square"></i> Edit</button>';
    }

    if ($canDelete) {
        $actions[] = '<button type="button" class="supplies-action-btn supplies-action-delete delete-supply-btn" data-id="' . $id . '" title="Move to Archive"><i class="bi bi-archive"></i> Archive</button>';
    }

    $assignedBadge = $isAssigned
        ? '<div class="supplies-assigned-note"><i class="bi bi-check-circle-fill"></i> Assigned</div>'
        : '';

    $currentStatus = trim((string) ($row['status'] ?? ''));
    if ($currentStatus !== '' && !in_array($currentStatus, $statusOptions, true)) {
        $statusOptions[] = $currentStatus;
    }
    $statusCell = supplies_badge($currentStatus, strtolower($currentStatus) === 'for repair' ? 'warning' : 'secondary');
    if ($canEdit) {
        $options = '<option value="">No Status</option>';
        foreach ($statusOptions as $statusOption) {
            $selected = $currentStatus === $statusOption ? ' selected' : '';
            $options .= '<option value="' . e($statusOption) . '"' . $selected . '>' . e($statusOption) . '</option>';
        }
        $statusCell = '<select class="form-select form-select-sm supplies-inline-status" data-id="' . $id . '">' . $options . '</select>';
    }

    $deliveryType = strtolower((string) ($row['delivery_status'] ?? '')) === 'delivered' ? 'success' : 'warning';
    $rows[] = [
        '<span class="supplies-actions">' . implode('', $actions) . '</span>',
        '<span class="supplies-code">' . e($row['code'] ?? '') . '</span>' . $assignedBadge,
        e($row['old_control_no'] ?? ''),
        '<span class="fw-semibold">' . e($row['description_name'] ?? '') . '</span>',
        e($row['brand_name'] ?? ''),
        e($row['serialno'] ?? ''),
        $statusCell,
        supplies_badge((string) ($row['delivery_status'] ?? ''), $deliveryType),
        e($row['delivered_date'] ?? ''),
        supplies_warranty_markup($row['warranty_date'] ?? null),
        e($row['eta_date'] ?? ''),
        (int) ($row['is_assigned'] ?? 0),
    ];
}

supplies_data_response([
    'draw' => $draw,
    'recordsTotal' => $totalRecords,
    'recordsFiltered' => $totalFiltered,
    'data' => $rows,
]);
