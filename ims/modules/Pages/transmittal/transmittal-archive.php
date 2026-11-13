<?php
$page_title = 'Transmittal Archive';
require_once ROOT_PATH . '/modules/layout/header.php';
require_once ROOT_PATH . '/helpers/functions.php';

require_login();
require_permission('ictd.transmittal.view');
?>

<style>
  .archive-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; margin-bottom: 1rem; }
  .archive-title { margin: 0; color: #1f2937; font-size: 1.35rem; font-weight: 850; }
  .archive-toolbar { display: flex; align-items: center; justify-content: flex-end; gap: .65rem; flex-wrap: wrap; margin-bottom: .8rem; }
  .archive-search { position: relative; min-width: min(320px, 100%); }
  .archive-search i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #98a2b3; font-size: .85rem; }
  .archive-search input { height: 38px; padding-left: 34px; border-radius: 4px; border-color: #eaecf0; font-size: .78rem; }
  .archive-soft-btn { height: 38px; border: 1px solid #eaecf0; background: #fff; color: #344054; border-radius: 4px; padding: 0 .8rem; font-size: .78rem; font-weight: 650; }
  .archive-card { border: 0; border-radius: 10px; background: rgba(255,255,255,.78); box-shadow: none; overflow: hidden; }
  .archive-table { border-collapse: separate !important; border-spacing: 0 !important; font-size: .8rem; color: #1f2937; }
  .archive-table thead th { white-space: nowrap; font-size: .72rem; color: #5f6672; font-weight: 760; background: #f3f4f6 !important; border: 0 !important; padding: .72rem .8rem !important; }
  .archive-table tbody td { border: 0 !important; border-bottom: 1px solid #f2f4f7 !important; padding: .72rem .8rem !important; vertical-align: middle; white-space: nowrap; }
  .archive-table tbody tr:nth-child(even) td { background: rgba(249,250,251,.72); }
  .archive-table tbody tr:hover td { background: #f8fbff; }
  .archive-card .dataTables_wrapper { padding: 0; }
  .archive-card .row:first-child { display: none; }
  .archive-card .row:last-child { margin: 0; padding: .9rem .25rem 0; align-items: center; color: #475467; font-size: .78rem; }
  .archive-card .dataTables_info { padding-top: .45rem !important; }
  .archive-card .dataTables_paginate .pagination { gap: .35rem; margin: 0; justify-content: flex-end; }
  .archive-card .page-link { min-width: 34px; height: 34px; border-radius: 4px !important; display: inline-flex; align-items: center; justify-content: center; border-color: #eaecf0; color: #344054; font-size: .78rem; box-shadow: none; }
  .archive-card .page-item.active .page-link { background: #3657f6; border-color: #3657f6; color: #fff; }
  .transmittal-action-btn { border: 0; background: transparent; padding: 0; line-height: 1; font-size: .95rem; }
  .transmittal-actions { display: inline-flex; gap: .65rem; align-items: center; }
  .ims-upload-panel { width: 220px; display: grid; gap: .28rem; justify-items: center; }
  .ims-upload-file-link { color: #14966f; font-size: .82rem; font-weight: 850; text-decoration: underline; max-width: 212px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .ims-upload-empty { color: #98a2b3; font-size: .72rem; font-weight: 750; }
  .ims-upload-actions { display: inline-flex; align-items: center; justify-content: center; gap: .45rem; }
  .ims-upload-btn { border: 0; border-radius: 11px; min-width: 38px; height: 30px; padding: 0 .75rem; display: inline-flex; align-items: center; justify-content: center; gap: .35rem; font-size: .72rem; font-weight: 800; line-height: 1; box-shadow: inset 0 1px 0 rgba(255,255,255,.48), 0 3px 7px rgba(15,23,42,.2); cursor: pointer; text-decoration: none; }
  .ims-upload-square { min-width: 64px; width: auto; padding: 0 .65rem; }
  .ims-upload-btn-blue { background: linear-gradient(180deg, #4f9cff 0%, #1f6fe5 100%); color: #fff; }
  .ims-upload-btn-gray { background: linear-gradient(180deg, #f9fafb 0%, #e5e7eb 100%); color: #64748b; }
</style>

<div class="container-fluid py-3">
    <div class="archive-head">
        <div>
            <div class="text-muted small fw-bold">Archive</div>
            <h2 class="archive-title">Transmittal Archive</h2>
        </div>
        <a href="<?= BASE_URL ?>/ictd/transmittal" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back to Transmittal</a>
    </div>

    <div class="archive-toolbar">
        <div class="archive-search">
            <i class="bi bi-search"></i>
            <input type="search" class="form-control" id="transmittalArchiveSearch" placeholder="Search archived transmittals">
        </div>
        <button type="button" class="archive-soft-btn" id="transmittalArchiveRefreshBtn" title="Refresh">
            <i class="bi bi-arrow-clockwise"></i>
        </button>
    </div>

    <div class="card archive-card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="transmittalArchiveTable" class="table align-middle nowrap mb-0 archive-table" style="width:100%">
                    <thead>
                        <tr>
                            <th>Action</th>
                            <th>Document</th>
                            <th>Transmittal ID</th>
                            <th>Department</th>
                            <th>Accountable Officer</th>
                            <th>Transmittal Date</th>
                            <th>Received By</th>
                            <th>Date Received</th>
                            <th>Items</th>
                            <th>Archived At</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="transmittalArchivePdfModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-pdf"></i> Transmittal PDF Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <iframe id="transmittalArchivePdfFrame" style="width:100%;height:75vh;border:0"></iframe>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const actionUrl = '<?= BASE_URL ?>/ictd/transmittal-action';
    const table = $('#transmittalArchiveTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: { url: '<?= BASE_URL ?>/ictd/transmittal-data', type: 'POST', data: d => { d.archive = 1; } },
        order: [[9, 'desc']],
        dom: 'rt<"row align-items-center"<"col-md-4"i><"col-md-4"l><"col-md-4"p>>',
        pageLength: 10,
        scrollX: true,
        columnDefs: [{ targets: [0, 1], orderable: false, searchable: false }],
        language: {
            info: '_START_ - _END_ of _TOTAL_',
            lengthMenu: 'Rows per page _MENU_',
            paginate: { previous: '<i class="bi bi-chevron-left"></i>', next: '<i class="bi bi-chevron-right"></i>' },
            processing: 'Loading archive...',
            emptyTable: 'No archived transmittals yet.'
        }
    });

    $('#transmittalArchiveSearch').on('input', function () {
        table.search(this.value).draw();
    });

    $('#transmittalArchiveRefreshBtn').on('click', function () {
        $('#transmittalArchiveSearch').val('');
        table.search('').ajax.reload();
    });

    $(document).on('click', '.restore-transmittal-btn', function () {
        const id = $(this).data('id');
        $.post(actionUrl, { action: 'restore', id }, function (res) {
            Swal.fire(res.success ? 'Restored' : 'Error', res.message, res.success ? 'success' : 'error');
            table.ajax.reload(null, false);
        }, 'json');
    });

    $(document).on('click', '.permanent-delete-transmittal-btn', function () {
        const id = $(this).data('id');
        Swal.fire({ title: 'Permanently delete?', text: 'This cannot be undone.', icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33' }).then(result => {
            if (!result.isConfirmed) return;
            $.post(actionUrl, { action: 'delete', id }, function (res) {
                Swal.fire(res.success ? 'Deleted' : 'Error', res.message, res.success ? 'success' : 'error');
                table.ajax.reload(null, false);
            }, 'json');
        });
    });

    $(document).on('click', '.preview-transmittal-pdf', function () {
        $('#transmittalArchivePdfFrame').attr('src', $(this).data('pdf'));
        bootstrap.Modal.getOrCreateInstance(document.getElementById('transmittalArchivePdfModal')).show();
    });

    $('#transmittalArchivePdfModal').on('hidden.bs.modal', function () {
        $('#transmittalArchivePdfFrame').attr('src', '');
    });
});
</script>

<?php require_once ROOT_PATH . '/modules/layout/footer.php'; ?>
