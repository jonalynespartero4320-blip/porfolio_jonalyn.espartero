<?php
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_login();
require_permission('ictd.transmittal.view');
header('Content-Type: application/json');

function td_response(array $payload): void
{
    echo json_encode($payload);
    exit;
}

function td_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

if (!db_table_exists($pdo, 'transmittal')) {
    td_response(['draw' => (int) ($_REQUEST['draw'] ?? 1), 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []]);
}

foreach ([
    'created_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
    'designation_id' => 'INT NULL AFTER department_id',
    'is_archived' => 'TINYINT(1) NOT NULL DEFAULT 0',
    'archived_at' => 'DATETIME NULL',
] as $column => $definition) {
    if (!td_column_exists($pdo, 'transmittal', $column)) {
        $pdo->exec("ALTER TABLE transmittal ADD COLUMN {$column} {$definition}");
    }
}
$pdo->exec("
    CREATE TABLE IF NOT EXISTS transmittal_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        transmittal_id VARCHAR(100) NULL,
        transmittal_pk INT NOT NULL,
        item_type VARCHAR(30) NOT NULL DEFAULT 'equipment',
        source_id INT NULL,
        control_no VARCHAR(100) NULL,
        old_control_no VARCHAR(100) NULL,
        description_id INT NULL,
        brand_id INT NULL,
        serialno VARCHAR(100) NULL,
        remarks TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_transmittal_items_report (transmittal_pk),
        INDEX idx_transmittal_items_source (item_type, source_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
foreach ([
    'transmittal_pk' => 'INT NULL AFTER transmittal_id',
    'item_type' => "VARCHAR(30) NOT NULL DEFAULT 'manual' AFTER transmittal_pk",
    'source_id' => 'INT NULL AFTER item_type',
    'equipment_id' => 'INT NULL AFTER source_id',
    'control_no' => 'VARCHAR(100) NULL AFTER source_id',
    'old_control_no' => 'VARCHAR(100) NULL AFTER control_no',
    'description' => 'TEXT NULL AFTER old_control_no',
    'brand' => 'VARCHAR(255) NULL AFTER description',
    'qty' => 'INT NULL DEFAULT 1 AFTER brand',
    'description_id' => 'INT NULL AFTER qty',
    'brand_id' => 'INT NULL AFTER description_id',
    'serialno' => 'VARCHAR(100) NULL AFTER brand_id',
    'remarks' => 'TEXT NULL AFTER serialno',
] as $column => $definition) {
    if (!td_column_exists($pdo, 'transmittal_items', $column)) {
        $pdo->exec("ALTER TABLE transmittal_items ADD COLUMN {$column} {$definition}");
    }
}
$pdo->exec("
    CREATE TABLE IF NOT EXISTS transmittal_equipment (
        id INT AUTO_INCREMENT PRIMARY KEY,
        transmittal_id VARCHAR(100) NOT NULL,
        equipment_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_transmittal_equipment_id (transmittal_id),
        INDEX idx_transmittal_equipment_equipment (equipment_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$archive = (int) ($_REQUEST['archive'] ?? 0);
$draw = (int) ($_REQUEST['draw'] ?? 1);
$start = max(0, (int) ($_REQUEST['start'] ?? 0));
$length = (int) ($_REQUEST['length'] ?? 10);
if ($length <= 0 || $length > 100) {
    $length = 10;
}
$search = trim((string) ($_REQUEST['search']['value'] ?? ''));
$orderColumnIndex = (int) ($_REQUEST['order'][0]['column'] ?? 0);
$orderDir = strtolower((string) ($_REQUEST['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
$columns = [
    0 => 't.id',
    1 => 't.attachment_pdf',
    2 => 't.transmittal_id',
    3 => 'dep.department_name',
    4 => 't.accountable_officer',
    5 => 't.transmittal_date',
    6 => 't.received_by',
    7 => 't.date_received',
    8 => 'item_count',
    9 => $archive ? 't.archived_at' : 't.created_at',
];
$orderColumn = $columns[$orderColumnIndex] ?? ($archive ? 't.archived_at' : 't.created_at');

$where = ['COALESCE(t.is_archived, 0) = :archive'];
$params = ['archive' => $archive];
ensure_record_scope_schema($pdo);
apply_record_scope(
    $pdo,
    $where,
    $params,
    record_scope_column($pdo, 'transmittal', 'created_by', 't'),
    record_scope_column($pdo, 'transmittal', 'department_id', 't'),
    'transmittal_scope'
);
apply_mine_only_scope($where, $params, record_scope_column($pdo, 'transmittal', 'created_by', 't'), 'transmittal_mine');
if ($search !== '') {
    $where[] = "(t.transmittal_id LIKE :search OR dep.department_name LIKE :search OR des.department_name LIKE :search OR t.accountable_officer LIKE :search OR t.received_by LIKE :search)";
    $params['search'] = '%' . $search . '%';
}
$whereSql = 'WHERE ' . implode(' AND ', $where);

$totalStmt = $pdo->prepare('SELECT COUNT(*) FROM transmittal t WHERE COALESCE(t.is_archived, 0) = ?');
$totalStmt->execute([$archive]);
$total = (int) $totalStmt->fetchColumn();

$countStmt = $pdo->prepare("
    SELECT COUNT(DISTINCT t.id)
    FROM transmittal t
    LEFT JOIN list_department dep ON dep.department_id = t.department_id
    LEFT JOIN list_department des ON des.department_id = t.designation_id
    {$whereSql}
");
foreach ($params as $key => $value) {
    $countStmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$countStmt->execute();
$filtered = (int) $countStmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT
        t.*,
        dep.department_name,
        des.department_name AS designation_name,
        (
            SELECT COUNT(DISTINCT CASE
                WHEN ti.item_type IN ('equipment', 'supply') AND ti.source_id IS NOT NULL THEN CONCAT(ti.item_type, ':', ti.source_id)
                WHEN ti.source_id IS NULL AND COALESCE(ti.description, ti.brand, ti.serialno, ti.remarks) IS NOT NULL THEN CONCAT('manual:', ti.id)
                ELSE CONCAT('manual:', ti.id)
            END)
            FROM transmittal_items ti
            WHERE ti.transmittal_pk = t.id OR ti.transmittal_id = t.transmittal_id
        ) + (
            SELECT COUNT(DISTINCT te.equipment_id)
            FROM transmittal_equipment te
            WHERE te.transmittal_id = t.transmittal_id
              AND NOT EXISTS (
                  SELECT 1
                  FROM transmittal_items ti2
                  WHERE ti2.item_type = 'equipment'
                    AND ti2.source_id = te.equipment_id
                    AND (ti2.transmittal_pk = t.id OR ti2.transmittal_id = t.transmittal_id)
              )
        ) AS item_count
    FROM transmittal t
    LEFT JOIN list_department dep ON dep.department_id = t.department_id
    LEFT JOIN list_department des ON des.department_id = t.designation_id
    {$whereSql}
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
    $document = '<div class="ims-upload-panel">'
        . ($pdfPath !== ''
            ? '<a href="' . e($pdfUrl) . '" target="_blank" class="ims-upload-file-link" title="' . e($pdfName) . '"><i class="bi bi-check-circle"></i> ' . e($pdfName) . '</a>'
            : '<div class="ims-upload-empty">No uploaded file</div>')
        . '<span class="ims-upload-actions">'
        . ($pdfPath !== ''
            ? '<button type="button" class="ims-upload-btn ims-upload-btn-blue ims-upload-square preview-transmittal-pdf" data-pdf="' . e($pdfUrl) . '" title="View PDF"><i class="bi bi-eye-fill"></i> View</button>'
              . '<a href="' . e($pdfUrl) . '" download class="ims-upload-btn ims-upload-btn-gray ims-upload-square" title="Download PDF"><i class="bi bi-download"></i> Download</a>'
              . (!$archive ? '<button type="button" class="ims-upload-btn ims-upload-btn-red ims-upload-square remove-transmittal-pdf" data-id="' . $id . '" title="Remove PDF"><i class="bi bi-trash3"></i> Delete</button>' : '')
            : '')
        . '</span>'
        . (!$archive
            ? '<input type="file" class="ims-upload-input transmittal-pdf-input" data-id="' . $id . '" accept="application/pdf">'
              . '<button type="button" class="ims-upload-btn ims-upload-submit upload-transmittal-pdf-btn" data-id="' . $id . '">Upload File</button>'
            : '')
        . '</div>';

    $exportUrl = BASE_URL . '/ictd/transmittal-export?id=' . $id . '&pdf=1';
    $pdfAction = '<a href="' . e($exportUrl) . '" target="_blank" class="transmittal-action-btn text-danger" title="Print / Export PDF"><i class="bi bi-file-earmark-pdf"></i> PDF</a>';

    if ($archive) {
        $actions = '<span class="transmittal-actions">'
            . $pdfAction
            . '<button type="button" class="transmittal-action-btn text-success restore-transmittal-btn" data-id="' . $id . '" title="Restore"><i class="bi bi-arrow-counterclockwise"></i> Restore</button>'
            . '<button type="button" class="transmittal-action-btn text-danger permanent-delete-transmittal-btn" data-id="' . $id . '" title="Permanently Delete"><i class="bi bi-trash3"></i> Delete</button>'
            . '</span>';
    } else {
        $actions = '<span class="transmittal-actions">'
            . '<button type="button" class="transmittal-action-btn text-primary toggle-transmittal-items-btn" data-id="' . $id . '" title="Show items"><i class="bi bi-chevron-right"></i></button>'
            . '<button type="button" class="transmittal-action-btn text-success view-transmittal-btn" data-id="' . $id . '" title="View"><i class="bi bi-eye"></i> View</button>'
            . '<button type="button" class="transmittal-action-btn text-info edit-transmittal-btn" data-id="' . $id . '" title="Edit"><i class="bi bi-pencil-square"></i> Edit</button>'
            . $pdfAction
            . '<button type="button" class="transmittal-action-btn text-warning archive-transmittal-btn" data-id="' . $id . '" title="Archive"><i class="bi bi-archive"></i> Archive</button>'
            . '</span>';
    }

    $rows[] = [
        $actions,
        $document,
        '<strong>' . e($row['transmittal_id'] ?? '') . '</strong>',
        e($row['department_name'] ?? ''),
        e($row['accountable_officer'] ?? ''),
        e($row['transmittal_date'] ?? ''),
        e($row['received_by'] ?? ''),
        e($row['date_received'] ?? ''),
        $archive
            ? '<span class="badge bg-info-subtle text-info">' . (int) ($row['item_count'] ?? 0) . ' item(s)</span>'
            : '<button type="button" class="transmittal-item-toggle toggle-transmittal-items-btn" data-id="' . $id . '"><span class="badge bg-info-subtle text-info">' . (int) ($row['item_count'] ?? 0) . ' item(s)</span></button>',
        e($archive ? ($row['archived_at'] ?? '') : ($row['created_at'] ?? '')),
    ];
}

td_response(['draw' => $draw, 'recordsTotal' => $total, 'recordsFiltered' => $filtered, 'data' => $rows]);
