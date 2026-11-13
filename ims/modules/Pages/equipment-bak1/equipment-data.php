<?php
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_login();
require_permission('ictd.equipment.view');

header('Content-Type: application/json');

function equipment_response(array $payload): void
{
    echo json_encode($payload);
    exit;
}

function equipment_warranty_markup(array $row): string
{
    $today = new DateTimeImmutable('today');
    $warrantyEnd = null;

    if (!empty($row['warranty_date'])) {
        $warrantyEnd = DateTimeImmutable::createFromFormat('Y-m-d', substr((string) $row['warranty_date'], 0, 10))
            ?: new DateTimeImmutable((string) $row['warranty_date']);
    } elseif (!empty($row['date_delivered']) && !empty($row['no_yrs_warranty'])) {
        $years = (int) $row['no_yrs_warranty'];
        $deliveryDate = DateTimeImmutable::createFromFormat('Y-m-d', substr((string) $row['date_delivered'], 0, 10))
            ?: new DateTimeImmutable((string) $row['date_delivered']);
        $warrantyEnd = $deliveryDate->modify('+' . $years . ' years');
    }

    if (!$warrantyEnd) {
        return '<span class="equipment-badge bg-secondary-subtle text-secondary">No Info</span>';
    }

    if ($warrantyEnd >= $today) {
        return e($warrantyEnd->format('Y-m-d')) . "<br><span class='equipment-badge bg-success-subtle text-success'>Under Warranty</span>";
    }

    return e($warrantyEnd->format('Y-m-d')) . "<br><span class='equipment-badge bg-danger-subtle text-danger'>Expired</span>";
}

function equipment_delivery_markup(string $value): string
{
    $normalized = strtolower(trim($value));
    if ($normalized === 'delivered') {
        return "<span class='equipment-badge bg-success-subtle text-success'>Delivered</span>";
    }

    return "<span class='equipment-badge bg-warning-subtle text-warning'>To Follow</span>";
}

$action = trim((string) ($_GET['action'] ?? $_POST['action'] ?? 'table'));

if (!db_table_exists($pdo, 'equipment') || !db_table_exists($pdo, 'list_description') || !db_table_exists($pdo, 'list_brand')) {
    equipment_response([
        'draw' => (int) ($_GET['draw'] ?? 1),
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'message' => 'Required equipment tables are missing.',
    ]);
}

