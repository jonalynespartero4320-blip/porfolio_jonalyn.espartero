<?php
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/functions.php';
require_once ROOT_PATH . '/helpers/pdf-renderer.php';

require_login();
require_permission('ictd.service-report.view');

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(422);
    echo 'Invalid Service ID.';
    exit;
}
$autoPrint = (int) ($_GET['print'] ?? 0) === 1;
if (db_table_exists($pdo, 'service_report') && !db_column_exists($pdo, 'service_report', 'employee_designation_id')) {
    $pdo->exec('ALTER TABLE service_report ADD COLUMN employee_designation_id INT NULL AFTER department_id');
}

$stmt = $pdo->prepare("
    SELECT
        sr.*,
        eu.end_user_name,
        dep.department_name,
        edes.department_name AS employee_designation_name
    FROM service_report sr
    LEFT JOIN list_end_user eu ON eu.end_user_id = sr.end_user_id
    LEFT JOIN list_department dep ON dep.department_id = sr.department_id
    LEFT JOIN list_department edes ON edes.department_id = sr.employee_designation_id
    WHERE sr.id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$report = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$report) {
    http_response_code(404);
    echo 'Service report not found.';
    exit;
}

$items = [];
if (db_table_exists($pdo, 'service_report_items')) {
    $itemStmt = $pdo->prepare("
        SELECT sri.*, ld.description_name, lb.brand_name
        FROM service_report_items sri
        LEFT JOIN list_description ld ON ld.id = sri.description_id
        LEFT JOIN list_brand lb ON lb.id = sri.brand_id
        WHERE sri.service_report_id = ?
        ORDER BY sri.id ASC
    ");
    $itemStmt->execute([$id]);
    $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function sr_export_value(array $row, string $key): string
{
    return trim((string) ($row[$key] ?? ''));
}

function sr_export_text($value): string
{
    $value = trim((string) ($value ?? ''));
    if ($value === '') {
        return '';
    }
    $decoded = json_decode($value, true);
    if (is_array($decoded)) {
        return implode(', ', array_values(array_filter(array_map('trim', array_map('strval', $decoded)))));
    }
    return $value;
}

function sr_export_date($value): string
{
    $value = trim((string) ($value ?? ''));
    if ($value === '' || $value === '0000-00-00') {
        return '0000-00-00';
    }
    $time = strtotime($value);
    return $time ? date('Y-m-d', $time) : $value;
}

function sr_export_time($value): string
{
    $value = trim((string) ($value ?? ''));
    if ($value === '') {
        return '';
    }
    $time = strtotime($value);
    return $time ? date('h:i A', $time) : $value;
}

$serviceNo = sr_export_value($report, 'old_service_cn') ?: sr_export_value($report, 'service_controlno');
$ratings = ['Excellent', 'Very Satisfactory', 'Satisfactory', 'Poor'];
$rating = sr_export_value($report, 'employee_satisfactory');
$itLogo = ims_pdf_asset_url('assets/images/demo-mark.svg');
$mandaLogo = ims_pdf_asset_url('assets/images/demo-mark.svg');
$bagongLogo = ims_pdf_asset_url('assets/images/demo-mark.svg');
$isoLogo = ims_pdf_asset_url('assets/images/demo-scene.svg');
ob_start();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Service Report <?= e($serviceNo) ?></title>
    <style>
        @page { size: A4 portrait; margin: 0; }
        * { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        :root { --sheet-w: 190mm; }
        body { margin: 0; background: #eef0f4; color: #000; font-family: Arial, Helvetica, sans-serif; font-size: 10px; }
        .print-actions { position: sticky; top: 0; z-index: 2; display: flex; justify-content: flex-end; gap: 8px; padding: 10px; background: #fff; border-bottom: 1px solid #d7dce5; }
        .print-actions button { border: 0; border-radius: 4px; padding: 8px 12px; font-weight: 700; cursor: pointer; }
        .print-actions .primary { background: #1f6fe5; color: #fff; }
        .print-actions .light { background: #eef1f5; color: #344054; }
        .page { width: 210mm; min-height: 297mm; margin: 12px auto; padding: 7mm 10mm 10mm; background: #fff; box-shadow: 0 16px 40px rgba(15,23,42,.18); overflow: hidden; }
        .header { position: relative; width: var(--sheet-w); min-height: 38mm; margin: 0 auto; text-align: center; line-height: 1.16; padding-top: 1mm; }
        .header img { position: absolute; object-fit: contain; }
        .logo-left-a { left: 0; top: 0; width: 21mm; height: 22mm; }
        .logo-left-b { left: 23mm; top: 0; width: 22mm; height: 22mm; }
        .logo-right-a { right: 25mm; top: 0; width: 25mm; height: 22mm; }
        .logo-right-b { right: 0; top: 0; width: 20mm; height: 22mm; }
        .republic { font-family: "Times New Roman", serif; font-weight: 700; font-size: 7px; }
        .office { font-family: "Times New Roman", serif; font-weight: 700; font-size: 10px; margin-top: 1px; }
        .cert { font-size: 7px; margin-top: 1px; }
        .address { max-width: 92mm; margin: 1px auto 0; font-size: 6px; }
        h1 { margin: 4mm 0 0; font-size: 14px; line-height: 1.1; }
        .service-no { width: var(--sheet-w); display: flex; justify-content: flex-end; align-items: center; gap: 5px; margin: 0 auto 1mm; font-size: 10px; }
        .service-no strong { min-width: 42mm; text-align: left; font-size: 11px; }
        table { width: var(--sheet-w); margin-left: auto; margin-right: auto; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #000; padding: 3px 4px; vertical-align: top; }
        th.section { background: #646464; color: #fff; text-align: center; font-size: 10px; padding: 4px; }
        th.section-half { width: 50%; }
        .info-table col.info-label-col { width: 30mm; }
        .info-table col.info-value-col { width: 108mm; }
        .info-table col.travel-label-col { width: 27mm; }
        .info-table col.travel-value-col { width: 25mm; }
        .label { font-weight: 400; }
        .value { font-weight: 700; }
        .unit-grid { width: var(--sheet-w); margin: 0 auto; display: grid; grid-template-columns: repeat(3, 1fr); border-left: 1px solid #000; border-top: 1px solid #000; }
        .unit-card { min-height: 22mm; border-right: 1px solid #000; border-bottom: 1px solid #000; padding: 3px 4px; line-height: 1.22; overflow: hidden; }
        .unit-card strong { display: block; font-size: 8px; margin-bottom: 1px; }
        .split { width: var(--sheet-w); margin: 0 auto; display: grid; grid-template-columns: var(--left) 1fr; }
        .split table { width: 100%; margin: 0; }
        .split .left-text { min-height: 15mm; border: 1px solid #000; border-top: 0; padding: 4px; font-size: 9px; line-height: 1.25; white-space: pre-line; overflow: hidden; }
        .split .time-table { border-left: 0; }
        .split .time-table td { height: 7.5mm; padding: 2px 4px; }
        .signature-row { width: var(--sheet-w); display: grid; grid-template-columns: 1fr 1fr; gap: 20mm; margin: 5mm auto 2mm; text-align: center; font-size: 10px; }
        .signature-name { min-height: 6mm; border-bottom: 1px solid #000; padding-top: 1mm; }
        .signature-label { font-weight: 700; margin-top: 1.5mm; }
        .conforme { width: 72mm; margin: 0 calc((100% - var(--sheet-w)) / 2 + 10mm) 0 auto; font-size: 9px; font-weight: 700; }
        .line-row { display: flex; align-items: end; gap: 5mm; height: 6mm; }
        .line-row span:last-child { flex: 1; border-bottom: 1px solid #000; }
        .disclaimer { width: var(--sheet-w); margin: 1mm auto 0; color: #666; font-style: italic; font-size: 7px; line-height: 1.25; white-space: pre-line; }
        .receipt-note { width: var(--sheet-w); margin: 1mm auto 0; }
        .receipt-survey { width: var(--sheet-w); display: grid; grid-template-columns: 1fr 1fr; gap: 12mm; margin: 2mm auto 0; font-size: 7px; }
        .section-title { font-weight: 700; margin-bottom: 3mm; }
        .small-line { display: flex; align-items: end; gap: 4mm; height: 5mm; }
        .small-line span:last-child { flex: 1; border-bottom: 1px solid #000; }
        .rating { display: grid; grid-template-columns: 4mm 1fr; gap: 2mm; align-items: center; margin-bottom: 1mm; }
        .box { width: 4mm; height: 4mm; border: 1px solid #000; text-align: center; line-height: 3.5mm; font-weight: 700; }
        @media print {
            html, body { width: 210mm; min-height: 297mm; background: #fff; }
            body { margin: 0; }
            .print-actions { display: none !important; }
            .page { margin: 0 !important; box-shadow: none !important; width: 210mm !important; min-height: 297mm !important; padding: 7mm 10mm 10mm !important; }
        }
        @media screen and (max-width: 800px) {
            :root { --sheet-w: 100%; }
            .page { width: 100%; min-height: auto; margin: 0; padding: 12px; overflow-x: auto; }
            .header { min-height: 48mm; padding-top: 25mm; }
            .service-no, table, .unit-grid, .split, .signature-row, .conforme, .disclaimer, .receipt-note, .receipt-survey { width: 100%; }
            .split { grid-template-columns: 1fr; }
            .split .time-table { border-left: 1px solid #000; }
        }
    </style>
</head>
<body>
    <div class="print-actions">
        <button type="button" class="light" onclick="window.close()">Close</button>
        <button type="button" class="primary" onclick="window.print()">Print / Save PDF</button>
    </div>

    <main class="page">
        <header class="header">
            <img class="logo-left-a" src="<?= e($mandaLogo) ?>" alt="">
            <img class="logo-left-b" src="<?= e($itLogo) ?>" alt="">
            <img class="logo-right-a" src="<?= e($bagongLogo) ?>" alt="">
            <img class="logo-right-b" src="<?= e($isoLogo) ?>" alt="">
            <div class="republic">DEMO SERVICES</div>
            <div class="republic">SAMPLE SERVICE CENTER</div>
            <div class="office">CITY INFORMATION AND COMMUNICATIONS</div>
            <div class="office">TECHNOLOGY DEPARTMENT</div>
            <div class="cert">ISO 9001:2015 CERTIFIED</div>
            <div class="address">Sample Service Center ? Fictional address</div>
            <div class="address">Email Address: support@example.test | Contact Number: Demo support</div>
            <h1>ACKNOWLEDGEMENT/SERVICE REPORT SLIP</h1>
        </header>

        <div class="service-no">
            <span>Service Report No.:</span>
            <strong><?= e($serviceNo) ?></strong>
        </div>

        <table class="info-table">
            <colgroup>
                <col class="info-label-col">
                <col class="info-value-col">
                <col class="travel-label-col">
                <col class="travel-value-col">
            </colgroup>
            <tr>
                <th colspan="2" class="section section-half">EMPLOYEE INFORMATION</th>
                <th colspan="2" class="section section-half">TRAVEL INFORMATION</th>
            </tr>
            <tr>
                <td class="label">End User :</td><td class="value"><?= e(sr_export_value($report, 'end_user_name')) ?></td>
                <td>Date :</td><td class="value"><?= e(sr_export_date(sr_export_value($report, 'travel_date'))) ?></td>
            </tr>
            <tr>
                <td class="label">Department :</td><td class="value"><?= e(sr_export_value($report, 'employee_designation_name') ?: sr_export_value($report, 'department_name')) ?></td>
                <td>Departure :</td><td class="value"><?= e(sr_export_time(sr_export_value($report, 'travel_departure'))) ?></td>
            </tr>
            <tr>
                <td class="label">Vicinity :</td><td class="value"><?= e(sr_export_value($report, 'vicinity')) ?></td>
                <td>Arrival :</td><td class="value"><?= e(sr_export_time(sr_export_value($report, 'travel_arrival'))) ?></td>
            </tr>
            <tr>
                <td class="label">Distance :</td><td class="value"><?= e(sr_export_value($report, 'distance')) ?></td>
                <td></td><td></td>
            </tr>
        </table>

        <table><tr><th class="section">UNIT / DESCRIPTION</th></tr></table>
        <section class="unit-grid">
            <?php if (empty($items)): ?>
                <div class="unit-card" style="grid-column: span 3; text-align: center; padding-top: 10mm;">No equipment recorded.</div>
            <?php else: ?>
                <?php foreach ($items as $item): ?>
                    <?php
                        $description = strtoupper(trim((string) ($item['description_name'] ?? $item['other_description'] ?? $item['control_no'] ?? '-')));
                        $brand = strtoupper(trim((string) ($item['brand_name'] ?? '-')));
                    ?>
                    <div class="unit-card">
                        <strong>DES : <?= e(trim($description . ' ' . $brand)) ?></strong>
                        <div>SN : <?= e($item['serialno'] ?? '-') ?></div>
                        <div>WTY : <?= e($item['unit_warranty'] ?? '-') ?></div>
                        <div>REMARKS : <?= e($item['warranty_remarks'] ?? $item['remarks'] ?? '-') ?></div>
                    </div>
                <?php endforeach; ?>
                <?php for ($i = count($items) % 3; $i > 0 && $i < 3; $i++): ?>
                    <div class="unit-card"></div>
                <?php endfor; ?>
            <?php endif; ?>
        </section>

        <div class="split" style="--left:149mm;">
            <table><tr><th class="section">COMPLAIN / PROBLEM</th></tr></table>
            <table><tr><th class="section">INSPECTION TIME</th></tr></table>
            <div class="left-text"><?= e(sr_export_text($report['complain'] ?? '')) ?></div>
            <table class="time-table">
                <tr><td>Start</td><td class="value"><?= e(sr_export_time(sr_export_value($report, 'complain_inspection_time_start'))) ?></td></tr>
                <tr><td>Finish</td><td class="value"><?= e(sr_export_time(sr_export_value($report, 'complain_inspection_time_finish'))) ?></td></tr>
            </table>
        </div>

        <div class="split" style="--left:157mm;">
            <table><tr><th class="section">TROUBLESHOOT / WORK ACCOMPLISHED</th></tr></table>
            <table><tr><th class="section">REPAIR TIME</th></tr></table>
            <div class="left-text"><?= e(sr_export_text($report['troubleshoot'] ?? '')) ?></div>
            <table class="time-table">
                <tr><td>Start</td><td class="value"><?= e(sr_export_time(sr_export_value($report, 'troubleshoot_start'))) ?></td></tr>
                <tr><td>Finish</td><td class="value"><?= e(sr_export_time(sr_export_value($report, 'troubleshoot_finish'))) ?></td></tr>
            </table>
        </div>

        <div class="split" style="--left:121mm;">
            <table><tr><th class="section">REMARKS / SERVICES DETAILS</th></tr></table>
            <table><tr><th class="section">PULL-OUT</th></tr></table>
            <div class="left-text"><?= e(sr_export_text($report['remarks_service_details'] ?? '')) ?></div>
            <table class="time-table">
                <tr><td>Pull-out</td><td class="value"><?= e(sr_export_date(sr_export_value($report, 'remarks_date_pullout'))) ?></td></tr>
                <tr><td>Returned</td><td class="value"><?= e(sr_export_date(sr_export_value($report, 'remarks_date_returned'))) ?></td></tr>
            </table>
        </div>

        <section class="signature-row">
            <div>
                <div class="signature-name"><?= e(sr_export_text($report['attending_technician'] ?? '')) ?></div>
                <div class="signature-label">Attending Technician</div>
            </div>
            <div>
                <div class="signature-name"><?= e(sr_export_value($report, 'end_user_name')) ?></div>
                <div class="signature-label">End User's Signature Over Printed Name</div>
            </div>
        </section>

        <section class="conforme">
            <div class="line-row"><span>CONFORME :</span><span></span></div>
            <div class="line-row"><span>DATE :</span><span></span></div>
        </section>

        <section class="disclaimer">Disclaimer :
     * For PC's that need to be re-formatted, end user should back-up their own files.
     * Demo Services is not liable to any loss of your personal files (document, pictures, etc.).
     * Demo Services does not back-up, copy, or maintain any files in your PC/device.</section>

        <p class="receipt-note">Please acknowledge receipt of the following ICT equipment requested for repair, in good and working condition:</p>

        <section class="receipt-survey">
            <div>
                <div class="section-title">RECEIVED &amp; CHECKED BY</div>
                <div class="small-line"><span>Received by:</span><span></span></div>
                <div class="small-line"><span>Checked by:</span><span></span></div>
                <div class="small-line"><span>Date:</span><span></span></div>
            </div>
            <div>
                <div class="section-title">CUSTOMER / EMPLOYEE SATISFACTION SURVEY</div>
                <?php foreach ($ratings as $item): ?>
                    <div class="rating">
                        <span class="box"><?= strcasecmp($item, $rating) === 0 ? 'X' : '' ?></span>
                        <span><?= e($item) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </main>
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
ims_output_pdf_or_html($html, 'service-report-' . ($serviceNo ?: $id) . '.pdf');
?>
