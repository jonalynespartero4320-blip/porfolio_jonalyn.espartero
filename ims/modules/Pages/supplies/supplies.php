<?php
$page_title = 'Supplies Records';
require_once ROOT_PATH . '/modules/layout/header.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/functions.php';

require_login();
require_permission('ictd.supplies.view');

$requiredTables = ['other_equipment', 'list_description', 'list_brand'];
$missingTables = array_values(array_filter($requiredTables, static fn(string $table): bool => !db_table_exists($pdo, $table)));

$canCreateSupply = can('ictd.supplies.create');
$canEditSupply = can('ictd.supplies.edit');
$canDeleteSupply = can('ictd.supplies.delete');
?>

<style>
  .supplies-shell { display: grid; gap: 1.05rem; }
  .supplies-page-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; }
  .supplies-eyebrow { color: #667085; font-size: .78rem; font-weight: 750; letter-spacing: .01em; }
  .supplies-title { color: #1f2937; font-size: 1.35rem; font-weight: 850; margin: 0; }
  .supplies-head-actions { display: flex; align-items: center; gap: .65rem; flex-wrap: wrap; }
  .supplies-head-actions .btn { border-radius: 4px; min-height: 38px; padding-inline: 1rem; }
  .supplies-head-actions .btn-primary { background: #3657f6; border-color: #3657f6; }
  .supplies-toolbar { display: flex; align-items: center; justify-content: flex-end; gap: .65rem; flex-wrap: wrap; }
  .supplies-search { position: relative; min-width: min(320px, 100%); }
  .supplies-search i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #98a2b3; font-size: .85rem; }
  .supplies-search input { height: 38px; padding-left: 34px; border-radius: 4px; border-color: #eaecf0; font-size: .78rem; }
  .supplies-filter { height: 38px; min-width: 150px; border-radius: 4px; border-color: #eaecf0; font-size: .78rem; color: #667085; }
  .supplies-soft-btn { height: 38px; border: 1px solid #eaecf0; background: #fff; color: #344054; border-radius: 4px; padding: 0 .8rem; font-size: .78rem; font-weight: 650; }
  .supplies-panel { border: 0; border-radius: 10px; background: rgba(255,255,255,.78); box-shadow: none; }
  .supplies-table-container { overflow: hidden; border-radius: 8px; background: rgba(255,255,255,.92); }
  .supplies-table { border-collapse: separate !important; border-spacing: 0 !important; font-size: .8rem; color: #1f2937; }
  .supplies-table thead th { white-space: nowrap; font-size: .72rem; color: #5f6672; font-weight: 760; background: #f3f4f6 !important; border: 0 !important; padding: .72rem .8rem !important; }
  .supplies-table tbody td { border: 0 !important; border-bottom: 1px solid #f2f4f7 !important; padding: .72rem .8rem !important; vertical-align: middle; white-space: nowrap; }
  .supplies-table tbody tr:nth-child(even) td { background: rgba(249,250,251,.72); }
  .supplies-table tbody tr:hover td { background: #f8fbff; }
  .supplies-table tbody tr.supplies-row-assigned td { background: linear-gradient(90deg, rgba(25,140,54,.13), rgba(25,140,54,.04)) !important; border-bottom-color: rgba(25,140,54,.18) !important;}
  .supplies-code { font-weight: 850; color: #1f2937; }
  .supplies-assigned-note { display: inline-flex; align-items: center; gap: .25rem; margin-left: .45rem; color: #3657f6; font-size: .68rem; font-weight: 850; }
  .supplies-badge { border-radius: 999px; padding: .28rem .55rem; font-weight: 750; font-size: .72rem; }
  .supplies-inline-status {
    min-width: 138px;
    height: 30px;
    border-radius: 999px;
    border-color: #d7f3df;
    background-color: #f6fff8;
    color: #344054;
    font-size: .74rem;
    font-weight: 750;
    padding-top: .2rem;
    padding-bottom: .2rem;
  }
  .supplies-actions { display: inline-flex; align-items: center; gap: .55rem; }
  .supplies-action-btn { border: 0; background: transparent; padding: 0; line-height: 1; font-size: .95rem; }
  .supplies-action-view { color: #16a085; }
  .supplies-action-edit { color: #1f9bb4; }
  .supplies-action-delete { color: #e26060; }
  .supplies-empty { border-radius: 20px; background: rgba(239, 68, 68, .08); border: 1px solid rgba(239, 68, 68, .12); padding: 1rem 1.1rem;
  }
  .supplies-modal { overflow-y: auto !important; }
  .supplies-modal .modal-dialog {
    width: min(920px, calc(100vw - 24px));
    max-width: min(920px, calc(100vw - 24px));
    height: calc(100dvh - 24px);
    max-height: calc(100dvh - 24px);
    margin: 12px auto;
    display: flex;
    align-items: stretch;
  }
  .supplies-modal .modal-content { height: 100%; min-height: 0; display: flex; flex-direction: column; overflow: hidden; border-radius: 18px;
  }
  .supplies-modal .modal-body { flex: 1 1 auto; min-height: 0 !important; max-height: calc(100dvh - 155px) !important; overflow-y: auto !important; overflow-x: hidden !important; -webkit-overflow-scrolling: touch;
  }
  .supplies-form-section { padding: .9rem; border: 1px solid #edf0f5; border-radius: 12px; background: #fbfcff; margin-bottom: .9rem; }
  .supplies-batch-meta { padding: .9rem; border: 1px solid #e7eefc; border-radius: 12px; background: #f8fbff; margin-bottom: .9rem; }
  .supplies-batch-meta-title { color: #263241; font-size: .82rem; font-weight: 900; letter-spacing: .02em; text-transform: uppercase; margin-bottom: .75rem; }
  .supplies-batch-row { position: relative; border: 1px solid #e7eefc; border-radius: 12px; background: #fff; padding: 2.7rem 1rem 1rem; margin-bottom: .9rem; }
  .supplies-batch-row + .supplies-batch-row { margin-top: .9rem; }
  .supplies-batch-remove,
  .supplies-batch-duplicate {
    position: absolute;
    top: .7rem;
    width: 28px;
    height: 28px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .28rem;
    border: 1px solid #e4e7ec;
    border-radius: 4px;
    background: #fff;
    padding: 0;
    font-size: .74rem;
    font-weight: 800;
    line-height: 1;
  }
  .supplies-batch-remove { right: .85rem; color: #d92d20; border-color: #ffd6d6; background: #fff7f7; }
  .supplies-batch-duplicate { right: 3rem; color: #1f9bb4; border-color: #d5f3f8; background: #f5fcfd; }
  .supplies-batch-remove:hover { background: #ffecec; }
  .supplies-batch-duplicate:hover { background: #eefdff; }
  .supplies-form-section-title {
    display: flex;
    align-items: center;
    gap: .45rem;
    margin-bottom: .75rem;
    color: #263241;
    font-size: .82rem;
    font-weight: 900;
    letter-spacing: .02em;
    text-transform: uppercase;
  }
  .supplies-form-section-title i { color: #3657f6; }
  .supplies-detail-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem 1rem; }
  .supplies-detail-item { padding: .75rem; border-radius: 10px; background: #f9fafb; }
  .supplies-detail-label { color: #667085; font-size: .72rem; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; }
  .supplies-detail-value { margin-top: .25rem; color: #1f2937; font-weight: 650; }
  .supplies-status-list { display: grid; gap: .6rem; }
  .supplies-status-item { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .7rem .8rem; border: 1px solid #edf0f5; border-radius: 10px; background: #fff;}
  .supplies-table-card .card-body { padding: 0; }
  .supplies-table-card .dataTables_wrapper { padding: 0; }
  .supplies-table-card .row:first-child { display: none; }
  .supplies-table-card .row:last-child { margin: 0; padding: .9rem .25rem 0; align-items: center; color: #475467; font-size: .78rem; }
  .supplies-table-card .dataTables_paginate .pagination { gap: .35rem; margin: 0; justify-content: flex-end; }
  .supplies-table-card .page-link {
    min-width: 34px;
    height: 34px;
    border-radius: 4px !important;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-color: #eaecf0;
    color: #344054;
    font-size: .78rem;
    box-shadow: none;
  }
  .supplies-table-card .page-item.active .page-link { background: #3657f6; border-color: #3657f6; color: #fff; }
  @media (max-width: 767px) {
    .supplies-page-head { flex-direction: column; }
    .supplies-toolbar { justify-content: stretch; }
    .supplies-search, .supplies-filter, .supplies-soft-btn { width: 100%; }
    .supplies-detail-grid { grid-template-columns: 1fr; }
    .supplies-modal .modal-dialog { width: 100%; max-width: none; height: 100dvh; max-height: 100dvh; margin: 0; }
    .supplies-modal .modal-content { height: 100dvh; max-height: 100dvh; border-radius: 0; }
  }
</style>

<div class="container-fluid py-3">
    <div class="supplies-shell">
        <div class="supplies-page-head">
            <div>
                <div class="supplies-eyebrow">Manage</div>
                <h2 class="supplies-title">Supplies Information</h2>
            </div>
            <div class="supplies-head-actions">
                <a href="<?= BASE_URL ?>/ictd/supplies/archive" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-archive"></i> Archive
                </a>
                <?php if ($canCreateSupply): ?>
                    <button type="button" class="btn btn-primary btn-sm" id="openAddSupplyModal">
                        <i class="bi bi-plus-lg"></i> Add Supply
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($missingTables)): ?>
            <div class="supplies-empty">
                <div class="fw-semibold mb-1">Supplies module cannot load yet</div>
                <div class="text-muted small">The following database tables are missing: <?= e(implode(', ', $missingTables)) ?>.</div>
            </div>
        <?php else: ?>

            <div class="supplies-toolbar">
                 <div class="supplies-search">
                    <i class="bi bi-search"></i>
                    <input type="search" class="form-control" id="suppliesSearch" placeholder="Search supplies table">
                </div>
                <div class="supplies-search">
                    <select class="form-select supplies-filter" id="suppliesStatusFilter">
                        <option value="">Supply Status</option>
                    </select>
                </div>
                <div class="supplies-search">
                 <select class="form-select supplies-filter" id="suppliesDeliveryFilter">
                    <option value="">Delivery Status</option>
                    <option value="Delivered">Delivered</option>
                    <option value="To Follow">To Follow</option>
                </select>
                </div>
                <div class="supplies-search">
                 <select class="form-select supplies-filter" id="suppliesAssignedFilter">
                    <option value="">Assignment</option>
                    <option value="assigned">Assigned</option>
                    <option value="unassigned">Unassigned</option>
                </select>
                </div>
                 <?php if ($canEditSupply): ?>
                    <button type="button" class="supplies-soft-btn" id="manageSupplyStatusBtn">
                        Status Masterlist <i class="bi bi-gear ms-1"></i>
                    </button>
                <?php endif; ?>
                <button type="button" class="supplies-soft-btn" id="suppliesMineOnlyBtn" data-active="0">
                    My Records <i class="bi bi-person-check ms-1"></i>
                </button>
                <button type="button" class="supplies-soft-btn" id="suppliesRefreshBtn">
                    <i class="bi bi-sliders"></i>
                </button>
            </div>

            <div class="card supplies-panel supplies-table-card">
                <div class="card-body p-0">
                    <div class="supplies-table-container">
                        <div class="table-responsive">
                            <table id="suppliesTable" class="table align-middle nowrap mb-0 supplies-table" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>Action</th>
                                        <th>Code</th>
                                        <th>Old Control No</th>
                                        <th>Description</th>
                                        <th>Brand</th>
                                        <th>Serial No</th>
                                        <th>Status</th>
                                        <th>Delivery</th>
                                        <th>Delivered</th>
                                        <th>Warranty</th>
                                        <th>ETA</th>
                                        <th>Assigned Flag</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (empty($missingTables)): ?>
<div class="modal fade supplies-modal" id="supplyFormModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title fw-bold" id="supplyModalTitle">Add Supply</h5>
                    <div class="text-muted small">Maintain supplies and other equipment records.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="supplyForm">
                <input type="hidden" id="supply_id">
                <div class="modal-body">
                    <div class="supplies-batch-meta" id="supplyBatchMeta">
                        <div class="supplies-batch-meta-title">Batch Details</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Supplier *</label>
                                <select class="form-select supply-select2" id="supply_batch_supplier_id"></select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Date Delivered *</label>
                                <input type="date" class="form-control" id="supply_batch_date_delivered">
                            </div>
                        </div>
                        <div class="text-muted small mt-2">This delivered date will be applied to every supply entry below.</div>
                    </div>
                    <div id="supplyBatchRows"></div>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="addSupplyRowBtn">
                        <i class="bi bi-plus-lg"></i> Add Another Supply
                    </button>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Save Supply
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade supplies-modal" id="supplyViewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Supply Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="supplies-detail-grid" id="supplyViewDetails"></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="supplyStatusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content border-0">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Status Masterlist</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="supplyStatusForm" class="d-flex gap-2 mb-3">
                    <input type="hidden" id="supply_status_master_id">
                    <input type="text" class="form-control" id="supply_status_master_name" placeholder="Working, For Repair, Condemned..." required>
                    <button type="submit" class="btn btn-primary">Save</button>
                </form>
                <div class="supplies-status-list" id="supplyStatusList"></div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const tableElement = $('#suppliesTable');
    if (!tableElement.length) {
        return;
    }

    const suppliesActionUrl = '<?= BASE_URL ?>/ictd/supplies-action';
    const canCreateSupply = <?= $canCreateSupply ? 'true' : 'false' ?>;
    const canEditSupply = <?= $canEditSupply ? 'true' : 'false' ?>;
    const canDeleteSupply = <?= $canDeleteSupply ? 'true' : 'false' ?>;
    let supplyMasterlists = {
        descriptions: [],
        brands: [],
        endUsers: [],
        departments: [],
        suppliers: [],
        statuses: []
    };
    const supplyFormGuard = window.IMSUnsavedGuard?.create({
        form: '#supplyForm',
        modal: '#supplyFormModal',
        dirtySelectors: ['#addSupplyRowBtn', '.duplicate-supply-row', '.remove-supply-row']
    });

    const table = tableElement.DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '<?= BASE_URL ?>/ictd/supplies-data',
            data: function (data) {
                data.status_filter = $('#suppliesStatusFilter').val();
                data.delivery_filter = $('#suppliesDeliveryFilter').val();
                data.assigned_filter = $('#suppliesAssignedFilter').val();
                data.mine_only = $('#suppliesMineOnlyBtn').data('active') === 1 ? 1 : 0;
            }
        },
        dom: 'rt<"row align-items-center supplies-table-footer"<"col-md-4"i><"col-md-4 supplies-length"l><"col-md-4"p>>',
        order: [[1, 'desc']],
        responsive: true,
        pageLength: 10,
        columnDefs: [
            { targets: [11], visible: false, searchable: false },
            { targets: [0], orderable: false, searchable: false }
        ],
        createdRow: function (row, data) {
            $(row).toggleClass('supplies-row-assigned', Number(data[11]) === 1);
        },
        language: {
            info: '_START_ - _END_ of _TOTAL_',
            lengthMenu: 'Rows per page _MENU_',
            paginate: {
                previous: '<i class="bi bi-chevron-left"></i>',
                next: '<i class="bi bi-chevron-right"></i>'
            },
            processing: 'Loading supplies...'
        }
    });

    $('#suppliesSearch').on('input', function () {
        table.search(this.value).draw();
    });

    $('#suppliesStatusFilter, #suppliesDeliveryFilter, #suppliesAssignedFilter').on('change', function () {
        table.ajax.reload();
    });

    $('#suppliesMineOnlyBtn').on('click', function () {
        const isActive = $(this).data('active') === 1;
        $(this)
            .data('active', isActive ? 0 : 1)
            .toggleClass('active btn-primary text-white', !isActive);
        table.ajax.reload();
    });

    $('#suppliesRefreshBtn').on('click', function () {
        $('#suppliesSearch').val('');
        $('#suppliesStatusFilter').val('');
        $('#suppliesDeliveryFilter').val('');
        $('#suppliesAssignedFilter').val('');
        table.search('').ajax.reload();
    });

    $(document).on('focus mousedown', '.supplies-inline-status', function () {
        $(this).data('previous-value', $(this).val());
    });

    $(document).on('change', '.supplies-inline-status', function () {
        if (!canEditSupply) {
            Swal.fire('Unavailable', 'You do not have permission to update supply status.', 'info');
            return;
        }

        const select = $(this);
        const previousValue = select.data('previous-value') ?? '';
        select.prop('disabled', true);

        $.ajax({
            url: suppliesActionUrl,
            method: 'POST',
            contentType: 'application/json',
            dataType: 'json',
            data: JSON.stringify({
                action: 'mark_status',
                id: select.data('id'),
                status: select.val()
            }),
            success: function (res) {
                if (!res.success) {
                    select.val(previousValue);
                    Swal.fire('Error', res.message || 'Unable to update supply status.', 'error');
                    return;
                }

                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: res.message || 'Supply status updated.',
                    showConfirmButton: false,
                    timer: 1300
                });
                table.ajax.reload(null, false);
            },
            error: function (xhr) {
                select.val(previousValue);
                Swal.fire('Error', xhr.responseJSON?.message || 'Unable to update supply status.', 'error');
            },
            complete: function () {
                select.prop('disabled', false);
            }
        });
    });

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, function (char) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[char];
        });
    }

    function masterOptions(items, selectedValue, placeholder = 'Select') {
        let html = `<option value="">${escapeHtml(placeholder)}</option>`;
        let hasSelectedValue = selectedValue === '' || selectedValue === null || selectedValue === undefined;
        (items || []).forEach(function (item) {
            const selected = String(item.id) === String(selectedValue ?? '') ? ' selected' : '';
            if (selected) {
                hasSelectedValue = true;
            }
            html += `<option value="${escapeHtml(item.id)}"${selected}>${escapeHtml(item.text)}</option>`;
        });
        if (!hasSelectedValue) {
            html += `<option value="${escapeHtml(selectedValue)}" selected>${escapeHtml(selectedValue)}</option>`;
        }
        return html;
    }

    function renderStatusControls(selectedValue = '') {
        $('#suppliesStatusFilter').html(masterOptions(supplyMasterlists.statuses, $('#suppliesStatusFilter').val(), 'Supply Status'));
        $('#supply_status').html(masterOptions(supplyMasterlists.statuses, selectedValue, 'Select'));
    }

    function renderSupplyStatusList() {
        const html = (supplyMasterlists.statuses || []).map(function (item) {
            return `
                <div class="supplies-status-item">
                    <span class="fw-semibold">${escapeHtml(item.text)}</span>
                    <span class="supplies-actions">
                        <button type="button" class="supplies-action-btn supplies-action-edit edit-supply-status-btn" data-id="${escapeHtml(item.id)}" data-name="${escapeHtml(item.text)}"><i class="bi bi-pencil-square"></i> Edit</button>
                        <button type="button" class="supplies-action-btn supplies-action-delete delete-supply-status-btn" data-id="${escapeHtml(item.id)}"><i class="bi bi-trash"></i> Delete</button>
                    </span>
                </div>
            `;
        }).join('');
        $('#supplyStatusList').html(html || '<div class="text-muted small">No status records yet.</div>');
    }

    function initSelect2(context) {
        if (!$.fn.select2) {
            return;
        }

        $(context).find('.supply-select2').select2({
            width: '100%',
            dropdownParent: $('#supplyFormModal')
        });
    }

    function supplyFieldsHtml(prefix, data = {}, options = {}) {
        const removable = Boolean(options.removable);
        const duplicable = Boolean(options.duplicable);
        const includeBatchFields = options.includeBatchFields !== false;
        const removeButton = removable ? '<button type="button" class="supplies-batch-remove remove-supply-row" title="Remove"><i class="bi bi-x-lg"></i></button>' : '';
        const duplicateButton = duplicable ? '<button type="button" class="supplies-batch-duplicate duplicate-supply-row" title="Duplicate this entry"><i class="bi bi-files"></i></button>' : '';
        const deliveredDateField = includeBatchFields ? '<div class="col-md-4"><label class="form-label">Delivered Date</label><input type="date" class="form-control" name="' + prefix + '[delivered_date]" value="' + escapeHtml(data.delivered_date) + '"></div>' : '';
        const deliveryStatus = data.delivery_status || 'To Follow';

        return `
            <div class="supplies-batch-row">
                ${removeButton}
                ${duplicateButton}
                <div class="supplies-form-section">
                    <div class="supplies-form-section-title"><i class="bi bi-upc-scan"></i> Identification</div>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Code</label><input type="text" class="form-control" name="${prefix}[code]" value="${escapeHtml(data.code)}" placeholder="Auto generated if blank"></div>
                        <div class="col-md-6"><label class="form-label">Old Control No</label><input type="text" class="form-control" name="${prefix}[old_control_no]" value="${escapeHtml(data.old_control_no)}" placeholder="Optional if applicable"></div>
                        <div class="col-md-6"><label class="form-label">Description</label><select class="form-select supply-select2" name="${prefix}[description_id]">${masterOptions(supplyMasterlists.descriptions, data.description_id)}</select></div>
                        <div class="col-md-6"><label class="form-label">Brand</label><select class="form-select supply-select2" name="${prefix}[brand_id]">${masterOptions(supplyMasterlists.brands, data.brand_id)}</select></div>
                        <div class="col-md-6"><label class="form-label">Serial No</label><input type="text" class="form-control" name="${prefix}[serialno]" value="${escapeHtml(data.serialno)}"></div>
                        <div class="col-md-6"><label class="form-label">Status</label><select class="form-select" name="${prefix}[status]">${masterOptions(supplyMasterlists.statuses, data.status, 'Select')}</select></div>
                        <div class="col-md-6"><label class="form-label">Warranty Date</label><input type="date" class="form-control" name="${prefix}[warranty_date]" value="${escapeHtml(data.warranty_date)}"></div>
                    </div>
                </div>

                <div class="supplies-form-section">
                    <div class="supplies-form-section-title"><i class="bi bi-truck"></i> Delivery Details</div>
                    <div class="row g-3">
                        ${deliveredDateField}
                        <div class="col-md-4">
                            <label class="form-label">Delivery Status</label>
                            <select class="form-select supply-delivery-status-field" name="${prefix}[delivery_status]">
                                ${masterOptions(supplyMasterlists.statuses, deliveryStatus, 'Select')}
                            </select>
                        </div>
                        <div class="col-md-4"><label class="form-label">ETA Date</label><input type="date" class="form-control supply-eta-date-field" name="${prefix}[eta_date]" value="${escapeHtml(data.eta_date)}"></div>
                        <div class="col-12"><label class="form-label">Remarks</label><textarea class="form-control" name="${prefix}[remarks]" rows="3">${escapeHtml(data.remarks)}</textarea></div>
                    </div>
                </div>
            </div>
        `;
    }

    function syncSupplyRowRules(row) {
        const container = $(row);
        const deliveryStatus = container.find('.supply-delivery-status-field');
        const etaDate = container.find('.supply-eta-date-field');
        const isDelivered = deliveryStatus.val() === 'Delivered';

        etaDate.prop('disabled', isDelivered);
        if (isDelivered) {
            etaDate.val('');
        }
    }

    function syncSupplyRules(scope) {
        $(scope).find('.supplies-batch-row').each(function () {
            syncSupplyRowRules(this);
        });
    }

    function serializeSupplyRow(container) {
        const data = {};
        $(container).find(':input[name]').each(function () {
            const match = this.name.match(/\[([^\]]+)\]$/);
            if (match) {
                data[match[1]] = $(this).val();
            }
        });
        return data;
    }

    function loadMasterlists(callback) {
        $.getJSON(suppliesActionUrl, { action: 'masterlists' })
            .done(function (res) {
                if (res.success) {
                    supplyMasterlists = res.data || supplyMasterlists;
                    renderStatusControls();
                    renderSupplyStatusList();
                }
                if (typeof callback === 'function') {
                    callback();
                }
            })
            .fail(function () {
                Swal.fire('Error', 'Unable to load supply masterlists.', 'error');
            });
    }

    loadMasterlists();

    function reloadSupplyMasterlists(callback) {
        supplyMasterlists = { descriptions: [], brands: [], endUsers: [], departments: [], suppliers: [], statuses: [] };
        loadMasterlists(function () {
            renderStatusControls();
            renderSupplyStatusList();
            table.ajax.reload(null, false);
            callback?.();
        });
    }

    function fillForm(data = {}) {
        $('#supply_id').val(data.id || '');
        const isEdit = Boolean(data.id);
        $('#addSupplyRowBtn').toggle(!isEdit);
        $('#supplyBatchMeta').toggle(!isEdit);
        $('#supply_batch_date_delivered').prop('required', !isEdit).val('');
        $('#supply_batch_supplier_id').prop('required', !isEdit).html(masterOptions(supplyMasterlists.suppliers, data.supplier_id));
        $('#supplyBatchRows').html(supplyFieldsHtml('rows[0]', data, {
            removable: false,
            duplicable: !isEdit,
            includeBatchFields: isEdit
        }));
        initSelect2($('#supplyFormModal'));
        $('.supply-select2').trigger('change.select2');
        syncSupplyRules($('#supplyFormModal'));
    }

    function formPayload(action) {
        const rows = $('#supplyBatchRows .supplies-batch-row').map(function () {
            return serializeSupplyRow(this);
        }).get();
        if (action === 'update') {
            return { action, id: $('#supply_id').val(), ...(rows[0] || {}) };
        }
        return {
            action: 'batch_create',
            batch_supplier_id: $('#supply_batch_supplier_id').val(),
            batch_date_delivered: $('#supply_batch_date_delivered').val(),
            rows
        };
    }

    function fetchSupply(id) {
        return $.getJSON(suppliesActionUrl, { action: 'view', id: id });
    }

    function showModal(selector) {
        const element = document.querySelector(selector);
        if (window.bootstrap && element) {
            bootstrap.Modal.getOrCreateInstance(element).show();
            return;
        }
        $(selector).modal('show');
    }

    function hideModal(selector) {
        const element = document.querySelector(selector);
        if (window.bootstrap && element) {
            bootstrap.Modal.getOrCreateInstance(element).hide();
            return;
        }
        $(selector).modal('hide');
    }

    function renderDetails(data) {
        const fields = [
            ['Assigned', Number(data.is_assigned) === 1 ? 'Yes' : 'No'],
            ['Code', data.code],
            ['Old Control No', data.old_control_no],
            ['Description', data.description_name],
            ['Brand', data.brand_name],
            ['Supplier', data.supplier_name],
            ['Serial No', data.serialno],
            ['Status', data.status],
            ['Delivery Status', data.delivery_status],
            ['ETA Date', data.eta_date],
            ['Delivered Date', data.delivered_date],
            ['Warranty Date', data.warranty_date],
            ['Batch ID', data.batch_id],
            ['End User', data.end_user_name],
            ['Department', data.department_name],
            ['Remarks', data.remarks],
            ['Created At', data.created_at]
        ];

        $('#supplyViewDetails').html(fields.map(function (field) {
            return `
                <div class="supplies-detail-item">
                    <div class="supplies-detail-label">${escapeHtml(field[0])}</div>
                    <div class="supplies-detail-value">${escapeHtml(field[1] || 'No Info')}</div>
                </div>
            `;
        }).join(''));
    }

    function openAddSupplyModal() {
        if (!canCreateSupply) {
            Swal.fire('Unavailable', 'You do not have permission to add supplies.', 'info');
            return;
        }

        loadMasterlists(function () {
            $('#supplyModalTitle').text('Add Supplies');
            $('#supplyFormModal .modal-header .text-muted.small').text('Maintain supplies and other equipment records.');
            fillForm({});
            showModal('#supplyFormModal');
        });
    }

    $('#openAddSupplyModal').on('click', function () {
        openAddSupplyModal();
    });

    $('#addSupplyRowBtn').on('click', function () {
        const index = $('#supplyBatchRows .supplies-batch-row').length;
        $('#supplyBatchRows').append(supplyFieldsHtml(`rows[${index}]`, {}, {
            removable: true,
            duplicable: true,
            includeBatchFields: false
        }));
        initSelect2($('#supplyBatchRows .supplies-batch-row').last());
        syncSupplyRules($('#supplyBatchRows .supplies-batch-row').last());
    });

    $(document).on('click', '.duplicate-supply-row', function () {
        const sourceRow = $(this).closest('.supplies-batch-row');
        const clonedData = serializeSupplyRow(sourceRow);
        clonedData.code = '';
        clonedData.serialno = '';

        const index = $('#supplyBatchRows .supplies-batch-row').length;
        $('#supplyBatchRows').append(supplyFieldsHtml(`rows[${index}]`, clonedData, {
            removable: true,
            duplicable: true,
            includeBatchFields: false
        }));
        initSelect2($('#supplyBatchRows .supplies-batch-row').last());
        syncSupplyRules($('#supplyBatchRows .supplies-batch-row').last());
    });

    $(document).on('click', '.remove-supply-row', function () {
        $(this).closest('.supplies-batch-row').remove();
    });

    $(document).on('change', '.supply-delivery-status-field', function () {
        syncSupplyRowRules($(this).closest('.supplies-batch-row'));
    });

    $('#manageSupplyStatusBtn').on('click', function () {
        loadMasterlists(function () {
            $('#supply_status_master_id').val('');
            $('#supply_status_master_name').val('');
            showModal('#supplyStatusModal');
        });
    });

    $('#supplyStatusForm').on('submit', function (event) {
        event.preventDefault();
        const id = $('#supply_status_master_id').val();

        $.ajax({
            url: suppliesActionUrl,
            method: 'POST',
            contentType: 'application/json',
            dataType: 'json',
            data: JSON.stringify({
                action: id ? 'status_update' : 'status_create',
                id: id,
                equipment_status: $('#supply_status_master_name').val()
            }),
            success: function (res) {
                if (!res.success) {
                    Swal.fire('Error', res.message || 'Unable to save status.', 'error');
                    return;
                }

                $('#supply_status_master_id').val('');
                $('#supply_status_master_name').val('');
                reloadSupplyMasterlists();
                Swal.fire('Saved', res.message, 'success');
            },
            error: function (xhr) {
                Swal.fire('Error', xhr.responseJSON?.message || 'Unable to save status.', 'error');
            }
        });
    });

    $(document).on('click', '.edit-supply-status-btn', function () {
        $('#supply_status_master_id').val($(this).data('id'));
        $('#supply_status_master_name').val($(this).data('name')).focus();
    });

    $(document).on('click', '.delete-supply-status-btn', function () {
        const id = $(this).data('id');
        Swal.fire({
            title: 'Delete status?',
            text: 'This cannot delete statuses currently used by equipment or supplies records.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete it',
            confirmButtonColor: '#d92d20'
        }).then(function (result) {
            if (!result.isConfirmed) {
                return;
            }

            $.ajax({
                url: suppliesActionUrl,
                method: 'POST',
                contentType: 'application/json',
                dataType: 'json',
                data: JSON.stringify({ action: 'status_delete', id: id }),
                success: function (res) {
                    if (!res.success) {
                        Swal.fire('Error', res.message || 'Unable to delete status.', 'error');
                        return;
                    }

                    reloadSupplyMasterlists();
                    Swal.fire('Deleted', res.message, 'success');
                },
                error: function (xhr) {
                    Swal.fire('Error', xhr.responseJSON?.message || 'Unable to delete status.', 'error');
                }
            });
        });
    });

    $(document).on('click', '.view-supply-btn', function () {
        fetchSupply($(this).data('id')).done(function (res) {
            if (!res.success) {
                Swal.fire('Error', res.message || 'Unable to load supply.', 'error');
                return;
            }
            renderDetails(res.data);
            showModal('#supplyViewModal');
        });
    });

    $(document).on('click', '.edit-supply-btn', function () {
        if (!canEditSupply) {
            Swal.fire('Unavailable', 'You do not have permission to edit supplies.', 'info');
            return;
        }

        const id = $(this).data('id');
        loadMasterlists(function () {
            fetchSupply(id).done(function (res) {
                if (!res.success) {
                    Swal.fire('Error', res.message || 'Unable to load supply.', 'error');
                    return;
                }

                $('#supplyModalTitle').text('Edit Supply');
                fillForm(res.data);
                showModal('#supplyFormModal');
            });
        });
    });

    $('#supplyForm').on('submit', function (event) {
        event.preventDefault();
        const id = $('#supply_id').val();
        const action = id ? 'update' : 'batch_create';
        if (!id && (!$('#supply_batch_supplier_id').val() || !$('#supply_batch_date_delivered').val())) {
            Swal.fire('Missing batch details', 'Please select a supplier and date delivered for this supply batch.', 'warning');
            return;
        }

        $.ajax({
            url: suppliesActionUrl,
            method: 'POST',
            contentType: 'application/json',
            dataType: 'json',
            data: JSON.stringify(formPayload(action)),
            beforeSend: function () {
                Swal.fire({
                    title: 'Saving...',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });
            },
            success: function (res) {
                if (!res.success) {
                    Swal.fire('Error', res.message || 'Unable to save supply.', 'error');
                    return;
                }

                supplyFormGuard?.forceClean();
                Swal.fire('Success', res.message, 'success');
                hideModal('#supplyFormModal');
                table.ajax.reload(null, false);
            },
            error: function (xhr) {
                Swal.fire('Error', xhr.responseJSON?.message || 'Unable to save supply.', 'error');
            }
        });
    });

    $(document).on('click', '.delete-supply-btn', function () {
        if (!canDeleteSupply) {
            Swal.fire('Unavailable', 'You do not have permission to delete supplies.', 'info');
            return;
        }

        const id = $(this).data('id');
        Swal.fire({
            title: 'Archive supply?',
            text: 'If this supply is assigned, the system will automatically unassign it first and save the assignment details in the archive.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Move to archive',
            confirmButtonColor: '#d92d20'
        }).then(function (result) {
            if (!result.isConfirmed) {
                return;
            }

            $.ajax({
                url: suppliesActionUrl,
                method: 'POST',
                contentType: 'application/json',
                dataType: 'json',
                data: JSON.stringify({ action: 'delete', id: id }),
                success: function (res) {
                    if (!res.success) {
                        Swal.fire('Error', res.message || 'Unable to delete supply.', 'error');
                        return;
                    }

                    Swal.fire('Archived', res.message, 'success');
                    table.ajax.reload(null, false);
                },
                error: function (xhr) {
                    Swal.fire('Error', xhr.responseJSON?.message || 'Unable to delete supply.', 'error');
                }
            });
        });
    });
});
</script>

<?php require_once ROOT_PATH . '/modules/layout/footer.php'; ?>