if ($action === 'printer_count') {
    $stmt = $pdo->query("
        SELECT COUNT(*) 
        FROM equipment e
        LEFT JOIN list_description d ON d.id = e.description_id
        WHERE LOWER(COALESCE(d.description_name, '')) LIKE '%printer%'
    ");

    equipment_response([
        'success' => true,
        'count' => (int) $stmt->fetchColumn(),
    ]);
}

$draw = (int) ($_GET['draw'] ?? 1);
$start = max(0, (int) ($_GET['start'] ?? 0));
$length = (int) ($_GET['length'] ?? 10);
if ($length <= 0 || $length > 100) {
    $length = 10;
}

$search = trim((string) ($_GET['search']['value'] ?? ''));
$statusFilter = trim((string) ($_GET['status_filter'] ?? ''));
$equipmentStatusFilter = trim((string) ($_GET['equipment_status_filter'] ?? ''));
$orderColumnIndex = (int) ($_GET['order'][0]['column'] ?? 20);
$orderDir = strtolower((string) ($_GET['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
$hasEquipmentStatusColumn = db_column_exists($pdo, 'equipment', 'equipment_status_id');

$columns = [
    1 => 'e.control_no',
    2 => 'e.old_control_no',
    3 => 'd.description_name',
    4 => 'b.brand_name',
    5 => 'e.serialno',
    6 => 'e.warranty_date',
    7 => 'e.delivery_stat',
    8 => $hasEquipmentStatusColumn ? 'es.equipment_status' : 'e.id',
    9 => 'e.price',
    10 => 'e.property_no',
];

$orderColumn = $columns[$orderColumnIndex] ?? 'e.created_at';
$whereClauses = [];
$params = [];
ensure_record_scope_schema($pdo);
apply_record_scope(
    $pdo,
    $whereClauses,
    $params,
    record_scope_column($pdo, 'equipment', 'created_by', 'e'),
    record_scope_column($pdo, 'equipment', 'department_id', 'e'),
    'equipment_scope'
);
apply_mine_only_scope($whereClauses, $params, record_scope_column($pdo, 'equipment', 'created_by', 'e'), 'equipment_mine');
$assignmentExistsSql = db_table_exists($pdo, 'assigned_record')
    ? "(
        e.department_id IS NOT NULL
        OR e.assigned_id IS NOT NULL
        OR EXISTS (
        SELECT 1
        FROM assigned_record ae
        WHERE TRIM(COALESCE(ae.control_no, '')) <> ''
          AND (
            ae.control_no = e.control_no
            OR ae.control_no = e.serialno
          )
        )
    )"
    : '0 = 1';

if ($search !== '') {
    $whereClauses[] = "(
        e.control_no LIKE :search
        OR e.old_control_no LIKE :search
        OR d.description_name LIKE :search
        OR b.brand_name LIKE :search
        OR e.serialno LIKE :search
        OR e.os LIKE :search
        OR e.ms_software LIKE :search
        OR e.processor LIKE :search
        " . ($hasEquipmentStatusColumn ? "OR es.equipment_status LIKE :search" : "") . "
    )";
    $params['search'] = '%' . $search . '%';
}

if ($equipmentStatusFilter !== '' && $hasEquipmentStatusColumn) {
    $whereClauses[] = 'e.equipment_status_id = :equipment_status_filter';
    $params['equipment_status_filter'] = $equipmentStatusFilter;
}

if ($statusFilter !== '') {
    $normalizedStatus = strtolower($statusFilter);

    if ($normalizedStatus === 'delivered') {
        $whereClauses[] = "LOWER(COALESCE(e.delivery_stat, '')) = 'delivered'";
    } elseif ($normalizedStatus === 'to follow') {
        $whereClauses[] = "(LOWER(COALESCE(e.delivery_stat, '')) <> 'delivered' OR e.delivery_stat IS NULL)";
    } elseif ($normalizedStatus === 'under warranty') {
        $whereClauses[] = "(
            e.warranty_date >= CURDATE()
            OR (
                e.warranty_date IS NULL
                AND e.date_delivered IS NOT NULL
                AND e.no_yrs_warranty IS NOT NULL
                AND DATE_ADD(e.date_delivered, INTERVAL e.no_yrs_warranty YEAR) >= CURDATE()
            )
        )";
    } elseif ($normalizedStatus === 'expired') {
        $whereClauses[] = "(
            e.warranty_date < CURDATE()
            OR (
                e.warranty_date IS NULL
                AND e.date_delivered IS NOT NULL
                AND e.no_yrs_warranty IS NOT NULL
                AND DATE_ADD(e.date_delivered, INTERVAL e.no_yrs_warranty YEAR) < CURDATE()
            )
        )";
    }
}

$whereSql = empty($whereClauses) ? '' : 'WHERE ' . implode(' AND ', $whereClauses);

$countStmt = $pdo->query("SELECT COUNT(*) FROM equipment");
$totalRecords = (int) $countStmt->fetchColumn();

$filteredStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM equipment e
    LEFT JOIN list_description d ON d.id = e.description_id
    LEFT JOIN list_brand b ON b.id = e.brand_id
    " . ($hasEquipmentStatusColumn ? "LEFT JOIN list_equipment_status es ON es.id = e.equipment_status_id" : "") . "
    {$whereSql}
");
foreach ($params as $key => $value) {
    $filteredStmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
}
$filteredStmt->execute();
$totalFiltered = (int) $filteredStmt->fetchColumn();

