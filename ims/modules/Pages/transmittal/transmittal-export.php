<?php
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/functions.php';
require_once ROOT_PATH . '/helpers/pdf-renderer.php';

require_login();
require_permission('ictd.transmittal.view');

$id = (int) ($_GET['id'] ?? 0);
$transmittalNo = trim((string) ($_GET['transmittal_id'] ?? ''));
if ($id <= 0 && $transmittalNo === '') {
    http_response_code(422);
    echo 'Transmittal ID required.';
    exit;
}
$autoPrint = (int) ($_GET['print'] ?? 0) === 1;
if (db_table_exists($pdo, 'transmittal') && !db_column_exists($pdo, 'transmittal', 'designation_id')) {
    $pdo->exec('ALTER TABLE transmittal ADD COLUMN designation_id INT NULL AFTER department_id');
}

$where = $id > 0 ? 't.id = ?' : 't.transmittal_id = ?';
$param = $id > 0 ? $id : $transmittalNo;
$stmt = $pdo->prepare("
    SELECT t.*, dep.department_name, des.department_name AS designation_name
    FROM transmittal t
    LEFT JOIN list_department dep ON dep.department_id = t.department_id
    LEFT JOIN list_department des ON des.department_id = t.designation_id
    WHERE {$where}
    LIMIT 1
");
$stmt->execute([$param]);
$info = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$info) {
    http_response_code(404);
    echo 'Transmittal not found.';
    exit;
}

$transmittalId = trim((string) ($info['transmittal_id'] ?? ''));
$grouped = [];

function tx_add_grouped_item(array &$grouped, string $description, string $brandSpecs, int $qty, string $serial): void
{
    $description = trim($description);
    $brandSpecs = trim($brandSpecs);
    $serial = trim($serial);
    $key = $description . '||' . $brandSpecs;
    if (!isset($grouped[$key])) {
        $grouped[$key] = [
            'description' => $description,
            'brand_specs' => $brandSpecs,
            'qty' => max(1, $qty),
            'serials' => $serial !== '' ? [$serial] : [],
        ];
        return;
    }
    $grouped[$key]['qty'] += max(1, $qty);
    if ($serial !== '') {
        $grouped[$key]['serials'][] = $serial;
    }
}

