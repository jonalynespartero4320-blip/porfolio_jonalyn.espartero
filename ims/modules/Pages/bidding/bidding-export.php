<?php

require_once __DIR__ . '/bidding-common.php';

require_login();
require_permission('ictd.bidding.view');
bidding_ensure_schema($pdo);

$exportAll = (int) ($_GET['export_all'] ?? 0) === 1;
$from = trim((string) ($_GET['from'] ?? ''));
$to = trim((string) ($_GET['to'] ?? ''));
$departmentId = trim((string) ($_GET['department_id'] ?? ''));

$where = ['COALESCE(b.is_archived, 0) = 0'];
$params = [];
if (!$exportAll) {
    if ($from !== '') {
        $where[] = 'b.date_requested >= ?';
        $params[] = $from;
    }
    if ($to !== '') {
        $where[] = 'b.date_requested <= ?';
        $params[] = $to;
    }
    if ($departmentId !== '') {
        $where[] = 'b.department_id = ?';
        $params[] = $departmentId;
    }
}

$stmt = $pdo->prepare("
    SELECT b.date_requested, lf.fund_name, dep.department_name, b.department_head, bi.description, bi.qty
    FROM bidding b
    LEFT JOIN list_funds lf ON lf.id = b.fund_id
    LEFT JOIN list_department dep ON dep.department_id = b.department_id
    LEFT JOIN bidding_items bi ON bi.bidding_id = b.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY b.date_requested DESC, b.id DESC, bi.id ASC
");
$stmt->execute($params);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="bidding-report-' . date('Ymd-His') . '.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['Date Requested', 'Source of Funds', 'Department', 'Department Head', 'Item Description', 'Qty']);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
    fputcsv($out, [
        $row['date_requested'] ?? '',
        $row['fund_name'] ?? '',
        $row['department_name'] ?? '',
        $row['department_head'] ?? '',
        $row['description'] ?? '',
        $row['qty'] ?? '',
    ]);
}
fclose($out);
exit;
