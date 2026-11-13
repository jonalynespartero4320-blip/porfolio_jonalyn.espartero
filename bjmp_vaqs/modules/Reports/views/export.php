<?php
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/functions.php';

require_login();
require_permission('reports.view');

$reportSource = strtolower(trim((string) ($_GET['source'] ?? 'admissions')));
$reportPeriod = strtolower(trim((string) ($_GET['period'] ?? 'daily')));
$filterYear = (int) ($_GET['year'] ?? date('Y'));
$filterMonth = (int) ($_GET['month'] ?? date('n'));
$dateFrom = trim((string) ($_GET['date_from'] ?? date('Y-m-01')));
$dateTo = trim((string) ($_GET['date_to'] ?? date('Y-m-d')));
$filterDorm = (int) ($_GET['dorm'] ?? 0);
$filterPdl = (int) ($_GET['pdl_id'] ?? 0);
$filterGender = trim((string) ($_GET['gender'] ?? ''));
$filterBanStatus = strtolower(trim((string) ($_GET['ban_status'] ?? '')));
$filterViolation = trim((string) ($_GET['violation'] ?? ''));
$selectedColumnsParam = trim((string) ($_GET['columns'] ?? ''));
$customHeadersParam = trim((string) ($_GET['headers'] ?? ''));
$reportTitleParam = trim((string) ($_GET['report_title'] ?? ''));
$format = strtolower(trim((string) ($_GET['format'] ?? 'print')));

$allowedSources = ['admissions', 'registrations', 'id_issuance', 'banned', 'special_cases'];
$allowedPeriods = ['daily', 'monthly', 'yearly', 'custom'];
$allowedFormats = ['print', 'excel', 'pdf'];
$allowedBanStatuses = ['', 'active', 'lifted', 'expired'];

if (!in_array($reportSource, $allowedSources, true)) {
    $reportSource = 'admissions';
}
if (!in_array($reportPeriod, $allowedPeriods, true)) {
    $reportPeriod = 'daily';
}
if (!in_array($format, $allowedFormats, true)) {
    $format = 'print';
}

if (!in_array($filterGender, ['', 'Male', 'Female'], true)) {
    $filterGender = '';
}

if (!in_array($filterBanStatus, $allowedBanStatuses, true)) {
    $filterBanStatus = '';
}

if ($format === 'excel') {
    require_permission('reports.export');
} else {
    require_permission('reports.print');
}

