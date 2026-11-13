<?php
$page_title = 'Queue Management';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/modules/layout/header.php';

$preselectedVisitorId = (int) ($_GET['visitor_id'] ?? 0);
$canQueueCreate = can('queue.create');
$canQueueEdit = can('queue.edit');
?>

<style>
  .queue-shell { display: grid; gap: 1.25rem;
    /* background:
      linear-gradient(180deg, rgba(255, 255, 255, 0.86) 0%, rgba(248, 250, 252, 0.96) 100%); */
    border-radius: 28px;
    padding: 24px;}
  .queue-hero {
    position: relative;
    overflow: hidden;
    border: 0;
    border-radius: 28px;
    background:
      linear-gradient(135deg, #062150, #447A61);
    box-shadow: 0 24px 60px rgba(15, 23, 42, 0.16);
  }
  .queue-hero::after {
    content: "";
    position: absolute;
    right: -90px;
    bottom: -90px;
    width: 240px;
    height: 240px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.08);
  }
  .queue-hero .card-body { position: relative; z-index: 1; padding: 1.6rem; }
  .queue-stat-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1rem; }
  .queue-stat-card, .queue-panel, .queue-table-card { border: 0; border-radius: 24px; box-shadow: 0 16px 38px rgba(15, 23, 42, 0.08); }
  .queue-stat-icon {
    width: 48px; height: 48px; border-radius: 16px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem;
  }
  #qr-reader video { border-radius: 12px; }
  .queue-counter {
    font-size: 11px;
    padding: 8px 14px;
    border-radius: 999px;
    background: #e9f7ef;
    color: #198754;
    font-weight: 700;
  }
  .scan-overlay {
    position: absolute;
    inset: 0;
    background: rgba(15, 23, 42, 0.55);
    display: none;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-weight: 700;
    border-radius: 12px;
    z-index: 10;
  }
  .visitor-photo {
    width: 160px;
    height: 160px;
    object-fit: cover;
    border-radius: 14px;
    border: 3px solid #dee2e6;
    background: #f8f9fa;
  }
  .queue-status-pill {
    border-radius: 999px;
    padding: 6px 12px;
    font-size: 12px;
    font-weight: 700;
  }
  .queue-field-chip {
    padding: .45rem .8rem;
    border-radius: 999px;
    background: #f1f5f9;
    color: #334155;
    font-size: .82rem;
  }
  .queue-table-card .card-header {
    background: linear-gradient(135deg, #062150, #80A497);
    border: 0;
    color: #fff;
  }
  @media (max-width: 1199px) {
    .queue-stat-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  }
  @media (max-width: 767px) {
    .queue-stat-grid { grid-template-columns: 1fr; }
  }
</style>

<div class="container-fluid py-3">
    <div class="queue-shell">
        <div class="card queue-hero text-white">
            <div class="card-body">
                <div class="row g-4 align-items-center">
                    <div class="col-xl-7">
                        <div class="text-uppercase small fw-semibold opacity-75 mb-2">Admission Operations</div>
                        <h2 class="fw-bold mb-2">Visitors admission, active queueing, and time in & out control.</h2>
                        <p class="mb-0 opacity-75">Use QR, manual lookup, and linked visitor records to move verified visitors smoothly into admissions while the public board and admin monitor stay in sync.</p>
                    </div>
                    <div class="col-xl-5">
                        <div class="d-flex align-items-center gap-2 flex-wrap justify-content-xl-end">
                            <span id="queueCounter" class="queue-counter">Queue: 0</span>
                            <?php if ($canQueueEdit): ?>
                                <a href="<?= BASE_URL ?>/queue-monitor" target="_blank" class="btn btn-outline-light btn-sm">
                                    <i class="bi bi-activity me-1"></i> Admin Monitor
                                </a>
                            <?php endif; ?>
                            <?php if (can('queue.view')): ?>
                                <a href="<?= BASE_URL ?>/queue-board" target="_blank" class="btn btn-light btn-sm">
                                    <i class="bi bi-display me-1"></i> Visitor Board
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="queue-stat-grid">
            <div class="card queue-stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div><div class="text-muted small">Waiting</div><div class="display-6 fw-bold mb-0" id="statWaiting">0</div></div>
                        <div class="queue-stat-icon bg-primary-subtle text-primary"><i class="bi bi-hourglass-split"></i></div>
                    </div>
                </div>
            </div>
            <div class="card queue-stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small">Inside</div>
                            <div class="display-6 fw-bold mb-0" id="statInside">0 / 10</div>
                            <div class="small text-muted mt-1" id="statInsideSlots">10 slot(s) available</div>
                        </div>
                        <div class="queue-stat-icon bg-success-subtle text-success"><i class="bi bi-door-open"></i></div>
                    </div>
                </div>
            </div>
            <div class="card queue-stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div><div class="text-muted small">Completed Today</div><div class="display-6 fw-bold mb-0" id="statCompleted">0</div></div>
                        <div class="queue-stat-icon bg-secondary-subtle text-secondary"><i class="bi bi-check2-circle"></i></div>
                    </div>
                </div>
            </div>
            <div class="card queue-stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div><div class="text-muted small">Average Wait</div><div class="display-6 fw-bold mb-0" id="statAvgWait">0m</div></div>
                        <div class="queue-stat-icon bg-warning-subtle text-warning"><i class="bi bi-stopwatch"></i></div>
                    </div>
                </div>
            </div>
        </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-5">
            <div class="card queue-panel h-100">
                <div class="card-header bg-white">
                    <i class="bi bi-upc-scan me-2"></i>Scan Visitor QR
                </div>
                <div class="card-body position-relative">
                    <div id="qr-reader" class="border rounded-4 overflow-hidden bg-light"></div>
                    <div class="scan-overlay" id="scanOverlay">Processing...</div>

                    <div class="row g-2 mt-3">
                        <div class="col-md-8">
                            <input type="text" class="form-control" id="manualQrInput" placeholder="Enter V-ID or QR token">
                        </div>
                        <div class="col-md-4">
                            <button type="button" class="btn btn-outline-primary w-100" id="btnManualSearch">
                                <i class="bi bi-search me-1"></i> Find
                            </button>
                        </div>
                    </div>

                    <?php if ($canQueueEdit): ?>
                        <button class="btn btn-danger w-100 mt-3" type="button" id="btnEnableTimeout">
                            <i class="bi bi-clock-history me-2"></i>Enable Time-Out Scan
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card queue-panel h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-person-lines-fill me-2"></i>Visitor Details</span>
                    <span id="visitorStatusBadge" class="badge bg-secondary queue-status-pill">No Visitor</span>
                </div>
                <div class="card-body">
                    <div class="d-flex gap-2 flex-wrap mb-3">
                        <span class="queue-field-chip"><i class="bi bi-card-heading me-1 text-primary"></i><span id="detailVidChip">No V-ID loaded</span></span>
                        <span class="queue-field-chip"><i class="bi bi-building me-1 text-success"></i><span id="detailDormChip">Dorm pending</span></span>
                        <span class="queue-field-chip"><i class="bi bi-person-vcard me-1 text-info"></i><span id="detailPdlChip">No Resident linked</span></span>
                    </div>
                    <form id="frmSearch" class="row g-3" onsubmit="return false;">
                        <div class="col-12 text-center mb-3">
                            <img id="visitor_photo_preview" class="visitor-photo" src="<?= e(BASE_URL . '/assets/images/demo-scene.svg') ?>" alt="Visitor photo">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">V-ID</label>
                            <input type="text" class="form-control" id="v_id" readonly>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">QR ID</label>
                            <input type="text" class="form-control" id="qr_id_number" readonly>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Firstname</label>
                            <input type="text" class="form-control" id="firstname" readonly>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Middlename</label>
                            <input type="text" class="form-control" id="middlename" readonly>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Lastname</label>
                            <input type="text" class="form-control" id="lastname" readonly>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Resident Name</label>
                            <input type="text" class="form-control" id="pdl_name" readonly>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Dorm</label>
                            <input type="text" class="form-control" id="dorm_no" readonly>
                        </div>

                        <div class="col-12 d-flex gap-2 mt-3">
                            <?php if ($canQueueCreate): ?>
                                <button type="button" class="btn btn-success" id="btnQueueVisitor">
                                    <i class="bi bi-person-plus"></i> Put in Queue
                                </button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-secondary" id="btnClear">
                                <i class="bi bi-x-circle"></i> Clear
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="card queue-table-card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="text-black"><i class="bi bi-list-check text-black me-2"></i>Queue Requests</span>
            <button type="button" class="btn btn-outline-success btn-sm" id="btnRefresh">
                <i class="bi bi-arrow-clockwise me-1"></i> Refresh
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="tblRequests" class="table table-hover align-middle w-100">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Visitor</th>
                            <th>Resident</th>
                            <th>Dorm</th>
                            <th>ID Type</th>
                            <th>ID Number</th>
                            <th>Status</th>
                            <th>Time In</th>
                            <th>Time Out</th>
                            <th>Hours Stayed</th>
                            <th>Requested At</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</div>

<div class="modal fade" id="queueModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form id="frmQueue" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-person-plus-fill me-2"></i>
                    Confirm Queue Entry
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="modal_visitor_id">

                <div class="mb-3">
                    <label class="form-label">ID Type <span class="text-muted fw-normal">(optional)</span></label>
                    <select class="form-select" id="modal_id_type">
                        <option value="">Skip / No ID provided</option>
                        <option value="Driver's License">Driver's License</option>
                        <option value="National ID">National ID</option>
                        <option value="Passport">Passport</option>
                        <option value="Student ID">Student ID</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">ID Number <span class="text-muted fw-normal">(optional)</span></label>
                    <input type="text" class="form-control" id="modal_id_number" placeholder="Leave blank if skipped">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success">Confirm</button>
            </div>
        </form>
    </div>
</div>

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
const QUEUE_BOOTSTRAP = {
    actionUrl: <?= json_encode(BASE_URL . '/queue-action') ?>,
    defaultPhoto: <?= json_encode(BASE_URL . '/assets/images/demo-scene.svg') ?>,
    initialVisitorId: <?= json_encode($preselectedVisitorId) ?>,
    canQueueCreate: <?= $canQueueCreate ? 'true' : 'false' ?>,
    canQueueEdit: <?= $canQueueEdit ? 'true' : 'false' ?>,
};
</script>

<script>
let tbl = null;
let scannerInstance = null;
let isProcessingScan = false;
let autoRefreshTimer = null;
let currentVisitor = null;
window.forceTimeoutMode = false;

function beep() {
    const ctx = new (window.AudioContext || window.webkitAudioContext)();
    const osc = ctx.createOscillator();
    osc.connect(ctx.destination);
    osc.start();
    setTimeout(() => osc.stop(), 120);
}

function showOverlay(show) {
    const overlay = document.getElementById('scanOverlay');
    if (overlay) {
        overlay.style.display = show ? 'flex' : 'none';
    }
}

function resetScanState() {
    showOverlay(false);
    isProcessingScan = false;
}

function statusBadgeClass(visitor) {
    if (!visitor) return 'bg-secondary';
    if (parseInt(visitor.is_banned || 0, 10) === 1) return 'bg-danger';
    if ((visitor.visitor_status || '').toLowerCase() === 'verified') return 'bg-success';
    return 'bg-secondary';
}

function fillVisitorDetails(v) {
    currentVisitor = v;
    $('#v_id').val(v.v_id || '');
    $('#qr_id_number').val(v.qr_value || v.v_id || '');
    $('#firstname').val(v.firstname || '');
    $('#middlename').val(v.middlename || '');
    $('#lastname').val(v.lastname || '');
    $('#pdl_name').val(v.pdl_name || '');
    $('#dorm_no').val(v.dorm_no || '');
    $('#modal_visitor_id').val(v.id || '');
    $('#visitor_photo_preview').attr('src', v.visitor_photo || QUEUE_BOOTSTRAP.defaultPhoto);
    $('#detailVidChip').text(v.v_id || 'No V-ID loaded');
    $('#detailDormChip').text(v.dorm_no || 'Dorm pending');
    $('#detailPdlChip').text(v.pdl_name || 'No Resident linked');
    $('#visitorStatusBadge')
        .removeClass('bg-secondary bg-success bg-danger')
        .addClass(statusBadgeClass(v))
        .text(v.visitor_status || 'Verified Visitor');
}

function clearVisitorDetails() {
    currentVisitor = null;
    $('#frmSearch').find('input').val('');
    $('#modal_visitor_id').val('');
    $('#visitor_photo_preview').attr('src', QUEUE_BOOTSTRAP.defaultPhoto);
    $('#detailVidChip').text('No V-ID loaded');
    $('#detailDormChip').text('Dorm pending');
    $('#detailPdlChip').text('No Resident linked');
    $('#visitorStatusBadge').removeClass('bg-success bg-danger').addClass('bg-secondary').text('No Visitor');
}

function initScanner() {
    if (scannerInstance !== null) return;

    const container = document.getElementById('qr-reader');
    if (!window.Html5QrcodeScanner) {
        if (container) {
            container.innerHTML = '<div class="p-4 text-center text-muted">QR scanner library failed to load. You can still search by V-ID above.</div>';
        }
        return;
    }

    if (container) container.innerHTML = '';

    scannerInstance = new Html5QrcodeScanner(
        'qr-reader',
        { fps: 10, qrbox: { width: 250, height: 250 } },
        false
    );

    scannerInstance.render(onScanSuccess, () => {});
}

function processValidatedVisitor(res, successTitle = 'Visitor Verified') {
    if (!res.ok) {
        Swal.fire('Invalid QR', res.msg || res.message || 'Unable to validate visitor.', 'error');
        resetScanState();
        return;
    }

    const visitor = res.visitor || null;
    if (!visitor) {
        Swal.fire('Error', 'Visitor data was not returned.', 'error');
        resetScanState();
        return;
    }

    if (window.forceTimeoutMode) {
        processTimeout(visitor.id);
        window.forceTimeoutMode = false;
        resetScanState();
        return;
    }

    fillVisitorDetails(visitor);
    Swal.fire({
        icon: 'success',
        title: successTitle,
        timer: 900,
        showConfirmButton: false
    });

    resetScanState();
}

function onScanSuccess(decodedText) {
    if (isProcessingScan) return;

    isProcessingScan = true;
    showOverlay(true);
    beep();

    $.post(QUEUE_BOOTSTRAP.actionUrl, {
        action: 'validateQR',
        qr_data: decodedText
    }, function (res) {
        processValidatedVisitor(res);
    }, 'json').fail(function () {
        Swal.fire('Error', 'Failed to validate QR.', 'error');
        resetScanState();
    });
}

function loadVisitorById(visitorId) {
    if (!visitorId) return;

    $.getJSON(QUEUE_BOOTSTRAP.actionUrl, {
        action: 'get_visitor',
        visitor_id: visitorId
    }).done(function (res) {
        if (!res.ok) {
            Swal.fire('Error', res.msg || 'Unable to load selected visitor.', 'error');
            return;
        }
        fillVisitorDetails(res.visitor || null);
    }).fail(function () {
        Swal.fire('Error', 'Unable to load selected visitor.', 'error');
    });
}

function openQueueModal() {
    if (!currentVisitor) {
        Swal.fire('No Visitor', 'Scan a QR or load a verified visitor first.', 'warning');
        return;
    }

    const modal = new bootstrap.Modal(document.getElementById('queueModal'));
    modal.show();
}

function enableTimeoutScan() {
    window.forceTimeoutMode = true;

    Swal.fire({
        icon: 'info',
        title: 'Timeout Mode Enabled',
        text: 'Scan the visitor QR to record time-out.',
        timer: 1800,
        showConfirmButton: false
    });
}

function processTimeout(visitorId) {
    $.post(QUEUE_BOOTSTRAP.actionUrl, {
        action: 'timeOut',
        visitor_id: visitorId
    }, function (res) {
        Swal.fire(
            res.ok ? 'Timed Out' : 'Error',
            res.msg || res.message || '',
            res.ok ? 'success' : 'error'
        );

        if (res.ok) {
            clearVisitorDetails();
            loadRequests();
        }
    }, 'json').fail(function () {
        Swal.fire('Error', 'Failed to process time-out.', 'error');
    });
}

function processTimeoutRequest(requestId) {
    $.post(QUEUE_BOOTSTRAP.actionUrl, {
        action: 'timeOutRequest',
        request_id: requestId
    }, function (res) {
        Swal.fire(
            res.ok ? 'Timed Out' : 'Error',
            res.msg || res.message || '',
            res.ok ? 'success' : 'error'
        );

        if (res.ok) {
            loadRequests();
        }
    }, 'json').fail(function () {
        Swal.fire('Error', 'Failed to process time-out.', 'error');
    });
}

function escapeHtml(value) {
    return $('<div>').text(value == null ? '' : value).html();
}

function formatDateTime(value) {
    if (!value) return '';
    const date = new Date(value.replace(' ', 'T'));
    return isNaN(date.getTime()) ? value : date.toLocaleString();
}

function loadQueueSummary() {
    $.getJSON(QUEUE_BOOTSTRAP.actionUrl, { action: 'board_data' }).done(function (res) {
        if (!res.ok) return;
        const stats = res.stats || {};
        $('#statWaiting').text(stats.waiting_count || 0);
        const insideCount = parseInt(stats.inside_count || 0, 10);
        const insideCapacity = parseInt(stats.inside_capacity || 10, 10);
        const insideAvailable = parseInt(stats.inside_available_slots || 0, 10);
        $('#statInside').text(insideCount + ' / ' + insideCapacity);
        $('#statInsideSlots').text(insideAvailable + ' slot(s) available');
        $('#statCompleted').text(stats.completed_count || 0);
        $('#statAvgWait').text(stats.avg_wait_minutes != null ? stats.avg_wait_minutes + 'm' : '0m');
    });
}

function loadRequests() {
    $.post(QUEUE_BOOTSTRAP.actionUrl, { action: 'listRequests' }, function (resp) {
        if (!resp.ok) return;

        $('#queueCounter').text('Queue: ' + (resp.active_count || 0));
        loadQueueSummary();

        if (!tbl) return;

        tbl.clear();

        (resp.rows || []).forEach(function (r, index) {
            const name = [r.firstname, r.middlename, r.lastname, r.suffix].filter(Boolean).join(' ');
            const visitorName = r.is_special_case
                ? `${escapeHtml(name)} <span class="badge bg-warning text-dark ms-1">Special Case</span>`
                : escapeHtml(name);
            let actions = '';

            if (r.status === 'pending') {
                actions = QUEUE_BOOTSTRAP.canQueueEdit ? `
                    <div class="d-flex gap-1 justify-content-center flex-wrap">
                        <button class="btn btn-success" onclick="acceptReq(${r.id})">
                            <i class="bi bi-check-circle me-1"></i> Approve
                        </button>
                        <button class="btn btn-danger" onclick="rejectReq(${r.id})">
                            <i class="bi bi-x-circle me-1"></i> Reject
                        </button>
                    </div>
                ` : '<span class="badge bg-warning text-dark">Pending</span>';
            } else if (r.status === 'inside') {
                actions = QUEUE_BOOTSTRAP.canQueueEdit ? `
                    <button class="btn btn-warning btn-sm" onclick="${r.is_special_case ? `processTimeoutRequest(${r.id})` : `processTimeout(${r.visitor_id})`}">
                        <i class="bi bi-box-arrow-right"></i> Time Out
                    </button>
                ` : '<span class="badge bg-success">Inside</span>';
            } else if (r.status === 'done') {
                actions = `
                    <span class="badge bg-secondary">
                        <i class="bi bi-check-circle"></i> Done
                    </span>
                `;
            } else if (r.status === 'rejected') {
                actions = '<span class="badge bg-danger">Rejected</span>';
            } else {
                actions = '<span class="badge bg-secondary">' + escapeHtml(r.status || '') + '</span>';
            }

            tbl.row.add([
                index + 1,
                visitorName,
                escapeHtml(r.pdl_name || ''),
                escapeHtml(r.dorm_no || ''),
                escapeHtml(r.id_type || ''),
                escapeHtml(r.id_number || ''),
                escapeHtml(r.status || ''),
                escapeHtml(r.time_in || ''),
                escapeHtml(r.time_out || ''),
                escapeHtml(r.hours_stayed_label || r.hours_stayed || ''),
                escapeHtml(formatDateTime(r.request_time)),
                actions
            ]);
        });

        tbl.draw(false);
    }, 'json');
}

function acceptReq(id) {
    $.post(QUEUE_BOOTSTRAP.actionUrl, {
        action: 'acceptQueue',
        id: id
    }, function (res) {
        Swal.fire(
            res.ok ? 'Allowed' : 'Error',
            res.msg || res.message || '',
            res.ok ? 'success' : 'error'
        );

        if (res.ok) {
            loadRequests();
        }
    }, 'json').fail(function () {
        Swal.fire('Error', 'Failed to accept queue request.', 'error');
    });
}

function rejectReq(id) {
    $.post(QUEUE_BOOTSTRAP.actionUrl, {
        action: 'rejectQueue',
        id: id
    }, function (res) {
        Swal.fire(
            res.ok ? 'Rejected' : 'Error',
            res.msg || res.message || '',
            res.ok ? 'info' : 'error'
        );

        if (res.ok) {
            loadRequests();
        }
    }, 'json').fail(function () {
        Swal.fire('Error', 'Failed to reject queue request.', 'error');
    });
}

$(function () {
    initScanner();
    clearVisitorDetails();

    tbl = $('#tblRequests').DataTable({
        order: [],
        pageLength: 10,
        processing: true,
        deferRender: true,
        columnDefs: [
            { targets: [0, 11], orderable: false, className: 'text-center' }
        ]
    });

    $('#btnRefresh').on('click', loadRequests);
    $('#btnClear').on('click', clearVisitorDetails);
    $('#btnQueueVisitor').on('click', openQueueModal);
    $('#btnEnableTimeout').on('click', enableTimeoutScan);

    $('#btnManualSearch').on('click', function () {
        const value = $('#manualQrInput').val().trim();
        if (!value) {
            Swal.fire('Warning', 'Enter a V-ID or QR token first.', 'warning');
            return;
        }

        showOverlay(true);
        $.post(QUEUE_BOOTSTRAP.actionUrl, {
            action: 'validateQR',
            qr_data: value
        }, function (res) {
            processValidatedVisitor(res, 'Visitor Loaded');
        }, 'json').fail(function () {
            Swal.fire('Error', 'Failed to load visitor.', 'error');
            resetScanState();
        });
    });

    $('#manualQrInput').on('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            $('#btnManualSearch').trigger('click');
        }
    });

    $('#frmQueue').on('submit', function (e) {
        e.preventDefault();

        $.post(QUEUE_BOOTSTRAP.actionUrl, {
            action: 'addToQueue',
            visitor_id: $('#modal_visitor_id').val(),
            id_type: $('#modal_id_type').val(),
            id_number: $('#modal_id_number').val()
        }, function (res) {
            Swal.fire(
                res.ok ? 'Queued' : 'Error',
                res.msg || res.message || '',
                res.ok ? 'success' : 'error'
            );

            if (res.ok) {
                bootstrap.Modal.getInstance(document.getElementById('queueModal')).hide();
                $('#frmQueue')[0].reset();
                loadRequests();
            }
        }, 'json').fail(function () {
            Swal.fire('Error', 'Failed to add visitor to queue.', 'error');
        });
    });

    autoRefreshTimer = setInterval(loadRequests, 15000);
    loadRequests();
    loadQueueSummary();

    if (parseInt(QUEUE_BOOTSTRAP.initialVisitorId || 0, 10) > 0) {
        loadVisitorById(parseInt(QUEUE_BOOTSTRAP.initialVisitorId, 10));
    }
});
</script>

<?php require_once ROOT_PATH . '/modules/layout/footer.php'; ?>
