<?php
$page_title = 'Equipment Requests';
require_once ROOT_PATH . '/modules/layout/header.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/functions.php';

require_login();
require_permission('ictd.request.view');
?>

<style>
  .request-shell { display: grid; gap: 1.05rem; }
  .request-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; }
  .request-eyebrow { color: #667085; font-size: .78rem; font-weight: 750; letter-spacing: .01em; }
  .request-title { color: #1f2937; font-size: 1.35rem; font-weight: 850; margin: 0; }
  .request-head-actions { display: flex; align-items: center; gap: .65rem; flex-wrap: wrap; }
  .request-head-actions .btn { border-radius: 4px; min-height: 38px; padding-inline: 1rem; }
  .request-toolbar { display: flex; align-items: center; justify-content: flex-end; gap: .65rem; flex-wrap: wrap; }
  .request-search { position: relative; min-width: min(340px, 100%); }
  .request-search i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #98a2b3; font-size: .85rem; }
  .request-search input { height: 38px; padding-left: 34px; border-radius: 4px; border-color: #eaecf0; font-size: .78rem; }
  .request-soft-btn { height: 38px; border: 1px solid #eaecf0; background: #fff; color: #344054; border-radius: 4px; padding: 0 .8rem; font-size: .78rem; font-weight: 650; display: inline-flex; align-items: center; justify-content: center; gap: .35rem; }
  .request-panel { border: 0; border-radius: 10px; background: rgba(255,255,255,.78); box-shadow: none; }
  .request-table-container { overflow: hidden; border-radius: 8px; background: rgba(255,255,255,.92); }
  .request-table { border-collapse: separate !important; border-spacing: 0 !important; font-size: .8rem; color: #1f2937; }
  .request-table thead th { white-space: nowrap; font-size: .72rem; color: #5f6672; font-weight: 760; background: #f3f4f6 !important; border: 0 !important; padding: .72rem .8rem !important; }
  .request-table tbody td { border: 0 !important; border-bottom: 1px solid #f2f4f7 !important; padding: .72rem .8rem !important; vertical-align: middle; white-space: nowrap; }
  .request-table tbody tr:nth-child(even) td { background: rgba(249,250,251,.72); }
  .request-table tbody tr:hover td { background: #f8fbff; }
  .request-table tbody tr.selected td { background: #eef4ff !important; }
  .request-actions { display: inline-flex; align-items: center; gap: .55rem; }
  .request-action-btn { border: 0; background: transparent; padding: 0; line-height: 1; font-size: .95rem; }
  .request-action-view { color: #16a085; }
  .request-action-edit { color: #1f9bb4; }
  .request-action-delete { color: #e26060; }
  .request-action-archive { color: #667085; }
  .request-table-card .row:first-child { display: none; }
  .request-table-card .row:last-child { margin: 0; padding: .9rem .25rem 0; align-items: center; color: #475467; font-size: .78rem; }
  .request-table-card .dataTables_paginate .pagination { gap: .35rem; margin: 0; justify-content: flex-end; }
  .request-table-card .page-link { min-width: 34px; height: 34px; border-radius: 4px !important; display: inline-flex; align-items: center; justify-content: center; border-color: #eaecf0; color: #344054; font-size: .78rem; box-shadow: none; }
  .request-table-card .page-item.active .page-link { background: #3657f6; border-color: #3657f6; color: #fff; }
  .request-items-card { border: 0; border-radius: 10px; background: rgba(255,255,255,.78); box-shadow: none; }
  .request-items-table { font-size: .8rem; }
  .request-items-table th { background: #f3f4f6 !important; color: #5f6672; font-size: .72rem; border: 0 !important; }
  .request-item-row { display: grid; grid-template-columns: 145px 110px minmax(240px, 1fr) 38px; gap: .5rem; margin-bottom: .55rem; align-items: center; }
  .request-item-row .form-control { height: 38px; font-size: .82rem; }
  .request-remove-row { width: 38px; height: 38px; border-radius: 4px; }
  @media (max-width: 767px) {
    .request-head { flex-direction: column; }
    .request-head-actions, .request-toolbar, .request-search, .request-soft-btn { width: 100%; }
    .request-item-row { grid-template-columns: 1fr; }
    .request-remove-row { width: 100%; }
  }
</style>

<div class="container-fluid py-3">
    <div class="request-shell">
        <div class="request-head">
            <div>
                <div class="request-eyebrow">Requests</div>
                <h2 class="request-title">Equipment Requests</h2>
                <div class="text-muted small">Create, update, and review requested equipment items by department.</div>
            </div>
            <div class="request-head-actions">
                <button type="button" class="btn btn-primary btn-sm" id="openRequestModal">
                    <i class="bi bi-plus-lg"></i> New Request
                </button>
            </div>
        </div>

        <div class="request-toolbar">
            <div class="request-search">
                <i class="bi bi-search"></i>
                <input type="search" class="form-control" id="requestSearch" placeholder="Search equipment requests">
            </div>
            <button type="button" class="request-soft-btn" id="requestRefreshBtn">
                <i class="bi bi-sliders"></i>
            </button>
        </div>

        <div class="card request-panel request-table-card">
            <div class="card-body p-0">
                <div class="request-table-container">
                    <div class="table-responsive">
                        <table id="equipmentRequestTable" class="table align-middle nowrap mb-0 request-table" style="width:100%">
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
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                    <div>
                        <div class="fw-bold">Items for Selected Request</div>
                        <div class="text-muted small" id="selectedRequestLabel">Click a request row or View to load its items.</div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0 request-items-table">
                        <thead>
                            <tr>
                                <th style="width:110px;">Type</th>
                                <th style="width:90px;">Qty</th>
                                <th>Description</th>
                                <th style="width:130px;">Status</th>
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

<div class="modal fade" id="equipmentRequestModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title fw-bold" id="requestModalTitle">New Equipment Request</h5>
                    <div class="text-muted small">Department head fills automatically from the selected department.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="equipmentRequestForm">
                <input type="hidden" id="request_id" name="id">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Date Requested *</label>
                            <input type="date" class="form-control" id="date_requested" name="date_requested" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Source of Funds *</label>
                            <select class="form-select" id="fund_id" name="fund_id" required></select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Budget Amount</label>
                            <input type="number" class="form-control" id="budget_amount" name="budget_amount" min="0" step="0.01" value="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Department *</label>
                            <select class="form-select" id="department_id" name="department_id" required></select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Department Head *</label>
                            <input type="text" class="form-control" id="department_head" name="department_head" readonly required>
                        </div>
                    </div>

                    <hr>

                    <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                        <label class="form-label fw-semibold mb-0">Items</label>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="addRequestItem">
                            <i class="bi bi-plus-lg"></i> Add Item
                        </button>
                    </div>
                    <div id="requestItemsContainer"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const actionUrl = '<?= BASE_URL ?>/ictd/requests/equipment-action';
    let optionsCache = { funds: [], departments: [] };

    const requestModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('equipmentRequestModal'));
    const table = $('#equipmentRequestTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: { url: '<?= BASE_URL ?>/ictd/requests/equipment-data', type: 'POST' },
        order: [[8, 'desc']],
        pageLength: 12,
        lengthMenu: [[12, 25, 50, 100], [12, 25, 50, 100]],
        scrollX: true,
        dom: 'rt<"row align-items-center mt-3 px-3 pb-3"<"col-md-4"i><"col-md-4 text-center"l><"col-md-4"p>>',
        columnDefs: [{ targets: [0], orderable: false, searchable: false }]
    });

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
    }

    function fillSelect(selector, items, placeholder) {
        const html = `<option value="">${escapeHtml(placeholder)}</option>` + (items || []).map(item => {
            const head = item.department_head ? ` data-head="${escapeHtml(item.department_head)}"` : '';
            return `<option value="${escapeHtml(item.id)}"${head}>${escapeHtml(item.text)}</option>`;
        }).join('');
        $(selector).html(html);
    }

    function loadOptions(callback) {
        $.getJSON(actionUrl, { action: 'options' }, function (res) {
            if (!res.success) return;
            optionsCache = res.data || optionsCache;
            fillSelect('#fund_id', optionsCache.funds, 'Select funds');
            fillSelect('#department_id', optionsCache.departments, 'Select department');
            if (typeof callback === 'function') callback();
        });
    }

    function itemRow(item = {}) {
        return `
            <div class="request-item-row">
                <select class="form-select request-item-type">
                    <option value="equipment" ${(item.item_type || 'equipment') === 'equipment' ? 'selected' : ''}>Equipment</option>
                    <option value="supply" ${(item.item_type || '') === 'supply' ? 'selected' : ''}>Supplies</option>
                </select>
                <input type="number" class="form-control request-item-quantity" min="1" value="${escapeHtml(item.quantity || 1)}" placeholder="Qty">
                <input type="text" class="form-control request-item-description" value="${escapeHtml(item.description || '')}" placeholder="Description">
                <button type="button" class="btn btn-outline-danger request-remove-row remove-request-item"><i class="bi bi-x-lg"></i></button>
            </div>
        `;
    }

    function addItemRow(item = {}) {
        $('#requestItemsContainer').append(itemRow(item));
    }

    function resetForm() {
        $('#equipmentRequestForm')[0].reset();
        $('#request_id').val('');
        $('#requestModalTitle').text('New Equipment Request');
        $('#date_requested').val(new Date().toISOString().slice(0, 10));
        $('#budget_amount').val(0);
        $('#department_head').val('');
        $('#requestItemsContainer').empty();
        addItemRow();
    }

    function collectItems() {
        return $('#requestItemsContainer .request-item-row').map(function () {
            return {
                item_type: $(this).find('.request-item-type').val(),
                quantity: $(this).find('.request-item-quantity').val(),
                description: $(this).find('.request-item-description').val()
            };
        }).get();
    }

    function loadItems(id, label) {
        $('#selectedRequestLabel').text(label || 'Loading request items...');
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

    $('#requestSearch').on('input', function () {
        table.search(this.value).draw();
    });

    $('#requestRefreshBtn').on('click', function () {
        $('#requestSearch').val('');
        table.search('').ajax.reload();
    });

    $('#openRequestModal').on('click', function () {
        loadOptions(function () {
            resetForm();
            requestModal.show();
        });
    });

    $('#department_id').on('change', function () {
        $('#department_head').val($(this).find(':selected').data('head') || '');
    });

    $('#addRequestItem').on('click', function () {
        addItemRow();
    });

    $(document).on('click', '.remove-request-item', function () {
        if ($('#requestItemsContainer .request-item-row').length <= 1) {
            $(this).closest('.request-item-row').find('input').val('');
            $(this).closest('.request-item-row').find('.request-item-quantity').val(1);
            return;
        }
        $(this).closest('.request-item-row').remove();
    });

    $('#equipmentRequestForm').on('submit', function (event) {
        event.preventDefault();
        const payload = {
            action: 'save',
            id: $('#request_id').val(),
            date_requested: $('#date_requested').val(),
            fund_id: $('#fund_id').val(),
            department_id: $('#department_id').val(),
            department_head: $('#department_head').val(),
            budget_amount: $('#budget_amount').val(),
            items: JSON.stringify(collectItems())
        };
        $.post(actionUrl, payload, function (res) {
            Swal.fire(res.success ? 'Saved' : 'Error', res.message || 'Unable to save request.', res.success ? 'success' : 'error');
            if (res.success) {
                requestModal.hide();
                table.ajax.reload(null, false);
            }
        }, 'json').fail(xhr => {
            Swal.fire('Error', (xhr.responseJSON && xhr.responseJSON.message) || 'Unable to save request.', 'error');
        });
    });

    $(document).on('click', '.edit-request-btn', function (event) {
        event.stopPropagation();
        const id = $(this).data('id');
        loadOptions(function () {
            $.getJSON(actionUrl, { action: 'get', id }, function (res) {
                if (!res.success) return Swal.fire('Error', res.message || 'Request not found.', 'error');
                resetForm();
                $('#requestModalTitle').text('Edit Equipment Request');
                $('#request_id').val(res.data.id);
                $('#date_requested').val(res.data.date_requested);
                $('#fund_id').val(res.data.fund_id);
                $('#budget_amount').val(res.data.budget_amount || 0);
                $('#department_id').val(res.data.department_id);
                $('#department_head').val(res.data.department_head || $('#department_id').find(':selected').data('head') || '');
                $('#requestItemsContainer').empty();
                (res.items || []).forEach(addItemRow);
                if (!$('#requestItemsContainer .request-item-row').length) addItemRow();
                requestModal.show();
            });
        });
    });

    $(document).on('click', '.view-request-btn', function (event) {
        event.stopPropagation();
        const row = $(this).closest('tr');
        $('#equipmentRequestTable tbody tr').removeClass('selected');
        row.addClass('selected');
        loadItems($(this).data('id'), 'Items for request #' + $(this).data('id'));
    });

    $(document).on('click', '.cancel-request-btn', function (event) {
        event.stopPropagation();
        const id = $(this).data('id');
        Swal.fire({
            title: 'Cancel request?',
            input: 'textarea',
            inputLabel: 'Reason for cancellation',
            inputPlaceholder: 'Enter the reason for cancelling this request',
            inputAttributes: { maxlength: 1000 },
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Cancel Request',
            inputValidator: value => !value.trim() ? 'Please enter a reason.' : undefined
        }).then(result => {
            if (!result.isConfirmed) return;
            $.post(actionUrl, { action: 'cancel', id, reason: result.value }, function (res) {
                Swal.fire(res.success ? 'Cancelled' : 'Error', res.message || 'Unable to cancel request.', res.success ? 'success' : 'error');
                if (res.success) {
                    $('#requestItemsBody').html('<tr><td colspan="5" class="text-center text-muted py-3">No request selected.</td></tr>');
                    $('#selectedRequestLabel').text('Click a request row or View to load its items.');
                    table.ajax.reload(null, false);
                }
            }, 'json');
        });
    });

    $(document).on('click', '.archive-request-btn', function (event) {
        event.stopPropagation();
        const id = $(this).data('id');
        Swal.fire({
            title: 'Archive request?',
            text: 'This will move the request to the archive.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3657f6',
            confirmButtonText: 'Archive'
        }).then(result => {
            if (!result.isConfirmed) return;
            $.post(actionUrl, { action: 'archive', id }, function (res) {
                Swal.fire(res.success ? 'Archived' : 'Error', res.message || 'Unable to archive request.', res.success ? 'success' : 'error');
                if (res.success) {
                    $('#requestItemsBody').html('<tr><td colspan="5" class="text-center text-muted py-3">No request selected.</td></tr>');
                    $('#selectedRequestLabel').text('Click a request row or View to load its items.');
                    table.ajax.reload(null, false);
                }
            }, 'json');
        });
    });

    $('#equipmentRequestTable tbody').on('click', 'tr', function () {
        const button = $(this).find('.view-request-btn');
        if (!button.length) return;
        $('#equipmentRequestTable tbody tr').removeClass('selected');
        $(this).addClass('selected');
        loadItems(button.data('id'), 'Items for request #' + button.data('id'));
    });
});
</script>

<?php require_once ROOT_PATH . '/modules/layout/footer.php'; ?>
