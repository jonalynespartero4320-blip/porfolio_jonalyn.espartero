<?php
$page_title = 'Visitor ID Card';
require_once ROOT_PATH . '/modules/layout/header.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/modules/Pages/id-issuance/id-issuance-common.php';

$canPrintIdCard = can('id-issuance.print');
$canExportIdCard = can('id-issuance.export');

$visitorId = (int) ($_GET['visitor_id'] ?? 0);
if ($visitorId <= 0) {
    echo '<div class="alert alert-danger">Invalid visitor ID.</div>';
    require_once ROOT_PATH . '/modules/layout/footer.php';
    exit;
}

issuance_ensure_history_table($pdo);
$history = issuance_history($pdo, $visitorId);
if (empty($history)) {
    issuance_process($pdo, $visitorId, 'issue', [
        'id' => (int) (current_user()['id'] ?? 0),
        'name' => trim((string) (current_user()['username'] ?? 'system')),
    ]);
}

$visitor = issuance_load_visitor($pdo, $visitorId);
issuance_assert_verified($visitor);
$card = issuance_card_payload($visitor);
$cardBackgroundUrl = BASE_URL . '/assets/images/demo-scene.svg?v=' . time();
$bagongPilipinasLogo = BASE_URL . '/assets/images/demo-mark.svg';
$bjmpMandaLogo = BASE_URL . '/assets/images/demo-mark.svg';
$bjmpNationalLogo = BASE_URL . '/assets/images/demo-mark.svg';
?>

