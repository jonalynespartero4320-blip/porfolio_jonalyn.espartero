<?php
require_once ROOT_PATH . '/config/database.php';
include ROOT_PATH . '/modules/layout/header.php';

ensure_record_history_table($pdo);

$user_filter = trim((string) ($_GET['user'] ?? ''));
$action_filter = trim((string) ($_GET['action'] ?? ''));
$date_from = trim((string) ($_GET['date_from'] ?? ''));
$date_to = trim((string) ($_GET['date_to'] ?? ''));

$query = "SELECT * FROM activity_logs WHERE 1=1";
$params = [];

if ($user_filter !== '') {
    $query .= " AND username = ?";
    $params[] = $user_filter;
}

if ($action_filter !== '') {
    $query .= " AND action LIKE ?";
    $params[] = "%{$action_filter}%";
}

if ($date_from !== '') {
    $query .= " AND DATE(created_at) >= ?";
    $params[] = $date_from;
}

if ($date_to !== '') {
    $query .= " AND DATE(created_at) <= ?";
    $params[] = $date_to;
}

$query .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$historyStmt = $pdo->prepare("
    SELECT *
    FROM record_history
    ORDER BY created_at DESC
    LIMIT 500
");
$historyStmt->execute();
$recordHistory = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

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
  .logs-shell { display: grid; gap: 1.25rem; 
    background:
      linear-gradient(180deg, rgba(255, 255, 255, 0.86) 0%, rgba(248, 250, 252, 0.96) 100%);
    border-radius: 28px;
    padding: 24px;}
  .logs-hero {
    position: relative;
    overflow: hidden;
    border: 0;
    border-radius: 28px;
    background:
      linear-gradient(135deg, #062150, #447A61);
    color: #fff;
    box-shadow: 0 24px 60px rgba(8, 19, 31, 0.14);
  }
  .logs-hero::after {
    content: "";
    position: absolute;
    right: -80px;
    bottom: -80px;
    width: 220px;
    height: 220px;
    border-radius: 50%;
    background: rgba(255,255,255,0.1);
  }
  .logs-hero .card-body { position: relative; z-index: 1; padding: 1.6rem; }
  .logs-stat-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1rem; }
  .logs-stat-card, .logs-filter-card, .logs-table-card {
    border: 0;
    border-radius: 24px;
    box-shadow: 0 16px 38px rgba(15, 23, 42, 0.08);
  }
  .logs-tools-card {
    border: 0;
    border-radius: 24px;
    box-shadow: 0 16px 38px rgba(15, 23, 42, 0.08);
    background: linear-gradient(180deg, rgba(255,255,255,0.96), rgba(246,250,255,0.96));
  }
  .logs-stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 16px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
  }
  .logs-table-card .card-header {
    background: linear-gradient(135deg, rgba(15,71,215,0.04), rgba(15,143,45,0.04));
    border-bottom: 1px solid rgba(8,19,31,0.08);
  }
  .history-json {
    max-width: 520px;
    max-height: 260px;
    overflow: auto;
    white-space: pre-wrap;
    word-break: break-word;
    font-size: .78rem;
    background: #0f172a;
    color: #e2e8f0;
    border-radius: 12px;
    padding: .85rem;
  }
  .history-summary {
    min-width: 260px;
    max-width: 420px;
  }
  .log-action-badge {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    padding: .45rem .7rem;
    border-radius: 999px;
    background: rgba(15,71,215,0.08);
    color: #1f4fa5;
    font-weight: 600;
  }
  .log-description {
    display: grid;
    gap: .35rem;
    min-width: 320px;
    max-width: 620px;
  }
  .log-description-line {
    display: block;
    padding: .35rem .55rem;
    border-radius: 10px;
    background: rgba(248, 250, 252, 0.92);
    border: 1px solid rgba(148, 163, 184, 0.18);
    color: #334155;
    line-height: 1.35;
  }
  .log-description-line:first-child {
    background: rgba(29, 92, 120, 0.08);
    border-color: rgba(29, 92, 120, 0.14);
    color: #14324f;
    font-weight: 700;
  }
  .logs-tool-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 1rem;
  }
  .logs-tool-panel {
    border: 1px solid rgba(20, 50, 79, 0.08);
    border-radius: 20px;
    padding: 1rem;
    background: rgba(255,255,255,0.82);
  }
  .logs-tool-panel h6 {
    margin-bottom: .35rem;
    font-weight: 700;
  }
  .logs-tool-panel p {
    color: #64748b;
    font-size: .92rem;
    margin-bottom: .85rem;
  }
  .logs-warning {
    border-radius: 18px;
    padding: .9rem 1rem;
    background: rgba(245, 158, 11, 0.12);
    color: #8a5300;
    font-size: .92rem;
  }
  @media (max-width: 991px) {
    .logs-stat-grid { grid-template-columns: 1fr; }
    .logs-tool-grid { grid-template-columns: 1fr; }
  }
