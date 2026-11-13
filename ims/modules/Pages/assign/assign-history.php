<?php
$page_title = 'Assignment History';
require_once ROOT_PATH . '/modules/layout/header.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/functions.php';

require_login();
require_permission('ictd.assign.view');

$missingTables = db_table_exists($pdo, 'assignment_history') ? [] : ['assignment_history'];
?>

<style>
  .assign-history-shell { display: grid; gap: 1rem; }
  .assign-history-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; }
  .assign-history-eyebrow { color: #667085; font-size: .78rem; font-weight: 750; }
  .assign-history-title { margin: 0; color: #1f2937; font-size: 1.35rem; font-weight: 850; }
  .assign-history-card { border: 0; border-radius: 18px; background: rgba(255,255,255,.9); box-shadow: 0 18px 44px rgba(15,23,42,.08); overflow: hidden; }
  .assign-history-card .card-body { padding: 1rem; }
  .assign-history-toolbar { display: flex; justify-content: space-between; gap: .65rem; flex-wrap: wrap; margin-bottom: .8rem; }
  .assign-history-search { position: relative; min-width: min(340px, 100%); }
  .assign-history-search i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #98a2b3; }
  .assign-history-search input { height: 38px; padding-left: 34px; border-radius: 4px; border-color: #eaecf0; font-size: .78rem; }
  .assign-history-table { font-size: .8rem; }
  .assign-history-table thead th { white-space: nowrap; background: #f3f4f6 !important; border: 0 !important; color: #5f6672; font-size: .72rem; padding: .72rem .8rem !important; }
  .assign-history-table tbody td { border: 0 !important; border-bottom: 1px solid #f2f4f7 !important; padding: .72rem .8rem !important; white-space: nowrap; vertical-align: middle; }
  .assign-type-badge { display: inline-flex; border-radius: 999px; padding: .22rem .5rem; background: #eef4ff; color: #3657f6; font-weight: 800; font-size: .68rem; }
  .assign-history-empty { border-radius: 20px; background: rgba(239,68,68,.08); border: 1px solid rgba(239,68,68,.12); padding: 1rem; }
</style>

<div class="container-fluid py-3">
    <div class="assign-history-shell">
        <div class="assign-history-head">
            <div>
                <div class="assign-history-eyebrow">Audit Trail</div>
                <h2 class="assign-history-title">Assignment History</h2>
            </div>
            <a href="<?= BASE_URL ?>/ictd/assign" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-person-workspace"></i> Back to Assign
            </a>
        </div>

        <?php if (!empty($missingTables)): ?>
            <div class="assign-history-empty">
                <div class="fw-semibold mb-1">Assignment history cannot load yet</div>
                <div class="text-muted small">Missing tables: <?= e(implode(', ', $missingTables)) ?>.</div>
            </div>
        <?php else: ?>
            <div class="card assign-history-card">
                <div class="card-body">
                    <div class="assign-history-toolbar">
                        <div class="assign-history-search">
                            <i class="bi bi-search"></i>
                            <input type="search" class="form-control" id="assignHistoryPageSearch" placeholder="Search assignment history">
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table id="assignHistoryPageTable" class="table align-middle nowrap mb-0 assign-history-table" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Action</th>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Control No</th>
                                    <th>Old User</th>
                                    <th>New User</th>
                                    <th>Old Dept</th>
                                    <th>New Dept</th>
                                    <th>Done By</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (empty($missingTables)): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const table = $('#assignHistoryPageTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: { url: '<?= BASE_URL ?>/ictd/assign-history-data' },
        dom: 'rt<"row align-items-center"<"col-md-4"i><"col-md-4"l><"col-md-4"p>>',
        order: [[1, 'desc']],
        responsive: true,
        pageLength: 25,
        language: {
            info: '_START_ - _END_ of _TOTAL_',
            lengthMenu: 'Rows per page _MENU_',
            paginate: {
                previous: '<i class="bi bi-chevron-left"></i>',
                next: '<i class="bi bi-chevron-right"></i>'
            },
            processing: 'Loading history...'
        }
    });

    $('#assignHistoryPageSearch').on('input', function () {
        table.search(this.value).draw();
    });
});
</script>
<?php endif; ?>

<?php require_once ROOT_PATH . '/modules/layout/footer.php'; ?>