<style>
  * {
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
    box-sizing: border-box;
  }
  .issuance-layout {
    display: grid;
    grid-template-columns: minmax(260px, 320px) minmax(0, 1fr);
    gap: 24px;
  }
  .control-panel,
  .preview-panel {
    border: 0;
    border-radius: 24px;
    box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
  }
  .print-instruction {
    border: 1px solid rgba(245, 158, 11, 0.28);
    background: #fffbeb;
    color: #4b5563;
    border-radius: 16px;
    padding: 12px 14px;
    font-size: .8rem;
    font-weight: 700;
  }
  .print-stage {
    background: linear-gradient(180deg, #e2e8f0 0%, #f8fafc 100%);
    border-radius: 24px;
    padding: 28px;
    overflow: scroll;
    min-height: 620px;
    max-height: calc(100vh - 220px);
    scrollbar-gutter: stable both-edges;
  }
  .zoom-canvas {
    display: inline-block;
    min-width: max-content;
    min-height: max-content;
    transform-origin: top left;
    transition: transform .2s ease;
  }
  .print-layout {
    display: flex;
    flex-wrap: nowrap;
    justify-content: flex-start;
    gap: 16mm;
    min-width: max-content;
    min-height: max-content;
    padding: 8mm;
  }
  .card-id {
    width: 54mm;
    height: 85.6mm;
    position: relative;
    overflow: hidden;
    color: white;
    border-radius: 3mm;
    background-color: #0f172a;
    background-size: cover;
    background-position: center;
    box-shadow: 0 18px 40px rgba(15, 23, 42, 0.18);
  }
  .card-id::before {
    content: "";
    position: absolute;
    inset: 0;
    background:
      linear-gradient(180deg, rgba(8, 40, 68, 0.34), rgba(79, 143, 63, 0.72));
    z-index: 0;
  }
  .card-id::after {
    content: "";
    position: absolute;
    width: 24mm;
    height: 24mm;
    right: -7mm;
    bottom: -7mm;
    border-radius: 50%;
    background: rgba(255,255,255,0.08);
    z-index: 0;
  }
  .card-back::before {
    background:
      linear-gradient(180deg, rgba(8, 40, 68, 0.68), rgba(79, 143, 63, 0.82));
  }
  .card-layer {
    position: relative;
    z-index: 1;
    width: 100%;
    height: 100%;
  }
  .card-header-brand {
    position: absolute;
    top: 2.2mm;
    width: 100%;
    text-align: center;
    font-size: 4pt;
    line-height: 1.1;
    padding: 0 2mm;
  }
  .brand-logos {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 1.2mm;
    margin-bottom: 1.2mm;
  }
  .brand-logos img {
    display: block;
    object-fit: contain;
  }
  .brand-logos .logo-manda,
  .brand-logos .logo-bagong {
    height: 11mm;
  }
  .brand-logos .logo-national {
    height: 8.5mm;
  }
  .brand-title {
    font-size: 5pt;
    font-weight: 800;
    margin: 0;
  }
  .brand-subtext {
    margin: 0;
    font-size: 4pt;
  }
  .photo {
    position: absolute;
    top: 26mm;
    left: 50%;
    transform: translateX(-50%);
    width: 20mm;
    height: 20mm;
    border-radius: 2px;
    object-fit: cover;
    border: .5mm solid #fff;
    background: #dbeafe;
  }
  .id-no {
    position: absolute;
    top: 47mm;
    width: 100%;
    text-align: center;
    font-size: 5pt;
    letter-spacing: .04em;
  }
  .name {
    position: absolute;
    top: 51mm;
    width: 100%;
    text-align: center;
    font-size: 8.8pt;
    font-weight: 700;
    padding: 0 3mm;
  }
  .label {
    position: absolute;
    top: 56mm;
    width: 100%;
    text-align: center;
    font-size: 5.4pt;
  }
  .sig-line {
    position: absolute;
    top: 61.8mm;
    left: 12%;
    width: 76%;
    border-top: .3mm solid #fff;
  }
  .sig-text {
    position: absolute;
    top: 63mm;
    width: 100%;
    text-align: center;
    font-size: 5.2pt;
  }
  .dates {
    position: absolute;
    top: 69mm;
    width: 100%;
    text-align: center;
    font-size: 5.8pt;
    line-height: 1.2;
  }
  .notice {
    position: absolute;
    bottom: 2mm;
    width: 100%;
    text-align: center;
    font-size: 4.4pt;
    color: rgba(255,255,255,0.9);
    padding: 0 2mm;
  }
  .qr-wrap {
    position: absolute;
    top: 8.5mm;
    left: 50%;
    transform: translateX(-50%);
    width: 35mm;
    height: 35mm;
    border: .6mm solid #fff;
    background: white;
    padding: 1mm;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .emergency-title {
    position: absolute;
    top: 46mm;
    width: 100%;
    text-align: center;
    font-size: 5.8pt;
    font-weight: 700;
  }
  .box {
    position: absolute;
    top: 50mm;
    left: 8%;
    width: 84%;
    border: .3mm solid #fff;
    padding: 1.6mm;
    font-size: 5pt;
    line-height: 1.35;
  }
  .cert {
    position: absolute;
    top: 66mm;
    width: 100%;
    font-size: 4.4pt;
    text-align: center;
    padding: 0 3mm;
  }
  .warden {
    position: absolute;
    bottom: 4mm;
    width: 100%;
    text-align: center;
    font-size: 6pt;
    font-weight: 700;
  }
  .role {
    position: absolute;
    bottom: 1.7mm;
    width: 100%;
    text-align: center;
    font-size: 5pt;
  }
  @media (max-width: 991px) {
    .issuance-layout {
      grid-template-columns: 1fr;
    }
  }
  @media print {
    @page {
      size: A4 portrait;
      margin: 0;
    }
    html, body {
      background: #fff !important;
      margin: 0 !important;
      padding: 0 !important;
    }
    body * {
      visibility: hidden !important;
    }
    .print-stage, .print-stage * {
      visibility: visible !important;
    }
    .print-stage {
      position: fixed;
      inset: 0;
      padding: 0;
      background: #fff !important;
      border-radius: 0 !important;
      display: flex;
      justify-content: center;
      align-items: center;
      overflow: visible !important;
      min-height: auto !important;
      max-height: none !important;
    }
    .zoom-canvas {
      transform: scale(1) !important;
      transform-origin: center center !important;
      width: auto !important;
      height: auto !important;
      min-width: 0 !important;
      min-height: 0 !important;
      display: block !important;
    }
    .print-layout {
      flex-wrap: nowrap !important;
      justify-content: center !important;
      align-items: center !important;
      gap: 8mm !important;
      padding: 0 !important;
      min-width: 0 !important;
      min-height: 0 !important;
    }
    .card-id {
      box-shadow: none !important;
      flex: 0 0 54mm !important;
      width: 54mm !important;
      height: 85.6mm !important;
    }
  }
</style>

<div class="container-fluid py-3">
    <div class="issuance-layout">
        <div class="card control-panel">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <div>
                    <h5 class="fw-bold mb-1"><i class="bi bi-sliders me-2 text-success"></i>ID Controls</h5>
                    <small class="text-muted">Update signatory values, zoom the preview, and export the visitor card.</small>
                </div>
            </div>
            <div class="card-body p-4">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Warden Name</label>
                    <input type="text" id="wardenName" class="form-control" value="SAMPLE APPROVER">
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold">Warden Title</label>
                    <input type="text" id="wardenRole" class="form-control" value="DEMO APPROVER">
                </div>

                <div class="d-grid gap-2 mb-4">
                    <div class="print-instruction">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        Before printing, reset the preview zoom to prevent damaged spacing or distorted letters on the ID card.
                    </div>
                    <?php if ($canPrintIdCard): ?>
                        <button class="btn btn-secondary" type="button" onclick="printPVC()">
                            <i class="bi bi-printer me-1"></i> Print PVC
                        </button>
                    <?php endif; ?>
                    <?php if ($canExportIdCard): ?>
                        <!-- <button class="btn btn-primary" type="button" onclick="exportToPDF('pvc')">
                            <i class="bi bi-badge-4k me-1"></i> Export PVC PDF
                        </button> -->
                        <button class="btn btn-success" type="button" onclick="exportToPDF('a4')">
                            <i class="bi bi-file-earmark-pdf me-1"></i> Export A4 PDF
                        </button>
                    <?php endif; ?>
                    <a href="<?= BASE_URL ?>/id-issuance" class="btn btn-outline-dark">
                        <i class="bi bi-arrow-left me-1"></i> Back to Issuance
                    </a>
                </div>

                <div class="border rounded-4 p-3 bg-light">
                    <div class="fw-semibold mb-2">Zoom Controls</div>
                    <div class="d-flex gap-2 flex-wrap">
                        <button class="btn btn-dark btn-sm" type="button" onclick="zoomIn()">Zoom +</button>
                        <button class="btn btn-dark btn-sm" type="button" onclick="zoomOut()">Zoom -</button>
                        <button class="btn btn-warning btn-sm" type="button" onclick="zoomReset()">Reset</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card preview-panel">
            <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="fw-bold mb-1"><i class="bi bi-credit-card-2-front me-2 text-primary"></i>Preview & Print Stage</h5>
                    <small class="text-muted"><?= e($card['visitor_name']) ?> | <?= e($card['v_id']) ?></small>
                </div>
                <span class="badge bg-success">Verified Visitor</span>
            </div>
            <div class="card-body p-4">
                <div class="print-stage" id="printStage">
                    <div id="zoomCanvas" class="zoom-canvas">
                        <div class="print-layout">
                            <div id="frontCard" class="card-id" style="background-image: url('<?= e($cardBackgroundUrl) ?>');">
                                <div class="card-layer">
                                    <div class="card-header-brand">
                                        <div class="brand-logos">
                                            <img src="<?= e($bjmpMandaLogo) ?>" alt="Demo Services" class="logo-manda">
                                            <img src="<?= e($bjmpNationalLogo) ?>" alt="Demo ID Services" class="logo-national">
                                            <img src="<?= e($bagongPilipinasLogo) ?>" alt="Demo Services" class="logo-bagong">
                                        </div>
                                        <div class="brand-title">DEMO SERVICES</div>
                                        <p class="brand-subtext">DEPARTMENT OF THE INTERIOR AND LOCAL GOVERNMENT</p>
                                        <p class="brand-subtext"><b>ID & QUEUING SYSTEM</b></p>
                                        <p class="brand-subtext"><b>DEMO VISITOR CENTER</b></p>
                                        <p class="brand-subtext">FICTIONAL DEMO LOCATION</p>
                                    </div>

                                    <img src="<?= e($card['photo_url']) ?>?v=<?= time() ?>" class="photo" alt="Visitor photo">

                                    <div class="id-no">ID NO: <?= e($card['v_id']) ?></div>
                                    <div class="name"><?= e($card['visitor_name_front']) ?></div>
                                    <div class="label">NAME OF VISITOR</div>
                                    <div class="sig-line"></div>
                                    <div class="sig-text">SIGNATURE</div>

                                    <div class="dates">
                                        DATE ISSUED: <b><?= e($card['date_issued']) ?></b><br>
                                        DATE EXPIRED: <b><?= e($card['date_expired']) ?></b>
                                    </div>

                                    <div class="notice">
                                        IMPORTANT NOTICE: Use this card upon entry to jail facility
                                    </div>
                                </div>
                            </div>

                            <div id="backCard" class="card-id card-back" style="background-image: url('<?= e($cardBackgroundUrl) ?>');">
                                <div class="card-layer">
                                    <div class="qr-wrap"><div id="qrCode"></div></div>
                                    <div class="emergency-title">IN CASE OF EMERGENCY, PLEASE NOTIFY</div>
                                    <div class="box">
                                        NAME: <b><?= e($card['emergency_name']) ?></b><br>
                                        CONTACT NO: <b><?= e($card['emergency_contact']) ?></b><br>
                                        ADDRESS: <b><?= e($card['emergency_address']) ?></b>
                                    </div>
                                    <div class="cert">
                                        This certifies the bearer is a registered detainee's visitor of the ID & Queuing System, Demo City.
                                    </div>
                                    <div class="warden" id="wardenNameDisplay">SAMPLE APPROVER</div>
                                    <div class="role" id="wardenRoleDisplay">DEMO APPROVER</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
const CARD_BOOTSTRAP = {
    qrPayload: <?= json_encode($card['qr_payload']) ?>
};

let zoomLevel = 1;
const zoomStep = 0.1;
const zoomMin = 0.5;
const zoomMax = 3;

function applyZoom() {
    const canvas = document.getElementById('zoomCanvas');
    if (canvas) {
        const layout = canvas.querySelector('.print-layout');
        if (layout) {
            const baseWidth = layout.scrollWidth;
            const baseHeight = layout.scrollHeight;
            canvas.style.width = `${baseWidth * zoomLevel}px`;
            canvas.style.height = `${baseHeight * zoomLevel}px`;
        }
        canvas.style.transform = `scale(${zoomLevel})`;
    }
}

function zoomIn() {
    zoomLevel = Math.min(zoomMax, zoomLevel + zoomStep);
    applyZoom();
}

function zoomOut() {
    zoomLevel = Math.max(zoomMin, zoomLevel - zoomStep);
    applyZoom();
}

function zoomReset() {
    zoomLevel = 1;
    applyZoom();
}

async function printPVC() {
    zoomReset();
    const front = document.getElementById('frontCard');
    const back = document.getElementById('backCard');

    if (!front || !back) {
        Swal.fire('Error', 'Card elements not found.', 'error');
        return;
    }

    const printWindow = window.open('', '_blank', 'width=980,height=720');
    if (!printWindow) {
        Swal.fire('Popup Blocked', 'Please allow popups so the PVC print window can open.', 'warning');
        return;
    }

    printWindow.document.open();
    printWindow.document.write('<!doctype html><html><head><title>Preparing PVC Print</title></head><body style="font-family:Arial;padding:24px;">Preparing PVC print preview...</body></html>');
    printWindow.document.close();

    try {
        const [frontCanvas, backCanvas] = await Promise.all([
            html2canvas(front, { scale: 4, useCORS: true, backgroundColor: null }),
            html2canvas(back, { scale: 4, useCORS: true, backgroundColor: null })
        ]);

        const frontImg = frontCanvas.toDataURL('image/png');
        const backImg = backCanvas.toDataURL('image/png');

        printWindow.document.open();
        printWindow.document.write(`
            <!doctype html>
            <html>
            <head>
                <title>Visitor PVC ID</title>
                <style>
                    * {
                        box-sizing: border-box;
                        -webkit-print-color-adjust: exact !important;
                        print-color-adjust: exact !important;
                    }
                    @page {
                        size: 54mm 85.6mm;
                        margin: 0;
                    }
                    html,
                    body {
                        width: 54mm;
                        min-height: 85.6mm;
                        margin: 0;
                        padding: 0;
                        background: #fff;
                    }
                    .pvc-page {
                        width: 54mm;
                        height: 85.6mm;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        page-break-after: always;
                        break-after: page;
                    }
                    .pvc-page:last-child {
                        page-break-after: auto;
                        break-after: auto;
                    }
                    .pvc-card {
                        width: 54mm;
                        height: 85.6mm;
                        display: block;
                    }
                    @media print {
                        html,
                        body {
                            width: 54mm;
                            min-height: 85.6mm;
                            overflow: hidden;
                        }
                        .pvc-page {
                            margin: 0;
                        }
                    }
                </style>
            </head>
            <body>
                <div class="pvc-page">
                    <img class="pvc-card" src="${frontImg}" alt="Front PVC ID">
                </div>
                <div class="pvc-page">
                    <img class="pvc-card" src="${backImg}" alt="Back PVC ID">
                </div>
                <script>
                    window.addEventListener('load', function () {
                        setTimeout(function () {
                            window.focus();
                            window.print();
                        }, 250);
                    });
                <\/script>
            </body>
            </html>
        `);
        printWindow.document.close();
    } catch (error) {
        printWindow.close();
        Swal.fire('Error', error.message || 'Failed to prepare PVC print.', 'error');
    }
}

async function exportToPDF(mode = 'pvc') {
    zoomReset();
    const { jsPDF } = window.jspdf;
    const front = document.getElementById('frontCard');
    const back = document.getElementById('backCard');

    if (!front || !back) {
        Swal.fire('Error', 'Card elements not found.', 'error');
        return;
    }

    Swal.fire({
        title: 'Generating PDF...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    try {
        const [frontCanvas, backCanvas] = await Promise.all([
            html2canvas(front, { scale: 4, useCORS: true }),
            html2canvas(back, { scale: 4, useCORS: true })
        ]);

        const frontImg = frontCanvas.toDataURL('image/png');
        const backImg = backCanvas.toDataURL('image/png');

        let pdf;
        if (mode === 'a4') {
            pdf = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });
            pdf.addImage(frontImg, 'PNG', 20, 24, 54, 85.6);
            pdf.addImage(backImg, 'PNG', 90, 24, 54, 85.6);
        } else {
            pdf = new jsPDF({ orientation: 'landscape', unit: 'mm', format: [54, 110] });
            pdf.addImage(frontImg, 'PNG', 1, 1.5, 54, 85.6);
            pdf.addImage(backImg, 'PNG', 56, 1.5, 54, 85.6);
        }

        pdf.save(mode === 'a4' ? 'visitor-id-a4.pdf' : 'visitor-id-pvc.pdf');
        Swal.close();
    } catch (error) {
        Swal.fire('Error', error.message || 'Failed to generate PDF.', 'error');
    }
}

document.addEventListener('wheel', function (e) {
    if (!e.ctrlKey) return;
    e.preventDefault();
    if (e.deltaY < 0) {
        zoomIn();
    } else {
        zoomOut();
    }
}, { passive: false });

document.addEventListener('DOMContentLoaded', function () {
    const qrTarget = document.getElementById('qrCode');
    if (qrTarget) {
        new QRCode(qrTarget, {
            text: CARD_BOOTSTRAP.qrPayload,
            width: 120,
            height: 120,
            correctLevel: QRCode.CorrectLevel.H
        });
    }

    const nameInput = document.getElementById('wardenName');
    const roleInput = document.getElementById('wardenRole');
    const nameDisplay = document.getElementById('wardenNameDisplay');
    const roleDisplay = document.getElementById('wardenRoleDisplay');

    if (localStorage.getItem('portfolio.demo.signatoryName')) {
        nameInput.value = localStorage.getItem('portfolio.demo.signatoryName');
        nameDisplay.textContent = localStorage.getItem('portfolio.demo.signatoryName');
    }
    if (localStorage.getItem('portfolio.demo.signatoryRole')) {
        roleInput.value = localStorage.getItem('portfolio.demo.signatoryRole');
        roleDisplay.textContent = localStorage.getItem('portfolio.demo.signatoryRole');
    }

    nameInput.addEventListener('input', function () {
        const value = this.value.toUpperCase();
        nameDisplay.textContent = value;
        localStorage.setItem('portfolio.demo.signatoryName', value);
    });

    roleInput.addEventListener('input', function () {
        const value = this.value.toUpperCase();
        roleDisplay.textContent = value;
        localStorage.setItem('portfolio.demo.signatoryRole', value);
    });

    applyZoom();
});
</script>

<?php require_once ROOT_PATH . '/modules/layout/footer.php'; ?>
