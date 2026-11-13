<?php
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_login();
require_permission('ictd.maintenance.view');

header('Content-Type: application/json');

function mr_data_response(array $payload): void
{
    echo json_encode($payload);
    exit;
}

if (!db_table_exists($pdo, 'maintenance_reports')) {
    mr_data_response([
        'draw' => (int) ($_REQUEST['draw'] ?? 1),
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
    ]);
}

$pdo->exec("
    CREATE TABLE IF NOT EXISTS maintenance_report_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        maintenance_report_id INT NOT NULL,
        item_type VARCHAR(30) NOT NULL DEFAULT 'equipment',
        source_id INT NULL,
        control_no VARCHAR(100) NULL,
        old_control_no VARCHAR(100) NULL,
        description_id INT NULL,
        brand_id INT NULL,
        serialno VARCHAR(100) NULL,
        remarks TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_mr_items_report (maintenance_report_id),
        INDEX idx_mr_items_source (item_type, source_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
if (!db_column_exists($pdo, 'maintenance_reports', 'is_archived')) {
    $pdo->exec('ALTER TABLE maintenance_reports ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0');
}
if (!db_column_exists($pdo, 'maintenance_reports', 'archived_at')) {
    $pdo->exec('ALTER TABLE maintenance_reports ADD COLUMN archived_at DATETIME NULL');
}
if (!db_column_exists($pdo, 'maintenance_reports', 'designation_id')) {
    $pdo->exec('ALTER TABLE maintenance_reports ADD COLUMN designation_id INT NULL AFTER department_id');
}

$draw = (int) ($_REQUEST['draw'] ?? 1);
$start = max(0, (int) ($_REQUEST['start'] ?? 0));
$length = (int) ($_REQUEST['length'] ?? 10);
if ($length <= 0 || $length > 100) {
    $length = 10;
}
$search = trim((string) ($_REQUEST['search']['value'] ?? ''));
$department = trim((string) ($_REQUEST['department'] ?? ''));
$archive = (int) ($_REQUEST['archive'] ?? 0);
$orderColumnIndex = (int) ($_REQUEST['order'][0]['column'] ?? 0);
$orderDir = strtolower((string) ($_REQUEST['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
$hasTechnicalTable = db_table_exists($pdo, 'list_technical');
$technicalIdColumn = $hasTechnicalTable && db_column_exists($pdo, 'list_technical', 'technical_id')
    ? 'technical_id'
    : ($hasTechnicalTable && db_column_exists($pdo, 'list_technical', 'id') ? 'id' : null);
$technicalJoin = $hasTechnicalTable
    ? 'LEFT JOIN list_technical lt ON lt.technical_name = mr.inspected_by' . ($technicalIdColumn ? ' OR CAST(lt.`' . $technicalIdColumn . '` AS CHAR) = CAST(mr.inspected_by AS CHAR)' : '')
    : '';
$technicalSelect = $hasTechnicalTable
    ? 'COALESCE(lt.technical_name, mr.inspected_by) AS inspected_by_name'
    : 'mr.inspected_by AS inspected_by_name';

$columns = [
    0 => 'mr.id',
    2 => 'mr.maintenance_id',
    3 => 'mr.control_no',
    4 => 'primary_serial',
    5 => 'mr.maintenance_status',
    6 => 'mr.branded',
    7 => 'mr.assemble',
    8 => 'eu.end_user_name',
    9 => 'dep.department_name',
    10 => 'mr.date_accomplished',
    11 => $hasTechnicalTable ? 'COALESCE(lt.technical_name, mr.inspected_by)' : 'mr.inspected_by',
    12 => 'mr.date_recorded',
];
$orderColumn = $columns[$orderColumnIndex] ?? 'mr.date_recorded';

$where = ['COALESCE(mr.is_archived, 0) = :archive'];
$params = ['archive' => $archive];
ensure_record_scope_schema($pdo);
apply_record_scope(
    $pdo,
    $where,
    $params,
    record_scope_column($pdo, 'maintenance_reports', 'created_by', 'mr'),
    record_scope_column($pdo, 'maintenance_reports', 'department_id', 'mr'),
    'maintenance_scope'
);
apply_mine_only_scope($where, $params, record_scope_column($pdo, 'maintenance_reports', 'created_by', 'mr'), 'maintenance_mine');
if ($search !== '') {
    $where[] = "(
        mr.maintenance_id LIKE :search
        OR mr.control_no LIKE :search
        OR mri.serialno LIKE :search
        OR eu.end_user_name LIKE :search
        OR dep.department_name LIKE :search
        OR des.department_name LIKE :search
        OR mr.inspected_by LIKE :search
        " . ($hasTechnicalTable ? "OR lt.technical_name LIKE :search" : "") . "
    )";
    $params['search'] = '%' . $search . '%';
}
if ($department !== '') {
    $where[] = 'dep.department_name = :department';
    $params['department'] = $department;
}
$whereSql = 'WHERE ' . implode(' AND ', $where);

$totalStmt = $pdo->prepare('SELECT COUNT(*) FROM maintenance_reports WHERE COALESCE(is_archived, 0) = ?');
$totalStmt->execute([$archive]);
$total = (int) $totalStmt->fetchColumn();

$countStmt = $pdo->prepare("
    SELECT COUNT(DISTINCT mr.id)
    FROM maintenance_reports mr
    LEFT JOIN list_end_user eu ON eu.end_user_id = mr.end_user_id
    LEFT JOIN list_department dep ON dep.department_id = mr.department_id
    LEFT JOIN list_department des ON des.department_id = mr.designation_id
    LEFT JOIN maintenance_report_items mri ON mri.maintenance_report_id = mr.id
    {$technicalJoin}
    {$whereSql}
");
foreach ($params as $key => $value) {
    $countStmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$countStmt->execute();
$filtered = (int) $countStmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT
        mr.*,
        eu.end_user_name,
        dep.department_name,
        des.department_name AS designation_name,
        {$technicalSelect},
        MIN(mri.serialno) AS primary_serial,
        COUNT(DISTINCT mri.id) AS item_count
    FROM maintenance_reports mr
    LEFT JOIN list_end_user eu ON eu.end_user_id = mr.end_user_id
    LEFT JOIN list_department dep ON dep.department_id = mr.department_id
    LEFT JOIN list_department des ON des.department_id = mr.designation_id
    LEFT JOIN maintenance_report_items mri ON mri.maintenance_report_id = mr.id
    {$technicalJoin}
    {$whereSql}
    GROUP BY mr.id
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
    $id = (int) ($row['id'] ?? 0);
    $pdfPath = trim((string) ($row['attachment_pdf'] ?? ''));
    $pdfUrl = $pdfPath !== '' ? BASE_URL . '/' . ltrim($pdfPath, '/') : '';
    $pdfName = $pdfPath !== '' ? basename($pdfPath) : '';
    $pdfButtons = '<div class="ims-upload-panel">'
        . ($pdfPath !== ''
            ? '<a href="' . e($pdfUrl) . '" target="_blank" class="ims-upload-file-link" title="' . e($pdfName) . '"><i class="bi bi-check-circle"></i> ' . e($pdfName) . '</a>'
            : '<div class="ims-upload-empty">No uploaded file</div>')
        . '<span class="ims-upload-actions">'
        . ($pdfPath !== ''
            ? ($archive
                ? '<a href="' . e($pdfUrl) . '" target="_blank" class="ims-upload-btn ims-upload-btn-blue ims-upload-square" title="View PDF"><i class="bi bi-eye-fill"></i> View</a>'
                : '<button type="button" class="ims-upload-btn ims-upload-btn-blue ims-upload-square view-maintenance-pdf" data-pdf="' . e($pdfUrl) . '" title="View PDF"><i class="bi bi-eye-fill"></i> View</button>')
              . '<a href="' . e($pdfUrl) . '" download class="ims-upload-btn ims-upload-btn-gray ims-upload-square" title="Download PDF"><i class="bi bi-download"></i> Download</a>'
              . (!$archive ? '<button type="button" class="ims-upload-btn ims-upload-btn-red ims-upload-square remove-maintenance-pdf" data-id="' . $id . '" title="Remove PDF"><i class="bi bi-trash3"></i> Delete</button>' : '')
            : '')
        . '</span>'
        . (!$archive
            ? '<input type="file" class="ims-upload-input maintenance-pdf-input" data-id="' . $id . '" accept="application/pdf">'
              . '<button type="button" class="ims-upload-btn ims-upload-submit upload-maintenance-pdf-btn" data-id="' . $id . '">Upload File</button>'
            : '')
        . '</div>';

    $status = strtolower((string) ($row['maintenance_status'] ?? ''));
    $statusBadge = '<span class="badge ' . ($status === 'completed' ? 'bg-success' : 'bg-warning text-dark') . '">' . e(ucfirst($status ?: 'incomplete')) . '</span>';
    $exportUrl = BASE_URL . '/ictd/maintenance-export?id=' . $id . '&pdf=1';
    $pdfAction = '<button type="button" class="maintenance-action-btn maintenance-action-pdf export-maintenance-pdf-btn" data-id="' . $id . '" data-export-url="' . e($exportUrl) . '" title="Print / Export PDF"><i class="bi bi-file-earmark-pdf"></i> PDF</button>';
    $actions = $archive
        ? '<span class="maintenance-actions">'
            . $pdfAction
            . '<button type="button" class="maintenance-action-btn text-success restore-maintenance-btn" data-id="' . $id . '" title="Restore"><i class="bi bi-arrow-counterclockwise"></i> Restore</button>'
            . '<button type="button" class="maintenance-action-btn maintenance-action-delete permanent-delete-maintenance-btn" data-id="' . $id . '" title="Permanently Delete"><i class="bi bi-trash3"></i> Delete</button>'
            . '</span>'
        : '<span class="maintenance-actions">'
            . '<button type="button" class="maintenance-action-btn maintenance-action-toggle toggle-maintenance-items-btn" data-id="' . $id . '" title="Show items"><i class="bi bi-chevron-right"></i></button>'
            . '<button type="button" class="maintenance-action-btn maintenance-action-view view-maintenance-btn" data-id="' . $id . '" title="View"><i class="bi bi-eye"></i> View</button>'
            . '<button type="button" class="maintenance-action-btn maintenance-action-edit edit-maintenance-btn" data-id="' . $id . '" title="Edit"><i class="bi bi-pencil-square"></i> Edit</button>'
            . $pdfAction
            . '<button type="button" class="maintenance-action-btn maintenance-action-delete delete-maintenance-btn" data-id="' . $id . '" title="Archive"><i class="bi bi-archive"></i> Archive</button>'
            . '</span>';

    $rows[] = [
        $actions,
        $pdfButtons,
        '<span class="maintenance-control">' . e($row['maintenance_id'] ?? '') . '</span>',
        e($row['control_no'] ?? ''),
        e($row['primary_serial'] ?? ''),
        $statusBadge,
        e($row['branded'] ?? ''),
        e($row['assemble'] ?? ''),
        e($row['end_user_name'] ?? ''),
        e($row['department_name'] ?? ''),
        e($row['date_accomplished'] ?? ''),
        e($row['inspected_by_name'] ?? $row['inspected_by'] ?? ''),
        e($row['date_recorded'] ?? ''),
        $archive
            ? '<span class="badge bg-info-subtle text-info">' . (int) ($row['item_count'] ?? 0) . ' item(s)</span>'
            : '<button type="button" class="maintenance-item-toggle toggle-maintenance-items-btn" data-id="' . $id . '"><span class="badge bg-info-subtle text-info">' . (int) ($row['item_count'] ?? 0) . ' item(s)</span></button>',
    ];
}

mr_data_response([
    'draw' => $draw,
    'recordsTotal' => $total,
    'recordsFiltered' => $filtered,
    'data' => $rows,
]);