if (db_table_exists($pdo, 'transmittal_items')) {
    $storedStmt = $pdo->prepare("
        SELECT
            COALESCE(NULLIF(ti.item_type, ''), CASE WHEN ti.source_id IS NULL THEN 'manual' ELSE 'equipment' END) AS item_type,
            ti.source_id,
            COALESCE(d2.description_name, de.description_name, ds.description_name, ti.description) AS description_name,
            COALESCE(b2.brand_name, be.brand_name, bs.brand_name, ti.brand) AS brand_name,
            COALESCE(ti.serialno, e.serialno, oe.serialno) AS serialno,
            e.os,
            e.os_prokey,
            e.ms_software,
            e.ms_uname,
            e.ms_pass,
            e.processor,
            e.memory,
            e.hdd,
            e.video_card,
            COALESCE(ti.qty, 1) AS qty
        FROM transmittal_items ti
        LEFT JOIN list_description d2 ON d2.id = ti.description_id
        LEFT JOIN list_brand b2 ON b2.id = ti.brand_id
        LEFT JOIN equipment e ON ti.item_type = 'equipment' AND e.id = ti.source_id
        LEFT JOIN list_description de ON de.id = e.description_id
        LEFT JOIN list_brand be ON be.id = e.brand_id
        LEFT JOIN other_equipment oe ON ti.item_type = 'supply' AND oe.id = ti.source_id
        LEFT JOIN list_description ds ON ds.id = oe.description_id
        LEFT JOIN list_brand bs ON bs.id = oe.brand_id
        WHERE ti.transmittal_pk = ? OR ti.transmittal_id = ?
        ORDER BY FIELD(COALESCE(NULLIF(ti.item_type, ''), 'manual'), 'equipment', 'supply', 'manual'), ti.id ASC
    ");
    $storedStmt->execute([(int) ($info['id'] ?? 0), $transmittalId]);
    foreach ($storedStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        if (($row['item_type'] ?? '') !== 'equipment') {
            tx_add_grouped_item(
                $grouped,
                (string) ($row['description_name'] ?? ''),
                (string) ($row['brand_name'] ?? ''),
                (int) ($row['qty'] ?? 1),
                (string) ($row['serialno'] ?? '')
            );
            continue;
        }
        $specs = [];
        foreach (['os', 'os_prokey', 'ms_software', 'ms_uname', 'ms_pass', 'processor', 'memory', 'hdd', 'video_card'] as $column) {
            $value = trim((string) ($row[$column] ?? ''));
            if ($value !== '') {
                $specs[] = $value;
            }
        }
        $brandSpecs = trim((string) ($row['brand_name'] ?? ''));
        if (!empty($specs)) {
            $brandSpecs .= ($brandSpecs !== '' ? ' - ' : '') . implode(', ', $specs);
        }
        tx_add_grouped_item($grouped, (string) ($row['description_name'] ?? ''), $brandSpecs, 1, (string) ($row['serialno'] ?? ''));
    }
}

if (empty($grouped) && db_table_exists($pdo, 'transmittal_equipment') && db_table_exists($pdo, 'equipment')) {
    $equipmentStmt = $pdo->prepare("
        SELECT DISTINCT
            e.id,
            d.description_name,
            b.brand_name,
            e.serialno,
            e.os,
            e.os_prokey,
            e.ms_software,
            e.ms_uname,
            e.ms_pass,
            e.processor,
            e.memory,
            e.hdd,
            e.video_card
        FROM transmittal_equipment te
        JOIN equipment e ON e.id = te.equipment_id
        LEFT JOIN list_description d ON d.id = e.description_id
        LEFT JOIN list_brand b ON b.id = e.brand_id
        WHERE te.transmittal_id = ?
        ORDER BY d.description_name ASC, e.serialno ASC
    ");
    $equipmentStmt->execute([$transmittalId]);
    foreach ($equipmentStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $specs = [];
        foreach (['os', 'os_prokey', 'ms_software', 'ms_uname', 'ms_pass', 'processor', 'memory', 'hdd', 'video_card'] as $column) {
            $value = trim((string) ($row[$column] ?? ''));
            if ($value !== '') {
                $specs[] = $value;
            }
        }
        $brandSpecs = trim((string) ($row['brand_name'] ?? ''));
        if (!empty($specs)) {
            $brandSpecs .= ($brandSpecs !== '' ? ' - ' : '') . implode(', ', $specs);
        }
        tx_add_grouped_item($grouped, (string) ($row['description_name'] ?? ''), $brandSpecs, 1, (string) ($row['serialno'] ?? ''));
    }
}

function tx_date($value, string $format = 'F j, Y'): string
{
    $value = trim((string) ($value ?? ''));
    if ($value === '' || $value === '0000-00-00') {
        return '';
    }
    $time = strtotime($value);
    return $time ? date($format, $time) : $value;
}

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
    <title>Transmittal <?= e($transmittalId) ?></title>
    <style>
        @page { size: A4 portrait; margin: 0; }
        * { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        :root { --sheet-w: 190mm; }
        body { margin: 0; background: #eef0f4; color: #111; font-family: Arial, Helvetica, sans-serif; font-size: 10px; }
        .print-actions { position: sticky; top: 0; z-index: 2; display: flex; justify-content: flex-end; gap: 8px; padding: 10px; background: #fff; border-bottom: 1px solid #d7dce5; }
        .print-actions button { border: 0; border-radius: 4px; padding: 8px 12px; font-weight: 800; cursor: pointer; }
        .print-actions .primary { background: #3657f6; color: #fff; }
        .print-actions .light { background: #eef1f5; color: #344054; }
        .page { width: 210mm; min-height: 297mm; margin: 12px auto; padding: 7mm 10mm 12mm; background: #fff; box-shadow: 0 16px 40px rgba(15,23,42,.18); }
        .header { position: relative; width: var(--sheet-w); min-height: 39mm; margin: 0 auto; text-align: center; line-height: 1.16; padding-top: 1mm; }
        .header img { position: absolute; object-fit: contain; }
        .logo-left-a { left: 0; top: 0; width: 21mm; height: 22mm; }
        .logo-left-b { left: 23mm; top: 0; width: 22mm; height: 22mm; }
        .logo-right-a { right: 25mm; top: 0; width: 25mm; height: 22mm; }
        .logo-right-b { right: 0; top: 0; width: 20mm; height: 22mm; }
        .republic { font-family: "Times New Roman", serif; font-weight: 700; font-size: 8px; }
        .office { font-family: "Times New Roman", serif; font-weight: 700; font-size: 11px; margin-top: 1mm; }
        .cert { font-size: 8px; margin-top: 1mm; }
        h1 { margin: 5mm 0 0; font-size: 16px; line-height: 1.1; }
        .content { width: var(--sheet-w); margin: 0 auto; }
        .date-line { text-align: right; margin-bottom: 4mm; }
        .info-row { display: grid; grid-template-columns: 50mm 1fr; gap: 2mm; margin-bottom: 1mm; font-size: 11px; }
        .info-row strong { font-size: 11px; }
        .intro { margin: 5mm 0 2mm; line-height: 1.4; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 8px; }
        th, td { border: 1px solid #111; padding: 2mm; vertical-align: top; }
        th { background: #c8dcff; text-align: center; font-size: 10px; }
        .num { width: 7mm; text-align: center; }
        .desc { width: 50mm; }
        .brand { width: 60mm; }
        .qty { width: 10mm; text-align: center; }
        .serials { width: 55mm; }
        .closing { margin-top: 4mm; font-size: 10px; line-height: 1.45; }
        .chief { width: 62mm; margin: 8mm 0 8mm auto; text-align: left; }
        .chief strong { display: block; }
        .receive { margin-top: 3mm; font-size: 10px; }
        .receive-title { font-weight: 700; margin-bottom: 6mm; }
        .date-received { margin-top: 4mm; width: 70mm; text-align: right; font-style: italic; }
        .footer { position: fixed; left: 10mm; right: 10mm; bottom: 5mm; display: flex; justify-content: space-between; color: #888; font-style: italic; font-size: 9px; }
        @media print {
            html, body { width: 210mm; min-height: 297mm; background: #fff; }
            body { margin: 0; }
            .print-actions { display: none !important; }
            .page { margin: 0 !important; box-shadow: none !important; width: 210mm !important; min-height: 297mm !important; padding: 7mm 10mm 12mm !important; }
        }
        @media screen and (max-width: 800px) {
            .page { width: 100%; min-height: auto; margin: 0; padding: 12px; }
            .header, .content { width: 100%; }
            .header { min-height: 47mm; padding-top: 24mm; }
            .info-row { grid-template-columns: 1fr; }
            table { min-width: 760px; }
            .table-wrap { overflow-x: auto; }
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
            <div class="office">CITY INFORMATION AND COMMUNICATION</div>
            <div class="office">TECHNOLOGY DEPARTMENT</div>
            <div class="cert">ISO 9001:2015 CERTIFIED</div>
            <h1>TRANSMITTAL</h1>
        </header>

        <section class="content">
            <div class="date-line">Transmittal Date: <?= e(tx_date($info['transmittal_date'] ?? '')) ?></div>

            <div class="info-row"><span>NAME OF OFFICE :</span><strong><?= e(($info['designation_name'] ?? '') ?: ($info['department_name'] ?? '')) ?></strong></div>
            <div class="info-row"><span>ACCOUNTABLE OFFICER :</span><strong><?= e($info['accountable_officer'] ?? '') ?></strong></div>

            <p class="intro">Please acknowledge the transfer of the following computer hardware devices and peripherals in good condition:</p>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th class="num">No</th>
                            <th class="desc">Description</th>
                            <th class="brand">Brand / Specs</th>
                            <th class="qty">Qty</th>
                            <th class="serials">Serial Numbers</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($grouped)): ?>
                            <tr><td colspan="5" style="text-align:center;">No items recorded.</td></tr>
                        <?php else: ?>
                            <?php $no = 1; foreach ($grouped as $item): ?>
                                <tr>
                                    <td class="num"><?= $no++ ?></td>
                                    <td><?= e($item['description']) ?></td>
                                    <td><?= e($item['brand_specs']) ?></td>
                                    <td class="qty"><?= (int) $item['qty'] ?></td>
                                    <td><?= e(implode(', ', array_unique($item['serials']))) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <section class="closing">
                <p>Please be advised that the above mentioned articles preempt MR _______________________ previously assigned to the undersigned.</p>
                <p>Thank you.</p>
                <div class="chief">
                    <div>Very truly yours,</div>
                    <br>
                    <strong>SAMPLE SUPERVISOR</strong>
                    <div>Demo Supervisor</div>
                </div>

                <div class="receive">
                    <div class="receive-title">RECEIVED AND CHECKED BY:</div>
                    <div>NAME: ________________________________ &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; DATE/TIME: ____________________</div>
                    <div>(Signature Over Printed Name)</div>
                    <br>
                    <div>DESIGNATION : ________________________</div>
                    <div class="date-received">Date Received: <?= e(tx_date($info['date_received'] ?? '')) ?></div>
                </div>
            </section>
        </section>
    </main>

    <footer class="footer">
        <span>Page 1</span>
        <span><?= e($transmittalId) ?></span>
    </footer>
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
ims_output_pdf_or_html($html, 'transmittal-' . ($transmittalId ?: $id) . '.pdf');
?>
