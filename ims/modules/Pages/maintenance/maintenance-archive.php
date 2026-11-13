<?php
$page_title = 'Maintenance Archive';
require_once ROOT_PATH . '/modules/layout/header.php';
require_once ROOT_PATH . '/helpers/functions.php';

require_login();
require_permission('ictd.maintenance.view');
?>

<style>
  .report-archive-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; margin-bottom: 1rem; }
  .report-archive-title { margin: 0; color: #1f2937; font-size: 1.35rem; font-weight: 850; }
  .report-archive-toolbar { display: flex; align-items: center; justify-content: flex-end; gap: .65rem; flex-wrap: wrap; margin-bottom: .8rem; }
  .report-archive-search { position: relative; min-width: min(320px, 100%); }
  .report-archive-search i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #98a2b3; font-size: .85rem; }
  .report-archive-search input { height: 38px; padding-left: 34px; border-radius: 4px; border-color: #eaecf0; font-size: .78rem; }
  .report-archive-soft-btn { height: 38px; border: 1px solid #eaecf0; background: #fff; color: #344054; border-radius: 4px; padding: 0 .8rem; font-size: .78rem; font-weight: 650; }
  .report-archive-card { border: 0; border-radius: 10px; background: rgba(255,255,255,.78); box-shadow: none; overflow: hidden; }
  .report-archive-table { border-collapse: separate !important; border-spacing: 0 !important; font-size: .8rem; color: #1f2937; }
  .report-archive-table thead th { white-space: nowrap; font-size: .72rem; color: #5f6672; font-weight: 760; background: #f3f4f6 !important; border: 0 !important; padding: .72rem .8rem !important; }
  .report-archive-table tbody td { border: 0 !important; border-bottom: 1px solid #f2f4f7 !important; padding: .72rem .8rem !important; vertical-align: middle; white-space: nowrap; }
  .report-archive-table tbody tr:nth-child(even) td { background: rgba(249,250,251,.72); }
  .report-archive-table tbody tr:hover td { background: #f8fbff; }
  .report-archive-card .dataTables_wrapper { padding: 0; }
  .report-archive-card .row:first-child { display: none; }
  .report-archive-card .row:last-child { margin: 0; padding: .9rem .25rem 0; align-items: center; color: #475467; font-size: .78rem; }
  .report-archive-card .dataTables_info { padding-top: .45rem !important; }
  .report-archive-card .dataTables_paginate .pagination { gap: .35rem; margin: 0; justify-content: flex-end; }
  .report-archive-card .page-link { min-width: 34px; height: 34px; border-radius: 4px !important; display: inline-flex; align-items: center; justify-content: center; border-color: #eaecf0; color: #344054; font-size: .78rem; box-shadow: none; }
  .report-archive-card .page-item.active .page-link { background: #3657f6; border-color: #3657f6; color: #fff; }
  .maintenance-actions { display: inline-flex; gap: .65rem; align-items: center; white-space: nowrap; }
  .maintenance-action-btn { border: 0; background: transparent; padding: 0; line-height: 1; font-size: .95rem; }
  .maintenance-action-pdf { color: #d92d20; }
  .maintenance-action-delete { color: #e26060; }
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
    <div class="report-archive-head">
        <div>
            <div class="text-muted small fw-bold">Archive</div>
            <h2 class="report-archive-title">Maintenance Archive</h2>
        </div>
        <a href="<?= BASE_URL ?>/ictd/maintenance" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back to Maintenance</a>
    </div>
    <div class="report-archive-toolbar">
        <div class="report-archive-search">
            <i class="bi bi-search"></i>
            <input type="search" class="form-control" id="maintenanceArchiveSearch" placeholder="Search archived maintenance reports">
        </div>
        <button type="button" class="report-archive-soft-btn" id="maintenanceArchiveRefreshBtn" title="Refresh">
            <i class="bi bi-arrow-clockwise"></i>
        </button>
    </div>
    <div class="card report-archive-card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="maintenanceArchiveTable" class="table align-middle nowrap mb-0 report-archive-table" style="width:100%">
                    <thead>
                        <tr>
                            <th>Action</th>
                            <th>PDF</th>
                            <th>Maintenance ID</th>
                            <th>Control No</th>
                            <th>Serial No</th>
                            <th>Status</th>
                            <th>Branded</th>
                            <th>Assembled</th>
                            <th>End User</th>
                            <th>Department</th>
                            <th>Date Accomplished</th>
                            <th>Inspected By</th>
                            <th>Date Recorded</th>
                            <th>Items</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const actionUrl = '<?= BASE_URL ?>/ictd/maintenance-action';
    const table = $('#maintenanceArchiveTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: { url: '<?= BASE_URL ?>/ictd/maintenance-data', type: 'POST', data: d => { d.archive = 1; } },
        order: [[12, 'desc']],
        dom: 'rt<"row align-items-center"<"col-md-4"i><"col-md-4"l><"col-md-4"p>>',
        pageLength: 10,
        scrollX: true,
        columnDefs: [{ targets: [0, 1], orderable: false, searchable: false }],
        language: {
            info: '_START_ - _END_ of _TOTAL_',
            lengthMenu: 'Rows per page _MENU_',
            paginate: { previous: '<i class="bi bi-chevron-left"></i>', next: '<i class="bi bi-chevron-right"></i>' },
            processing: 'Loading archive...',
            emptyTable: 'No archived maintenance reports yet.'
        }
    });

    $('#maintenanceArchiveSearch').on('input', function () {
        table.search(this.value).draw();
    });

    $('#maintenanceArchiveRefreshBtn').on('click', function () {
        $('#maintenanceArchiveSearch').val('');
        table.search('').ajax.reload();
    });

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
    }

    function maintenanceItemPdfLabel(item) {
        const label = [
            String(item.item_type || 'item').toUpperCase(),
            item.description_name || '',
            item.control_no ? 'Control: ' + item.control_no : '',
            item.old_control_no ? 'Old: ' + item.old_control_no : '',
            item.brand_name ? 'Brand: ' + item.brand_name : '',
            item.serialno ? 'SN: ' + item.serialno : ''
        ].filter(Boolean).join(' | ');
        return label || ('Item #' + (item.id || ''));
    }

    function openMaintenanceExportPicker(id, exportUrl) {
        $.getJSON(actionUrl, { action: 'get', id }, function (res) {
            if (!res.success) {
                Swal.fire('Error', res.message || 'Unable to load maintenance items.', 'error');
                return;
            }
            const items = res.items || [];
            if (!items.length) {
                window.open(exportUrl, '_blank');
                return;
            }
            const html = `
                <div class="text-start" style="max-height:340px;overflow:auto;">
                    ${items.map(item => `
                        <label class="d-flex gap-2 align-items-start py-2 border-bottom" style="color:#1f2937;background:#fff;">
                            <input type="checkbox" class="form-check-input maintenance-export-item" value="${escapeHtml(item.id)}" checked>
                            <span style="font-size:.86rem;line-height:1.35;font-weight:700;color:#1f2937;">${escapeHtml(maintenanceItemPdfLabel(item))}</span>
                        </label>
                    `).join('')}
                </div>
            `;
            Swal.fire({
                title: 'Select Items for PDF',
                html,
                showCancelButton: true,
                confirmButtonText: 'Generate PDF',
                confirmButtonColor: '#3657f6',
                preConfirm: () => {
                    const ids = $('.maintenance-export-item:checked').map(function () { return this.value; }).get();
                    if (!ids.length) {
                        Swal.showValidationMessage('Select at least one item to include.');
                        return false;
                    }
                    return ids;
                }
            }).then(result => {
                if (!result.isConfirmed) return;
                const url = new URL(exportUrl, window.location.origin);
                url.searchParams.delete('item_filter');
                url.searchParams.set('item_ids', result.value.join(','));
                window.open(url.toString(), '_blank');
            });
        }).fail(function (xhr) {
            Swal.fire('Error', (xhr.responseJSON && xhr.responseJSON.message) || 'Unable to load maintenance items.', 'error');
        });
    }

    $(document).on('click', '.export-maintenance-pdf-btn', function () {
        const exportUrl = $(this).data('export-url');
        openMaintenanceExportPicker($(this).data('id'), exportUrl);
    });

    $(document).on('click', '.restore-maintenance-btn', function () {
        $.post(actionUrl, { action: 'restore', id: $(this).data('id') }, function (res) {
            Swal.fire(res.success ? 'Restored' : 'Error', res.message, res.success ? 'success' : 'error');
            table.ajax.reload(null, false);
        }, 'json').fail(function (xhr) {
            Swal.fire('Error', (xhr.responseJSON && xhr.responseJSON.message) || 'Unable to restore maintenance report.', 'error');
        });
    });

    $(document).on('click', '.permanent-delete-maintenance-btn', function () {
        const id = $(this).data('id');
        Swal.fire({ title: 'Permanently delete?', text: 'This cannot be undone.', icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33' }).then(result => {
            if (!result.isConfirmed) return;
            $.post(actionUrl, { action: 'permanent_delete', id }, function (res) {
                Swal.fire(res.success ? 'Deleted' : 'Error', res.message, res.success ? 'success' : 'error');
                table.ajax.reload(null, false);
            }, 'json').fail(function (xhr) {
                Swal.fire('Error', (xhr.responseJSON && xhr.responseJSON.message) || 'Unable to delete maintenance report.', 'error');
            });
        });
    });
});
</script>

<?php require_once ROOT_PATH . '/modules/layout/footer.php'; ?>
