<?php
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/functions.php';
require_once ROOT_PATH . '/modules/layout/header.php';

$canReportExport = can('reports.export');
$canReportPrint = can('reports.print');

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

$allowedSources = ['admissions', 'registrations', 'id_issuance', 'banned', 'special_cases'];
$allowedPeriods = ['daily', 'monthly', 'yearly', 'custom'];
$allowedBanStatuses = ['', 'active', 'lifted', 'expired'];

if (!in_array($reportSource, $allowedSources, true)) {
    $reportSource = 'admissions';
}

if (!in_array($reportPeriod, $allowedPeriods, true)) {
    $reportPeriod = 'daily';
}

if ($filterYear < 2000 || $filterYear > ((int) date('Y') + 5)) {
    $filterYear = (int) date('Y');
}

if ($filterMonth < 1 || $filterMonth > 12) {
    $filterMonth = (int) date('n');
}

if (!in_array($filterGender, ['', 'Male', 'Female'], true)) {
    $filterGender = '';
}

if (!in_array($filterBanStatus, $allowedBanStatuses, true)) {
    $filterBanStatus = '';
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

$dormOptions = $pdo->query("
    SELECT id, dorm_no, description
    FROM dorm_masterlist
    WHERE COALESCE(TRIM(dorm_no), '') <> ''
    ORDER BY description ASC, dorm_no ASC
")->fetchAll(PDO::FETCH_ASSOC);

$selectedDorm = null;
if ($filterDorm > 0) {
    foreach ($dormOptions as $dormOption) {
        if ((int) ($dormOption['id'] ?? 0) === $filterDorm) {
            $selectedDorm = $dormOption;
            break;
        }
    }

    if ($selectedDorm === null) {
        $filterDorm = 0;
    }
}

$dormDisplayExpr = "COALESCE((SELECT COALESCE(NULLIF(TRIM(dm.description), ''), NULLIF(TRIM(dm.dorm_no), '')) FROM dorm_masterlist dm WHERE TRIM(CAST(dm.id AS CHAR)) = TRIM(p.dorm_no) OR TRIM(dm.dorm_no) = TRIM(p.dorm_no) LIMIT 1), TRIM(p.dorm_no))";
$pdlNameExpr = "TRIM(CONCAT(COALESCE(p.pdl_fname, ''), ' ', COALESCE(p.pdl_mname, ''), ' ', COALESCE(p.pdl_lname, ''), ' ', COALESCE(p.pdl_suffix, '')))";

$pdlOptions = $pdo->query("
    SELECT
        p.id,
        p.pdl_id,
        $pdlNameExpr AS pdl_name,
        $dormDisplayExpr AS dorm_name
    FROM pdl_info p
    ORDER BY p.pdl_lname ASC, p.pdl_fname ASC
")->fetchAll(PDO::FETCH_ASSOC);

$selectedPdl = null;
if ($filterPdl > 0) {
    foreach ($pdlOptions as $pdlOption) {
        if ((int) ($pdlOption['id'] ?? 0) === $filterPdl) {
            $selectedPdl = $pdlOption;
            break;
        }
    }

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

function report_format_datetime(?string $value): string
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

function report_format_duration($hoursStayed): string
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

function report_detail_value(string $columnKey, array $row): string
{
    $dateColumns = ['event_at', 'time_in', 'time_out', 'banned_at', 'lifted_at'];
    if (in_array($columnKey, $dateColumns, true)) {
        return report_format_datetime($row[$columnKey] ?? null);
    }

    if ($columnKey === 'hours_stayed') {
        return report_format_duration($row[$columnKey] ?? null);
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
$reportTitle = $reportTitleParam !== '' ? substr($reportTitleParam, 0, 120) : 'Manage Report';
$columnBuilderOrder = array_values(array_unique(array_merge($selectedReportColumns, array_keys($reportColumnDefinitions))));
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
        $bucketExpr = "DATE($dateColumn)";
        $bucketLabel = 'Date';
        break;
    case 'monthly':
        $whereParts[] = "YEAR($dateColumn) = ?";
        $params[] = $filterYear;
        $bucketExpr = "DATE_FORMAT($dateColumn, '%Y-%m')";
        $bucketLabel = 'Month';
        break;
    case 'yearly':
        $bucketExpr = "YEAR($dateColumn)";
        $bucketLabel = 'Year';
        break;
    case 'custom':
    default:
        $whereParts[] = "DATE($dateColumn) >= ?";
        $params[] = $dateFrom;
        $whereParts[] = "DATE($dateColumn) <= ?";
        $params[] = $dateTo;
        $bucketExpr = "DATE($dateColumn)";
        $bucketLabel = 'Date';
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

$chartSql = "
    SELECT $bucketExpr AS bucket, COUNT(*) AS total_count
    FROM $baseFrom
    $baseJoins
    WHERE $whereSql
    GROUP BY $bucketExpr
    ORDER BY $bucketExpr ASC
";
$chartStmt = $pdo->prepare($chartSql);
$chartStmt->execute($params);
$chartRows = $chartStmt->fetchAll(PDO::FETCH_ASSOC);

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
    LIMIT 300
";
$detailStmt = $pdo->prepare($detailSql);
$detailStmt->execute($params);
$detailRows = $detailStmt->fetchAll(PDO::FETCH_ASSOC);

$topDormSql = "
    SELECT {$config['detail_dorm']} AS dorm_no, COUNT(*) AS total_count
    FROM $baseFrom
    $baseJoins
    WHERE $whereSql
      AND COALESCE({$config['detail_dorm']}, '') <> ''
    GROUP BY {$config['detail_dorm']}
    ORDER BY total_count DESC, {$config['detail_dorm']} ASC
    LIMIT 5
";
$topDormStmt = $pdo->prepare($topDormSql);
$topDormStmt->execute($params);
$topDorms = $topDormStmt->fetchAll(PDO::FETCH_ASSOC);

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

$totalRows = (int) ($summary['total_rows'] ?? 0);
$uniqueVisitors = (int) ($summary['unique_visitors'] ?? 0);
$activeDays = (int) ($summary['active_days'] ?? 0);
$activeDorms = (int) ($summary['active_dorms'] ?? 0);
$activePdl = (int) ($summary['active_pdl'] ?? 0);

$chartLabels = array_map(static fn($row) => (string) ($row['bucket'] ?? ''), $chartRows);
$chartValues = array_map(static fn($row) => (int) ($row['total_count'] ?? 0), $chartRows);
$monthOptions = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
];
$exportQuery = http_build_query([
    'source' => $reportSource,
    'period' => $reportPeriod,
    'year' => $filterYear,
    'month' => $filterMonth,
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
    'dorm' => $filterDorm,
    'pdl_id' => $filterPdl,
    'gender' => $filterGender,
    'ban_status' => $filterBanStatus,
    'violation' => $filterViolation,
    'columns' => implode(',', $selectedReportColumns),
    'headers' => json_encode($selectedReportHeaders),
    'report_title' => $reportTitle,
]);

$activeFilterChips = [
    'Source: ' . $config['label'],
    'Coverage: ' . ucfirst($reportPeriod),
];

if ($reportPeriod === 'daily') {
    $activeFilterChips[] = 'Month: ' . ($monthOptions[$filterMonth] ?? $filterMonth) . ' ' . $filterYear;
} elseif ($reportPeriod === 'monthly') {
    $activeFilterChips[] = 'Year: ' . $filterYear;
} elseif ($reportPeriod === 'yearly') {
    $activeFilterChips[] = 'Year: ' . $filterYear;
} else {
    $activeFilterChips[] = 'Range: ' . $dateFrom . ' to ' . $dateTo;
}

if ($selectedDorm) {
    $dormLabel = trim((string) ($selectedDorm['description'] ?? '')) ?: trim((string) ($selectedDorm['dorm_no'] ?? ''));
    if ($dormLabel !== '') {
        $activeFilterChips[] = 'Dorm: ' . $dormLabel;
    }
}

if ($filterGender !== '') {
    $activeFilterChips[] = 'Gender: ' . $filterGender;
}

if ($selectedPdl) {
    $activeFilterChips[] = 'Resident: ' . (trim((string) ($selectedPdl['pdl_name'] ?? '')) ?: 'Selected Resident');
}

if ($reportSource === 'banned' && $filterBanStatus !== '') {
    $activeFilterChips[] = 'Ban: ' . ucfirst($filterBanStatus);
}

if ($reportSource === 'banned' && $filterViolation !== '') {
    $activeFilterChips[] = 'Violation: ' . $filterViolation;
}
?>

<style>
  .reports-shell {
    display: grid;
    gap: 1rem;
  }
  .reports-hero {
    position: relative;
    overflow: hidden;
    border: 0;
    border-radius: 18px;
    background: linear-gradient(135deg, #062150, #447A61);
    color: #fff;
    box-shadow: 0 18px 42px rgba(8, 40, 68, 0.18);
  }
  .reports-hero::after {
    content: "";
    position: absolute;
    right: -80px;
    bottom: -80px;
    width: 220px;
    height: 220px;
    border-radius: 50%;
    background: rgba(255,255,255,0.1);
  }
  .reports-hero .card-body { position: relative; z-index: 1; padding: 1.25rem 1.35rem; }
  .reports-hero-meta {
    display: flex;
    gap: .5rem;
    flex-wrap: wrap;
    justify-content: flex-end;
  }
  .reports-hero-meta .badge {
    border: 1px solid rgba(255,255,255,0.28);
    background: rgba(255,255,255,0.92) !important;
    color: #062150 !important;
  }
  .reports-stat-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .85rem; }
  .reports-stat-card, .reports-filter-card, .reports-panel-card {
    border: 1px solid rgba(180, 215, 190, 0.35);
    border-radius: 14px;
    box-shadow: 0 14px 30px rgba(8, 40, 68, 0.08);
    overflow: hidden;
  }
  .reports-stat-card {
    border-top: 4px solid #80A497;
  }
  .reports-stat-card .display-6 {
    color: #062150;
    font-size: 1.85rem;
  }
  .reports-stat-icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
  }
  .reports-filter-card .form-control,
  .reports-filter-card .form-select { border-radius: 8px; }
  .reports-filter-header,
  .reports-panel-heading {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 1rem;
  }
  .reports-filter-title,
  .reports-panel-title {
    display: flex;
    align-items: center;
    gap: .65rem;
  }
  .reports-filter-title span,
  .reports-panel-title span {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #6b7280;
    color: #062150;
  }
  .reports-section-title {
    display: flex;
    align-items: center;
    gap: .55rem;
    font-weight: 800;
    color: #0f172a;
    margin-bottom: .25rem;
  }
  .reports-section-title span {
    width: 26px;
    height: 26px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #edf5ec;
    color: #447A61;
  }
  .reports-helper {
    color: #64748b;
    font-size: .88rem;
    margin-bottom: .85rem;
  }
  .reports-column-builder {
    border: 1px solid rgba(180, 215, 190, 0.46);
    border-radius: 12px;
    background: #f8faf5;
    padding: 1rem;
  }
  .reports-column-builder summary {
    cursor: pointer;
    color: #062150;
  }
  .reports-column-row {
    display: grid;
    grid-template-columns: 34px 34px minmax(140px, 1fr) minmax(150px, 1.2fr);
    gap: .65rem;
    align-items: center;
    padding: .55rem;
    border-radius: 10px;
    background: #fff;
    border: 1px solid rgba(180, 215, 190, 0.35);
  }
  .reports-column-row.is-dragging {
    opacity: .55;
    border-style: dashed;
  }
  .reports-drag-handle {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #eef6f8;
    color: #062150;
    cursor: grab;
  }
  .reports-column-list {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: .65rem;
  }
  .reports-mini-list {
    display: grid;
    gap: .85rem;
  }
  .reports-mini-item {
    border-radius: 12px;
    background: #f8faf5;
    border: 1px solid rgba(180, 215, 190, 0.34);
    padding: .9rem 1rem;
  }
  .reports-mini-item:hover {
    border-color: rgba(79, 143, 63, .42);
    background: #fff;
  }
  .reports-chart-wrap {
    min-height: 320px;
    padding: .5rem;
    border-radius: 12px;
    background: linear-gradient(135deg, rgba(255,255,255,.95), rgba(244,247,239,.95));
  }
  .reports-table-card {
    overflow: hidden;
  }
  .reports-table-card .card-body {
    padding: .85rem 1rem 1rem;
  }
  .reports-table-wrap {
    border: 1px solid rgba(8,40,68,.08);
    border-radius: 14px;
    background: #fff;
    padding: .45rem;
  }
  .reports-table-wrap table {
    margin-bottom: 0 !important;
  }
  .reports-table-wrap thead th {
    background: #f4f7ef;
    color: #334155;
    font-size: .78rem;
    text-transform: uppercase;
    white-space: nowrap;
    border-bottom: 1px solid rgba(8,40,68,.08);
  }
  .reports-table-wrap tbody td {
    vertical-align: middle;
    color: #334155;
  }
  .reports-table-wrap .dataTables_wrapper .row,
  .reports-table-wrap .dataTables_wrapper .top,
  .reports-table-wrap .dataTables_wrapper .bottom {
    gap: .5rem;
  }
  .reports-status-badge {
    display: inline-flex;
    align-items: center;
    max-width: 180px;
    padding: .36rem .6rem;
    border-radius: 999px;
    background: #e9f7ef;
    color: #157347;
    font-size: .78rem;
    font-weight: 800;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .reports-action-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: .75rem;
    flex-wrap: wrap;
    padding: .8rem 1rem;
    border: 1px solid rgba(180, 215, 190, 0.35);
    border-radius: 14px;
    background: rgba(255,255,255,.9);
    box-shadow: 0 10px 24px rgba(8,40,68,.06);
  }
  .reports-actions {
    display: flex;
    justify-content: flex-end;
    gap: .6rem;
    flex-wrap: wrap;
  }
  .reports-active-filters {
    display: flex;
    gap: .45rem;
    flex-wrap: wrap;
    margin-top: .65rem;
  }
  .reports-filter-chip {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    padding: .38rem .62rem;
    border-radius: 999px;
    background: #f4f7ef;
    border: 1px solid rgba(180, 215, 190, .55);
    color: #405545;
    font-size: .78rem;
    font-weight: 700;
  }
  .reports-submit-row {
    display: flex;
    justify-content: flex-end;
    gap: .6rem;
    flex-wrap: wrap;
  }
  @media (max-width: 1199px) {
    .reports-stat-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  }
  @media (max-width: 767px) {
    .reports-stat-grid { grid-template-columns: 1fr; }
    .reports-column-list { grid-template-columns: 1fr; }
    .reports-column-row { grid-template-columns: 30px 30px 1fr; }
    .reports-column-row .column-header-input { grid-column: 1 / -1; }
    .reports-action-bar,
    .reports-filter-header,
    .reports-panel-heading {
      align-items: stretch;
      flex-direction: column;
    }
    .reports-actions,
    .reports-submit-row {
      justify-content: stretch;
    }
    .reports-actions .btn,
    .reports-submit-row .btn {
      width: 100%;
    }
  }
</style>

<div class="container-fluid py-3">
    <div class="reports-shell">
        <div class="card reports-hero">
            <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <div class="text-uppercase small fw-semibold opacity-75 mb-2">Reports</div>
                    <h2 class="fw-bold mb-1"><i class="bi bi-bar-chart-line me-2"></i>Manage Report</h2>
                    <p class="mb-0 opacity-75">Review trends, verify matching records, and export the exact report layout you need.</p>
                </div>
                <div class="reports-hero-meta">
                    <span class="badge bg-light text-dark fs-6"><?= htmlspecialchars($config['label']) ?></span>
                    <span class="badge bg-dark-subtle text-dark fs-6"><?= strtoupper($reportPeriod) ?></span>
                    <span class="badge bg-light text-dark fs-6"><?= number_format($totalRows) ?> row<?= $totalRows === 1 ? '' : 's' ?></span>
                </div>
            </div>
        </div>

        <div class="reports-stat-grid">
            <div class="card reports-stat-card"><div class="card-body"><div class="d-flex justify-content-between align-items-start"><div><div class="text-muted small"><?= $reportSource === 'admissions' ? 'Total Visits' : 'Total Records' ?></div><div class="display-6 fw-bold mb-0"><?= $totalRows ?></div></div><div class="reports-stat-icon bg-primary-subtle text-primary"><i class="bi bi-collection"></i></div></div></div></div>
            <div class="card reports-stat-card"><div class="card-body"><div class="d-flex justify-content-between align-items-start"><div><div class="text-muted small">Unique Visitors</div><div class="display-6 fw-bold mb-0"><?= $uniqueVisitors ?></div></div><div class="reports-stat-icon bg-success-subtle text-success"><i class="bi bi-people"></i></div></div></div></div>
            <div class="card reports-stat-card"><div class="card-body"><div class="d-flex justify-content-between align-items-start"><div><div class="text-muted small">Dorm Coverage</div><div class="display-6 fw-bold mb-0"><?= $activeDorms ?></div></div><div class="reports-stat-icon bg-info-subtle text-info"><i class="bi bi-building"></i></div></div></div></div>
            <div class="card reports-stat-card"><div class="card-body"><div class="d-flex justify-content-between align-items-start"><div><div class="text-muted small">Active Days</div><div class="display-6 fw-bold mb-0"><?= $activeDays ?></div></div><div class="reports-stat-icon bg-warning-subtle text-warning"><i class="bi bi-calendar-event"></i></div></div></div></div>
        </div>

        <div class="card reports-filter-card">
            <div class="card-body">
                <div class="reports-filter-header">
                    <div>
                        <div class="reports-filter-title">
                            <span><i class="bi bi-sliders"></i></span>
                            <div>
                                <h5 class="fw-bold mb-0">Report Controls</h5>
                                <div class="text-muted small">Set scope, date coverage, filters, and optional export columns.</div>
                            </div>
                        </div>
                        <!-- <div class="reports-active-filters">
                            <?php foreach ($activeFilterChips as $filterChip): ?>
                                <span class="reports-filter-chip"><i class="bi bi-check2-circle"></i><?= htmlspecialchars($filterChip) ?></span>
                            <?php endforeach; ?>
                        </div> -->
                    </div>
                    <a href="<?= BASE_URL ?>/reports" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-counterclockwise"></i> Reset
                    </a>
                </div>
                <form method="get" action="<?= BASE_URL ?>/reports" class="row g-3 align-items-end" id="reportFilterForm">
                    <input type="hidden" name="columns" id="reportColumnsInput" value="<?= htmlspecialchars(implode(',', $selectedReportColumns)) ?>">
                    <input type="hidden" name="headers" id="reportHeadersInput" value="<?= htmlspecialchars(json_encode($selectedReportHeaders)) ?>">
                    <!-- <div class="col-12">
                        <div class="reports-section-title"><span><i class="bi bi-file-earmark-bar-graph"></i></span>Report Setup</div>
                    </div> -->
                    <div class="col-lg-4">
                        <label class="form-label fw-semibold">Report title</label>
                        <input type="text" name="report_title" id="reportTitleInput" class="form-control" value="<?= htmlspecialchars($reportTitle) ?>" maxlength="120" placeholder="Example: Monthly Visitor Admission Report">
                    </div>
                    <div class="col-lg-3">
                        <label class="form-label fw-semibold">Report Source</label>
                        <select name="source" id="reportSource" class="form-select">
                            <option value="admissions" <?= $reportSource === 'admissions' ? 'selected' : '' ?>>Visitor Admissions</option>
                            <option value="registrations" <?= $reportSource === 'registrations' ? 'selected' : '' ?>>Visitor Registrations</option>
                            <option value="id_issuance" <?= $reportSource === 'id_issuance' ? 'selected' : '' ?>>ID Issuance</option>
                            <option value="banned" <?= $reportSource === 'banned' ? 'selected' : '' ?>>Banned Visitors Records</option>
                            <option value="special_cases" <?= $reportSource === 'special_cases' ? 'selected' : '' ?>>Special Case Visits Records</option>
                        </select>
                    </div>
                    <div class="col-lg-2">
                        <label class="form-label fw-semibold">Date coverage</label>
                        <select name="period" id="reportPeriod" class="form-select">
                            <option value="daily" <?= $reportPeriod === 'daily' ? 'selected' : '' ?>>Daily</option>
                            <option value="monthly" <?= $reportPeriod === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                            <option value="yearly" <?= $reportPeriod === 'yearly' ? 'selected' : '' ?>>Yearly</option>
                            <option value="custom" <?= $reportPeriod === 'custom' ? 'selected' : '' ?>>Custom Range</option>
                        </select>
                    </div>
                    <!-- <div class="col-12 mt-2">
                        <div class="reports-section-title"><span><i class="bi bi-funnel"></i></span>Filters</div>
                    </div> -->
                    <div class="col-lg-2">
                        <label class="form-label fw-semibold">Dorm</label>
                        <select name="dorm" class="form-select">
                            <option value="">All Dorms</option>
                            <?php foreach ($dormOptions as $dormOption): ?>
                                <?php
                                    $dormId = (int) ($dormOption['id'] ?? 0);
                                    $dormNo = trim((string) ($dormOption['dorm_no'] ?? ''));
                                    $dormDescription = trim((string) ($dormOption['description'] ?? ''));
                                    $dormLabel = $dormDescription !== '' ? $dormDescription : $dormNo;
                                ?>
                                <option value="<?= $dormId ?>" <?= $filterDorm === $dormId ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($dormLabel) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-2">
                        <label class="form-label fw-semibold">Resident Gender</label>
                        <select name="gender" class="form-select">
                            <option value="">All Resident Genders</option>
                            <option value="Male" <?= $filterGender === 'Male' ? 'selected' : '' ?>>Male Resident</option>
                            <option value="Female" <?= $filterGender === 'Female' ? 'selected' : '' ?>>Female Resident</option>
                        </select>
                    </div>
                    <div class="col-lg-3">
                        <label class="form-label fw-semibold">Resident</label>
                        <select name="pdl_id" class="form-select select2" data-placeholder="Search Resident name">
                            <option value="">All Resident</option>
                            <?php foreach ($pdlOptions as $pdlOption): ?>
                                <?php
                                    $pdlOptionId = (int) ($pdlOption['id'] ?? 0);
                                    $pdlName = trim((string) ($pdlOption['pdl_name'] ?? ''));
                                    $pdlCode = trim((string) ($pdlOption['pdl_id'] ?? ''));
                                    $pdlDorm = trim((string) ($pdlOption['dorm_name'] ?? ''));
                                    $pdlLabel = ($pdlName !== '' ? $pdlName : 'Unnamed Resident') . ($pdlCode !== '' ? ' | ' . $pdlCode : '') . ($pdlDorm !== '' ? ' | ' . $pdlDorm : '');
                                ?>
                                <option value="<?= $pdlOptionId ?>" <?= $filterPdl === $pdlOptionId ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($pdlLabel) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-2 ban-filter">
                        <label class="form-label fw-semibold">Ban status</label>
                        <select name="ban_status" class="form-select">
                            <option value="">All Ban Records</option>
                            <option value="active" <?= $filterBanStatus === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="lifted" <?= $filterBanStatus === 'lifted' ? 'selected' : '' ?>>Lifted</option>
                            <option value="expired" <?= $filterBanStatus === 'expired' ? 'selected' : '' ?>>Expired</option>
                        </select>
                    </div>
                    <div class="col-lg-3 ban-filter">
                        <label class="form-label fw-semibold">Violation keyword</label>
                        <input type="text" name="violation" class="form-control" value="<?= htmlspecialchars($filterViolation) ?>" placeholder="Search violation">
                    </div>
                    <div class="col-lg-2 filter-year">
                        <label class="form-label fw-semibold">Year</label>
                        <input type="number" name="year" class="form-control" value="<?= $filterYear ?>" min="2000" max="<?= (int) date('Y') + 5 ?>">
                    </div>
                    <div class="col-lg-2 filter-month">
                        <label class="form-label fw-semibold">Month</label>
                        <select name="month" class="form-select">
                            <?php foreach ($monthOptions as $monthValue => $monthLabel): ?>
                                <option value="<?= $monthValue ?>" <?= $filterMonth === $monthValue ? 'selected' : '' ?>><?= htmlspecialchars($monthLabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-2 filter-from">
                        <label class="form-label fw-semibold">Date From</label>
                        <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>">
                    </div>
                    <div class="col-lg-2 filter-to">
                        <label class="form-label fw-semibold">Date To</label>
                        <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>">
                    </div>
                    <div class="col-lg-2 d-grid">
                        <button type="submit" class="btn btn-success"><i class="bi bi-search"></i> Run Report</button>
                    </div>
                    <div class="col-12">
                        <details class="reports-column-builder" <?= $selectedColumnsParam !== '' || $customHeadersParam !== '' ? 'open' : '' ?>>
                            <summary class="fw-bold">
                                <i class="bi bi-layout-three-columns me-1"></i> Optional: choose columns for this report
                                <span class="text-muted fw-normal small ms-2">Check fields to include, drag them into order, and rename headers.</span>
                            </summary>
                            <div class="alert alert-info border-0 mt-3 mb-3 py-2 small">
                                Tip: Hold and drag the handle beside each field to arrange the report columns. The table and exports follow the same order.
                            </div>
                            <div class="reports-column-list mt-3">
                                <?php foreach ($columnBuilderOrder as $columnKey): ?>
                                    <?php
                                        $defaultHeader = $reportColumnDefinitions[$columnKey];
                                        $isSelectedColumn = in_array($columnKey, $selectedReportColumns, true);
                                        $headerValue = $selectedReportHeaders[$columnKey] ?? $defaultHeader;
                                    ?>
                                    <div class="reports-column-row" data-column-row draggable="true">
                                        <span class="reports-drag-handle" title="Drag to arrange"><i class="bi bi-grip-vertical"></i></span>
                                        <input type="checkbox" class="form-check-input report-column-check" data-column="<?= htmlspecialchars($columnKey) ?>" <?= $isSelectedColumn ? 'checked' : '' ?>>
                                        <div class="fw-semibold small"><?= htmlspecialchars($defaultHeader) ?></div>
                                        <input type="text" class="form-control form-control-sm column-header-input report-column-header" value="<?= htmlspecialchars($headerValue) ?>" placeholder="Rename header">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </details>
                    </div>
                </form>
            </div>
        </div>

        <div class="reports-action-bar">
            <div>
                <div class="fw-bold"><i class="bi bi-download me-1 text-success"></i>Export Current Report</div>
                <div class="text-muted small">Exports use the filters and selected columns shown below.</div>
            </div>
            <div class="reports-actions">
                <?php if ($canReportExport): ?>
                    <a href="<?= BASE_URL ?>/reports-export?format=excel&<?= $exportQuery ?>" class="btn btn-success report-export-link" data-format="excel">
                        <i class="bi bi-file-earmark-excel"></i> Excel
                    </a>
                <?php endif; ?>
                <?php if ($canReportPrint): ?>
                    <a href="<?= BASE_URL ?>/reports-export?format=print&<?= $exportQuery ?>" target="_blank" class="btn btn-outline-primary report-export-link" data-format="print">
                        <i class="bi bi-printer"></i> Print
                    </a>
                    <a href="<?= BASE_URL ?>/reports-export?format=pdf&<?= $exportQuery ?>" target="_blank" class="btn btn-outline-danger report-export-link" data-format="pdf">
                        <i class="bi bi-file-earmark-pdf"></i> PDF
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="card reports-panel-card reports-table-card">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <div class="reports-panel-heading mb-0">
                    <div class="reports-panel-title">
                        <span><i class="bi bi-table"></i></span>
                        <div>
                            <h6 class="fw-bold mb-0"><?= htmlspecialchars($reportTitle) ?></h6>
                            <small class="text-muted">Showing up to 300 matching record(s) for the current filters.</small>
                        </div>
                    </div>
                    <span class="badge bg-success rounded-pill"><?= count($detailRows) ?> displayed</span>
                </div>
            </div>
            <div class="card-body">
                <div class="reports-table-wrap table-responsive">
                    <table id="reportsTable" class="table table-hover align-middle w-100">
                        <thead>
                            <tr>
                                <th>#</th>
                                <?php foreach ($selectedReportColumns as $columnKey): ?>
                                    <th><?= htmlspecialchars($selectedReportHeaders[$columnKey] ?? $reportColumnDefinitions[$columnKey]) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($detailRows as $rowIndex => $row): ?>
                                <tr>
                                    <td class="fw-semibold text-muted"><?= $rowIndex + 1 ?></td>
                                    <?php foreach ($selectedReportColumns as $columnKey): ?>
                                        <?php $cellValue = report_detail_value($columnKey, $row); ?>
                                        <td>
                                            <?php if ($columnKey === 'status_label'): ?>
                                                <span class="reports-status-badge" title="<?= htmlspecialchars($cellValue) ?>"><?= htmlspecialchars($cellValue) ?></span>
                                            <?php else: ?>
                                                <?= htmlspecialchars($cellValue) ?>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-xl-8">
                <div class="card reports-panel-card h-100">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <div class="reports-panel-heading mb-0">
                            <div class="reports-panel-title">
                                <span><i class="bi bi-graph-up-arrow"></i></span>
                                <div>
                                    <h6 class="fw-bold mb-0"><?= htmlspecialchars($config['label']) ?> Trend</h6>
                                    <small class="text-muted">Records found for each <?= strtolower(htmlspecialchars($bucketLabel)) ?>.</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body px-4 pb-4">
                        <div class="reports-chart-wrap">
                            <canvas id="reportsChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card reports-panel-card h-100">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <div class="reports-panel-heading mb-0">
                            <div class="reports-panel-title">
                                <span><i class="bi bi-person-lines-fill"></i></span>
                                <div>
                                    <h6 class="fw-bold mb-0">Visitor Frequency</h6>
                                    <small class="text-muted"><?= $selectedPdl ? 'Repeat visits connected to the selected Resident.' : 'Visitors with the most matching records.' ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body px-4 pb-4">
                        <div class="reports-mini-list">
                            <?php if (empty($visitorFrequencyRows)): ?>
                                <div class="reports-mini-item text-muted">No visitor activity found for this report filter.</div>
                            <?php else: ?>
                                <?php foreach ($visitorFrequencyRows as $visitorRow): ?>
                                    <div class="reports-mini-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="fw-semibold"><?= htmlspecialchars(trim((string) ($visitorRow['visitor_name'] ?? '')) ?: 'Unknown Visitor') ?></div>
                                            <div class="small text-muted">
                                                <?= htmlspecialchars(trim((string) ($visitorRow['visitor_gender'] ?? '')) ?: 'No Resident gender') ?>
                                                | Last: <?= htmlspecialchars(report_format_datetime($visitorRow['last_visit'] ?? null)) ?>
                                            </div>
                                        </div>
                                        <span class="badge bg-success rounded-pill"><?= (int) ($visitorRow['visit_count'] ?? 0) ?> visit<?= (int) ($visitorRow['visit_count'] ?? 0) === 1 ? '' : 's' ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const REPORT_BOOTSTRAP = {
    labels: <?= json_encode($chartLabels) ?>,
    values: <?= json_encode($chartValues) ?>,
    period: <?= json_encode($reportPeriod) ?>
};

$(function () {
    $('select.select2').select2({
        width: '100%',
        placeholder: function () {
            return $(this).data('placeholder') || 'Select an option';
        },
        allowClear: true
    });

    const orderColumn = <?= in_array('event_at', $selectedReportColumns, true) ? ((int) array_search('event_at', $selectedReportColumns, true) + 1) : 0 ?>;
    $('#reportsTable').DataTable({
        responsive: true,
        pageLength: 10,
        order: [[orderColumn, 'desc']],
        language: {
            search: '',
            searchPlaceholder: 'Search report rows...',
            emptyTable: 'No records found for the selected filters.'
        }
    });

    function updateReportExportLinks() {
        const formData = new FormData(document.getElementById('reportFilterForm'));
        const params = new URLSearchParams(formData);
        $('.report-export-link').each(function () {
            const format = $(this).data('format');
            const exportParams = new URLSearchParams(params);
            exportParams.set('format', format);
            $(this).attr('href', <?= json_encode(BASE_URL . '/reports-export') ?> + '?' + exportParams.toString());
        });
    }

    function syncReportColumns() {
        const selected = [];
        const headers = {};
        $('[data-column-row]').each(function () {
            const row = $(this);
            const column = row.find('.report-column-check').data('column');
            if (!row.find('.report-column-check').is(':checked')) {
                return;
            }

            selected.push(column);
            headers[column] = row.find('.report-column-header').val() || '';
        });

        if (selected.length === 0) {
            $('.report-column-check[data-column="event_at"], .report-column-check[data-column="visitor_name"]').prop('checked', true);
            return syncReportColumns();
        }

        $('#reportColumnsInput').val(selected.join(','));
        $('#reportHeadersInput').val(JSON.stringify(headers));
        updateReportExportLinks();
    }

    $('#reportFilterForm').on('submit', syncReportColumns);
    $('.report-column-check, .report-column-header').on('change keyup', syncReportColumns);
    $('#reportFilterForm select, #reportFilterForm input').on('change keyup', updateReportExportLinks);

    let draggedColumnRow = null;
    const columnList = document.querySelector('.reports-column-list');
    if (columnList) {
        columnList.addEventListener('dragstart', function (event) {
            const row = event.target.closest('[data-column-row]');
            if (!row) return;
            draggedColumnRow = row;
            row.classList.add('is-dragging');
            event.dataTransfer.effectAllowed = 'move';
        });

        columnList.addEventListener('dragend', function () {
            if (draggedColumnRow) {
                draggedColumnRow.classList.remove('is-dragging');
            }
            draggedColumnRow = null;
            syncReportColumns();
        });

        columnList.addEventListener('dragover', function (event) {
            event.preventDefault();
            const targetRow = event.target.closest('[data-column-row]');
            if (!draggedColumnRow || !targetRow || targetRow === draggedColumnRow) return;

            const targetBox = targetRow.getBoundingClientRect();
            const shouldInsertAfter = event.clientY > targetBox.top + (targetBox.height / 2);
            columnList.insertBefore(draggedColumnRow, shouldInsertAfter ? targetRow.nextSibling : targetRow);
        });
    }

    syncReportColumns();

    const period = $('#reportPeriod').val();
    $('.filter-year').toggle(period !== 'custom');
    $('.filter-month').toggle(period === 'daily');
    $('.filter-from, .filter-to').toggle(period === 'custom');
    $('.ban-filter').toggle($('#reportSource').val() === 'banned');

    $('#reportPeriod').on('change', function () {
        const nextPeriod = $(this).val();
        $('.filter-year').toggle(nextPeriod !== 'custom');
        $('.filter-month').toggle(nextPeriod === 'daily');
        $('.filter-from, .filter-to').toggle(nextPeriod === 'custom');
    });

    $('#reportSource').on('change', function () {
        $('.ban-filter').toggle($(this).val() === 'banned');
    });

    const chartEl = document.getElementById('reportsChart');
    if (chartEl) {
        new Chart(chartEl, {
            type: REPORT_BOOTSTRAP.period === 'yearly' ? 'bar' : 'line',
            data: {
                labels: REPORT_BOOTSTRAP.labels,
                datasets: [{
                    label: 'Report Count',
                    data: REPORT_BOOTSTRAP.values,
                    borderColor: '#062150',
                    backgroundColor: 'rgba(180, 215, 190, 0.22)',
                    fill: true,
                    tension: 0.32,
                    borderWidth: 3,
                    pointBackgroundColor: '#447A61',
                    pointRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        display: true,
                        labels: {
                            color: '#405545',
                            usePointStyle: true,
                            boxWidth: 8,
                            boxHeight: 8
                        }
                    },
                    tooltip: {
                        backgroundColor: '#062150',
                        titleColor: '#ffffff',
                        bodyColor: '#ffffff',
                        padding: 12,
                        displayColors: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0,
                            color: '#5e7062'
                        },
                        grid: {
                            color: 'rgba(180, 215, 190, 0.28)'
                        }
                    },
                    x: {
                        ticks: {
                            color: '#5e7062'
                        },
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }
});
</script>

<?php require_once ROOT_PATH . '/modules/layout/footer.php'; ?>