$dataStmt = $pdo->prepare("
    SELECT
        e.*,
        d.description_name,
        b.brand_name,
        " . ($hasEquipmentStatusColumn ? "es.equipment_status" : "NULL AS equipment_status") . ",
        CASE WHEN {$assignmentExistsSql} THEN 1 ELSE 0 END AS is_assigned
    FROM equipment e
    LEFT JOIN list_description d ON d.id = e.description_id
    LEFT JOIN list_brand b ON b.id = e.brand_id
    " . ($hasEquipmentStatusColumn ? "LEFT JOIN list_equipment_status es ON es.id = e.equipment_status_id" : "") . "
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

$canEditEquipment = can('ictd.equipment.edit');
$canDeleteEquipment = can('ictd.equipment.delete');
$equipmentStatusOptions = [];
if ($hasEquipmentStatusColumn && db_table_exists($pdo, 'list_equipment_status')) {
    $equipmentStatusOptions = $pdo
        ->query('SELECT id, equipment_status FROM list_equipment_status ORDER BY equipment_status ASC')
        ->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
$rows = [];

foreach ($dataStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $actions = [];

    $id = (int) ($row['id'] ?? 0);

    if ($canEditEquipment) {
        $actions[] = '<button type="button" class="equipment-action-btn equipment-action-edit edit-equipment-btn" data-id="' . $id . '" title="Edit"><i class="bi bi-pencil-square"></i> Edit</button>';
    }

    if ($canDeleteEquipment) {
        $actions[] = '<button type="button" class="equipment-action-btn equipment-action-delete delete-equipment-btn" data-id="' . $id . '" title="Move to Archive"><i class="bi bi-archive"></i> Archive</button>';
    }

    array_unshift($actions, '<button type="button" class="equipment-action-btn equipment-action-view view-equipment-btn" data-id="' . $id . '" title="View"><i class="bi bi-eye"></i> View</button>');

    $equipmentStatusCell = '<span class="equipment-badge bg-secondary-subtle text-secondary">No Status</span>';
    if ($hasEquipmentStatusColumn) {
        if ($canEditEquipment) {
            $options = '<option value="">No Status</option>';
            foreach ($equipmentStatusOptions as $statusOption) {
                $selected = (string) ($row['equipment_status_id'] ?? '') === (string) $statusOption['id'] ? ' selected' : '';
                $options .= '<option value="' . e($statusOption['id']) . '"' . $selected . '>' . e($statusOption['equipment_status']) . '</option>';
            }

            $equipmentStatusCell = '<select class="form-select form-select-sm equipment-inline-status" data-id="' . $id . '">' . $options . '</select>';
        } else {
            $equipmentStatusCell = '<span class="equipment-badge bg-info-subtle text-info">' . e($row['equipment_status'] ?? 'No Status') . '</span>';
        }
    }

    $assignedBadge = ((int) ($row['is_assigned'] ?? 0) === 1)
        ? '<div class="equipment-assigned-note"><i class="bi bi-check-circle-fill"></i> Assigned</div>'
        : '';

    $rows[] = [
        '<span class="equipment-actions">' . implode('', $actions) . '</span>',
        '<span class="equipment-control">' . e($row['control_no'] ?? '') . '</span>' . $assignedBadge,
        '<span class="equipment-old-control">' . e($row['old_control_no'] ?? '') . '</span>',
        '<span class="fw-semibold">' . e($row['description_name'] ?? '') . '</span>',
        e($row['brand_name'] ?? ''),
        e($row['serialno'] ?? ''),
        equipment_warranty_markup($row),
        equipment_delivery_markup((string) ($row['delivery_stat'] ?? '')),
        $equipmentStatusCell,
        number_format((float) ($row['price'] ?? 0), 2),
        e($row['property_no'] ?? ''),
        (int) ($row['is_assigned'] ?? 0),
    ];
}

equipment_response([
    'draw' => $draw,
    'recordsTotal' => $totalRecords,
    'recordsFiltered' => $totalFiltered,
    'data' => $rows,
]);
