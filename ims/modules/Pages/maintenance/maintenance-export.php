<?php
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/functions.php';
require_once ROOT_PATH . '/helpers/pdf-renderer.php';

require_login();
require_permission('ictd.maintenance.view');

$id = (int) ($_GET['id'] ?? $_GET['maintenance_id'] ?? 0);
if ($id <= 0) {
    http_response_code(422);
    echo 'Invalid Maintenance Report ID.';
    exit;
}
$autoPrint = (int) ($_GET['print'] ?? 0) === 1;
if (db_table_exists($pdo, 'maintenance_reports') && !db_column_exists($pdo, 'maintenance_reports', 'designation_id')) {
    $pdo->exec('ALTER TABLE maintenance_reports ADD COLUMN designation_id INT NULL AFTER department_id');
}

$hasTechnicalTable = db_table_exists($pdo, 'list_technical');
$technicalIdColumn = $hasTechnicalTable && db_column_exists($pdo, 'list_technical', 'technical_id')
    ? 'technical_id'
    : ($hasTechnicalTable && db_column_exists($pdo, 'list_technical', 'id') ? 'id' : null);
$technicalJoinCondition = $hasTechnicalTable
    ? 'lt.technical_name = mr.inspected_by' . ($technicalIdColumn ? ' OR CAST(lt.`' . $technicalIdColumn . '` AS CHAR) = CAST(mr.inspected_by AS CHAR)' : '')
    : '';
$technicianJoin = $hasTechnicalTable
    ? 'LEFT JOIN list_technical lt ON ' . $technicalJoinCondition
    : '';
$technicianSelect = $hasTechnicalTable
    ? 'COALESCE(lt.technical_name, mr.inspected_by) AS inspected_by_name'
    : 'mr.inspected_by AS inspected_by_name';

$stmt = $pdo->prepare("
    SELECT
        mr.*,
        eu.end_user_name,
        dep.department_name,
        des.department_name AS designation_name,
        {$technicianSelect}
    FROM maintenance_reports mr
    LEFT JOIN list_end_user eu ON eu.end_user_id = mr.end_user_id
    LEFT JOIN list_department dep ON dep.department_id = mr.department_id
    LEFT JOIN list_department des ON des.department_id = mr.designation_id
    {$technicianJoin}
    WHERE mr.id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$report = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$report) {
    http_response_code(404);
    echo 'Maintenance Report not found.';
    exit;
}

