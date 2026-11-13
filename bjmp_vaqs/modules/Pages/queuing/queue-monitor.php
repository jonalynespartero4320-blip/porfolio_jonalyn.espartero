<?php
$page_title = 'Queue Monitor';
require_once ROOT_PATH . '/modules/layout/header.php';
?>

<style>
  .monitor-surface {
    background:
      linear-gradient(135deg, #f8fbff 0%, #f6fff8 100%);
    border-radius: 28px;
    padding: 24px;
  }
  .monitor-card {
    border: 0;
    border-radius: 22px;
    box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08);
  }
  .metric-value {
    font-size: clamp(1.8rem, 2.4vw, 2.7rem);
    font-weight: 800;
    line-height: 1;
  }
  .mini-label {
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #64748b;
    font-size: 0.78rem;
    font-weight: 800;
  }
  .monitor-table td,
  .monitor-table th {
    vertical-align: middle;
  }
  .monitor-feed-status {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
  }
  .monitor-summary {
    border-radius: 22px;
    background: linear-gradient(135deg, #eff6ff, #f0fdf4);
    border: 1px solid #dbeafe;
    padding: 1rem 1.1rem;
    margin-bottom: 1rem;
  }
  .inside-list {
    display: grid;
    gap: .75rem;
    max-height: 390px;
    overflow: auto;
    padding-right: .25rem;
  }
  .inside-item {
    border: 1px solid rgba(15, 23, 42, 0.08);
    border-radius: 18px;
    background: #fff;
    padding: .9rem;
  }
  .inside-item-name {
    font-size: 1rem;
    font-weight: 800;
    color: #111827;
  }
  .inside-item-meta {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: .55rem;
    margin-top: .75rem;
  }
  .inside-meta-box {
    border-radius: 14px;
    background: #f8fafc;
    padding: .65rem;
  }
  @media (max-width: 575px) {
    .inside-item-meta { grid-template-columns: 1fr; }
  }
</style>

<div class="container-fluid py-3">
    <div class="monitor-surface">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="bi bi-activity me-2 text-primary"></i>
                    Live Admission Monitor
                </h4>
                <small class="text-muted">Real-time admin view of waiting, inside, completed, and rejected admissions.</small>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="badge bg-dark fs-6" id="monitorClock">--</span>
                <a href="<?= BASE_URL ?>/queue" class="btn btn-outline-success btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Back to Queue
                </a>
                <a href="<?= BASE_URL ?>/queue-board" target="_blank" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-display me-1"></i> Visitor Board
                </a>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card monitor-card h-100">
                    <div class="card-body">
                        <div class="mini-label mb-2">Waiting</div>
                        <div class="metric-value text-primary" id="metricWaiting">0</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card monitor-card h-100">
                    <div class="card-body">
                        <div class="mini-label mb-2">Inside</div>
                        <div class="metric-value text-success" id="metricInside">0</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card monitor-card h-100">
                    <div class="card-body">
                        <div class="mini-label mb-2">Completed Today</div>
                        <div class="metric-value text-secondary" id="metricCompleted">0</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card monitor-card h-100">
                    <div class="card-body">
                        <div class="mini-label mb-2">Average Wait Today</div>
                        <div class="metric-value text-danger" id="metricRejected">0m</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-xl-6">
                <div class="card monitor-card h-100">
                    <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h6 class="fw-bold mb-0">Visitors Currently Inside</h6>
                            <small class="text-muted">Live list of admitted visitors occupying the 10-person capacity.</small>
                        </div>
                        <span class="badge bg-success" id="monitorInsideBadge">0 / 10 inside</span>
                    </div>
                    <div class="card-body px-4 pb-4">
                        <div class="monitor-summary">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div>
                                    <div class="mini-label mb-1">Admission Capacity</div>
                                    <div class="fw-semibold" id="monitorInsideSummary">No visitor inside.</div>
                                </div>
                                <div class="text-end">
                                    <div class="mini-label mb-1">Last Refresh</div>
                                    <div class="fw-semibold" id="monitorLastRefresh">-</div>
                                </div>
                            </div>
                        </div>
                        <div class="inside-list" id="monitorInsideList">
                            <div class="text-center text-muted py-4">Loading inside visitors...</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="card monitor-card h-100">
                    <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0">Waiting Queue</h6>
                        <span class="badge bg-primary" id="monitorWaitingBadge">0 waiting</span>
                    </div>
                    <div class="card-body px-4 pb-4">
                        <div class="monitor-summary">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div>
                                    <div class="mini-label mb-1">Operations Snapshot</div>
                                    <div class="fw-semibold" id="monitorSummaryLine">Queue is stable and awaiting refresh.</div>
                                </div>
                                <span class="badge bg-primary" id="monitorPendingBadge">0 pending approval</span>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table monitor-table mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Visitor</th>
                                        <th>Queued At</th>
                                    </tr>
                                </thead>
                                <tbody id="monitorWaitingBody">
                                    <tr><td colspan="3" class="text-center text-muted py-4">Loading waiting queue...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mt-1">
            <div class="col-xl-6">
                <div class="card monitor-card h-100">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <h6 class="fw-bold mb-0">Recent Admissions</h6>
                    </div>
                    <div class="card-body px-4 pb-4">
                        <div class="table-responsive">
                            <table class="table monitor-table mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Visitor</th>
                                        <th>Time In</th>
                                        <th>Time Out</th>
                                        <th>Hours</th>
                                    </tr>
                                </thead>
                                <tbody id="monitorDoneBody">
                                    <tr><td colspan="4" class="text-center text-muted py-4">Loading completed admissions...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="card monitor-card h-100">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <h6 class="fw-bold mb-0">Live Activity Feed</h6>
                    </div>
                    <div class="card-body px-4 pb-4">
                        <div class="table-responsive">
                            <table class="table monitor-table mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Visitor</th>
                                        <th>Status</th>
                                        <th>Time</th>
                                        <th>ID</th>
                                    </tr>
                                </thead>
                                <tbody id="monitorFeedBody">
                                    <tr><td colspan="4" class="text-center text-muted py-4">Loading activity feed...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const QUEUE_MONITOR_BOOTSTRAP = {
    actionUrl: <?= json_encode(BASE_URL . '/queue-action') ?>
};
</script>

<script>
function monitorEscape(value) {
    return $('<div>').text(value == null ? '' : value).html();
}

function monitorClock() {
    const now = new Date();
    $('#monitorClock').text(now.toLocaleString());
}

function monitorDateTime(value) {
    if (!value) return '-';
    const parsed = new Date(String(value).replace(' ', 'T'));
    return isNaN(parsed.getTime()) ? value : parsed.toLocaleString();
}

function monitorMinutesSince(value) {
    if (!value) return '-';
    const parsed = new Date(String(value).replace(' ', 'T'));
    if (isNaN(parsed.getTime())) return '-';
    const mins = Math.max(0, Math.floor((Date.now() - parsed.getTime()) / 60000));
    if (mins <= 0) return '0 mins';
    if (mins < 60) return mins + ' min' + (mins === 1 ? '' : 's');
    const hrs = Math.floor(mins / 60);
    const remainingMins = mins % 60;
    const parts = [hrs + ' hr' + (hrs === 1 ? '' : 's')];
    if (remainingMins > 0) {
        parts.push(remainingMins + ' min' + (remainingMins === 1 ? '' : 's'));
    }
    return parts.join(' ');
}

function monitorStatusBadge(status) {
    const normalized = String(status || '').toLowerCase();
    if (normalized === 'inside') return '<span class="badge bg-success">Inside</span>';
    if (normalized === 'pending') return '<span class="badge bg-primary">Pending</span>';
    if (normalized === 'accepted') return '<span class="badge bg-info text-dark">Accepted</span>';
    if (normalized === 'done') return '<span class="badge bg-secondary">Done</span>';
    if (normalized === 'rejected') return '<span class="badge bg-danger">Rejected</span>';
    return '<span class="badge bg-dark">' + monitorEscape(status || '-') + '</span>';
}

function loadMonitor() {
    $.when(
        $.getJSON(QUEUE_MONITOR_BOOTSTRAP.actionUrl, { action: 'board_data' }),
        $.post(QUEUE_MONITOR_BOOTSTRAP.actionUrl, { action: 'listRequests' }, null, 'json')
    ).done(function (boardResp, listResp) {
        const board = boardResp[0] || {};
        const list = listResp[0] || {};

        if (!board.ok || !list.ok) {
            return;
        }

        const waiting = Array.isArray(board.waiting) ? board.waiting : [];
        const insideList = Array.isArray(board.inside_list) ? board.inside_list : (board.inside ? [board.inside] : []);
        const inside = insideList[0] || null;
        const done = Array.isArray(board.done) ? board.done : [];
        const rows = Array.isArray(list.rows) ? list.rows : [];

        const insideCount = rows.filter((row) => row.status === 'inside').length;
        const rejectedCount = rows.filter((row) => row.status === 'rejected').length;
        const completedCount = rows.filter((row) => row.status === 'done').length;
        const stats = board.stats || {};

        $('#metricWaiting').text(waiting.length);
        $('#metricInside').text(insideCount);
        $('#metricCompleted').text(completedCount);
        $('#metricRejected').text(stats.avg_wait_minutes != null ? stats.avg_wait_minutes + 'm' : '0m');
        $('#monitorPendingBadge').text((stats.pending_count || 0) + ' pending approval');
        $('#monitorSummaryLine').text(
            insideList.length
                ? (insideList.length + ' visitor(s) currently inside while ' + waiting.length + ' visitor(s) remain in queue.')
                : (waiting.length
                    ? ('No visitor is currently inside. ' + waiting.length + ' visitor(s) are waiting.')
                    : 'Queue is clear at the moment.')
        );

        const capacity = (stats.inside_capacity || 10);
        const availableSlots = Math.max(0, capacity - insideCount);
        $('#monitorInsideBadge').text(insideCount + ' / ' + capacity + ' inside');
        $('#monitorInsideSummary').text(
            insideCount
                ? (insideCount + ' admitted visitor(s). ' + availableSlots + ' slot(s) available.')
                : 'No visitor inside. All admission slots are available.'
        );
        $('#monitorLastRefresh').text(new Date().toLocaleTimeString());
        $('#monitorWaitingBadge').text(waiting.length + ' waiting');

        if (!insideList.length) {
            $('#monitorInsideList').html('<div class="text-center text-muted py-4">No visitors are currently inside.</div>');
        } else {
            const insideHtml = insideList.map(function (row, index) {
                return `
                    <div class="inside-item">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div>
                                <div class="mini-label mb-1">Visitor #${index + 1}</div>
                                <div class="inside-item-name">${monitorEscape(row.display_name || '-')}</div>
                            </div>
                            <span class="badge bg-success">Inside</span>
                        </div>
                        <div class="inside-item-meta">
                            <div class="inside-meta-box">
                                <div class="mini-label mb-1">Time In</div>
                                <div class="fw-semibold">${monitorEscape(monitorDateTime(row.time_in || row.display_time))}</div>
                            </div>
                            <div class="inside-meta-box">
                                <div class="mini-label mb-1">Duration</div>
                                <div class="fw-semibold">${monitorEscape(monitorMinutesSince(row.time_in || row.display_time))}</div>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
            $('#monitorInsideList').html(insideHtml);
        }

        if (!waiting.length) {
            $('#monitorWaitingBody').html('<tr><td colspan="3" class="text-center text-muted py-4">No visitors waiting.</td></tr>');
        } else {
            const waitingHtml = waiting.map(function (row) {
                return `
                    <tr>
                        <td class="fw-bold">#${monitorEscape(row.position || '-')}</td>
                        <td>${monitorEscape(row.display_name || '-')}</td>
                        <td>${monitorEscape(monitorDateTime(row.display_time))}</td>
                    </tr>
                `;
            }).join('');
            $('#monitorWaitingBody').html(waitingHtml);
        }

        if (!done.length) {
            $('#monitorDoneBody').html('<tr><td colspan="4" class="text-center text-muted py-4">No completed admissions yet.</td></tr>');
        } else {
            const doneHtml = done.map(function (row) {
                return `
                    <tr>
                        <td>${monitorEscape(row.display_name || '-')}</td>
                        <td>${monitorEscape(monitorDateTime(row.time_in))}</td>
                        <td>${monitorEscape(monitorDateTime(row.time_out))}</td>
                        <td>${monitorEscape(row.hours_stayed_label || row.hours_stayed || '-')}</td>
                    </tr>
                `;
            }).join('');
            $('#monitorDoneBody').html(doneHtml);
        }

        if (!rows.length) {
            $('#monitorFeedBody').html('<tr><td colspan="4" class="text-center text-muted py-4">No queue activity found.</td></tr>');
        } else {
            const feedHtml = rows.slice(0, 12).map(function (row) {
                const visitor = [row.firstname, row.middlename, row.lastname, row.suffix].filter(Boolean).join(' ');
                return `
                    <tr>
                        <td>${monitorEscape(visitor || '-')}</td>
                        <td>${monitorStatusBadge(row.status)}</td>
                        <td>${monitorEscape(monitorDateTime(row.time_out || row.time_in || row.request_time))}</td>
                        <td>${monitorEscape(row.id_type || '-')}</td>
                    </tr>
                `;
            }).join('');
            $('#monitorFeedBody').html(feedHtml);
        }
    });
}

$(function () {
    monitorClock();
    loadMonitor();

    setInterval(monitorClock, 1000);
    setInterval(loadMonitor, 10000);
});
</script>

<?php require_once ROOT_PATH . '/modules/layout/footer.php'; ?>
