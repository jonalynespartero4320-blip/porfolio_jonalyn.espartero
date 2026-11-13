<?php
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/functions.php';
require_once ROOT_PATH . '/modules/Reports/report_common.php';

require_login();
require_any_permission(['reports.view', 'ictd.reports.view']);

$format = strtolower(trim((string) ($_GET['format'] ?? 'print')));
if (!in_array($format, ['print', 'excel', 'pdf'], true)) {
    $format = 'print';
}
if ($format === 'excel') {
    require_permission('reports.export');
} else {
    require_permission('reports.print');
}

$modules = gr_modules($pdo);
$moduleKey = gr_active_module($pdo, trim((string) ($_GET['module'] ?? 'equipment')));
$config = $modules[$moduleKey];
$fieldLabels = gr_field_labels($config);

$selected = array_values(array_filter(explode(',', (string) ($_GET['fields'] ?? implode(',', array_keys($fieldLabels))))));
$order = array_values(array_filter(explode(',', (string) ($_GET['order'] ?? implode(',', array_keys($fieldLabels))))));
$fields = array_values(array_filter($order, static fn($field) => in_array($field, $selected, true) && isset($fieldLabels[$field])));
foreach ($selected as $field) {
    if (isset($fieldLabels[$field]) && !in_array($field, $fields, true)) {
        $fields[] = $field;
    }
}
if (empty($fields)) {
    $fields = array_keys($fieldLabels);
}

$filters = [
    'search' => trim((string) ($_GET['search'] ?? '')),
    'date_from' => trim((string) ($_GET['date_from'] ?? '')),
    'date_to' => trim((string) ($_GET['date_to'] ?? '')),
    'description_name' => trim((string) ($_GET['description_name'] ?? '')),
    'status' => trim((string) ($_GET['status'] ?? '')),
];
$title = trim((string) ($_GET['title'] ?? $config['title'])) ?: $config['title'];
$rows = gr_fetch_rows($pdo, $config, $filters);
$totalPrice = array_reduce($rows, static fn($sum, $row) => $sum + (float) ($row['price'] ?? 0), 0.0);
$wideReport = count($fields) > 7;

function gre_report_cell(array $row, string $field): string
{
    if ($field === 'price') {
        return number_format((float) ($row[$field] ?? 0), 2);
    }
    return trim((string) ($row[$field] ?? ''));
}

if ($format === 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-z0-9_]+/i', '_', strtolower($title)) . '_' . date('Ymd_His') . '.xls"');
    echo '<html><head><meta charset="UTF-8"></head><body><table border="1">';
    echo '<tr><th colspan="' . count($fields) . '" style="font-size:18px;background:#d9ead3;">' . e($title) . '</th></tr>';
    echo '<tr><td colspan="' . count($fields) . '">Generated: ' . e(date('F j, Y g:i A')) . '</td></tr>';
    echo '<tr>';
    foreach ($fields as $field) {
        echo '<th>' . e($fieldLabels[$field]) . '</th>';
    }
    echo '</tr>';
    foreach ($rows as $row) {
        echo '<tr>';
        foreach ($fields as $field) {
            echo '<td>' . e(gre_report_cell($row, $field)) . '</td>';
        }
        echo '</tr>';
    }
    if (in_array('price', $fields, true)) {
        echo '<tr><td colspan="' . max(1, count($fields) - 1) . '">Total Price</td><td>' . number_format($totalPrice, 2) . '</td></tr>';
    }
    echo '</table></body></html>';
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= e($title) ?></title>
    <style>
        @page { size: <?= $wideReport ? 'landscape' : 'portrait' ?>; margin: 10mm; }
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; margin: 24px; color: #111827; }
        .actions { display: flex; justify-content: flex-end; gap: 8px; margin-bottom: 16px; }
        .actions button { border: 0; border-radius: 4px; padding: 8px 12px; font-weight: 700; cursor: pointer; }
        .primary { background: #3657f6; color: #fff; }
        .light { background: #eef1f5; color: #344054; }
        h1 { margin: 0; font-size: 22px; }
        .meta { color: #667085; margin: 4px 0 16px; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: <?= $wideReport ? '10px' : '12px' ?>; table-layout: fixed; }
        th, td { border: 1px solid #d0d5dd; padding: <?= $wideReport ? '5px 6px' : '7px 8px' ?>; text-align: left; vertical-align: top; overflow-wrap: anywhere; word-break: break-word; }
        th { background: #f3f4f6; }
        thead { display: table-header-group; }
        tfoot { display: table-row-group; }
        tr { page-break-inside: avoid; break-inside: avoid; }
        tfoot td { font-weight: 800; background: #f9fafb; }
        @media print {
            .actions { display: none; }
            body { margin: 0; }
            .table-wrap { overflow: visible; }
        }
    </style>
</head>
<body>
    <div class="actions">
        <button class="light" onclick="window.close()">Close</button>
        <button class="primary" onclick="window.print()"><?= $format === 'pdf' ? 'Save PDF' : 'Print / Save PDF' ?></button>
    </div>
    <h1><?= e($title) ?></h1>
    <div class="meta">Module: <?= e($config['label']) ?> | Generated: <?= e(date('F j, Y g:i A')) ?> | Records: <?= number_format(count($rows)) ?></div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><?php foreach ($fields as $field): ?><th><?= e($fieldLabels[$field]) ?></th><?php endforeach; ?></tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr><?php foreach ($fields as $field): ?><td><?= e(gre_report_cell($row, $field)) ?></td><?php endforeach; ?></tr>
                <?php endforeach; ?>
                <?php if (empty($rows)): ?><tr><td colspan="<?= count($fields) ?>">No records found.</td></tr><?php endif; ?>
            </tbody>
            <?php if (in_array('price', $fields, true)): ?>
                <tfoot><tr><td colspan="<?= max(1, count($fields) - 1) ?>">Total Price</td><td><?= number_format($totalPrice, 2) ?></td></tr></tfoot>
            <?php endif; ?>
        </table>
    </div>
    <script><?php if ($format === 'pdf'): ?>window.addEventListener('load', () => setTimeout(() => window.print(), 250));<?php endif; ?></script>
</body>
</html>