$pdo->exec("
    CREATE TABLE IF NOT EXISTS visitor_ban_history (
        id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        visitor_id INT(11) NOT NULL,
        violation_type VARCHAR(255) NOT NULL,
        banned_by INT(11) DEFAULT NULL,
        lifted_by INT(11) DEFAULT NULL,
        effective_from DATE DEFAULT NULL,
        effective_until DATE DEFAULT NULL,
        lifted_on DATE DEFAULT NULL,
        lift_reason VARCHAR(255) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_visitor_id (visitor_id),
        INDEX idx_effective_until (effective_until)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");
$pdo->exec("ALTER TABLE visitor_ban_history ADD COLUMN IF NOT EXISTS banned_by INT(11) DEFAULT NULL AFTER violation_type");
$pdo->exec("ALTER TABLE visitor_ban_history ADD COLUMN IF NOT EXISTS lifted_by INT(11) DEFAULT NULL AFTER banned_by");
$pdo->exec("ALTER TABLE visitor_ban_history ADD COLUMN IF NOT EXISTS effective_from DATE DEFAULT NULL AFTER lifted_by");
$pdo->exec("ALTER TABLE visitor_ban_history ADD COLUMN IF NOT EXISTS effective_until DATE DEFAULT NULL AFTER effective_from");
$pdo->exec("ALTER TABLE visitor_ban_history ADD COLUMN IF NOT EXISTS lifted_on DATE DEFAULT NULL AFTER effective_until");
$pdo->exec("ALTER TABLE visitor_ban_history ADD COLUMN IF NOT EXISTS lift_reason VARCHAR(255) DEFAULT NULL AFTER lifted_on");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS queue_special_cases (
        id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        firstname VARCHAR(150) NOT NULL,
        middlename VARCHAR(150) DEFAULT NULL,
        lastname VARCHAR(150) NOT NULL,
        age INT(11) DEFAULT NULL,
        gender ENUM('Male','Female','Other') NOT NULL,
        pdl_id INT(11) NOT NULL,
        pdl_name VARCHAR(255) NOT NULL,
        dorm_no VARCHAR(100) DEFAULT NULL,
        address TEXT NOT NULL,
        id_type VARCHAR(100) DEFAULT NULL,
        id_number VARCHAR(150) DEFAULT NULL,
        reason_for_visit TEXT NOT NULL,
        approved_by VARCHAR(150) NOT NULL,
        approved_date DATE NOT NULL,
        status ENUM('approved','queued','inside','completed','rejected') NOT NULL DEFAULT 'approved',
        created_by INT(11) DEFAULT NULL,
        queue_request_id INT(11) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_pdl_id (pdl_id),
        INDEX idx_approved_date (approved_date),
        INDEX idx_queue_request_id (queue_request_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");
$pdo->exec("ALTER TABLE queuing_requests ADD COLUMN IF NOT EXISTS special_case_id INT(11) DEFAULT NULL AFTER visitor_id");
$pdo->exec("ALTER TABLE queue_display ADD COLUMN IF NOT EXISTS special_case_id INT(11) DEFAULT NULL AFTER visitor_id");

$selectedDorm = null;
if ($filterDorm > 0) {
    $dormStmt = $pdo->prepare("
        SELECT id, dorm_no, description
        FROM dorm_masterlist
        WHERE id = ?
        LIMIT 1
    ");
    $dormStmt->execute([$filterDorm]);
    $selectedDorm = $dormStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($selectedDorm === null) {
        $filterDorm = 0;
    }
}

$dormDisplayExpr = "COALESCE((SELECT COALESCE(NULLIF(TRIM(dm.description), ''), NULLIF(TRIM(dm.dorm_no), '')) FROM dorm_masterlist dm WHERE TRIM(CAST(dm.id AS CHAR)) = TRIM(p.dorm_no) OR TRIM(dm.dorm_no) = TRIM(p.dorm_no) LIMIT 1), TRIM(p.dorm_no))";
$pdlNameExpr = "TRIM(CONCAT(COALESCE(p.pdl_fname, ''), ' ', COALESCE(p.pdl_mname, ''), ' ', COALESCE(p.pdl_lname, ''), ' ', COALESCE(p.pdl_suffix, '')))";

$selectedPdl = null;
if ($filterPdl > 0) {
    $pdlStmt = $pdo->prepare("
        SELECT
            p.id,
            p.pdl_id,
            $pdlNameExpr AS pdl_name,
            $dormDisplayExpr AS dorm_name
        FROM pdl_info p
        WHERE p.id = ?
        LIMIT 1
    ");
    $pdlStmt->execute([$filterPdl]);
    $selectedPdl = $pdlStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($selectedPdl === null) {
        $filterPdl = 0;
    }
}

$sourceConfig = [
    'admissions' => [
        'label' => 'Visitor Admissions',
        'date_column' => 'qr.request_time',
        'from' => 'queuing_requests qr',
        'joins' => "LEFT JOIN visitors_registration vr ON vr.id = qr.visitor_id
            LEFT JOIN pdl_info p ON p.id = vr.pdl_id
            LEFT JOIN queue_display qd ON qd.id = (
                SELECT qd2.id
                FROM queue_display qd2
                WHERE qd2.visitor_id = qr.visitor_id
                  AND (
                    qd2.time_in <=> qr.accepted_time
                    OR (qd2.time_in IS NULL AND qr.accepted_time IS NULL)
                  )
                  AND DATE(qd2.display_time) = DATE(qr.request_time)
                ORDER BY qd2.id DESC
                LIMIT 1
            )",
        'where' => '1=1',
        'person_label' => "TRIM(CONCAT(COALESCE(vr.firstname, ''), ' ', COALESCE(vr.middlename, ''), ' ', COALESCE(vr.lastname, ''), ' ', COALESCE(vr.suffix, '')))",
        'detail_visitor_id' => 'vr.id',
        'detail_status' => 'qr.status',
        'detail_gender' => 'p.pdl_sex',
        'detail_pdl_id' => 'p.id',
        'detail_pdl' => $pdlNameExpr,
        'detail_dorm' => $dormDisplayExpr,
        'detail_dorm_key' => 'p.dorm_no',
        'detail_time_in' => 'qd.time_in',
        'detail_time_out' => 'qd.time_out',
        'detail_hours_stayed' => 'qd.hours_stayed',
        'detail_violation' => 'NULL',
        'detail_approved_by' => 'NULL',
        'detail_banned_by' => 'NULL',
        'detail_banned_at' => 'NULL',
        'detail_lifted_by' => 'NULL',
        'detail_lifted_at' => 'NULL',
        'detail_lift_reason' => 'NULL',
    ],
    'registrations' => [
        'label' => 'Visitor Registrations',
        'date_column' => 'vr.created_at',
        'from' => 'visitors_registration vr',
        'joins' => 'LEFT JOIN pdl_info p ON p.id = vr.pdl_id',
        'where' => '1=1',
        'person_label' => "TRIM(CONCAT(COALESCE(vr.firstname, ''), ' ', COALESCE(vr.middlename, ''), ' ', COALESCE(vr.lastname, ''), ' ', COALESCE(vr.suffix, '')))",
        'detail_visitor_id' => 'vr.id',
        'detail_status' => 'vr.visitor_status',
        'detail_gender' => 'p.pdl_sex',
        'detail_pdl_id' => 'p.id',
        'detail_pdl' => $pdlNameExpr,
        'detail_dorm' => $dormDisplayExpr,
        'detail_dorm_key' => 'p.dorm_no',
        'detail_time_in' => 'NULL',
        'detail_time_out' => 'NULL',
        'detail_hours_stayed' => 'NULL',
        'detail_violation' => 'NULL',
        'detail_approved_by' => 'NULL',
        'detail_banned_by' => 'NULL',
        'detail_banned_at' => 'NULL',
        'detail_lifted_by' => 'NULL',
        'detail_lifted_at' => 'NULL',
        'detail_lift_reason' => 'NULL',
    ],
    'id_issuance' => [
        'label' => 'ID Issuance',
        'date_column' => 'vih.issued_at',
        'from' => 'visitor_id_issuance_history vih',
        'joins' => 'LEFT JOIN visitors_registration vr ON vr.id = vih.visitor_id LEFT JOIN pdl_info p ON p.id = vr.pdl_id',
        'where' => '1=1',
        'person_label' => "TRIM(CONCAT(COALESCE(vr.firstname, ''), ' ', COALESCE(vr.middlename, ''), ' ', COALESCE(vr.lastname, ''), ' ', COALESCE(vr.suffix, '')))",
        'detail_visitor_id' => 'vr.id',
        'detail_status' => 'vih.issue_mode',
        'detail_gender' => 'p.pdl_sex',
        'detail_pdl_id' => 'p.id',
        'detail_pdl' => $pdlNameExpr,
        'detail_dorm' => $dormDisplayExpr,
        'detail_dorm_key' => 'p.dorm_no',
        'detail_time_in' => 'NULL',
        'detail_time_out' => 'NULL',
        'detail_hours_stayed' => 'NULL',
        'detail_violation' => 'NULL',
        'detail_approved_by' => 'NULL',
        'detail_banned_by' => 'NULL',
        'detail_banned_at' => 'NULL',
        'detail_lifted_by' => 'NULL',
        'detail_lifted_at' => 'NULL',
        'detail_lift_reason' => 'NULL',
    ],
    'banned' => [
        'label' => 'Banned Visitors',
        'date_column' => 'vbh.created_at',
        'from' => 'visitor_ban_history vbh',
        'joins' => 'LEFT JOIN visitors_registration vr ON vr.id = vbh.visitor_id
            LEFT JOIN pdl_info p ON p.id = vr.pdl_id
            LEFT JOIN users banned_user ON banned_user.id = vbh.banned_by
            LEFT JOIN users lifted_user ON lifted_user.id = vbh.lifted_by',
        'where' => '1=1',
        'person_label' => "TRIM(CONCAT(COALESCE(vr.firstname, ''), ' ', COALESCE(vr.middlename, ''), ' ', COALESCE(vr.lastname, ''), ' ', COALESCE(vr.suffix, '')))",
        'detail_visitor_id' => 'vr.id',
        'detail_status' => "CASE WHEN vbh.lifted_on IS NOT NULL THEN 'Lifted' WHEN vbh.effective_until IS NOT NULL AND vbh.effective_until < CURDATE() THEN 'Expired' ELSE 'Active' END",
        'detail_gender' => 'p.pdl_sex',
        'detail_pdl_id' => 'p.id',
        'detail_pdl' => $pdlNameExpr,
        'detail_dorm' => $dormDisplayExpr,
        'detail_dorm_key' => 'p.dorm_no',
        'detail_time_in' => 'vbh.effective_from',
        'detail_time_out' => 'COALESCE(vbh.lifted_on, vbh.effective_until)',
        'detail_hours_stayed' => 'NULL',
        'detail_violation' => 'vbh.violation_type',
        'detail_approved_by' => 'banned_user.username',
        'detail_banned_by' => 'banned_user.username',
        'detail_banned_at' => 'vbh.created_at',
        'detail_lifted_by' => 'lifted_user.username',
        'detail_lifted_at' => 'vbh.lifted_on',
        'detail_lift_reason' => 'vbh.lift_reason',
    ],
    'special_cases' => [
        'label' => 'Special Case Visits',
        'date_column' => 'qsc.approved_date',
        'from' => 'queue_special_cases qsc',
        'joins' => 'LEFT JOIN pdl_info p ON p.id = qsc.pdl_id
            LEFT JOIN queuing_requests qr ON qr.id = qsc.queue_request_id
            LEFT JOIN queue_display qd ON qd.special_case_id = qsc.id
            LEFT JOIN users accepted_user ON accepted_user.id = qr.accepted_by',
        'where' => '1=1',
        'person_label' => "TRIM(CONCAT(COALESCE(qsc.firstname, ''), ' ', COALESCE(qsc.middlename, ''), ' ', COALESCE(qsc.lastname, '')))",
        'detail_visitor_id' => 'qsc.id',
        'detail_status' => 'qsc.status',
        'detail_gender' => 'p.pdl_sex',
        'detail_pdl_id' => 'qsc.pdl_id',
        'detail_pdl' => 'qsc.pdl_name',
        'detail_dorm' => 'qsc.dorm_no',
        'detail_dorm_key' => 'p.dorm_no',
        'detail_time_in' => 'qd.time_in',
        'detail_time_out' => 'qd.time_out',
        'detail_hours_stayed' => 'qd.hours_stayed',
        'detail_violation' => 'qsc.reason_for_visit',
        'detail_approved_by' => "COALESCE(accepted_user.username, qsc.approved_by)",
        'detail_banned_by' => 'NULL',
        'detail_banned_at' => 'NULL',
        'detail_lifted_by' => 'NULL',
        'detail_lifted_at' => 'NULL',
        'detail_lift_reason' => 'NULL',
    ],
];

function report_export_format_datetime(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '' || $value === '0000-00-00 00:00:00') {
        return '-';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $value;
    }

    return date('M j, Y g:i A', $timestamp);
}

function report_export_format_duration($hoursStayed): string
{
    if ($hoursStayed === null || $hoursStayed === '') {
        return '-';
    }

    $minutes = (int) round(((float) $hoursStayed) * 60);
    if ($minutes <= 0) {
        return '0 mins';
    }

    $hours = intdiv($minutes, 60);
    $remainingMinutes = $minutes % 60;
    $parts = [];

    if ($hours > 0) {
        $parts[] = $hours . ' hr' . ($hours === 1 ? '' : 's');
    }

    if ($remainingMinutes > 0) {
        $parts[] = $remainingMinutes . ' min' . ($remainingMinutes === 1 ? '' : 's');
    }

    return implode(' ', $parts);
}

function report_export_detail_value(string $columnKey, array $row): string
{
    $dateColumns = ['event_at', 'time_in', 'time_out', 'banned_at', 'lifted_at'];
    if (in_array($columnKey, $dateColumns, true)) {
        return report_export_format_datetime($row[$columnKey] ?? null);
    }

    if ($columnKey === 'hours_stayed') {
        return report_export_format_duration($row[$columnKey] ?? null);
    }

    return trim((string) ($row[$columnKey] ?? '')) ?: '-';
}

$config = $sourceConfig[$reportSource];
$reportColumnDefinitions = [
    'event_at' => 'Date / Time',
    'visitor_name' => 'Visitor',
    'visitor_gender' => 'Resident Gender',
    'pdl_name' => 'Resident',
    'dorm_no' => 'Dorm',
    'violation_type' => $reportSource === 'banned' ? 'Violation' : ($reportSource === 'special_cases' ? 'Reason' : 'Remarks'),
    'banned_by' => 'Banned By',
    'banned_at' => 'Banned On',
    'lifted_by' => 'Lifted By',
    'lifted_at' => 'Lifted On',
    'lift_reason' => 'Reason for Lifting',
    'approved_by' => $reportSource === 'special_cases' ? 'Accepted By' : 'Approved By',
    'status_label' => 'Status',
    'time_in' => 'Time In',
    'time_out' => 'Time Out',
    'hours_stayed' => 'Hours Stayed',
];
$defaultColumnsBySource = [
    'banned' => ['event_at', 'visitor_name', 'visitor_gender', 'pdl_name', 'dorm_no', 'violation_type', 'banned_by', 'banned_at', 'lifted_by', 'lifted_at', 'lift_reason', 'status_label'],
    'default' => ['event_at', 'visitor_name', 'visitor_gender', 'pdl_name', 'dorm_no', 'violation_type', 'approved_by', 'status_label', 'time_in', 'time_out', 'hours_stayed'],
];
$requestedColumns = array_values(array_filter(array_map('trim', explode(',', $selectedColumnsParam))));
$selectedReportColumns = array_values(array_filter($requestedColumns, static fn($column) => array_key_exists($column, $reportColumnDefinitions)));
if (empty($selectedReportColumns)) {
    $selectedReportColumns = $defaultColumnsBySource[$reportSource] ?? $defaultColumnsBySource['default'];
}
$decodedHeaders = json_decode($customHeadersParam, true);
$customColumnHeaders = is_array($decodedHeaders) ? $decodedHeaders : [];
$selectedReportHeaders = [];
foreach ($selectedReportColumns as $columnKey) {
    $customHeader = trim((string) ($customColumnHeaders[$columnKey] ?? ''));
    $selectedReportHeaders[$columnKey] = $customHeader !== '' ? $customHeader : $reportColumnDefinitions[$columnKey];
}
$reportTitle = $reportTitleParam !== '' ? substr($reportTitleParam, 0, 120) : ($config['label'] . ' Report');
$dateColumn = $config['date_column'];
$baseFrom = $config['from'];
$baseJoins = $config['joins'];
$baseWhere = $config['where'];

$whereParts = [$baseWhere];
$params = [];

switch ($reportPeriod) {
    case 'daily':
        $whereParts[] = "YEAR($dateColumn) = ?";
        $params[] = $filterYear;
        $whereParts[] = "MONTH($dateColumn) = ?";
        $params[] = $filterMonth;
        break;
    case 'monthly':
        $whereParts[] = "YEAR($dateColumn) = ?";
        $params[] = $filterYear;
        break;
    case 'custom':
        $whereParts[] = "DATE($dateColumn) >= ?";
        $params[] = $dateFrom;
        $whereParts[] = "DATE($dateColumn) <= ?";
        $params[] = $dateTo;
        break;
    case 'yearly':
    default:
        break;
}

if ($selectedDorm !== null) {
    $whereParts[] = "(TRIM(COALESCE({$config['detail_dorm_key']}, '')) = ? OR TRIM(COALESCE({$config['detail_dorm_key']}, '')) = ?)";
    $params[] = (string) ($selectedDorm['id'] ?? '');
    $params[] = trim((string) ($selectedDorm['dorm_no'] ?? ''));
}

if ($filterGender !== '') {
    $whereParts[] = "TRIM(COALESCE({$config['detail_gender']}, '')) = ?";
    $params[] = $filterGender;
}

if ($selectedPdl !== null) {
    $whereParts[] = "{$config['detail_pdl_id']} = ?";
    $params[] = $filterPdl;
}

if ($reportSource === 'banned') {
    if ($filterBanStatus === 'active') {
        $whereParts[] = "vbh.lifted_on IS NULL AND (vbh.effective_until IS NULL OR vbh.effective_until >= CURDATE())";
    } elseif ($filterBanStatus === 'lifted') {
        $whereParts[] = "vbh.lifted_on IS NOT NULL";
    } elseif ($filterBanStatus === 'expired') {
        $whereParts[] = "vbh.lifted_on IS NULL AND vbh.effective_until IS NOT NULL AND vbh.effective_until < CURDATE()";
    }

    if ($filterViolation !== '') {
        $whereParts[] = "vbh.violation_type LIKE ?";
        $params[] = '%' . $filterViolation . '%';
    }
}

$whereSql = implode(' AND ', $whereParts);

$summarySql = "
    SELECT
        COUNT(*) AS total_rows,
        COUNT(DISTINCT {$config['detail_visitor_id']}) AS unique_visitors,
        COUNT(DISTINCT DATE($dateColumn)) AS active_days,
        COUNT(DISTINCT {$config['detail_dorm']}) AS active_dorms,
        COUNT(DISTINCT {$config['detail_pdl']}) AS active_pdl
    FROM $baseFrom
    $baseJoins
    WHERE $whereSql
";
$summaryStmt = $pdo->prepare($summarySql);
$summaryStmt->execute($params);
$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$detailSql = "
    SELECT
        $dateColumn AS event_at,
        {$config['person_label']} AS visitor_name,
        {$config['detail_gender']} AS visitor_gender,
        {$config['detail_pdl']} AS pdl_name,
        {$config['detail_dorm']} AS dorm_no,
        {$config['detail_violation']} AS violation_type,
        {$config['detail_approved_by']} AS approved_by,
        {$config['detail_banned_by']} AS banned_by,
        {$config['detail_banned_at']} AS banned_at,
        {$config['detail_lifted_by']} AS lifted_by,
        {$config['detail_lifted_at']} AS lifted_at,
        {$config['detail_lift_reason']} AS lift_reason,
        {$config['detail_status']} AS status_label,
        {$config['detail_time_in']} AS time_in,
        {$config['detail_time_out']} AS time_out,
        {$config['detail_hours_stayed']} AS hours_stayed
    FROM $baseFrom
    $baseJoins
    WHERE $whereSql
    ORDER BY $dateColumn DESC
";
$detailStmt = $pdo->prepare($detailSql);
$detailStmt->execute($params);
$detailRows = $detailStmt->fetchAll(PDO::FETCH_ASSOC);

$visitorFrequencySql = "
    SELECT
        {$config['person_label']} AS visitor_name,
        {$config['detail_gender']} AS visitor_gender,
        COUNT(*) AS visit_count,
        MIN($dateColumn) AS first_visit,
        MAX($dateColumn) AS last_visit
    FROM $baseFrom
    $baseJoins
    WHERE $whereSql
      AND {$config['detail_visitor_id']} IS NOT NULL
    GROUP BY {$config['detail_visitor_id']}, {$config['person_label']}, {$config['detail_gender']}
    ORDER BY visit_count DESC, last_visit DESC
    LIMIT 10
";
$visitorFrequencyStmt = $pdo->prepare($visitorFrequencySql);
$visitorFrequencyStmt->execute($params);
$visitorFrequencyRows = $visitorFrequencyStmt->fetchAll(PDO::FETCH_ASSOC);

$generatedAt = date('F j, Y g:i A');
$periodText = match ($reportPeriod) {
    'daily' => date('F Y', mktime(0, 0, 0, $filterMonth, 1, $filterYear)),
    'monthly' => (string) $filterYear,
    'yearly' => 'All Years',
    'custom' => $dateFrom . ' to ' . $dateTo,
    default => 'Current Filter',
};
$dormText = $selectedDorm !== null
    ? (trim((string) ($selectedDorm['description'] ?? '')) ?: trim((string) ($selectedDorm['dorm_no'] ?? '')))
    : 'All Dorms';
$genderText = $filterGender !== '' ? $filterGender . ' Resident' : 'All Resident Genders';
$pdlText = $selectedPdl !== null
    ? (trim((string) ($selectedPdl['pdl_name'] ?? '')) ?: 'Selected Resident')
    : 'All Resident';
$banStatusText = $filterBanStatus !== '' ? ucfirst($filterBanStatus) : 'All Ban Records';
$violationText = $filterViolation !== '' ? $filterViolation : 'All Violations';
$detailColumnCount = count($selectedReportColumns) + 1;
$sheetColumnCount = max(12, $detailColumnCount);
$summarySourceColspan = max(1, $sheetColumnCount - 4);

if ($format === 'excel') {
    $filename = 'report_' . $reportSource . '_' . date('Ymd_His') . '.xls';
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    echo '<html><head><meta charset="UTF-8"></head><body>';
    echo '<table border="1">';
    echo '<tr><th colspan="' . $sheetColumnCount . '" style="background:#d9e8f5;font-size:18px;">' . htmlspecialchars($reportTitle) . '</th></tr>';
    echo '<tr><td colspan="' . $sheetColumnCount . '">Generated At: ' . htmlspecialchars($generatedAt) . '</td></tr>';
    echo '<tr><td colspan="' . $sheetColumnCount . '">Period Covered: ' . htmlspecialchars($periodText) . '</td></tr>';
    echo '<tr><td colspan="' . $sheetColumnCount . '">Period Type: ' . htmlspecialchars(ucfirst($reportPeriod)) . '</td></tr>';
    echo '<tr><td colspan="' . $sheetColumnCount . '">Dorm Filter: ' . htmlspecialchars($dormText) . '</td></tr>';
    echo '<tr><td colspan="' . $sheetColumnCount . '">Resident Filter: ' . htmlspecialchars($pdlText) . '</td></tr>';
    echo '<tr><td colspan="' . $sheetColumnCount . '">Resident Gender Filter: ' . htmlspecialchars($genderText) . '</td></tr>';
    if ($reportSource === 'banned') {
        echo '<tr><td colspan="' . $sheetColumnCount . '">Ban Status Filter: ' . htmlspecialchars($banStatusText) . '</td></tr>';
        echo '<tr><td colspan="' . $sheetColumnCount . '">Violation Filter: ' . htmlspecialchars($violationText) . '</td></tr>';
    }
    echo '<tr><td colspan="' . $sheetColumnCount . '"></td></tr>';
    echo '<tr>';
    echo '<th>Total Visits / Records</th><th>Unique Visitors</th><th>Active Days</th><th>Dorm Coverage</th><th colspan="' . $summarySourceColspan . '">Report Source</th>';
    echo '</tr>';
    echo '<tr>';
    echo '<td>' . (int) ($summary['total_rows'] ?? 0) . '</td>';
    echo '<td>' . (int) ($summary['unique_visitors'] ?? 0) . '</td>';
    echo '<td>' . (int) ($summary['active_days'] ?? 0) . '</td>';
    echo '<td>' . (int) ($summary['active_dorms'] ?? 0) . '</td>';
    echo '<td colspan="' . $summarySourceColspan . '">' . htmlspecialchars($config['label']) . '</td>';
    echo '</tr>';
    echo '<tr><td colspan="' . $sheetColumnCount . '"></td></tr>';
    echo '<tr><th colspan="' . $sheetColumnCount . '">Visitor Frequency</th></tr>';
    echo '<tr><th>#</th><th colspan="5">Visitor</th><th>Resident Gender</th><th>Visit Count</th><th colspan="2">First Visit</th><th colspan="2">Last Visit</th></tr>';
    foreach ($visitorFrequencyRows as $index => $row) {
        echo '<tr>';
        echo '<td>' . ($index + 1) . '</td>';
        echo '<td colspan="5">' . htmlspecialchars(trim((string) ($row['visitor_name'] ?? '')) ?: 'Unknown Visitor') . '</td>';
        echo '<td>' . htmlspecialchars(trim((string) ($row['visitor_gender'] ?? '')) ?: '-') . '</td>';
        echo '<td>' . (int) ($row['visit_count'] ?? 0) . '</td>';
        echo '<td colspan="2">' . htmlspecialchars(report_export_format_datetime($row['first_visit'] ?? null)) . '</td>';
        echo '<td colspan="2">' . htmlspecialchars(report_export_format_datetime($row['last_visit'] ?? null)) . '</td>';
        echo '</tr>';
    }
    if (empty($visitorFrequencyRows)) {
        echo '<tr><td colspan="' . $sheetColumnCount . '">No visitor frequency data found for the selected filters.</td></tr>';
    }
    echo '<tr><td colspan="' . $sheetColumnCount . '"></td></tr>';
    echo '<tr>';
    echo '<th>#</th>';
    foreach ($selectedReportColumns as $columnKey) {
        echo '<th>' . htmlspecialchars($selectedReportHeaders[$columnKey] ?? $reportColumnDefinitions[$columnKey]) . '</th>';
    }
    echo '</tr>';
    foreach ($detailRows as $index => $row) {
        echo '<tr>';
        echo '<td>' . ($index + 1) . '</td>';
        foreach ($selectedReportColumns as $columnKey) {
            echo '<td>' . htmlspecialchars(report_export_detail_value($columnKey, $row)) . '</td>';
        }
        echo '</tr>';
    }
    if (empty($detailRows)) {
        echo '<tr><td colspan="' . $detailColumnCount . '">No records found for the selected report filters.</td></tr>';
    }
    echo '</table></body></html>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($reportTitle) ?></title>
    <style>
      body {
        margin: 0;
        font-family: Arial, Helvetica, sans-serif;
        background: #f4f7fb;
        color: #0f172a;
      }
      .report-wrap {
        max-width: 1200px;
        margin: 0 auto;
        padding: 24px;
      }
      .report-sheet {
        background: #fff;
        border-radius: 24px;
        box-shadow: 0 20px 50px rgba(15, 23, 42, 0.08);
        overflow: hidden;
      }
      .report-header {
        padding: 28px 32px;
        color: #fff;
        background:
          linear-gradient(135deg, #062150, #447A61);
      }
      .report-kpi-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
        padding: 24px 32px 0;
      }
      .report-kpi {
        border: 1px solid #dbe5ef;
        border-radius: 18px;
        padding: 16px;
        background: #f8fbff;
      }
      .report-kpi-label {
        font-size: 12px;
        text-transform: uppercase;
        color: #64748b;
        font-weight: 700;
      }
      .report-kpi-value {
        margin-top: 8px;
        font-size: 28px;
        font-weight: 800;
      }
      .report-meta {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
        padding: 24px 32px 0;
      }
      .report-meta-card {
        border-radius: 16px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        padding: 14px 16px;
      }
      .report-meta-label {
        font-size: 12px;
        text-transform: uppercase;
        color: #64748b;
        font-weight: 700;
      }
      .report-meta-value {
        margin-top: 6px;
        font-size: 15px;
        font-weight: 700;
      }
      .report-body {
        padding: 24px 32px 32px;
      }
      table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
      }
      th, td {
        border: 1px solid #dbe5ef;
        padding: 10px 12px;
        text-align: left;
        vertical-align: top;
        font-size: 13px;
      }
      th {
        background: #eaf2fb;
        color: #14324f;
        font-weight: 700;
      }
      @media (max-width: 900px) {
        .report-kpi-grid,
        .report-meta {
          grid-template-columns: repeat(2, minmax(0, 1fr));
        }
      }
      @media print {
        @page {
          size: A4 landscape;
          margin: 10mm;
        }
        body {
          background: #fff;
        }
        .report-wrap {
          max-width: none;
          padding: 0;
        }
        .report-sheet {
          box-shadow: none;
          border-radius: 0;
        }
        .report-header {
          padding: 14mm 14mm 8mm;
        }
        .report-kpi-grid,
        .report-meta,
        .report-body {
          padding-left: 14mm;
          padding-right: 14mm;
        }
        h1 {
          font-size: 24pt !important;
        }
        table {
          table-layout: fixed;
        }
        th, td {
          font-size: 10pt;
          word-break: break-word;
        }
      }
    </style>
</head>
<body>
    <div class="report-wrap">
        <div class="report-sheet">
            <div class="report-header">
                <div style="font-size:12px; text-transform:uppercase; letter-spacing:.08em; opacity:.82; font-weight:700;">Professional Report Layout</div>
                <h1 style="margin:8px 0 6px; font-size:32px;"><?= htmlspecialchars($reportTitle) ?></h1>
                <div style="opacity:.88; font-size:15px;">Generated report for operational review and printing.</div>
            </div>

            <div class="report-kpi-grid">
                <div class="report-kpi"><div class="report-kpi-label">Total Records</div><div class="report-kpi-value"><?= (int) ($summary['total_rows'] ?? 0) ?></div></div>
                <div class="report-kpi"><div class="report-kpi-label">Unique Visitors</div><div class="report-kpi-value"><?= (int) ($summary['unique_visitors'] ?? 0) ?></div></div>
                <div class="report-kpi"><div class="report-kpi-label">Active Days</div><div class="report-kpi-value"><?= (int) ($summary['active_days'] ?? 0) ?></div></div>
                <div class="report-kpi"><div class="report-kpi-label">Dorm Coverage</div><div class="report-kpi-value"><?= (int) ($summary['active_dorms'] ?? 0) ?></div></div>
            </div>

            <div class="report-meta">
                <div class="report-meta-card"><div class="report-meta-label">Report Source</div><div class="report-meta-value"><?= htmlspecialchars($config['label']) ?></div></div>
                <div class="report-meta-card"><div class="report-meta-label">Period Type</div><div class="report-meta-value"><?= htmlspecialchars(ucfirst($reportPeriod)) ?></div></div>
                <div class="report-meta-card"><div class="report-meta-label">Period Covered</div><div class="report-meta-value"><?= htmlspecialchars($periodText) ?></div></div>
                <div class="report-meta-card"><div class="report-meta-label">Generated At</div><div class="report-meta-value"><?= htmlspecialchars($generatedAt) ?></div></div>
                <div class="report-meta-card"><div class="report-meta-label">Dorm Filter</div><div class="report-meta-value"><?= htmlspecialchars($dormText) ?></div></div>
                <div class="report-meta-card"><div class="report-meta-label">Resident Filter</div><div class="report-meta-value"><?= htmlspecialchars($pdlText) ?></div></div>
                <div class="report-meta-card"><div class="report-meta-label">Resident Gender Filter</div><div class="report-meta-value"><?= htmlspecialchars($genderText) ?></div></div>
                <?php if ($reportSource === 'banned'): ?>
                    <div class="report-meta-card"><div class="report-meta-label">Ban Status</div><div class="report-meta-value"><?= htmlspecialchars($banStatusText) ?></div></div>
                    <div class="report-meta-card"><div class="report-meta-label">Violation</div><div class="report-meta-value"><?= htmlspecialchars($violationText) ?></div></div>
                <?php endif; ?>
            </div>

            <div class="report-body">
                <h2 style="margin:0 0 12px; font-size:20px;">Visitor Frequency</h2>
                <table style="margin-bottom:24px;">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Visitor</th>
                            <th>Resident Gender</th>
                            <th>Visit Count</th>
                            <th>First Visit</th>
                            <th>Last Visit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($visitorFrequencyRows as $index => $row): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><?= htmlspecialchars(trim((string) ($row['visitor_name'] ?? '')) ?: 'Unknown Visitor') ?></td>
                                <td><?= htmlspecialchars(trim((string) ($row['visitor_gender'] ?? '')) ?: '-') ?></td>
                                <td><?= (int) ($row['visit_count'] ?? 0) ?></td>
                                <td><?= htmlspecialchars(report_export_format_datetime($row['first_visit'] ?? null)) ?></td>
                                <td><?= htmlspecialchars(report_export_format_datetime($row['last_visit'] ?? null)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($visitorFrequencyRows)): ?>
                            <tr>
                                <td colspan="6" style="text-align:center; color:#64748b;">No visitor frequency data found for the selected filters.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <h2 style="margin:0 0 12px; font-size:20px;">Detailed Report</h2>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <?php foreach ($selectedReportColumns as $columnKey): ?>
                                <th><?= htmlspecialchars($selectedReportHeaders[$columnKey] ?? $reportColumnDefinitions[$columnKey]) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($detailRows as $index => $row): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <?php foreach ($selectedReportColumns as $columnKey): ?>
                                    <td><?= htmlspecialchars(report_export_detail_value($columnKey, $row)) ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($detailRows)): ?>
                            <tr>
                                <td colspan="<?= $detailColumnCount ?>" style="text-align:center; color:#64748b;">No records found for the selected report filters.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php if (in_array($format, ['print', 'pdf'], true)): ?>
    <script>
    window.addEventListener('load', function () {
        setTimeout(function () {
            window.print();
        }, 250);
    });
    </script>
    <?php endif; ?>
</body>
</html>
