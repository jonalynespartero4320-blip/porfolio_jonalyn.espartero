<?php
$page_title = 'Service Request Archive';
require_once ROOT_PATH . '/modules/layout/header.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/functions.php';

require_login();
require_permission('ictd.request.view');
?>

<style>
  .request-shell { display: grid; gap: 1.05rem; }
  .request-head { display: flex; justify-content: space-between; gap: 1rem; align-items: flex-start; }
  .request-eyebrow { color: #667085; font-size: .78rem; font-weight: 750; }
  .request-title { color: #1f2937; font-size: 1.35rem; font-weight: 850; margin: 0; }
  .request-toolbar { display: flex; justify-content: flex-end; gap: .65rem; flex-wrap: wrap; }
  .request-search { position: relative; min-width: min(340px, 100%); }
  .request-search i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #98a2b3; }
  .request-search input { height: 38px; padding-left: 34px; border-radius: 4px; border-color: #eaecf0; font-size: .78rem; }
  .request-panel { border: 0; border-radius: 10px; background: rgba(255,255,255,.78); box-shadow: none; }
  .request-table { font-size: .8rem; color: #1f2937; }
  .request-table thead th { white-space: nowrap; font-size: .72rem; color: #5f6672; background: #f3f4f6 !important; border: 0 !important; padding: .72rem .8rem !important; }
  .request-table tbody td { border: 0 !important; border-bottom: 1px solid #f2f4f7 !important; padding: .72rem .8rem !important; vertical-align: middle; white-space: nowrap; }
  .request-actions { display: inline-flex; gap: .55rem; align-items: center; }
  .request-action-btn { border: 0; background: transparent; padding: 0; line-height: 1; font-size: .95rem; }
  .request-action-restore { color: #16803d; }
  .request-action-delete { color: #e26060; }
  .request-table-card .row:first-child { display: none; }
  .request-table-card .row:last-child { margin: 0; padding: .9rem .25rem 0; align-items: center; color: #475467; font-size: .78rem; }
</style>

<div class="container-fluid py-3">
    <div class="request-shell">
        <div class="request-head">
            <div>
                <div class="request-eyebrow">Archive</div>
                <h2 class="request-title">Service Request Archive</h2>
                <div class="text-muted small">Review and restore archived service requests.</div>
            </div>
            <a href="<?= BASE_URL ?>/ictd/requests/service" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back to Requests</a>
        </div>
        <div class="request-toolbar">
            <div class="request-search"><i class="bi bi-search"></i><input type="search" class="form-control" id="archiveSearch" placeholder="Search archived service requests"></div>
        </div>
        <div class="card request-panel request-table-card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="serviceArchiveTable" class="table align-middle nowrap mb-0 request-table" style="width:100%">
                        <thead><tr><th>Action</th><th>ID</th><th>End User</th><th>Department</th><th>Request Type</th><th>Description</th><th>Status</th><th>Technician</th><th>Remarks / Reason</th><th>Created</th><th>Archived</th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const actionUrl = '<?= BASE_URL ?>/ictd/requests/service-action';
    const table = $('#serviceArchiveTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: { url: '<?= BASE_URL ?>/ictd/requests/service-data', type: 'POST', data: d => { d.archive = 1; } },
        order: [[10, 'desc']],
        pageLength: 12,
        scrollX: true,
        dom: 'rt<"row align-items-center mt-3 px-3 pb-3"<"col-md-4"i><"col-md-4 text-center"l><"col-md-4"p>>',
        columnDefs: [{ targets: [0], orderable: false, searchable: false }]
    });
    $('#archiveSearch').on('input', function () { table.search(this.value).draw(); });
    $(document).on('click', '.restore-service-btn', function () {
        const id = $(this).data('id');
        Swal.fire({ title: 'Restore request?', text: 'This will return the request to the active table.', icon: 'question', showCancelButton: true, confirmButtonText: 'Restore' }).then(result => {
            if (!result.isConfirmed) return;
            $.post(actionUrl, { action: 'restore', id }, res => { Swal.fire(res.success ? 'Restored' : 'Error', res.message, res.success ? 'success' : 'error'); if (res.success) table.ajax.reload(null, false); }, 'json');
        });
    });
    $(document).on('click', '.delete-service-btn', function () {
        const id = $(this).data('id');
        Swal.fire({ title: 'Permanently delete?', text: 'This cannot be undone.', icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc3545', confirmButtonText: 'Delete permanently' }).then(result => {
            if (!result.isConfirmed) return;
            $.post(actionUrl, { action: 'delete', id }, res => { Swal.fire(res.success ? 'Deleted' : 'Error', res.message, res.success ? 'success' : 'error'); if (res.success) table.ajax.reload(null, false); }, 'json');
        });
    });
});
</script>

<?php require_once ROOT_PATH . '/modules/layout/footer.php'; ?>
