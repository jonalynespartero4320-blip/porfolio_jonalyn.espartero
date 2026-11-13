<?php
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/functions.php';

ensure_activity_logs_schema($pdo);

include ROOT_PATH . '/modules/layout/header.php';

$user_filter = trim((string) ($_GET['user'] ?? ''));
$action_filter = trim((string) ($_GET['action'] ?? ''));
$date_from = trim((string) ($_GET['date_from'] ?? ''));
$date_to = trim((string) ($_GET['date_to'] ?? ''));

$users = $pdo->query("SELECT DISTINCT username FROM activity_logs ORDER BY username ASC")->fetchAll(PDO::FETCH_COLUMN);
$summary = $pdo->query("
    SELECT
        COUNT(*) AS total_logs,
        COUNT(DISTINCT username) AS total_users,
        SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) AS today_logs
    FROM activity_logs
")->fetch(PDO::FETCH_ASSOC) ?: [];

$totalLogs = (int) ($summary['total_logs'] ?? 0);
$totalUsers = (int) ($summary['total_users'] ?? 0);
$todayLogs = (int) ($summary['today_logs'] ?? 0);
$canDeleteLogs = can('logs.delete');
?>

<style>
  .logs-shell { display: grid; gap: 1.05rem; }
  .logs-head { display: flex; justify-content: space-between; gap: 1rem; align-items: flex-start; }
  .logs-eyebrow { color: #667085; font-size: .78rem; font-weight: 750; }
  .logs-title { margin: 0; color: #1f2937; font-size: 1.35rem; font-weight: 850; }
  .logs-toolbar { display: grid; gap: .65rem; }
  .logs-filter-row {
    display: grid;
    grid-template-columns: minmax(280px, 1.45fr) minmax(170px, 1fr) minmax(170px, 1fr) minmax(150px, .75fr) minmax(150px, .75fr);
    gap: .65rem;
    align-items: center;
  }
  .logs-action-row { display: flex; justify-content: flex-end; align-items: center; gap: .65rem; flex-wrap: wrap; }
  .logs-search { position: relative; min-width: 0; }
  .logs-search i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #98a2b3; font-size: .85rem; }
  .logs-search input { height: 38px; padding-left: 34px; border-radius: 4px; border-color: #eaecf0; font-size: .78rem; }
  .logs-filter { height: 38px; width: 100%; border-radius: 4px; border-color: #eaecf0; font-size: .78rem; color: #667085; }
  .logs-soft-btn {
    height: 38px;
    border: 1px solid #eaecf0;
    background: #fff;
    color: #344054;
    border-radius: 4px;
    padding: 0 .8rem;
    font-size: .78rem;
    font-weight: 650;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .35rem;
    white-space: nowrap;
  }
  .logs-panel { border: 0; border-radius: 10px; background: rgba(255,255,255,.78); box-shadow: none; }
  .logs-table-container { overflow: hidden; border-radius: 8px; background: rgba(255,255,255,.92); }
  .logs-table { border-collapse: separate !important; border-spacing: 0 !important; font-size: .8rem; color: #1f2937; }
  .logs-table thead th { white-space: nowrap; font-size: .72rem; color: #5f6672; font-weight: 760; background: #f3f4f6 !important; border: 0 !important; padding: .72rem .8rem !important; }
  .logs-table tbody td { border: 0 !important; border-bottom: 1px solid #f2f4f7 !important; padding: .72rem .8rem !important; vertical-align: middle; white-space: nowrap; }
  .logs-table tbody tr:nth-child(even) td { background: rgba(249,250,251,.72); }
  .logs-table tbody tr:hover td { background: #f8fbff; }
  .logs-table-card .card-body { padding: 0; }
  .logs-table-card .dataTables_wrapper { padding: 0; }
  .logs-table-card .row:first-child { display: none; }
  .logs-table-card .row:last-child { margin: 0; padding: .9rem .25rem 0; align-items: center; color: #475467; font-size: .78rem; }
  .logs-table-card .dataTables_paginate .pagination { gap: .35rem; margin: 0; justify-content: flex-end; }
  .logs-table-card .page-link {
    min-width: 34px;
    height: 34px;
    border-radius: 4px !important;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-color: #eaecf0;
    color: #344054;
    font-size: .78rem;
    box-shadow: none;
  }
  .logs-table-card .page-item.active .page-link { background: #3657f6; border-color: #3657f6; color: #fff; }
  .logs-table-card .page-item.disabled .page-link { color: #98a2b3; background: #f9fafb; }
  .log-action-badge {
    display: inline-flex;
    align-items: center;
    padding: .24rem .52rem;
    border-radius: 999px;
    background: #eef4ff;
    color: #3657f6;
    font-weight: 800;
    font-size: .68rem;
  }
  .logs-modal-note {
    border: 1px solid rgba(245, 158, 11, 0.18);
    border-radius: 10px;
    padding: .75rem .85rem;
    background: rgba(245, 158, 11, 0.08);
    color: #8a5300;
    font-size: .82rem;
  }
  @media (max-width: 991px) {
    .logs-head { flex-direction: column; }
    .logs-filter-row { grid-template-columns: 1fr; }
    .logs-action-row { justify-content: stretch; }
    .logs-soft-btn { width: 100%; }
  }
</style>

<div class="container-fluid py-3">
    <div class="logs-shell">
        <div class="logs-head">
            <div>
                <div class="logs-eyebrow">Audit Trail</div>
                <h2 class="logs-title">System Logs</h2>
                <div class="text-muted small mt-1">
                    <?= $totalLogs ?> log(s) recorded | <?= $totalUsers ?> user(s) | <?= $todayLogs ?> today
                </div>
            </div>
        </div>

        <form method="GET" class="logs-toolbar">
            <div class="logs-filter-row">
                <div class="logs-search">
                    <i class="bi bi-search"></i>
                    <input type="search" class="form-control" id="logsSearch" placeholder="Search logs">
                </div>
                <select name="user" class="form-select logs-filter">
                    <option value="">All Users</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= e($u) ?>" <?= $user_filter === $u ? 'selected' : '' ?>><?= e($u) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="action" class="form-control logs-filter" placeholder="Action" value="<?= e($action_filter) ?>">
                <input type="date" name="date_from" class="form-control logs-filter" value="<?= e($date_from) ?>" title="Date From">
                <input type="date" name="date_to" class="form-control logs-filter" value="<?= e($date_to) ?>" title="Date To">
            </div>
            <div class="logs-action-row">
                <button class="logs-soft-btn" type="submit">
                    Filter <i class="bi bi-funnel"></i>
                </button>
                <a href="<?= BASE_URL ?>/activity-logs" class="logs-soft-btn text-decoration-none">
                    Reset <i class="bi bi-arrow-clockwise"></i>
                </a>
                <?php if ($canDeleteLogs): ?>
                    <button type="button" class="logs-soft-btn text-danger" data-bs-toggle="modal" data-bs-target="#logsCleanupModal">
                        Cleanup <i class="bi bi-trash3"></i>
                    </button>
                <?php endif; ?>
            </div>
        </form>

        <div class="card logs-panel logs-table-card">
            <div class="card-body p-0">
                <div class="logs-table-container">
                    <div class="table-responsive">
                        <table id="activityTable" class="table align-middle nowrap mb-0 logs-table" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Action</th>
                                    <th>ID</th>
                                    <th>User</th>
                                    <th>Description</th>
                                    <th>IP Address</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($canDeleteLogs): ?>
<div class="modal fade" id="logsCleanupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title fw-bold">Log Cleanup</h5>
                    <div class="text-muted small">Delete logs for today, one date, or a date range.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="logs-modal-note mb-3">
                    Deleted log records cannot be restored from this screen.
                </div>
                <div class="row g-3">
                    <div class="col-lg-4">
                        <label class="form-label fw-semibold">Today</label>
                        <button type="button" class="btn btn-outline-danger w-100 js-delete-logs" data-mode="today">
                            <i class="bi bi-trash3 me-1"></i>Delete Today
                        </button>
                    </div>

                    <div class="col-lg-4">
                        <label class="form-label fw-semibold">Specific Date</label>
                        <div class="d-grid gap-2">
                            <input type="date" class="form-control" id="deleteLogDate" max="<?= date('Y-m-d') ?>">
                            <button type="button" class="btn btn-outline-danger js-delete-logs" data-mode="date">
                                <i class="bi bi-calendar-x me-1"></i>Delete Date
                            </button>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <label class="form-label fw-semibold">Date Range</label>
                        <div class="row g-2">
                            <div class="col-6">
                                <input type="date" class="form-control" id="deleteLogFrom" max="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="col-6">
                                <input type="date" class="form-control" id="deleteLogTo" max="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="col-12">
                                <button type="button" class="btn btn-outline-danger w-100 js-delete-logs" data-mode="range">
                                    <i class="bi bi-trash me-1"></i>Delete Range
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
$(function () {
    const activityTable = $('#activityTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '<?= BASE_URL ?>/activity-logs-data',
            type: 'POST',
            data: function (data) {
                data.user = <?= json_encode($user_filter) ?>;
                data.action_filter = <?= json_encode($action_filter) ?>;
                data.date_from = <?= json_encode($date_from) ?>;
                data.date_to = <?= json_encode($date_to) ?>;
            }
        },
        pageLength: 12,
        lengthMenu: [[12, 25, 50, 100], [12, 25, 50, 100]],
        order: [[5, 'desc']],
        responsive: true,
        language: {
            search: '',
            searchPlaceholder: 'Search logs'
        }
    });

    $('#logsSearch').on('input', function () {
        activityTable.search(this.value).draw();
    });

    $('.js-delete-logs').on('click', function () {
        const mode = $(this).data('mode');
        const payload = { mode };
        let detailLabel = "today's logs";

        if (mode === 'date') {
            const date = $('#deleteLogDate').val();
            if (!date) {
                Swal.fire('Date Required', 'Please choose a date to delete.', 'warning');
                return;
            }
            payload.date = date;
            detailLabel = 'logs for ' + date;
        }

        if (mode === 'range') {
            const dateFrom = $('#deleteLogFrom').val();
            const dateTo = $('#deleteLogTo').val();
            if (!dateFrom || !dateTo) {
                Swal.fire('Date Range Required', 'Please choose both start and end dates.', 'warning');
                return;
            }
            payload.date_from = dateFrom;
            payload.date_to = dateTo;
            detailLabel = 'logs from ' + dateFrom + ' to ' + dateTo;
        }

        Swal.fire({
            title: 'Delete Logs?',
            html: 'You are about to permanently remove <b>' + detailLabel + '</b>.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Yes, Delete',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (!result.isConfirmed) return;

            $.ajax({
                url: '<?= BASE_URL ?>/activity-logs-action',
                method: 'POST',
                data: payload,
                dataType: 'json',
                beforeSend: function () {
                    Swal.fire({
                        title: 'Deleting Logs...',
                        text: 'Please wait while the cleanup is being processed.',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        didOpen: () => Swal.showLoading()
                    });
                },
                success: function (response) {
                    if (!response.ok) {
                        Swal.fire('Unable to Delete', response.message || 'No logs were deleted.', 'error');
                        return;
                    }

                    Swal.fire({
                        icon: 'success',
                        title: 'Logs Deleted',
                        text: response.message || 'Selected logs were deleted successfully.'
                    }).then(() => window.location.reload());
                },
                error: function (xhr) {
                    let message = 'Something went wrong while deleting logs.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    }
                    Swal.fire('Delete Failed', message, 'error');
                }
            });
        });
    });
});
</script>

<?php include ROOT_PATH . '/modules/layout/footer.php'; ?>
