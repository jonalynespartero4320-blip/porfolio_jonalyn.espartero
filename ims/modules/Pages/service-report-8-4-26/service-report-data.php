<?php
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_login();
require_permission('ictd.service-report.view');

header('Content-Type: application/json');

function sr_data_response(array $payload): void
{
    echo json_encode($payload);
    exit;
}

if (!db_table_exists($pdo, 'service_report')) {
    sr_data_response([
        'draw' => (int) ($_GET['draw'] ?? 1),
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
    ]);
}
if (!db_column_exists($pdo, 'service_report', 'is_archived')) {
    $pdo->exec('ALTER TABLE service_report ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0');
}
if (!db_column_exists($pdo, 'service_report', 'archived_at')) {
    $pdo->exec('ALTER TABLE service_report ADD COLUMN archived_at DATETIME NULL');
}
if (!db_column_exists($pdo, 'service_report', 'employee_designation_id')) {
    $pdo->exec('ALTER TABLE service_report ADD COLUMN employee_designation_id INT NULL AFTER department_id');
}

$draw = (int) ($_GET['draw'] ?? 1);
$start = max(0, (int) ($_GET['start'] ?? 0));
$length = (int) ($_GET['length'] ?? 10);
if ($length <= 0 || $length > 100) {
    $length = 10;
}
$search = trim((string) ($_GET['search']['value'] ?? ''));
$archive = (int) ($_GET['archive'] ?? $_POST['archive'] ?? 0);
$orderColumnIndex = (int) ($_GET['order'][0]['column'] ?? 0);
$orderDir = strtolower((string) ($_GET['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';

$columns = [
    2 => 'sr.id',
    3 => 'sr.service_controlno',
    4 => 'sr.old_service_cn',
    5 => 'eu.end_user_name',
    6 => 'COALESCE(edes.department_name, dep.department_name)',
    7 => 'sr.report_date',
    8 => 'sr.report_time',
    9 => 'sr.employee_satisfactory',
    10 => 'item_count',
    11 => $archive ? 'sr.archived_at' : 'sr.created_at',
];
$orderColumn = $columns[$orderColumnIndex] ?? 'sr.created_at';

$where = ['COALESCE(sr.is_archived, 0) = :archive'];
$params = ['archive' => $archive];
ensure_record_scope_schema($pdo);
apply_record_scope(
    $pdo,
    $where,
    $params,
    record_scope_column($pdo, 'service_report', 'created_by', 'sr'),
    record_scope_column($pdo, 'service_report', 'department_id', 'sr'),
    'service_scope'
);
apply_mine_only_scope($where, $params, record_scope_column($pdo, 'service_report', 'created_by', 'sr'), 'service_mine');
if ($search !== '') {
    $where[] = "(
        sr.service_controlno LIKE :search
        OR sr.old_service_cn LIKE :search
        OR eu.end_user_name LIKE :search
        OR dep.department_name LIKE :search
        OR edes.department_name LIKE :search
        OR sr.vicinity LIKE :search
        OR sr.complain LIKE :search
        OR sr.troubleshoot LIKE :search
        OR sr.attending_technician LIKE :search
    )";
    $params['search'] = '%' . $search . '%';
}
$whereSql = 'WHERE ' . implode(' AND ', $where);

$totalStmt = $pdo->prepare('SELECT COUNT(*) FROM service_report WHERE COALESCE(is_archived, 0) = ?');
$totalStmt->execute([$archive]);
$total = (int) $totalStmt->fetchColumn();

$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM service_report sr
    LEFT JOIN list_end_user eu ON eu.end_user_id = sr.end_user_id
    LEFT JOIN list_department dep ON dep.department_id = sr.department_id
    LEFT JOIN list_department edes ON edes.department_id = sr.employee_designation_id
    {$whereSql}
");
foreach ($params as $key => $value) {
    $countStmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$countStmt->execute();
$filtered = (int) $countStmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT
        sr.*,
        eu.end_user_name,
        dep.department_name,
        edes.department_name AS employee_designation_name,
        COUNT(sri.id) AS item_count
    FROM service_report sr
    LEFT JOIN list_end_user eu ON eu.end_user_id = sr.end_user_id
    LEFT JOIN list_department dep ON dep.department_id = sr.department_id
    LEFT JOIN list_department edes ON edes.department_id = sr.employee_designation_id
    LEFT JOIN service_report_items sri ON sri.service_report_id = sr.id
    {$whereSql}
    GROUP BY sr.id
    ORDER BY {$orderColumn} {$orderDir}
    LIMIT :start, :length
");
foreach ($params as $key => $value) {
    $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->bindValue(':start', $start, PDO::PARAM_INT);
$stmt->bindValue(':length', $length, PDO::PARAM_INT);
$stmt->execute();

$canEdit = can('ictd.service-report.view');
$rows = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $id = (int) ($row['id'] ?? 0);
    $pdfPath = trim((string) ($row['attachment_pdf'] ?? ''));
    $pdfUrl = $pdfPath !== '' ? BASE_URL . '/' . ltrim($pdfPath, '/') : '';
    $pdfName = $pdfPath !== '' ? basename($pdfPath) : '';
    $uploadColumn = '<div class="ims-upload-panel">'
        . ($pdfPath !== ''
            ? '<a href="' . e($pdfUrl) . '" target="_blank" class="ims-upload-file-link" title="' . e($pdfName) . '"><i class="bi bi-check-circle"></i> ' . e($pdfName) . '</a>'
            : '<div class="ims-upload-empty">No uploaded file</div>')
        . '<span class="ims-upload-actions">'
        . ($pdfPath !== ''
            ? '<button type="button" class="ims-upload-btn ims-upload-btn-blue ims-upload-square preview-service-pdf" data-pdf="' . e($pdfUrl) . '" title="View PDF"><i class="bi bi-eye-fill"></i> View</button>'
              . '<a href="' . e($pdfUrl) . '" download class="ims-upload-btn ims-upload-btn-gray ims-upload-square" title="Download PDF"><i class="bi bi-download"></i> Download</a>'
              . (!$archive ? '<button type="button" class="ims-upload-btn ims-upload-btn-red ims-upload-square remove-service-pdf" data-id="' . $id . '" title="Remove PDF"><i class="bi bi-trash3"></i> Delete</button>' : '')
            : '')
        . '</span>'
        . (!$archive
            ? '<input type="file" class="ims-upload-input service-pdf-input" data-id="' . $id . '" accept="application/pdf">'
              . '<button type="button" class="ims-upload-btn ims-upload-submit upload-service-pdf-btn" data-id="' . $id . '">Upload File</button>'
            : '')
        . '</div>';
    $exportUrl = BASE_URL . '/ictd/service-report-export?id=' . $id . '&pdf=1';
    $pdfAction = '<a href="' . e($exportUrl) . '" target="_blank" class="service-action-btn service-action-pdf" title="Print / Export PDF"><i class="bi bi-file-earmark-pdf"></i> PDF</a>';
    $actions = $archive
        ? [
            $pdfAction,
            '<button type="button" class="service-action-btn text-success restore-service-btn" data-id="' . $id . '" title="Restore"><i class="bi bi-arrow-counterclockwise"></i> Restore</button>',
            '<button type="button" class="service-action-btn service-action-delete permanent-delete-service-btn" data-id="' . $id . '" title="Permanently Delete"><i class="bi bi-trash3"></i> Delete</button>',
        ]
        : [
            '<button type="button" class="service-action-btn service-action-toggle toggle-service-items-btn" data-id="' . $id . '" title="Show items"><i class="bi bi-chevron-right"></i></button>',
            '<button type="button" class="service-action-btn service-action-view view-service-btn" data-id="' . $id . '" title="View"><i class="bi bi-eye"></i> View</button>',
            '<button type="button" class="service-action-btn service-action-edit edit-service-btn" data-id="' . $id . '" title="Edit"><i class="bi bi-pencil-square"></i> Edit</button>',
            $pdfAction,
            '<button type="button" class="service-action-btn service-action-delete delete-service-btn" data-id="' . $id . '" title="Archive"><i class="bi bi-archive"></i> Archive</button>',
        ];

    $rows[] = [
        '<span class="service-actions">' . implode('', array_filter($actions)) . '</span>',
        $uploadColumn,
        $id,
        '<span class="service-control">' . e($row['service_controlno'] ?? '') . '</span>',
        e($row['old_service_cn'] ?? ''),
        e($row['end_user_name'] ?? ''),
        e($row['employee_designation_name'] ?? $row['department_name'] ?? ''),
        e($row['report_date'] ?? ''),
        e($row['report_time'] ?? ''),
        e($row['employee_satisfactory'] ?? ''),
        $archive
            ? '<span class="service-badge bg-info-subtle text-info">' . (int) ($row['item_count'] ?? 0) . ' item(s)</span>'
            : '<button type="button" class="service-item-toggle toggle-service-items-btn" data-id="' . $id . '"><span class="service-badge bg-info-subtle text-info">' . (int) ($row['item_count'] ?? 0) . ' item(s)</span></button>',
        e($archive ? ($row['archived_at'] ?? '') : ($row['created_at'] ?? '')),
    ];
}

sr_data_response([
    'draw' => $draw,
    'recordsTotal' => $total,
    'recordsFiltered' => $filtered,
    'data' => $rows,
]);
