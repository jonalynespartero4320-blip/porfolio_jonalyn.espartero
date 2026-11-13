<?php
$page_title = 'Equipment Request Archive';
require_once ROOT_PATH . '/modules/layout/header.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/functions.php';

require_login();
require_permission('ictd.request.view');
?>

<style>
  .request-shell { display: grid; gap: 1.05rem; }
  .request-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; }
  .request-eyebrow { color: #667085; font-size: .78rem; font-weight: 750; }
  .request-title { color: #1f2937; font-size: 1.35rem; font-weight: 850; margin: 0; }
  .request-toolbar { display: flex; justify-content: flex-end; gap: .65rem; flex-wrap: wrap; }
  .request-search { position: relative; min-width: min(340px, 100%); }
  .request-search i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #98a2b3; font-size: .85rem; }
  .request-search input { height: 38px; padding-left: 34px; border-radius: 4px; border-color: #eaecf0; font-size: .78rem; }
  .request-soft-btn { height: 38px; border: 1px solid #eaecf0; background: #fff; color: #344054; border-radius: 4px; padding: 0 .8rem; font-size: .78rem; font-weight: 650; }
  .request-panel, .request-items-card { border: 0; border-radius: 10px; background: rgba(255,255,255,.78); box-shadow: none; }
  .request-table-container { overflow: hidden; border-radius: 8px; background: rgba(255,255,255,.92); }
  .request-table { border-collapse: separate !important; border-spacing: 0 !important; font-size: .8rem; color: #1f2937; }
  .request-table thead th { white-space: nowrap; font-size: .72rem; color: #5f6672; font-weight: 760; background: #f3f4f6 !important; border: 0 !important; padding: .72rem .8rem !important; }
  .request-table tbody td { border: 0 !important; border-bottom: 1px solid #f2f4f7 !important; padding: .72rem .8rem !important; vertical-align: middle; white-space: nowrap; }
  .request-table tbody tr:nth-child(even) td { background: rgba(249,250,251,.72); }
  .request-table tbody tr:hover td, .request-table tbody tr.selected td { background: #eef4ff !important; }
  .request-actions { display: inline-flex; align-items: center; gap: .55rem; }
  .request-action-btn { border: 0; background: transparent; padding: 0; line-height: 1; font-size: .95rem; }
  .request-action-view { color: #16a085; }
  .request-action-restore { color: #16803d; }
  .request-action-delete { color: #e26060; }
  .request-table-card .row:first-child { display: none; }
  .request-table-card .row:last-child { margin: 0; padding: .9rem .25rem 0; align-items: center; color: #475467; font-size: .78rem; }
  .request-table-card .page-link { min-width: 34px; height: 34px; border-radius: 4px !important; display: inline-flex; align-items: center; justify-content: center; border-color: #eaecf0; color: #344054; font-size: .78rem; box-shadow: none; }
  .request-table-card .page-item.active .page-link { background: #3657f6; border-color: #3657f6; color: #fff; }
  .request-items-table { font-size: .8rem; }
  .request-items-table th { background: #f3f4f6 !important; color: #5f6672; font-size: .72rem; border: 0 !important; }
  @media (max-width: 767px) { .request-head { flex-direction: column; } .request-toolbar, .request-search, .request-soft-btn { width: 100%; } }
</style>

<div class="container-fluid py-3">
    <div class="request-shell">
        <div class="request-head">
            <div>
                <div class="request-eyebrow">Archive</div>
                <h2 class="request-title">Equipment Request Archive</h2>
                <div class="text-muted small">Review, restore, or permanently remove archived equipment and supply requests.</div>
            </div>
            <a href="<?= BASE_URL ?>/ictd/requests/equipment" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Back to Requests
            </a>
        </div>

        <div class="request-toolbar">
            <div class="request-search">
                <i class="bi bi-search"></i>
                <input type="search" class="form-control" id="requestArchiveSearch" placeholder="Search archived requests">
            </div>
            <button type="button" class="request-soft-btn" id="requestArchiveRefreshBtn" title="Refresh">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
        </div>

        <div class="card request-panel request-table-card">
            <div class="card-body p-0">
                <div class="request-table-container">
                    <div class="table-responsive">
                        <table id="equipmentRequestArchiveTable" class="table align-middle nowrap mb-0 request-table" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Action</th>
                                    <th>Date Requested</th>
                                    <th>Control No</th>
                                    <th>Funds</th>
                                    <th>Department</th>
                                    <th>Head</th>
                                    <th>Items</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Archived</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="card request-items-card">
            <div class="card-body">
                <div class="fw-bold mb-1">Items for Selected Request</div>
                <div class="text-muted small mb-2" id="selectedRequestLabel">Click a request row or View to load its items.</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0 request-items-table">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Qty</th>
                                <th>Description</th>
                                <th>Status</th>
                                <th>Reason</th>
                            </tr>
                        </thead>
                        <tbody id="requestItemsBody">
                            <tr><td colspan="5" class="text-center text-muted py-3">No request selected.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const actionUrl = '<?= BASE_URL ?>/ictd/requests/equipment-action';
    const table = $('#equipmentRequestArchiveTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: { url: '<?= BASE_URL ?>/ictd/requests/equipment-archive-data', type: 'POST' },
        order: [[9, 'desc']],
        pageLength: 12,
        lengthMenu: [[12, 25, 50, 100], [12, 25, 50, 100]],
        scrollX: true,
        dom: 'rt<"row align-items-center mt-3 px-3 pb-3"<"col-md-4"i><"col-md-4 text-center"l><"col-md-4"p>>',
        columnDefs: [{ targets: [0], orderable: false, searchable: false }]
    });

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
    }

    function loadItems(id) {
        $('#selectedRequestLabel').text('Items for request #' + id);
        $.getJSON(actionUrl, { action: 'items', request_id: id }, function (res) {
            const tbody = $('#requestItemsBody');
            tbody.empty();
            if (!res.success || !res.items || !res.items.length) {
                tbody.html('<tr><td colspan="5" class="text-center text-muted py-3">No items found.</td></tr>');
                return;
            }
            res.items.forEach(item => {
                const status = item.status || 'Pending';
                const badgeClass = status === 'Approved' ? 'success' : status === 'Rejected' ? 'danger' : status === 'In Review' ? 'info' : 'secondary';
                tbody.append(`
                    <tr>
                        <td><span class="badge bg-primary-subtle text-primary">${escapeHtml((item.item_type || 'equipment').toUpperCase())}</span></td>
                        <td>${escapeHtml(item.quantity)}</td>
                        <td>${escapeHtml(item.description)}</td>
                        <td><span class="badge text-bg-${badgeClass}">${escapeHtml(status)}</span></td>
                        <td>${escapeHtml(item.rejection_reason || '')}</td>
                    </tr>
                `);
            });
        });
    }

    $('#requestArchiveSearch').on('input', function () { table.search(this.value).draw(); });
    $('#requestArchiveRefreshBtn').on('click', function () {
        $('#requestArchiveSearch').val('');
        table.search('').ajax.reload();
    });

    $(document).on('click', '.view-request-btn', function (event) {
        event.stopPropagation();
        $('#equipmentRequestArchiveTable tbody tr').removeClass('selected');
        $(this).closest('tr').addClass('selected');
        loadItems($(this).data('id'));
    });

    $(document).on('click', '.restore-request-btn', function (event) {
        event.stopPropagation();
        const id = $(this).data('id');
        Swal.fire({
            title: 'Restore request?',
            text: 'This will return the request to the active request table.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#16803d',
            confirmButtonText: 'Restore'
        }).then(result => {
            if (!result.isConfirmed) return;
            $.post(actionUrl, { action: 'restore', id }, function (res) {
                Swal.fire(res.success ? 'Restored' : 'Error', res.message || 'Unable to restore request.', res.success ? 'success' : 'error');
                if (res.success) table.ajax.reload(null, false);
            }, 'json');
        });
    });

    $(document).on('click', '.delete-request-btn', function (event) {
        event.stopPropagation();
        const id = $(this).data('id');
        Swal.fire({
            title: 'Permanently delete?',
            text: 'This will remove the archived request and its items forever.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Delete permanently'
        }).then(result => {
            if (!result.isConfirmed) return;
            $.post(actionUrl, { action: 'delete', id }, function (res) {
                Swal.fire(res.success ? 'Deleted' : 'Error', res.message || 'Unable to delete request.', res.success ? 'success' : 'error');
                if (res.success) table.ajax.reload(null, false);
            }, 'json');
        });
    });

    $('#equipmentRequestArchiveTable tbody').on('click', 'tr', function () {
        const button = $(this).find('.view-request-btn');
        if (!button.length) return;
        $('#equipmentRequestArchiveTable tbody tr').removeClass('selected');
        $(this).addClass('selected');
        loadItems(button.data('id'));
    });
});
</script>

<?php require_once ROOT_PATH . '/modules/layout/footer.php'; ?>
