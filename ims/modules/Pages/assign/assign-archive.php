<?php
$page_title = 'Assignment Archive';
require_once ROOT_PATH . '/modules/layout/header.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/functions.php';

require_login();
require_permission('ictd.assign.view');
?>
<style>
  .assign-archive-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; margin-bottom: 1rem; }
  .assign-archive-title { margin: 0; color: #1f2937; font-size: 1.35rem; font-weight: 850; }
  .assign-archive-toolbar { display: flex; align-items: center; justify-content: flex-end; gap: .65rem; flex-wrap: wrap; margin-bottom: .8rem; }
  .assign-archive-search { position: relative; min-width: min(360px, 100%); }
  .assign-archive-search i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #98a2b3; font-size: .85rem; }
  .assign-archive-search input { height: 38px; padding-left: 34px; border-radius: 4px; border-color: #eaecf0; font-size: .78rem; }
  .assign-archive-soft-btn { height: 38px; border: 1px solid #eaecf0; background: #fff; color: #344054; border-radius: 4px; padding: 0 .8rem; font-size: .78rem; font-weight: 650; }
  .assign-archive-card { border: 0; border-radius: 10px; background: rgba(255,255,255,.78); box-shadow: none; overflow: hidden; }
  .assign-archive-table { border-collapse: separate !important; border-spacing: 0 !important; font-size: .8rem; color: #1f2937; }
  .assign-archive-table thead th { white-space: nowrap; font-size: .72rem; color: #5f6672; font-weight: 760; background: #f3f4f6 !important; border: 0 !important; padding: .72rem .8rem !important; }
  .assign-archive-table tbody td { border: 0 !important; border-bottom: 1px solid #f2f4f7 !important; padding: .72rem .8rem !important; vertical-align: middle; white-space: nowrap; }
  .assign-archive-table tbody tr:nth-child(even) td { background: rgba(249,250,251,.72); }
  .assign-archive-table tbody tr:hover td { background: #f8fbff; }
  .assign-archive-card .row:first-child { display: none; }
  .assign-type-badge { display: inline-flex; align-items: center; border-radius: 999px; padding: .18rem .5rem; background: #eef4ff; color: #3657f6; font-weight: 800; font-size: .68rem; }
</style>

<div class="assign-archive-head">
    <div>
        <div class="text-muted small fw-semibold text-uppercase">Assignment</div>
        <h2 class="assign-archive-title">Assignment Archive</h2>
        <div class="text-muted small">Archived assignment records keep the equipment, end user, and department details at the time of archive.</div>
    </div>
    <a href="<?= BASE_URL ?>/ictd/assign" class="assign-archive-soft-btn d-inline-flex align-items-center gap-2 text-decoration-none">
        <i class="bi bi-arrow-left"></i> Back to Assign
    </a>
</div>

<div class="assign-archive-toolbar">
    <div class="assign-archive-search">
        <i class="bi bi-search"></i>
        <input type="search" class="form-control" id="assignArchiveSearch" placeholder="Search archived assignments">
    </div>
    <button type="button" class="assign-archive-soft-btn" id="assignArchiveRefreshBtn" title="Refresh">
        <i class="bi bi-arrow-clockwise"></i>
    </button>
</div>

<div class="card assign-archive-card">
    <div class="table-responsive">
        <table id="assignArchiveTable" class="table align-middle nowrap mb-0 assign-archive-table" style="width:100%">
            <thead>
                <tr>
                    <th>Assign ID</th>
                    <th>Type</th>
                    <th>Control No</th>
                    <th>End User</th>
                    <th>Department</th>
                    <th>IP</th>
                    <th>Internet</th>
                    <th>WiFi</th>
                    <th>Assigned Date</th>
                    <th>Reason</th>
                    <th>Archived</th>
                    <th>By</th>
                </tr>
            </thead>
        </table>
    </div>
</div>

<script>
$(function () {
    const table = $('#assignArchiveTable').DataTable({
        processing: true,
        serverSide: true,
        scrollX: true,
        ajax: { url: '<?= BASE_URL ?>/ictd/assign-archive-data', type: 'POST' },
        order: [[10, 'desc']],
        pageLength: 10,
        lengthChange: false,
        language: {
            processing: 'Loading archive...',
            emptyTable: 'No archived assignments yet.'
        }
    });

    $('#assignArchiveSearch').on('input', function () {
        table.search(this.value).draw();
    });

    $('#assignArchiveRefreshBtn').on('click', function () {
        table.ajax.reload(null, false);
    });
});
</script>
<?php require_once ROOT_PATH . '/modules/layout/footer.php'; ?>
