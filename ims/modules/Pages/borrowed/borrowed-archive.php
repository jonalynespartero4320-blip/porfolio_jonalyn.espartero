<?php
$page_title = 'Borrowed Archive';
require_once ROOT_PATH . '/modules/layout/header.php';
require_once __DIR__ . '/borrowed-common.php';

require_login();
require_permission('ictd.borrowed.view');
borrowed_ensure_schema($pdo);
?>

<style>
  .borrowed-archive-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; margin-bottom: 1rem; }
  .borrowed-archive-title { margin: 0; color: #1f2937; font-size: 1.35rem; font-weight: 850; }
  .borrowed-toolbar { display: flex; align-items: center; justify-content: flex-end; gap: .65rem; flex-wrap: wrap; margin-bottom: .8rem; }
  .borrowed-search { position: relative; min-width: min(360px, 100%); }
  .borrowed-search i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #98a2b3; font-size: .85rem; }
  .borrowed-search input { height: 38px; padding-left: 34px; border-radius: 4px; border-color: #eaecf0; font-size: .78rem; }
  .borrowed-soft-btn { height: 38px; border: 1px solid #eaecf0; background: #fff; color: #344054; border-radius: 4px; padding: 0 .8rem; font-size: .78rem; font-weight: 650; }
  .borrowed-panel { border: 0; border-radius: 10px; background: rgba(255,255,255,.78); box-shadow: none; overflow: hidden; }
  .borrowed-table { border-collapse: separate !important; border-spacing: 0 !important; font-size: .8rem; color: #1f2937; }
  .borrowed-table thead th { white-space: nowrap; font-size: .72rem; color: #5f6672; font-weight: 760; background: #f3f4f6 !important; border: 0 !important; padding: .72rem .8rem !important; }
  .borrowed-table tbody td { border: 0 !important; border-bottom: 1px solid #f2f4f7 !important; padding: .72rem .8rem !important; vertical-align: middle; white-space: nowrap; }
  .borrowed-table tbody tr:nth-child(even) td { background: rgba(249,250,251,.72); }
  .borrowed-actions { display: inline-flex; align-items: center; gap: .6rem; white-space: nowrap; }
  .borrowed-action-btn { border: 0; background: transparent; padding: 0; line-height: 1; font-size: .9rem; font-weight: 750; }
  .borrowed-action-delete { color: #e26060; }
  .borrowed-panel .row:first-child { display: none; }
  .borrowed-panel .row:last-child { margin: 0; padding: .9rem .25rem 0; align-items: center; color: #475467; font-size: .78rem; }
  .borrowed-panel .dataTables_paginate .pagination { gap: .35rem; margin: 0; justify-content: flex-end; }
  .borrowed-panel .page-link { min-width: 34px; height: 34px; border-radius: 4px !important; display: inline-flex; align-items: center; justify-content: center; border-color: #eaecf0; color: #344054; font-size: .78rem; box-shadow: none; }
  .borrowed-panel .page-item.active .page-link { background: #3657f6; border-color: #3657f6; color: #fff; }
</style>

<div class="container-fluid py-3">
    <div class="borrowed-archive-head">
        <div>
            <div class="text-muted small fw-bold">Archive</div>
            <h2 class="borrowed-archive-title">Borrowed Archive</h2>
        </div>
        <a href="<?= BASE_URL ?>/ictd/borrowed" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back to Borrowed</a>
    </div>
    <div class="borrowed-toolbar">
        <div class="borrowed-search"><i class="bi bi-search"></i><input type="search" class="form-control" id="borrowedArchiveSearch" placeholder="Search archived borrowed items"></div>
        <button type="button" class="borrowed-soft-btn" id="borrowedArchiveRefreshBtn"><i class="bi bi-arrow-clockwise"></i></button>
    </div>
    <div class="card borrowed-panel">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="borrowedArchiveTable" class="table align-middle nowrap mb-0 borrowed-table" style="width:100%">
                    <thead><tr><th>Action</th><th>Status</th><th>End User</th><th>Department</th><th>Item Borrowed</th><th>Serial No</th><th>Date Borrowed</th><th>Date Returned</th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
$(function () {
    const actionUrl = '<?= BASE_URL ?>/ictd/borrowed-action';
    const table = $('#borrowedArchiveTable').DataTable({
        ajax: { url: '<?= BASE_URL ?>/ictd/borrowed-data', type: 'POST', data: { archive: 1 } },
        order: [[6, 'desc']],
        scrollX: true,
        dom: 'rt<"row align-items-center mt-3 px-3 pb-3"<"col-md-4"i><"col-md-4 text-center"l><"col-md-4"p>>',
        columnDefs: [{ targets: [0], orderable: false, searchable: false }],
        language: { emptyTable: 'No archived borrowed items yet.' }
    });
    $('#borrowedArchiveSearch').on('input', function () { table.search(this.value).draw(); });
    $('#borrowedArchiveRefreshBtn').on('click', function () { $('#borrowedArchiveSearch').val(''); table.search('').ajax.reload(); });
    $(document).on('click', '.restore-borrowed-btn', function () {
        $.post(actionUrl, { action: 'restore', id: $(this).data('id') }, res => { Swal.fire(res.success ? 'Restored' : 'Error', res.message, res.success ? 'success' : 'error'); table.ajax.reload(null, false); }, 'json');
    });
    $(document).on('click', '.permanent-delete-borrowed-btn', function () {
        const id = $(this).data('id');
        Swal.fire({ title: 'Permanently delete?', text: 'This cannot be undone.', icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33' }).then(result => {
            if (!result.isConfirmed) return;
            $.post(actionUrl, { action: 'permanent_delete', id }, res => { Swal.fire(res.success ? 'Deleted' : 'Error', res.message, res.success ? 'success' : 'error'); table.ajax.reload(null, false); }, 'json');
        });
    });
});
</script>

<?php require_once ROOT_PATH . '/modules/layout/footer.php'; ?>
