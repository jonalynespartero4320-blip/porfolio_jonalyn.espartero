<?php
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_login();
require_permission('ictd.assign.view');

header('Content-Type: application/json');

function assign_data_response(array $payload): void
{
    echo json_encode($payload);
    exit;
}

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(static function (Throwable $exception): void {
    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code(200);
    }
    echo json_encode([
        'draw' => (int) ($_GET['draw'] ?? 1),
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => 'Unable to load assignments: ' . $exception->getMessage(),
    ]);
    exit;
});

if (!db_table_exists($pdo, 'assigned_record')) {
    assign_data_response([
        'draw' => (int) ($_GET['draw'] ?? 1),
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
    ]);
}
if (!db_column_exists($pdo, 'assigned_record', 'attachment_pdf')) {
    $pdo->exec('ALTER TABLE assigned_record ADD COLUMN attachment_pdf VARCHAR(255) NULL');
}
if (db_table_exists($pdo, 'other_equipment') && !db_column_exists($pdo, 'other_equipment', 'assigned_id')) {
    $pdo->exec('ALTER TABLE other_equipment ADD COLUMN assigned_id INT NULL');
}
$pdo->exec("
    CREATE TABLE IF NOT EXISTS assigned_record_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        assigned_id INT NOT NULL,
        item_type VARCHAR(30) NOT NULL,
        source_id INT NULL,
        control_no VARCHAR(100) NULL,
        serialno VARCHAR(100) NULL,
        item_status VARCHAR(100) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ari_assigned (assigned_id),
        INDEX idx_ari_source (item_type, source_id),
        INDEX idx_ari_control (control_no)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
$existingItems = (int) $pdo->query('SELECT COUNT(*) FROM assigned_record_items')->fetchColumn();
if ($existingItems === 0) {
    $legacyRows = $pdo->query("
        SELECT
            ar.assigned_id,
            CASE WHEN ar.equipment_id IS NOT NULL AND ar.equipment_id > 0 THEN 'equipment' ELSE 'supply' END AS item_type,
            CASE WHEN ar.equipment_id IS NOT NULL AND ar.equipment_id > 0 THEN ar.equipment_id ELSE oe.id END AS source_id,
            ar.control_no,
            COALESCE(e.serialno, oe.serialno) AS serialno,
            COALESCE(es.equipment_status, oe.status) AS item_status
        FROM assigned_record ar
        LEFT JOIN equipment e ON e.id = ar.equipment_id OR e.control_no = ar.control_no OR e.serialno = ar.control_no
        LEFT JOIN list_equipment_status es ON es.id = e.equipment_status_id
        LEFT JOIN other_equipment oe ON oe.assigned_id = ar.assigned_id OR oe.serialno = ar.control_no OR oe.code = ar.control_no
        WHERE TRIM(COALESCE(ar.control_no, '')) <> ''
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $legacyInsert = $pdo->prepare('INSERT INTO assigned_record_items (assigned_id, item_type, source_id, control_no, serialno, item_status) VALUES (?, ?, ?, ?, ?, ?)');
    foreach ($legacyRows as $legacy) {
        $legacyInsert->execute([
            (int) $legacy['assigned_id'],
            $legacy['item_type'] ?: 'equipment',
            is_numeric($legacy['source_id'] ?? null) ? (int) $legacy['source_id'] : null,
            trim((string) ($legacy['control_no'] ?? '')) ?: null,
            trim((string) ($legacy['serialno'] ?? '')) ?: null,
            trim((string) ($legacy['item_status'] ?? '')) ?: null,
        ]);
    }
}

$draw = (int) ($_GET['draw'] ?? 1);
$start = max(0, (int) ($_GET['start'] ?? 0));
$length = (int) ($_GET['length'] ?? 10);
if ($length <= 0 || $length > 100) {
    $length = 10;
}

$search = trim((string) ($_GET['search']['value'] ?? ''));
$orderColumnIndex = (int) ($_GET['order'][0]['column'] ?? 0);
$orderDir = strtolower((string) ($_GET['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';

$columns = [
    2 => 'ar.assigned_id',
    3 => 'eu.end_user_name',
    4 => 'dep.department_name',
    5 => 'ar.control_no',
    6 => 'ar.connection_ip',
    7 => 'ar.internet',
    8 => 'ar.wifi',
    9 => 'ar.assigned_date',
    10 => 'ar.months_since_assigned',
    11 => 'ar.created_at',
];
$orderColumn = $columns[$orderColumnIndex] ?? 'ar.created_at';

$whereClauses = [];
$params = [];
ensure_record_scope_schema($pdo);
apply_record_scope(
    $pdo,
    $whereClauses,
    $params,
    record_scope_column($pdo, 'assigned_record', 'created_by', 'ar'),
    record_scope_column($pdo, 'assigned_record', 'department_id', 'ar'),
    'assign_scope'
);
apply_mine_only_scope($whereClauses, $params, record_scope_column($pdo, 'assigned_record', 'created_by', 'ar'), 'assign_mine');
if ($search !== '') {
    $whereClauses[] = "(
        eu.end_user_name LIKE :search
        OR dep.department_name LIKE :search
        OR ar.control_no LIKE :search
        OR ari.control_no LIKE :search
        OR ari.serialno LIKE :search
        OR ar.connection_ip LIKE :search
        OR ar.program_installed LIKE :search
        OR ar.equipment_analysis LIKE :search
    )";
    $params['search'] = '%' . $search . '%';
}
$whereSql = empty($whereClauses) ? '' : 'WHERE ' . implode(' AND ', $whereClauses);

$total = (int) $pdo->query('SELECT COUNT(*) FROM assigned_record')->fetchColumn();

$filteredStmt = $pdo->prepare("
    SELECT COUNT(DISTINCT ar.assigned_id)
    FROM assigned_record ar
    LEFT JOIN list_end_user eu ON eu.end_user_id = ar.end_user_id
    LEFT JOIN list_department dep ON dep.department_id = ar.department_id
    LEFT JOIN assigned_record_items ari ON ari.assigned_id = ar.assigned_id
    {$whereSql}
");
foreach ($params as $key => $value) {
    $filteredStmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
}
$filteredStmt->execute();
$filtered = (int) $filteredStmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT
        ar.*,
        eu.end_user_name,
        dep.department_name,
        COUNT(ari.id) AS item_count,
        GROUP_CONCAT(DISTINCT CONCAT(UPPER(LEFT(ari.item_type, 1)), SUBSTRING(ari.item_type, 2)) ORDER BY ari.item_type SEPARATOR ', ') AS item_types,
        GROUP_CONCAT(ari.control_no ORDER BY ari.id SEPARATOR ', ') AS item_controls
    FROM assigned_record ar
    LEFT JOIN list_end_user eu ON eu.end_user_id = ar.end_user_id
    LEFT JOIN list_department dep ON dep.department_id = ar.department_id
    LEFT JOIN assigned_record_items ari ON ari.assigned_id = ar.assigned_id
    {$whereSql}
    GROUP BY ar.assigned_id
    ORDER BY {$orderColumn} {$orderDir}
    LIMIT :start, :length
");
foreach ($params as $key => $value) {
    $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
}
$stmt->bindValue(':start', $start, PDO::PARAM_INT);
$stmt->bindValue(':length', $length, PDO::PARAM_INT);
$stmt->execute();

$canModify = can('ictd.assign.create');
$canDelete = can('ictd.assign.delete');
$rows = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $assignedId = (int) $row['assigned_id'];
    $pdfPath = trim((string) ($row['attachment_pdf'] ?? ''));
    $pdfUrl = $pdfPath !== '' ? BASE_URL . '/' . ltrim($pdfPath, '/') : '';
    $pdfName = $pdfPath !== '' ? basename($pdfPath) : '';
    $pdfAction = $pdfUrl !== ''
        ? '<a href="' . e($pdfUrl) . '" target="_blank" class="assign-action-btn assign-action-pdf" title="Print / Export PDF"><i class="bi bi-file-earmark-pdf"></i> PDF</a>'
        : '<button type="button" class="assign-action-btn assign-action-pdf opacity-50" title="No PDF available" disabled><i class="bi bi-file-earmark-pdf"></i> PDF</button>';
    $actions = '<span class="assign-actions">';
    $actions .= '<button type="button" class="assign-action-btn assign-action-toggle toggle-items-btn" data-id="' . $assignedId . '" title="Show assigned items"><i class="bi bi-chevron-right"></i></button>';
    if ($canModify) {
        $actions .= '<button type="button" class="assign-action-btn assign-action-edit edit-record-btn" data-id="' . $assignedId . '" title="Edit"><i class="bi bi-pencil-square"></i> Edit</button>';
        $actions .= '<button type="button" class="assign-action-btn assign-action-reassign reassign-record-btn" data-id="' . $assignedId . '" title="Reassign"><i class="bi bi-arrow-left-right"></i> Reassign</button>';
    }
    $actions .= $pdfAction;
    if ($canDelete) {
        $actions .= '<button type="button" class="assign-action-btn assign-action-delete unassign-record-btn" data-id="' . $assignedId . '" title="Unassign"><i class="bi bi-person-dash"></i> Unassign</button>';
    }
    $actions .= '</span>';
    $uploadColumn = '<div class="ims-upload-panel">'
        . ($pdfPath !== ''
            ? '<a href="' . e($pdfUrl) . '" target="_blank" class="ims-upload-file-link" title="' . e($pdfName) . '"><i class="bi bi-check-circle"></i> ' . e($pdfName) . '</a>'
            : '<div class="ims-upload-empty">No uploaded file</div>')
        . '<span class="ims-upload-actions">'
        . ($pdfPath !== ''
            ? '<button type="button" class="ims-upload-btn ims-upload-btn-blue ims-upload-square preview-assign-pdf" data-pdf="' . e($pdfUrl) . '" title="View PDF"><i class="bi bi-eye-fill"></i> View</button>'
              . '<a href="' . e($pdfUrl) . '" download class="ims-upload-btn ims-upload-btn-gray ims-upload-square" title="Download PDF"><i class="bi bi-download"></i> Download</a>'
              . '<button type="button" class="ims-upload-btn ims-upload-btn-red ims-upload-square remove-assign-pdf" data-id="' . $assignedId . '" title="Remove PDF"><i class="bi bi-trash3"></i> Delete</button>'
            : '')
        . '</span>'
        . '<input type="file" class="ims-upload-input assign-pdf-input" data-id="' . $assignedId . '" accept="application/pdf">'
        . '<button type="button" class="ims-upload-btn ims-upload-submit upload-assign-pdf-btn" data-id="' . $assignedId . '">Upload File</button>'
        . '</div>';

    $rows[] = [
        $actions,
        $uploadColumn,
        $assignedId,
        e($row['end_user_name'] ?? ''),
        e($row['department_name'] ?? ''),
        '<button type="button" class="assign-record-toggle toggle-items-btn" data-id="' . $assignedId . '">'
            . '<span class="assign-type-badge">' . e($row['item_types'] ?: 'Record') . '</span> '
            . e(($row['item_count'] ?? 0) . ' item(s)')
            . '<span class="text-muted ms-1">' . e($row['item_controls'] ?? '') . '</span>'
        . '</button>',
        e($row['connection_ip'] ?? ''),
        e($row['internet'] ?? 'No'),
        e($row['wifi'] ?? 'No'),
        e($row['assigned_date'] ?? ''),
        e($row['months_since_assigned'] ?? ''),
        e($row['created_at'] ?? ''),
    ];
}

assign_data_response([
    'draw' => $draw,
    'recordsTotal' => $total,
    'recordsFiltered' => $filtered,
    'data' => $rows,
]);