</style>

<div class="container-fluid py-3">
    <div class="logs-shell">
        <div class="card logs-hero">
            <div class="card-body">
                <div class="text-uppercase small fw-semibold opacity-75 mb-2">Audit Trail</div>
                <h2 class="fw-bold mb-1"><i class="bi bi-journal-text me-2"></i>System Logs</h2>
                <p class="mb-0 opacity-75">Track user activity, operational events, and audit history with a cleaner monitoring workspace.</p>
            </div>
        </div>

        <div class="logs-stat-grid">
            <div class="card logs-stat-card"><div class="card-body"><div class="d-flex justify-content-between align-items-start"><div><div class="text-muted small">Total Logs</div><div class="display-6 fw-bold mb-0"><?= $totalLogs ?></div></div><div class="logs-stat-icon bg-primary-subtle text-primary"><i class="bi bi-database"></i></div></div></div></div>
            <div class="card logs-stat-card"><div class="card-body"><div class="d-flex justify-content-between align-items-start"><div><div class="text-muted small">Users Logged</div><div class="display-6 fw-bold mb-0"><?= $totalUsers ?></div></div><div class="logs-stat-icon bg-success-subtle text-success"><i class="bi bi-people"></i></div></div></div></div>
            <div class="card logs-stat-card"><div class="card-body"><div class="d-flex justify-content-between align-items-start"><div><div class="text-muted small">Today</div><div class="display-6 fw-bold mb-0"><?= $todayLogs ?></div></div><div class="logs-stat-icon bg-warning-subtle text-warning"><i class="bi bi-clock-history"></i></div></div></div></div>
        </div>

        <div class="card logs-filter-card">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">User</label>
                        <select name="user" class="form-select">
                            <option value="">All Users</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= e($u) ?>" <?= $user_filter === $u ? 'selected' : '' ?>><?= e($u) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Action</label>
                        <input type="text" name="action" class="form-control" placeholder="Search action..." value="<?= e($action_filter) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Date From</label>
                        <input type="date" name="date_from" class="form-control" value="<?= e($date_from) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Date To</label>
                        <input type="date" name="date_to" class="form-control" value="<?= e($date_to) ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end gap-2">
                        <button class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>Filter</button>
                        <a href="<?= BASE_URL ?>/activity-logs" class="btn btn-secondary w-100">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($canDeleteLogs): ?>
        <div class="card logs-tools-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                    <div>
                        <h5 class="mb-1 fw-bold">Log Cleanup Controls</h5>
                        <small class="text-muted">Delete logs for today, a specific date, or a selected date range.</small>
                    </div>
                    <span class="badge text-bg-warning">Restricted Action</span>
                </div>

                <div class="logs-warning mb-3">
                    Only use this when audit cleanup is truly intended. Deleted log records cannot be restored from this screen.
                </div>

                <div class="logs-tool-grid">
                    <div class="logs-tool-panel">
                        <h6><i class="bi bi-calendar-day me-2"></i>Delete Today</h6>
                        <p>Removes all log records created today.</p>
                        <button type="button" class="btn btn-danger w-100 js-delete-logs" data-mode="today">
                            <i class="bi bi-trash3 me-1"></i>Delete Today's Logs
                        </button>
                    </div>

                    <div class="logs-tool-panel">
                        <h6><i class="bi bi-calendar-date me-2"></i>Delete Specific Date</h6>
                        <p>Delete logs only from one selected calendar date.</p>
                        <div class="d-grid gap-2">
                            <input type="date" class="form-control" id="deleteLogDate" max="<?= date('Y-m-d') ?>">
                            <button type="button" class="btn btn-danger js-delete-logs" data-mode="date">
                                <i class="bi bi-calendar-x me-1"></i>Delete Selected Date
                            </button>
                        </div>
                    </div>

                    <div class="logs-tool-panel">
                        <h6><i class="bi bi-calendar-range me-2"></i>Delete Date Range</h6>
                        <p>Delete logs from a start date through an end date.</p>
                        <div class="row g-2">
                            <div class="col-6">
                                <input type="date" class="form-control" id="deleteLogFrom" max="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="col-6">
                                <input type="date" class="form-control" id="deleteLogTo" max="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="col-12">
                                <button type="button" class="btn btn-danger w-100 js-delete-logs" data-mode="range">
                                    <i class="bi bi-trash me-1"></i>Delete Date Range
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="card logs-table-card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="mb-1 fw-bold">Activity Log Ledger</h5>
                    <!-- <small class="text-muted">Detailed record of actions captured by the system.</small> -->
                </div>
                <span class="badge text-bg-light text-dark"><?= count($logs) ?> record(s)</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="activityTable" class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Description</th>
                                <th>IP Address</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td class="fw-semibold"><?= (int) $log['id'] ?></td>
                                    <td><?= e($log['username']) ?></td>
                                    <td><span class="log-action-badge"><?= e($log['action']) ?></span></td>
                                    <td>
                                        <div class="log-description">
                                            <?php
                                            $descriptionParts = array_filter(array_map('trim', explode(' | ', (string) ($log['description'] ?? ''))), 'strlen');
                                            if (empty($descriptionParts)) {
                                                $descriptionParts = ['No activity description recorded.'];
                                            }
                                            ?>
                                            <?php foreach ($descriptionParts as $descriptionPart): ?>
                                                <span class="log-description-line"><?= e($descriptionPart) ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                    <td><?= e($log['ip_address']) ?></td>
                                    <td><?= e($log['created_at']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card logs-table-card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="mb-1 fw-bold">Record Change History</h5>
                    <small class="text-muted">Archive of edited and deleted records with before/after snapshots.</small>
                </div>
                <span class="badge text-bg-light text-dark"><?= count($recordHistory) ?> latest record(s)</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="recordHistoryTable" class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Record</th>
                                <th>Action</th>
                                <th>Summary</th>
                                <th>Before</th>
                                <th>After</th>
                                <th>Changed By</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recordHistory as $history): ?>
                                <tr>
                                    <td class="fw-semibold"><?= (int) $history['id'] ?></td>
                                    <td>
                                        <div class="fw-semibold"><?= e($history['entity_type']) ?></div>
                                        <small class="text-muted">ID: <?= e($history['entity_id']) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge <?= ($history['action_type'] ?? '') === 'deleted' ? 'text-bg-danger' : 'text-bg-primary' ?>">
                                            <?= strtoupper(e($history['action_type'])) ?>
                                        </span>
                                    </td>
                                    <td class="history-summary"><?= e($history['summary'] ?? '-') ?></td>
                                    <td>
                                        <?php if (!empty($history['before_data'])): ?>
                                            <details>
                                                <summary class="btn btn-sm btn-outline-secondary">View Before</summary>
                                                <pre class="history-json mt-2"><?= e($history['before_data']) ?></pre>
                                            </details>
                                        <?php else: ?>
                                            <span class="text-muted">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($history['after_data'])): ?>
                                            <details>
                                                <summary class="btn btn-sm btn-outline-success">View After</summary>
                                                <pre class="history-json mt-2"><?= e($history['after_data']) ?></pre>
                                            </details>
                                        <?php else: ?>
                                            <span class="text-muted">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div><?= e($history['changed_by_username'] ?? 'Guest') ?></div>
                                        <small class="text-muted"><?= e($history['ip_address'] ?? '') ?></small>
                                    </td>
                                    <td><?= e($history['created_at']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(function () {
    $('#activityTable').DataTable({
        pageLength: 12,
        order: [[5, 'desc']],
        responsive: true,
        language: {
            search: '',
            searchPlaceholder: 'Search logs'
        }
    });

    $('#recordHistoryTable').DataTable({
        pageLength: 10,
        order: [[7, 'desc']],
        responsive: true,
        language: {
            search: '',
            searchPlaceholder: 'Search record history'
        }
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
