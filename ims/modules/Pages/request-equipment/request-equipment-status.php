<?php
$page_title = 'Equipment Request Status';
require_once ROOT_PATH . '/modules/layout/header.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/functions.php';

require_login();
require_any_permission(['ictd.request.manage', 'ictd.request.view']);

if (!is_admin() && !can('ictd.request.manage')) {
    http_response_code(403);
    echo '<div class="container-fluid py-4"><div class="card border-0 shadow-sm"><div class="card-body">Access denied. Only administrators or allowed personnel can manage equipment request status.</div></div></div>';
    require_once ROOT_PATH . '/modules/layout/footer.php';
    exit;
}
?>

<style>
  .request-status-shell { display: grid; gap: 1.05rem; }
  .request-status-head { display: flex; justify-content: space-between; gap: 1rem; align-items: flex-start; }
  .request-status-eyebrow { color: #667085; font-size: .78rem; font-weight: 750; }
  .request-status-title { margin: 0; color: #1f2937; font-size: 1.35rem; font-weight: 850; }
  .request-status-filters { display: flex; gap: .35rem; flex-wrap: wrap; border-bottom: 1px solid #eaecf0; }
  .request-status-filters .btn { border: 0; border-bottom: 2px solid transparent; border-radius: 0; min-height: 38px; font-size: .78rem; background: transparent; color: #475467; }
  .request-status-filters .btn.active { border-bottom-color: #3657f6; color: #3657f6; font-weight: 750; }
  .request-status-toolbar { display: flex; justify-content: flex-end; gap: .65rem; flex-wrap: wrap; }
  .request-status-search { position: relative; min-width: min(340px, 100%); }
  .request-status-search i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #98a2b3; }
  .request-status-search input { height: 38px; padding-left: 34px; border-radius: 4px; border-color: #eaecf0; font-size: .78rem; }
  .request-status-panel { border: 0; border-radius: 10px; background: rgba(255,255,255,.78); box-shadow: none; }
  .request-status-table { font-size: .8rem; }
  .request-status-table thead th { white-space: nowrap; background: #f3f4f6 !important; border: 0 !important; color: #5f6672; font-size: .72rem; padding: .72rem .8rem !important; }
  .request-status-table tbody td { border: 0 !important; border-bottom: 1px solid #f2f4f7 !important; padding: .72rem .8rem !important; white-space: nowrap; vertical-align: middle; }
  .request-status-table tbody tr.selected td { background: #eef4ff !important; }
  .request-status-panel .row:first-child { display: none; }
  .request-status-panel .row:last-child { margin: 0; padding: .9rem .25rem 0; align-items: center; color: #475467; font-size: .78rem; }
  .request-status-panel .page-link { min-width: 34px; height: 34px; border-radius: 4px !important; display: inline-flex; align-items: center; justify-content: center; border-color: #eaecf0; color: #344054; font-size: .78rem; box-shadow: none; }
  .request-status-panel .page-item.active .page-link { background: #3657f6; border-color: #3657f6; color: #fff; }
  .status-items-panel { border: 0; border-radius: 10px; background: rgba(255,255,255,.78); box-shadow: none; }
  .status-items-table { font-size: .8rem; }
  .status-items-table th { background: #f3f4f6 !important; color: #5f6672; font-size: .72rem; border: 0 !important; }
  .status-items-table .form-control { min-width: 110px; height: 32px; font-size: .78rem; }
  .status-items-table .reason-input { min-width: 220px; }
  .request-action-btn { border: 0; background: transparent; color: #3657f6; font-weight: 750; padding: 0; }
  @media (max-width: 767px) {
    .request-status-head { flex-direction: column; }
    .request-status-toolbar, .request-status-search { width: 100%; }
  }
</style>

<div class="container-fluid py-3">
    <div class="request-status-shell">
        <div class="request-status-head">
            <div>
                <div class="request-status-eyebrow">Requests</div>
                <h2 class="request-status-title">Equipment Request Status</h2>
                <div class="text-muted small">Review requested equipment and supplies, set estimated amounts, and approve or reject each line item.</div>
            </div>
            <a href="<?= BASE_URL ?>/ictd/requests/equipment" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Back to Requests
            </a>
        </div>

        <div class="request-status-filters">
            <button class="btn filter-status active" data-status="">All (<span id="count-all">0</span>)</button>
            <button class="btn filter-status" data-status="Pending">Pending (<span id="count-pending">0</span>)</button>
            <button class="btn filter-status" data-status="Approved">Approved (<span id="count-approved">0</span>)</button>
            <button class="btn filter-status" data-status="Rejected">Rejected (<span id="count-rejected">0</span>)</button>
            <button class="btn filter-status" data-status="Cancelled">Cancelled (<span id="count-cancelled">0</span>)</button>
            <button class="btn filter-status" data-status="In Review">In Review (<span id="count-inreview">0</span>)</button>
        </div>

        <div class="request-status-toolbar">
            <div class="request-status-search">
                <i class="bi bi-search"></i>
                <input type="search" class="form-control" id="statusSearch" placeholder="Search request status">
            </div>
        </div>

        <div class="card request-status-panel">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="requestStatusTable" class="table align-middle nowrap mb-0 request-status-table" style="width:100%">
                        <thead>
                            <tr>
                                <th>Control No</th>
                                <th>Date Requested</th>
                                <th>Department Head</th>
                                <th>Department</th>
                                <th>Budget Amount</th>
                                <th>Total Amount</th>
                                <th>Quarter</th>
                                <th>Budget Balance</th>
                                <th>Status</th>
                                <th>Reason</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card status-items-panel" id="itemsSection">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                    <div>
                        <div class="fw-bold">Request Items</div>
                        <div class="text-muted small" id="itemsLabel">Select a request to review its items.</div>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" id="saveItems" disabled>
                        <i class="bi bi-save"></i> Save All Items
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0 status-items-table">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Description</th>
                                <th>Qty</th>
                                <th>Est. Amount</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Rejection Reason</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="itemsBody">
                            <tr><td colspan="8" class="text-center text-muted py-3">No request selected.</td></tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="4" class="text-end">Grand Total</th>
                                <th id="grandTotal">0.00</th>
                                <th colspan="2">Remaining Budget</th>
                                <th id="budgetDisplay">0.00</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const dataUrl = '<?= BASE_URL ?>/ictd/requests/equipment-status-data';
    const actionUrl = '<?= BASE_URL ?>/ictd/requests/equipment-status-action';
    let selectedRequestId = null;
    let availableBudget = 0;
    let selectedStatus = '';

    const table = $('#requestStatusTable').DataTable({
        ajax: {
            url: dataUrl,
            type: 'POST',
            data: d => { d.status = selectedStatus; },
            dataSrc: function (json) {
                updateCounts(json.counts || {});
                return json.data || [];
            }
        },
        pageLength: 12,
        scrollX: true,
        dom: 'rt<"row align-items-center mt-3 px-3 pb-3"<"col-md-4"i><"col-md-4 text-center"l><"col-md-4"p>>',
        columnDefs: [{ targets: [10], orderable: false, searchable: false }]
    });

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
    }

    function updateCounts(counts) {
        $('#count-all').text(counts.all || 0);
        $('#count-pending').text(counts.Pending || 0);
        $('#count-approved').text(counts.Approved || 0);
        $('#count-rejected').text(counts.Rejected || 0);
        $('#count-cancelled').text(counts.Cancelled || 0);
        $('#count-inreview').text(counts['In Review'] || 0);
    }

    function statusBadge(status) {
        const cls = status === 'Approved' ? 'success' : status === 'Rejected' ? 'danger' : status === 'Cancelled' ? 'dark' : status === 'In Review' ? 'info' : 'secondary';
        return `<span class="badge text-bg-${cls} item-status">${escapeHtml(status || 'Pending')}</span>`;
    }

    function loadItems(requestId, balance, requestStatus = '') {
        selectedRequestId = requestId;
        availableBudget = parseFloat(balance || 0);
        const isCancelled = requestStatus === 'Cancelled';
        $('#saveItems').prop('disabled', isCancelled);
        $('#itemsLabel').text('Reviewing request #' + requestId);
        $.getJSON(actionUrl, { action: 'items', request_id: requestId }, function (res) {
            const tbody = $('#itemsBody');
            tbody.empty();
            if (!res.success || !res.items || !res.items.length) {
                tbody.html('<tr><td colspan="8" class="text-center text-muted py-3">No items found.</td></tr>');
                updateTotals();
                return;
            }
            res.items.forEach(item => {
                const qty = parseFloat(item.quantity || 0);
                const est = parseFloat(item.estimated_amount || 0);
                const disabled = isCancelled ? 'disabled' : '';
                tbody.append(`
                    <tr data-item-id="${escapeHtml(item.id)}" class="${item.status === 'Approved' ? 'approved' : item.status === 'Rejected' ? 'rejected' : ''}">
                        <td><span class="badge bg-primary-subtle text-primary">${escapeHtml((item.item_type || 'equipment').toUpperCase())}</span></td>
                        <td>${escapeHtml(item.description || '')}</td>
                        <td class="item-qty">${qty}</td>
                        <td><input type="number" min="0" step="0.01" class="form-control est-amount" value="${est.toFixed(2)}" ${disabled}></td>
                        <td class="item-total">${(qty * est).toFixed(2)}</td>
                        <td>${statusBadge(item.status || 'Pending')}</td>
                        <td><input type="text" class="form-control reason-input" value="${escapeHtml(item.rejection_reason || '')}" placeholder="Required when rejected" ${disabled}></td>
                        <td>
                            <button type="button" class="btn btn-outline-success btn-sm approve-item" ${disabled}>Approve</button>
                            <button type="button" class="btn btn-outline-danger btn-sm reject-item" ${disabled}>Reject</button>
                        </td>
                    </tr>
                `);
            });
            updateTotals();
        });
    }

    function updateTotals() {
        let total = 0;
        let used = 0;
        $('#itemsBody tr').each(function () {
            const qty = parseFloat($(this).find('.item-qty').text()) || 0;
            const est = parseFloat($(this).find('.est-amount').val()) || 0;
            const lineTotal = qty * est;
            $(this).find('.item-total').text(lineTotal.toFixed(2));
            total += lineTotal;
            if ($(this).find('.item-status').text() === 'Approved') {
                used += lineTotal;
            }
        });
        $('#grandTotal').text(total.toFixed(2));
        $('#budgetDisplay').text((availableBudget - used).toFixed(2));
    }

    $('.filter-status').on('click', function () {
        selectedStatus = $(this).data('status') || '';
        $('.filter-status').removeClass('active');
        $(this).addClass('active');
        table.ajax.reload();
    });

    $('#statusSearch').on('input', function () {
        table.search(this.value).draw();
    });

    $(document).on('click', '.review-request-btn', function () {
        $('#requestStatusTable tbody tr').removeClass('selected');
        $(this).closest('tr').addClass('selected');
        loadItems($(this).data('id'), $(this).data('balance'), $(this).data('status'));
    });

    $(document).on('input', '.est-amount', updateTotals);

    $(document).on('click', '.approve-item', function () {
        const row = $(this).closest('tr');
        const payload = {
            action: 'update_item',
            request_id: selectedRequestId,
            item_id: row.data('item-id'),
            status: 'Approved',
            estimated_amount: row.find('.est-amount').val()
        };
        $.post(actionUrl, payload, function (res) {
            if (!res.success) return Swal.fire('Error', res.message || 'Unable to update item.', 'error');
            row.find('.item-status').replaceWith(statusBadge('Approved'));
            row.find('.reason-input').val('');
            row.toggleClass('approved', true).toggleClass('rejected', false);
            updateTotals();
            table.ajax.reload(null, false);
        }, 'json');
    });

    $(document).on('click', '.reject-item', function () {
        const row = $(this).closest('tr');
        Swal.fire({
            title: 'Reject item?',
            input: 'textarea',
            inputLabel: 'Reason for rejection',
            inputPlaceholder: 'Enter the reason so the requester can see it',
            inputValue: row.find('.reason-input').val(),
            inputAttributes: { maxlength: 1000 },
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Reject Item',
            inputValidator: value => !value.trim() ? 'Please enter a reason.' : undefined
        }).then(result => {
            if (!result.isConfirmed) return;
            const payload = {
                action: 'update_item',
                request_id: selectedRequestId,
                item_id: row.data('item-id'),
                status: 'Rejected',
                estimated_amount: row.find('.est-amount').val(),
                rejection_reason: result.value
            };
            $.post(actionUrl, payload, function (res) {
                if (!res.success) return Swal.fire('Error', res.message || 'Unable to update item.', 'error');
                row.find('.item-status').replaceWith(statusBadge('Rejected'));
                row.find('.reason-input').val(result.value);
                row.toggleClass('approved', false).toggleClass('rejected', true);
                updateTotals();
                table.ajax.reload(null, false);
            }, 'json');
        });
    });

    $('#saveItems').on('click', function () {
        if (!selectedRequestId) return;
        const items = $('#itemsBody tr[data-item-id]').map(function () {
            return {
                item_id: $(this).data('item-id'),
                estimated_amount: $(this).find('.est-amount').val(),
                status: $(this).find('.item-status').text(),
                rejection_reason: $(this).find('.reason-input').val()
            };
        }).get();
        $.post(actionUrl, { action: 'save_items', request_id: selectedRequestId, items: JSON.stringify(items) }, function (res) {
            Swal.fire(res.success ? 'Saved' : 'Error', res.message || 'Unable to save items.', res.success ? 'success' : 'error');
            if (res.success) table.ajax.reload(null, false);
        }, 'json');
    });
});
</script>

<?php require_once ROOT_PATH . '/modules/layout/footer.php'; ?>