$itemFilter = strtolower(trim((string) ($_GET['item_filter'] ?? 'all')));
$itemFilterAliases = [
    'equipment' => 'equipment',
    'equipments' => 'equipment',
    'supply' => 'supply',
    'supplies' => 'supply',
    'all' => 'all',
];
$itemFilter = $itemFilterAliases[$itemFilter] ?? 'all';
$selectedItemIds = array_values(array_unique(array_filter(array_map(
    static fn($value) => (int) $value,
    explode(',', (string) ($_GET['item_ids'] ?? ''))
), static fn($value) => $value > 0)));
$hasSelectedItems = !empty($selectedItemIds);
$allItems = [];
$items = [];
if (db_table_exists($pdo, 'maintenance_report_items')) {
    foreach ([
        'description_name' => 'VARCHAR(255) NULL',
        'brand_name' => 'VARCHAR(255) NULL',
    ] as $column => $definition) {
        if (!db_column_exists($pdo, 'maintenance_report_items', $column)) {
            $pdo->exec("ALTER TABLE maintenance_report_items ADD COLUMN {$column} {$definition}");
        }
    }
    $allItemStmt = $pdo->prepare("
        SELECT
            mri.*,
            COALESCE(d.description_name, mri.description_name) AS description_name,
            COALESCE(b.brand_name, mri.brand_name) AS brand_name
        FROM maintenance_report_items mri
        LEFT JOIN list_description d ON d.id = mri.description_id
        LEFT JOIN list_brand b ON b.id = mri.brand_id
        WHERE mri.maintenance_report_id = ?
        ORDER BY mri.id ASC
    ");
    $allItemStmt->execute([$id]);
    $allItems = $allItemStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $itemWhere = 'WHERE mri.maintenance_report_id = ?';
    $itemParams = [$id];
    if ($hasSelectedItems) {
        $itemWhere .= ' AND mri.id IN (' . implode(',', array_fill(0, count($selectedItemIds), '?')) . ')';
        $itemParams = array_merge($itemParams, $selectedItemIds);
    } elseif ($itemFilter !== 'all') {
        $itemWhere .= ' AND mri.item_type = ?';
        $itemParams[] = $itemFilter;
    }
    $itemStmt = $pdo->prepare("
        SELECT
            mri.*,
            COALESCE(d.description_name, mri.description_name) AS description_name,
            COALESCE(b.brand_name, mri.brand_name) AS brand_name
        FROM maintenance_report_items mri
        LEFT JOIN list_description d ON d.id = mri.description_id
        LEFT JOIN list_brand b ON b.id = mri.brand_id
        {$itemWhere}
        ORDER BY mri.id ASC
    ");
    $itemStmt->execute($itemParams);
    $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$defaultSections = [
    'Hardware Maintenance' => [
        'Physically inspect for damage, loose cables, or components.',
        'Clean the computer and peripherals.',
        'Remove dust and debris from inside using compressed air.',
        'Assess hardware upgrades (e.g., RAM, hard drive).',
        'Inspect and clean peripheral devices (e.g., mouse, keyboard, printer).',
    ],
    'Software Maintenance' => [
        'Install operating system updates and patches.',
        'Update antivirus software and run a full system scan.',
        'Update and patch all software applications.',
        'Regularly back up important data.',
        'Perform disk cleanup to optimize storage.',
        'Review and update security settings and passwords.',
        'Organize files and folders for efficient data retrieval.',
    ],
    'Performance Testing' => [
        'Run system performance tests or diagnostics.',
        'Test internet speed and connection stability.',
    ],
    'Backup and Recovery' => [
        'Ensure there is a data recovery plan.',
        'Test data recovery procedures.',
    ],
    'Security Checks' => [
        'Confirm the firewall is active and configured correctly.',
        'Verify that security software is functioning.',
        'Monitor for security alerts and threats.',
        'Internet Connection.',
    ],
    'Documentation' => [
        'Maintain records of all maintenance activities.',
    ],
];

$sections = [];
if (db_table_exists($pdo, 'maintenance_report_checks')) {
    $checkStmt = $pdo->prepare('SELECT category, item_label, is_checked FROM maintenance_report_checks WHERE maintenance_report_id = ? ORDER BY id ASC');
    $checkStmt->execute([$id]);
    foreach ($checkStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $check) {
        $category = trim((string) ($check['category'] ?? 'General')) ?: 'General';
        $sections[$category][] = [
            'label' => trim((string) ($check['item_label'] ?? '')),
            'checked' => (int) ($check['is_checked'] ?? 0) === 1,
        ];
    }
}

if (empty($sections)) {
    foreach ($defaultSections as $category => $labels) {
        foreach ($labels as $label) {
            $sections[$category][] = ['label' => $label, 'checked' => true];
        }
    }
}

function mx_value(array $row, string $key): string
{
    return trim((string) ($row[$key] ?? ''));
}

function mx_date($value): string
{
    $value = trim((string) ($value ?? ''));
    if ($value === '' || $value === '0000-00-00') {
        return '';
    }
    $time = strtotime($value);
    return $time ? date('Y-m-d', $time) : $value;
}

function mx_yes($value): bool
{
    $value = strtolower(trim((string) ($value ?? '')));
    return in_array($value, ['yes', 'y', '1', 'true', 'branded', 'assembled'], true);
}

function mx_item_label(array $item): string
{
    $type = strtoupper(trim((string) ($item['item_type'] ?? 'ITEM')));
    $description = trim((string) ($item['description_name'] ?? ''));
    $control = trim((string) ($item['control_no'] ?? ''));
    $oldControl = trim((string) ($item['old_control_no'] ?? ''));
    $brand = trim((string) ($item['brand_name'] ?? ''));
    $serial = trim((string) ($item['serialno'] ?? ''));
    $parts = array_filter([
        $type,
        $description,
        $control !== '' ? 'Control: ' . $control : '',
        $oldControl !== '' ? 'Old: ' . $oldControl : '',
        $brand !== '' ? 'Brand: ' . $brand : '',
        $serial !== '' ? 'SN: ' . $serial : '',
    ], static fn($value) => $value !== '');
    return implode(' | ', $parts) ?: ('Item #' . (int) ($item['id'] ?? 0));
}

function mx_items_are_assembled(array $items): bool
{
    foreach ($items as $item) {
        $haystack = strtolower(trim((string) ($item['description_name'] ?? '') . ' ' . (string) ($item['brand_name'] ?? '')));
        if (strpos($haystack, 'assembled') !== false) {
            return true;
        }
    }
    return false;
}

function mx_item_brand_label(array $items): string
{
    $brands = [];
    foreach ($items as $item) {
        $brand = trim((string) ($item['brand_name'] ?? ''));
        if ($brand !== '') {
            $brands[$brand] = true;
        }
    }
    return implode(', ', array_keys($brands));
}

$isAssembled = !empty($items) ? mx_items_are_assembled($items) : mx_yes(mx_value($report, 'assemble'));
$isBranded = !$isAssembled && (!empty($items) || (mx_value($report, 'branded') !== '' && strtolower(mx_value($report, 'branded')) !== 'no'));
$brandedDisplay = $isBranded ? (mx_item_brand_label($items) ?: mx_value($report, 'branded') ?: 'Branded') : (mx_value($report, 'branded') ?: '__________');

$itLogo = ims_pdf_asset_url('assets/images/demo-mark.svg');
$mandaLogo = ims_pdf_asset_url('assets/images/demo-mark.svg');
$isoLogo = ims_pdf_asset_url('assets/images/demo-scene.svg');
$bagongLogo = ims_pdf_asset_url('assets/images/demo-mark.svg');
ob_start();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Maintenance Checklist <?= e(mx_value($report, 'maintenance_id')) ?></title>
    <style>
        @page { size: A4 portrait; margin: 0; }
        * { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        :root { --sheet-w: 190mm; }
        body { margin: 0; background: #eef0f4; color: #111; font-family: Arial, Helvetica, sans-serif; font-size: 10px; }
        .print-actions { position: sticky; top: 0; z-index: 2; display: flex; justify-content: flex-end; align-items: center; gap: 8px; padding: 10px; background: #fff; border-bottom: 1px solid #d7dce5; }
        .print-actions .toolbar-label { color: #344054; font-size: 11px; font-weight: 800; }
        .print-actions select { min-width: 420px; max-width: 46vw; min-height: 74px; border: 1px solid #d7dce5; border-radius: 4px; padding: 6px 10px; color: #1f2937; background: #fff; font-weight: 800; font-size: 11px; }
        .print-actions button { border: 0; border-radius: 4px; padding: 8px 12px; font-weight: 800; cursor: pointer; }
        .print-actions .primary { background: #3657f6; color: #fff; }
        .print-actions .light { background: #eef1f5; color: #344054; }
        .page { width: 210mm; min-height: 297mm; margin: 12px auto; padding: 7mm 10mm 12mm; background: #fff; box-shadow: 0 16px 40px rgba(15,23,42,.18); }
        .header { position: relative; width: var(--sheet-w); min-height: 37mm; margin: 0 auto; text-align: center; line-height: 1.16; padding-top: 1mm; }
        .header img { position: absolute; object-fit: contain; }
        .it-logo { left: 0; top: 0; width: 20mm; height: 20mm; }
        .manda-logo { left: 22mm; top: 0; width: 18mm; height: 20mm; }
        .iso-logo { right: 22mm; top: 0; width: 18mm; height: 20mm; }
        .bagong-logo { right: 0; top: 0; width: 20mm; height: 20mm; }
        .republic { font-family: "Times New Roman", serif; font-size: 10px; }
        .office { font-family: "Times New Roman", serif; font-weight: 700; font-size: 14px; margin-top: 2mm; }
        .cert { font-size: 8px; margin-top: 1mm; }
        h1 { margin: 3mm 0 0; font-size: 14px; line-height: 1.1; }
        .control-no { width: var(--sheet-w); margin: 0 auto 2mm; text-align: right; font-size: 10px; }
        .control-no strong { font-size: 11px; }
        .section { width: var(--sheet-w); margin: 0 auto 2mm; break-inside: avoid; }
        .section h2 { margin: 0 0 1mm; font-size: 11px; }
        .check-row { display: flex; align-items: flex-start; gap: 2mm; min-height: 5mm; line-height: 1.35; }
        .check-box { flex: 0 0 auto; width: 4mm; height: 4mm; margin-top: .4mm; border: 1px solid #111; display: inline-flex; align-items: center; justify-content: center; font-size: 9px; font-weight: 800; }
        .hardware-row { width: var(--sheet-w); margin: 2mm auto 3mm; display: grid; grid-template-columns: 1fr 1fr; gap: 16mm; }
        .field-check { display: flex; align-items: center; gap: 2mm; font-size: 11px; }
        .field-check strong { font-size: 11px; }
        table { width: var(--sheet-w); margin: 0 auto; border-collapse: collapse; table-layout: fixed; }
        td, th { border: 1px solid #111; padding: 2mm; vertical-align: top; }
        th { background: #f3f4f6; text-align: left; font-size: 10px; }
        .info-table td:first-child { width: 56mm; font-weight: 700; }
        .info-table td:last-child { font-weight: 700; }
        .items-table { margin-top: 3mm; font-size: 9px; }
        .items-table + .info-table { margin-top: 3mm; }
        .footer { position: fixed; left: 10mm; right: 10mm; bottom: 5mm; text-align: center; font-size: 7px; line-height: 1.25; color: #444; }
        @media print {
            html, body { width: 210mm; min-height: 297mm; background: #fff; }
            body { margin: 0; }
            .print-actions { display: none !important; }
            .page { margin: 0 !important; box-shadow: none !important; width: 210mm !important; min-height: 297mm !important; padding: 7mm 10mm 12mm !important; }
        }
        @media screen and (max-width: 800px) {
            .page { width: 100%; min-height: auto; margin: 0; padding: 12px; }
            .header, .section, .control-no, table, .hardware-row { width: 100%; }
            .header { min-height: 44mm; padding-top: 24mm; }
            .hardware-row { grid-template-columns: 1fr; gap: 2mm; }
        }
    </style>
</head>
<body>
    <div class="print-actions">
        <?php if (!empty($allItems)): ?>
            <span class="toolbar-label">Include</span>
            <select id="itemPickerSelect" multiple size="3" aria-label="Included maintenance items">
                <?php foreach ($allItems as $item): ?>
                    <?php $itemId = (int) ($item['id'] ?? 0); ?>
                    <option value="<?= $itemId ?>" <?= (!$hasSelectedItems || in_array($itemId, $selectedItemIds, true)) ? 'selected' : '' ?>>
                        <?= e(mx_item_label($item)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="button" class="light" id="applyItemPicker">Apply Items</button>
        <?php endif; ?>
        <button type="button" class="light" onclick="window.close()">Close</button>
        <button type="button" class="primary" onclick="window.print()">Print / Save PDF</button>
    </div>

    <main class="page">
        <header class="header">
            <img class="it-logo" src="<?= e($itLogo) ?>" alt="">
            <img class="manda-logo" src="<?= e($mandaLogo) ?>" alt="">
            <img class="iso-logo" src="<?= e($isoLogo) ?>" alt="">
            <img class="bagong-logo" src="<?= e($bagongLogo) ?>" alt="">
            <div class="republic">DEMO SERVICES</div>
            <div class="republic">SAMPLE SERVICE CENTER</div>
            <div class="office">CITY INFORMATION AND COMMUNICATIONS</div>
            <div class="office">TECHNOLOGY DEPARTMENT</div>
            <div class="cert">ISO 9001:2015 CERTIFIED</div>
            <h1>Computer Maintenance Checklist</h1>
        </header>

        <div class="control-no">Control No: <strong><?= e(mx_value($report, 'control_no')) ?></strong></div>

        <?php foreach ($sections as $category => $checks): ?>
            <section class="section">
                <h2><?= e($category) ?></h2>
                <?php foreach ($checks as $check): ?>
                    <div class="check-row">
                        <span class="check-box"><?= !empty($check['checked']) ? '&#10003;' : '' ?></span>
                        <span><?= e($check['label']) ?></span>
                    </div>
                <?php endforeach; ?>
            </section>
        <?php endforeach; ?>

        <section class="hardware-row">
            <div class="field-check"><span class="check-box"><?= $isBranded ? '&#10003;' : '' ?></span><span>Branded: <strong><?= e($brandedDisplay) ?></strong></span></div>
            <div class="field-check"><span class="check-box"><?= $isAssembled ? '&#10003;' : '' ?></span><span>Assemble: <strong><?= e(mx_value($report, 'assemble') ?: '__________') ?></strong></span></div>
        </section>

        <?php if (!empty($items)): ?>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Control No</th>
                        <th>Old Control No</th>
                        <th>Brand</th>
                        <th>Serial No</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= e($item['description_name'] ?? $item['item_type'] ?? '') ?></td>
                            <td><?= e($item['control_no'] ?? '') ?></td>
                            <td><?= e($item['old_control_no'] ?? '') ?></td>
                            <td><?= e($item['brand_name'] ?? '') ?></td>
                            <td><?= e($item['serialno'] ?? '') ?></td>
                            <td><?= e($item['remarks'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <table class="info-table">
            <tr><td>Computer Owner's Name and Signature:</td><td><?= e(mx_value($report, 'end_user_name')) ?></td></tr>
            <tr><td>Dept./Office:</td><td><?= e(mx_value($report, 'designation_name') ?: mx_value($report, 'department_name')) ?></td></tr>
            <tr><td>Date Accomplished:</td><td><?= e(mx_date(mx_value($report, 'date_accomplished'))) ?></td></tr>
            <tr><td>Inspected by (ITD Staff):</td><td><?= e(mx_value($report, 'inspected_by_name')) ?></td></tr>
        </table>
    </main>

    <footer class="footer">
        Sample Service Center ? Fictional address<br>
        Email: support@example.test &nbsp;&nbsp; Contact No: Demo support
    </footer>
    <?php if (!empty($allItems)): ?>
        <script>
            document.getElementById('applyItemPicker').addEventListener('click', function () {
                const selectedIds = Array.from(document.getElementById('itemPickerSelect').selectedOptions).map(option => option.value);
                if (!selectedIds.length) {
                    alert('Select at least one item to include.');
                    return;
                }
                const url = new URL(window.location.href);
                url.searchParams.delete('item_filter');
                url.searchParams.set('item_ids', selectedIds.join(','));
                window.location.href = url.toString();
            });
        </script>
    <?php endif; ?>
    <?php if ($autoPrint): ?>
        <script>
            window.addEventListener('load', function () {
                setTimeout(function () { window.print(); }, 250);
            });
        </script>
    <?php endif; ?>
</body>
</html>
<?php
$html = ob_get_clean();
ims_output_pdf_or_html($html, 'maintenance-' . (mx_value($report, 'maintenance_id') ?: $id) . '.pdf');
?>
