<?php
$page_title = 'Transmittal Reports';
require_once ROOT_PATH . '/modules/layout/header.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/functions.php';

require_login();
require_permission('ictd.transmittal.view');
?>

<style>
  .transmittal-shell { display: grid; gap: 1rem; }
  .transmittal-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; }
  .transmittal-title { margin: 0; color: #1f2937; font-size: 1.35rem; font-weight: 850; }
  .transmittal-eyebrow { color: #667085; font-size: .78rem; font-weight: 750; }
  .transmittal-card { border: 0; border-radius: 16px; background: rgba(255,255,255,.9); box-shadow: 0 18px 44px rgba(15,23,42,.08); overflow: hidden; }
  .transmittal-table { font-size: .8rem; }
  .transmittal-table thead th { white-space: nowrap; background: #f3f4f6 !important; border: 0 !important; color: #5f6672; font-size: .72rem; padding: .72rem .8rem !important; }
  .transmittal-table tbody td { border: 0 !important; border-bottom: 1px solid #f2f4f7 !important; padding: .72rem .8rem !important; white-space: nowrap; vertical-align: middle; }
  .transmittal-actions { display: inline-flex; align-items: center; gap: .55rem; }
  .transmittal-action-btn { border: 0; background: transparent; padding: 0; line-height: 1; font-size: .95rem; }
  .transmittal-item-toggle { border: 0; background: transparent; padding: 0; }
  .transmittal-items-child { margin: .25rem 0 .35rem 3rem; border: 1px solid #e7eefc; border-radius: 8px; overflow: hidden; background: #fff; }
  .transmittal-items-child table { width: 100%; margin: 0; font-size: .76rem; }
  .transmittal-items-child th { background: #f8fafc; color: #667085; font-size: .68rem; text-transform: uppercase; padding: .55rem .65rem; }
  .transmittal-items-child td { padding: .55rem .65rem; border-top: 1px solid #f2f4f7; }
  .ims-upload-actions { display: inline-flex; align-items: center; gap: .45rem; }
  .ims-upload-btn { border: 0; border-radius: 11px; min-width: 38px; height: 30px; padding: 0 .75rem; display: inline-flex; align-items: center; justify-content: center; gap: .35rem; font-size: .72rem; font-weight: 800; line-height: 1; box-shadow: inset 0 1px 0 rgba(255,255,255,.48), 0 3px 7px rgba(15,23,42,.2); cursor: pointer; text-decoration: none; transition: transform .15s ease, box-shadow .15s ease, filter .15s ease; }
  .ims-upload-btn:hover { transform: translateY(-1px); box-shadow: inset 0 1px 0 rgba(255,255,255,.55), 0 5px 10px rgba(15,23,42,.24); filter: brightness(1.03); text-decoration: none; }
  .ims-upload-btn i { font-size: .86rem; }
  .ims-upload-btn-blue { background: linear-gradient(180deg, #4f9cff 0%, #1f6fe5 100%); color: #fff; }
  .ims-upload-btn-red { background: linear-gradient(180deg, #ff5c5c 0%, #e21f2f 100%); color: #fff; }
  .ims-upload-panel { width: 220px; display: grid; gap: .28rem; justify-items: center; }
  .ims-upload-file-link { color: #14966f; font-size: .82rem; font-weight: 850; text-decoration: underline; max-width: 212px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .ims-upload-empty { color: #98a2b3; font-size: .72rem; font-weight: 750; }
  .ims-upload-actions { justify-content: center; gap: .45rem; }
  .ims-upload-square { min-width: 64px; width: auto; height: 30px; padding: 0 .65rem; }
  .ims-upload-btn-gray { background: linear-gradient(180deg, #f9fafb 0%, #e5e7eb 100%); color: #64748b; }
  .ims-upload-input { width: 100%; max-width: 216px; font-size: .72rem; padding: .25rem .35rem; border-radius: 6px; border: 1px solid #d7dce5; background: #fff; }
  .ims-upload-submit { width: 100%; max-width: 216px; border-radius: 9px; background: linear-gradient(180deg, #fff 0%, #eef1f5 100%); color: #5f6672; }
  .transmittal-section { border: 1px solid #edf0f5; border-radius: 14px; background: #fbfcff; padding: 1rem; margin-bottom: 1rem; }
  .transmittal-section-title { display: flex; align-items: center; gap: .45rem; color: #263241; font-size: .82rem; font-weight: 900; text-transform: uppercase; letter-spacing: .02em; margin-bottom: .8rem; }
  .transmittal-section-title i { color: #2563eb; }
  .transmittal-assigned-wrap { display: none; margin-bottom: 1rem; }
  .transmittal-assigned-wrap.active { display: block; }
  .transmittal-item-table { border: 1px solid #edf0f5; border-radius: 12px; overflow: auto; background: #fff; }
  .transmittal-item-table table { margin: 0; min-width: 860px; font-size: .77rem; }
  .transmittal-item-table th { background: #f8fafc; color: #667085; white-space: nowrap; }
  .transmittal-item-table td { vertical-align: middle; white-space: nowrap; }
  .transmittal-item-table .form-control { min-width: 130px; height: 34px; font-size: .77rem; }
  .transmittal-available-table { max-height: 320px; overflow: auto; border: 1px solid #edf0f5; border-radius: 12px; background: #fff; }
  .transmittal-available-table table { margin: 0; font-size: .78rem; }
  .transmittal-available-table th { position: sticky; top: 0; background: #f8fafc; z-index: 1; color: #667085; white-space: nowrap; }
  .transmittal-available-table td { vertical-align: middle; }
  .transmittal-available-table tr.selected-row { background: #e9f7ef; font-weight: 700; }
  .transmittal-tabs .nav-link { color: #667085; font-size: .78rem; font-weight: 850; }
  .transmittal-tabs .nav-link.active { color: #0d6efd; }
  .manual-row { display: grid; grid-template-columns: minmax(150px, 1fr) minmax(130px, 1fr) 82px minmax(130px, 1fr) 40px; gap: .5rem; margin-bottom: .55rem; align-items: center; }
  @media (max-width: 767px) {
    .manual-row { grid-template-columns: 1fr; }
  }
  #transmittalFormModal .modal-dialog { width: min(1180px, calc(100vw - 24px)); max-width: min(1180px, calc(100vw - 24px)); margin: .75rem auto; }
  #transmittalFormModal .modal-content { max-height: calc(100dvh - 1.5rem); display: flex; flex-direction: column; overflow: hidden; }
  #transmittalFormModal form { flex: 1 1 auto; min-height: 0; display: flex; flex-direction: column; }
  #transmittalFormModal .modal-body { flex: 1 1 auto; min-height: 0; overflow-y: auto; }
  #transmittalFormModal .modal-footer { flex: 0 0 auto; background: #fff; box-shadow: 0 -10px 24px rgba(15,23,42,.08); }
  @media (max-width: 767px) {
    .transmittal-head { flex-direction: column; }
    .transmittal-head .btn { width: 100%; }
  }
</style>

<div class="container-fluid py-3">
    <div class="transmittal-shell">
        <div class="transmittal-head">
            <div>
                <div class="transmittal-eyebrow">Reports</div>
                <h2 class="transmittal-title">Transmittal Reports</h2>
                <div class="text-muted small">Create transmittals from unassigned equipment and supplies that have not yet been transmitted.</div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="<?= BASE_URL ?>/ictd/transmittal/archive" class="btn btn-outline-secondary btn-sm"><i class="bi bi-archive"></i> Archive</a>
                <button type="button" class="btn btn-outline-primary btn-sm" id="transmittalMineOnlyBtn" data-active="0"><i class="bi bi-person-check"></i> My Records</button>
                <button type="button" class="btn btn-primary btn-sm" id="openTransmittalBtn"><i class="bi bi-plus-lg"></i> New Transmittal</button>
            </div>
        </div>

        <div class="card transmittal-card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="transmittalTable" class="table align-middle nowrap mb-0 transmittal-table" style="width:100%">
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
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="transmittalFormModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title fw-bold" id="transmittalModalTitle">New Transmittal</h5>
                    <div class="text-muted small">Transmittal ID is generated from the transmittal date year.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="transmittalForm" enctype="multipart/form-data">
                <input type="hidden" id="transmittal_pk" name="id">
                <div class="modal-body">
                    <div class="transmittal-section">
                        <div class="transmittal-section-title"><i class="bi bi-file-earmark-text"></i> Transmittal Details</div>
                        <div class="row g-3">
                            <div class="col-md-3"><label class="form-label">Transmittal No.</label><input type="text" class="form-control" id="transmittal_id" name="transmittal_id" placeholder="Auto generated" readonly></div>
                            <div class="col-md-5"><label class="form-label">Department *</label><select class="form-select transmittal-select2" id="department_id" name="department_id" required></select></div>
                            <div class="col-md-4"><label class="form-label">Accountable Officer *</label><input type="text" class="form-control" id="accountable_officer" name="accountable_officer" readonly required></div>
                            <div class="col-md-4"><label class="form-label">Designation</label><select class="form-select transmittal-select2" id="designation_id" name="designation_id"></select></div>
                            <div class="col-md-3"><label class="form-label">Transmittal Date *</label><input type="date" class="form-control" id="transmittal_date" name="transmittal_date" required></div>
                            <div class="col-md-3"><label class="form-label">Received By</label><input type="text" class="form-control" id="received_by" name="received_by"></div>
                            <div class="col-md-3"><label class="form-label">Date Received</label><input type="date" class="form-control" id="date_received" name="date_received"></div>
                        </div>
                    </div>

                    <div class="transmittal-section">
                        <div class="transmittal-section-title"><i class="bi bi-box-seam"></i> Transmittal Items</div>
                        <div id="transmittalItemHint" class="text-muted small mb-2">Available unassigned equipment and supplies that have not yet been transmitted are loaded here.</div>

                        <ul class="nav nav-tabs transmittal-tabs mb-3" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="equipment-tab" data-bs-toggle="tab" data-bs-target="#equipmentPane" type="button" role="tab"><i class="bi bi-pc-display"></i> Equipment</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="supplies-tab" data-bs-toggle="tab" data-bs-target="#suppliesPane" type="button" role="tab"><i class="bi bi-collection"></i> Supplies</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="selected-tab" data-bs-toggle="tab" data-bs-target="#selectedPane" type="button" role="tab"><i class="bi bi-check2-square"></i> Selected</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="manual-tab" data-bs-toggle="tab" data-bs-target="#manualPane" type="button" role="tab"><i class="bi bi-pencil-square"></i> Manual Items</button>
                            </li>
                        </ul>

                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="equipmentPane" role="tabpanel" aria-labelledby="equipment-tab">
                                <div class="d-flex justify-content-between flex-wrap gap-2 mb-2">
                                    <input type="text" class="form-control form-control-sm" id="serialSearch" placeholder="Filter equipment..." style="max-width: 280px;">
                                    <label class="small fw-semibold"><input type="checkbox" class="form-check-input me-1" id="selectAllEquipment"> Select All Equipment</label>
                                </div>
                                <div class="transmittal-available-table mb-2" id="availableEquipmentTable"></div>
                                <div class="text-muted small"><i class="bi bi-info-circle"></i> Click a row to toggle selection. Selected rows move to the top.</div>
                            </div>

                            <div class="tab-pane fade" id="suppliesPane" role="tabpanel" aria-labelledby="supplies-tab">
                                <div class="d-flex justify-content-between flex-wrap gap-2 mb-2">
                                    <input type="text" class="form-control form-control-sm" id="supplySearch" placeholder="Filter supplies..." style="max-width: 280px;">
                                    <label class="small fw-semibold"><input type="checkbox" class="form-check-input me-1" id="selectAllSupplies"> Select All Supplies</label>
                                </div>
                                <div class="transmittal-available-table mb-2" id="availableSuppliesTable"></div>
                                <div class="text-muted small"><i class="bi bi-info-circle"></i> Supplies can be included together with equipment in one transmittal.</div>
                            </div>

                            <div class="tab-pane fade" id="selectedPane" role="tabpanel" aria-labelledby="selected-tab">
                                <div id="transmittalItems"></div>
                            </div>

                            <div class="tab-pane fade" id="manualPane" role="tabpanel" aria-labelledby="manual-tab">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div class="text-muted small">Use this for items that are not yet encoded in equipment or supplies.</div>
                                    <button type="button" class="btn btn-outline-primary btn-sm" id="addManualRow"><i class="bi bi-plus"></i> Add Row</button>
                                </div>
                                <div id="manualContainer"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save Transmittal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="transmittalViewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Transmittal Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="transmittalViewBody"></div>
        </div>
    </div>
</div>

<div class="modal fade" id="transmittalPdfModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-pdf"></i> Transmittal PDF Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <iframe id="transmittalPdfFrame" style="width:100%;height:75vh;border:0"></iframe>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const actionUrl = '<?= BASE_URL ?>/ictd/transmittal-action';
    let masterlists = { departments: [], users: [], equipment: [], supplies: [] };
    let transmittalItems = [];

    const table = $('#transmittalTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '<?= BASE_URL ?>/ictd/transmittal-data',
            data: d => { d.mine_only = $('#transmittalMineOnlyBtn').data('active') === 1 ? 1 : 0; }
        },
        order: [[9, 'desc']],
        scrollX: true,
        dom: 'rt<"row align-items-center mt-3 px-3 pb-3"<"col-md-4"i><"col-md-4 text-center"l><"col-md-4"p>>',
        columnDefs: [{ targets: [0, 1], orderable: false, searchable: false }]
    });

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
    }

    $('#transmittalMineOnlyBtn').on('click', function () {
        const isActive = $(this).data('active') === 1;
        $(this).data('active', isActive ? 0 : 1).toggleClass('active', !isActive);
        table.ajax.reload();
    });

    function transmittalItemsChild(items) {
        if (!items || !items.length) {
            return '<div class="transmittal-items-child"><div class="p-3 text-muted small">No transmittal items.</div></div>';
        }
        const rows = items.map(item => {
            const itemType = item.item_type || 'equipment';
            const badgeClass = itemType === 'supply'
                ? 'bg-success-subtle text-success'
                : itemType === 'manual'
                    ? 'bg-secondary-subtle text-secondary'
                    : 'bg-primary-subtle text-primary';

            return `
                <tr>
                    <td><span class="badge ${badgeClass}">${escapeHtml(itemType === 'manual' ? 'manual input' : itemType)}</span></td>
                    <td><strong>${escapeHtml(itemType === 'manual' ? 'Manual Entry' : (item.control_no || ''))}</strong></td>
                    <td>${escapeHtml(item.old_control_no || '')}</td>
                    <td>${escapeHtml(item.description_name || item.description || '')}</td>
                    <td>${escapeHtml(item.brand_name || item.brand || '')}</td>
                    <td>${escapeHtml(item.qty || '1')}</td>
                    <td>${escapeHtml(item.serialno || '')}</td>
                    <td>${escapeHtml(item.remarks || '')}</td>
                </tr>
            `;
        }).join('');
        return `<div class="transmittal-items-child"><table><thead><tr><th>Type</th><th>Code / Control</th><th>Old Control</th><th>Description</th><th>Brand</th><th>Qty</th><th>Serial No</th><th>Remarks</th></tr></thead><tbody>${rows}</tbody></table></div>`;
    }

    function toggleTransmittalItems(button) {
        const tr = $(button).closest('tr');
        const row = table.row(tr);
        const id = $(button).data('id');
        if (row.child.isShown()) {
            row.child.hide();
            tr.removeClass('shown');
            tr.find('.toggle-transmittal-items-btn i').removeClass('bi-chevron-down').addClass('bi-chevron-right');
            return;
        }
        tr.find('.toggle-transmittal-items-btn i').removeClass('bi-chevron-right').addClass('bi-chevron-down');
        row.child('<div class="transmittal-items-child"><div class="p-3 text-muted small">Loading items...</div></div>').show();
        $.getJSON(actionUrl, { action: 'get', id }).done(res => row.child(transmittalItemsChild(res.success ? (res.items || []) : [])).show())
            .fail(() => row.child('<div class="transmittal-items-child"><div class="p-3 text-danger small">Unable to load items.</div></div>').show());
    }

    $(document).on('click', '.toggle-transmittal-items-btn', function (event) {
        event.stopPropagation();
        toggleTransmittalItems(this);
    });

    $('#transmittalTable tbody').on('click', 'tr', function (event) {
        if ($(event.target).closest('button, a, input, select, textarea, label').length) return;
        const toggle = $(this).find('.toggle-transmittal-items-btn').first();
        if (toggle.length) toggleTransmittalItems(toggle[0]);
    });

    function modal(id) {
        const element = document.getElementById(id);
        return bootstrap.Modal.getInstance(element) || new bootstrap.Modal(element);
    }

    function options(items, placeholder) {
        return '<option value="">' + placeholder + '</option>' + (items || []).map(item => `<option value="${escapeHtml(item.id)}" data-department-id="${escapeHtml(item.department_id || '')}" data-head="${escapeHtml(item.department_head || '')}">${escapeHtml(item.text)}</option>`).join('');
    }

    function initSelect2() {
        if (!$.fn.select2) return;
        $('.transmittal-select2').each(function () {
            if ($(this).hasClass('select2-hidden-accessible')) $(this).select2('destroy');
            $(this).select2({ width: '100%', dropdownParent: $('#transmittalFormModal'), allowClear: true, placeholder: $(this).find('option:first').text() || 'Select' });
        });
    }

    function loadMasterlists(callback) {
        $.getJSON(actionUrl, { action: 'masterlists' }, function (res) {
            if (!res.success) return;
            const selectedDesignation = $('#designation_id').val();
            masterlists = { ...masterlists, ...res.data };
            $('#department_id').html(options(masterlists.departments, 'Select department'));
            $('#designation_id').html(options(masterlists.departments, 'Select designation')).val(selectedDesignation);
            renderAvailableEquipment();
            renderAvailableSupplies();
            initSelect2();
            if (typeof callback === 'function') callback();
        }).fail(function (xhr) {
            $('#transmittalItemHint').text((xhr.responseJSON && xhr.responseJSON.message) || 'Unable to load transmittal form data.').addClass('text-danger').show();
        });
    }

    function loadNextTransmittalId(callback) {
        $.getJSON(actionUrl, { action: 'next_id', transmittal_date: $('#transmittal_date').val() }, function (res) {
            if (res.success && !$('#transmittal_pk').val()) {
                $('#transmittal_id').val(res.transmittal_id);
            }
            if (typeof callback === 'function') callback();
        }).fail(function () {
            if (typeof callback === 'function') callback();
        });
    }

    function setDepartmentItems(equipment, supplies) {
        masterlists.equipment = equipment || [];
        masterlists.supplies = supplies || [];
        renderAvailableEquipment();
        renderAvailableSupplies();
    }

    function itemMatches(selectedItem, item) {
        return (selectedItem.item_type || 'equipment') === (item.item_type || 'equipment')
            && String(selectedItem.source_id) === String(item.source_id);
    }

    function itemKey(item) {
        return (item.item_type || 'equipment') + ':' + String(item.source_id || '');
    }

    function uniqueSelectedItems(items) {
        const seen = new Set();
        const unique = [];
        (items || []).forEach(item => {
            if (!item || !item.source_id) return;
            const key = itemKey(item);
            if (seen.has(key)) return;
            seen.add(key);
            unique.push(item);
        });
        return unique;
    }

    function setTransmittalItems(items) {
        transmittalItems = uniqueSelectedItems(items);
    }

    function isSelected(item) {
        return transmittalItems.some(selectedItem => itemMatches(selectedItem, item));
    }

    function findDepartmentItem(type, sourceId) {
        const listName = type === 'supply' ? 'supplies' : 'equipment';
        return (masterlists[listName] || []).find(item => String(item.source_id) === String(sourceId));
    }

    function renderAvailableList(listName, target, emptyMessage) {
        const rows = (masterlists[listName] || []).map(item => {
            const selected = isSelected(item);
            return `
                <tr class="${selected ? 'selected-row' : ''}" data-type="${escapeHtml(item.item_type || 'equipment')}" data-source-id="${escapeHtml(item.source_id)}">
                    <td class="text-center"><input type="checkbox" class="form-check-input transmittal-item-check" ${selected ? 'checked' : ''}></td>
                    <td>${escapeHtml(item.control_no || '')}</td>
                    <td>${escapeHtml(item.old_control_no || '')}</td>
                    <td>${escapeHtml(item.description_name || '')}</td>
                    <td>${escapeHtml(item.brand_name || '')}</td>
                    <td>${escapeHtml(item.serialno || '')}</td>
                </tr>
            `;
        }).join('');
        $(target).html(`
            <table class="table table-sm table-hover align-middle">
                <thead><tr><th style="width:70px;">Select</th><th>Control No</th><th>Old Control No</th><th>Description</th><th>Brand</th><th>Serial No</th></tr></thead>
                <tbody>${rows || `<tr><td colspan="6" class="text-center text-muted p-3">${emptyMessage}</td></tr>`}</tbody>
            </table>
        `);
        reorderRows(target);
        syncSelectAll();
    }

    function renderAvailableEquipment() {
        renderAvailableList('equipment', '#availableEquipmentTable', 'No available equipment.');
    }

    function renderAvailableSupplies() {
        renderAvailableList('supplies', '#availableSuppliesTable', 'No available supplies.');
    }

    function reorderRows(target) {
        const tbody = $(`${target} tbody`);
        const rows = tbody.find('tr').get();
        rows.sort((a, b) => ($(b).find('.transmittal-item-check').is(':checked') ? 1 : 0) - ($(a).find('.transmittal-item-check').is(':checked') ? 1 : 0));
        rows.forEach(row => tbody.append(row));
    }

    function loadDepartmentItems(departmentId, currentTransmittalId) {
        $('#transmittalItemHint').removeClass('text-danger').text('Loading available unassigned equipment and supplies...').show();
        $.getJSON(actionUrl, {
            action: 'department_items',
            department_id: departmentId || '',
            transmittal_id: currentTransmittalId || ''
        }, function (res) {
            if (res.success) {
                setDepartmentItems(res.data.equipment || [], res.data.supplies || []);
                $('#transmittalItemHint').text('Choose unassigned equipment and supplies from the tabs to add them to this transmittal.').show();
            } else {
                setDepartmentItems([], []);
                $('#transmittalItemHint').addClass('text-danger').text(res.message || 'Unable to load equipment and supplies.').show();
            }
        }).fail(function (xhr) {
            setDepartmentItems([], []);
            $('#transmittalItemHint').addClass('text-danger').text((xhr.responseJSON && xhr.responseJSON.message) || 'Unable to load equipment and supplies.').show();
        });
    }

    function syncSelectAll() {
        const equipmentChecks = $('#availableEquipmentTable .transmittal-item-check');
        const supplyChecks = $('#availableSuppliesTable .transmittal-item-check');
        const visibleEquipment = equipmentChecks.filter(':visible');
        const visibleSupplies = supplyChecks.filter(':visible');
        $('#selectAllEquipment').prop('checked', visibleEquipment.length > 0 && visibleEquipment.length === visibleEquipment.filter(':checked').length);
        $('#selectAllSupplies').prop('checked', visibleSupplies.length > 0 && visibleSupplies.length === visibleSupplies.filter(':checked').length);
    }

    function renderItems() {
        transmittalItems = uniqueSelectedItems(transmittalItems);
        if (!transmittalItems.length) {
            $('#transmittalItems').html('<div class="text-muted small">No selected equipment/supplies yet.</div>');
            return;
        }
        $('#transmittalItems').html(`
            <div class="transmittal-item-table">
                <table class="table table-sm align-middle">
                    <thead><tr><th>Action</th><th>Type</th><th>Control No</th><th>Old Control No</th><th>Description</th><th>Brand</th><th>Serial No</th><th>Remarks</th></tr></thead>
                    <tbody>
                        ${transmittalItems.map((item, index) => `
                            <tr class="transmittal-item-row" data-index="${index}">
                                <td><button type="button" class="btn btn-sm btn-outline-danger remove-transmittal-item"><i class="bi bi-x-lg"></i> Remove</button></td>
                                <td>
                                    <span class="badge ${item.item_type === 'supply' ? 'bg-success-subtle text-success' : 'bg-primary-subtle text-primary'}">${escapeHtml((item.item_type || 'equipment').toUpperCase())}</span>
                                    <input type="hidden" class="tr-item-type" value="${escapeHtml(item.item_type || 'equipment')}">
                                    <input type="hidden" class="tr-item-source" value="${escapeHtml(item.source_id || '')}">
                                    <input type="hidden" class="tr-item-description-id" value="${escapeHtml(item.description_id || '')}">
                                    <input type="hidden" class="tr-item-brand-id" value="${escapeHtml(item.brand_id || '')}">
                                </td>
                                <td><input type="text" class="form-control tr-item-control" value="${escapeHtml(item.control_no || '')}"></td>
                                <td><input type="text" class="form-control tr-item-old-control" value="${escapeHtml(item.old_control_no || '')}"></td>
                                <td><input type="text" class="form-control tr-item-description" value="${escapeHtml(item.description_name || '')}"></td>
                                <td><input type="text" class="form-control tr-item-brand" value="${escapeHtml(item.brand_name || '')}"></td>
                                <td><input type="text" class="form-control tr-item-serial" value="${escapeHtml(item.serialno || '')}"></td>
                                <td><input type="text" class="form-control tr-item-remarks" value="${escapeHtml(item.remarks || '')}"></td>
                            </tr>`).join('')}
                    </tbody>
                </table>
            </div>
        `);
    }

    function renderViewItemsTable(items) {
        if (!items || !items.length) {
            return '<div class="text-muted">No items linked to this transmittal.</div>';
        }

        return `
            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Type</th>
                            <th>Control No</th>
                            <th>Old Control No</th>
                            <th>Description</th>
                            <th>Brand / Specs</th>
                            <th>Qty</th>
                            <th>Serial No</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${items.map(item => {
                            const itemType = item.item_type || 'equipment';
                            const badgeClass = itemType === 'manual'
                                ? 'bg-warning-subtle text-warning'
                                : itemType === 'supply'
                                    ? 'bg-success-subtle text-success'
                                : 'bg-primary-subtle text-primary';

                            return `
                                <tr>
                                    <td><span class="badge ${badgeClass}">${escapeHtml(itemType.toUpperCase())}</span></td>
                                    <td>${escapeHtml(item.control_no || '')}</td>
                                    <td>${escapeHtml(item.old_control_no || '')}</td>
                                    <td>${escapeHtml(item.description_name || item.description || '')}</td>
                                    <td>${escapeHtml(item.brand_name || item.brand || '')}</td>
                                    <td>${escapeHtml(item.qty || 1)}</td>
                                    <td>${escapeHtml(item.serialno || '')}</td>
                                    <td>${escapeHtml(item.remarks || '')}</td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }

    function collectItems() {
        const rows = $('#transmittalItems .transmittal-item-row');
        if (!rows.length) {
            return uniqueSelectedItems(transmittalItems);
        }

        const items = [];
        rows.each(function () {
            items.push({
                item_type: $(this).find('.tr-item-type').val() || 'equipment',
                source_id: $(this).find('.tr-item-source').val(),
                description_id: $(this).find('.tr-item-description-id').val(),
                brand_id: $(this).find('.tr-item-brand-id').val(),
                control_no: $(this).find('.tr-item-control').val(),
                old_control_no: $(this).find('.tr-item-old-control').val(),
                description_name: $(this).find('.tr-item-description').val(),
                brand_name: $(this).find('.tr-item-brand').val(),
                serialno: $(this).find('.tr-item-serial').val(),
                remarks: $(this).find('.tr-item-remarks').val()
            });
        });
        return uniqueSelectedItems(items);
    }

    function addManualRow(item = {}) {
        $('#manualContainer').append(`
            <div class="manual-row">
                <input type="text" class="form-control manual-description" placeholder="Description" value="${escapeHtml(item.description || item.description_name || '')}">
                <input type="text" class="form-control manual-brand" placeholder="Brand / Specs" value="${escapeHtml(item.brand || item.brand_name || '')}">
                <input type="number" class="form-control manual-qty" min="1" value="${escapeHtml(item.qty || 1)}">
                <input type="text" class="form-control manual-serial" placeholder="Serial No" value="${escapeHtml(item.serialno || '')}">
                <button type="button" class="btn btn-danger btn-sm remove-manual-row"><i class="bi bi-trash"></i></button>
            </div>
        `);
    }

    function collectManualItems() {
        const items = [];
        $('#manualContainer .manual-row').each(function () {
            items.push({
                description: $(this).find('.manual-description').val(),
                brand: $(this).find('.manual-brand').val(),
                qty: $(this).find('.manual-qty').val(),
                serialno: $(this).find('.manual-serial').val()
            });
        });
        return items;
    }

    function resetForm() {
        $('#transmittalForm')[0].reset();
        $('#transmittal_pk').val('');
        $('#transmittalModalTitle').text('New Transmittal');
        $('#transmittal_date').val(new Date().toISOString().slice(0, 10));
        setTransmittalItems([]);
        renderItems();
        $('#manualContainer').empty();
        addManualRow();
        $('.transmittal-select2').val(null).trigger('change.select2');
        $('#designation_id').val(null).trigger('change.select2');
        $('#accountable_officer').val('');
        setDepartmentItems([], []);
        loadNextTransmittalId(function () {
            loadDepartmentItems($('#department_id').val(), $('#transmittal_id').val());
        });
    }
    const transmittalFormGuard = window.IMSUnsavedGuard?.create({
        form: '#transmittalForm',
        modal: '#transmittalFormModal',
        dirtySelectors: [
            '#addManualRow',
            '.remove-manual-row',
            '.remove-transmittal-item',
            '#availableEquipmentTable tbody tr',
            '#availableSuppliesTable tbody tr'
        ]
    });

    $('#openTransmittalBtn').on('click', function () {
        loadMasterlists(function () {
            resetForm();
            modal('transmittalFormModal').show();
        });
    });

    $('#department_id').on('change', function () {
        setTransmittalItems([]);
        $('#accountable_officer').val($(this).find(':selected').data('head') || '');
        renderItems();
        loadDepartmentItems($(this).val(), $('#transmittal_id').val());
    });
    $('#transmittal_date').on('change', loadNextTransmittalId);
    $(document).on('click', '#availableEquipmentTable tbody tr, #availableSuppliesTable tbody tr', function (event) {
        if ($(event.target).is('input')) return;
        const checkbox = $(this).find('.transmittal-item-check');
        if (!checkbox.length) return;
        checkbox.prop('checked', !checkbox.prop('checked')).trigger('change');
    });
    $(document).on('change', '.transmittal-item-check', function () {
        const row = $(this).closest('tr');
        const type = row.data('type') || 'equipment';
        const sourceId = row.data('source-id');
        const item = findDepartmentItem(type, sourceId);
        row.toggleClass('selected-row', this.checked);
        if (item) {
            if (this.checked && !isSelected(item)) {
                transmittalItems = uniqueSelectedItems(transmittalItems.concat([item]));
            }
            if (!this.checked) {
                setTransmittalItems(transmittalItems.filter(selectedItem => !itemMatches(selectedItem, item)));
            }
            renderItems();
        }
        reorderRows(type === 'supply' ? '#availableSuppliesTable' : '#availableEquipmentTable');
        syncSelectAll();
    });
    $('#selectAllEquipment').on('change', function () {
        $('#availableEquipmentTable .transmittal-item-check:visible').prop('checked', this.checked).trigger('change');
    });
    $('#selectAllSupplies').on('change', function () {
        $('#availableSuppliesTable .transmittal-item-check:visible').prop('checked', this.checked).trigger('change');
    });
    $('#serialSearch').on('input', function () {
        const keyword = this.value.toLowerCase();
        $('#availableEquipmentTable tbody tr').each(function () {
            const text = $(this).text().toLowerCase();
            $(this).toggle(text.indexOf(keyword) > -1);
        });
        syncSelectAll();
    });
    $('#supplySearch').on('input', function () {
        const keyword = this.value.toLowerCase();
        $('#availableSuppliesTable tbody tr').each(function () {
            const text = $(this).text().toLowerCase();
            $(this).toggle(text.indexOf(keyword) > -1);
        });
        syncSelectAll();
    });
    $('#addManualRow').on('click', function () { addManualRow(); });
    $(document).on('click', '.remove-manual-row', function () {
        $(this).closest('.manual-row').remove();
    });
    $(document).on('click', '.remove-transmittal-item', function () {
        const row = $(this).closest('.transmittal-item-row');
        const type = row.find('.tr-item-type').val() || 'equipment';
        const sourceId = row.find('.tr-item-source').val();
        setTransmittalItems(transmittalItems.filter(item => !((item.item_type || 'equipment') === type && String(item.source_id) === String(sourceId))));
        $(`.transmittal-item-check`).each(function () {
            const pickerRow = $(this).closest('tr');
            if ((pickerRow.data('type') || 'equipment') === type && String(pickerRow.data('source-id')) === String(sourceId)) {
                $(this).prop('checked', false);
                pickerRow.removeClass('selected-row');
            }
        });
        renderItems();
        syncSelectAll();
    });

    $('#transmittalForm').on('submit', function (event) {
        event.preventDefault();
        const formData = new FormData(this);
        formData.append('action', $('#transmittal_pk').val() ? 'update' : 'create');
        formData.append('items', JSON.stringify(collectItems()));
        formData.append('manual_items', JSON.stringify(collectManualItems()));
        $.ajax({
            url: actionUrl,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function (res) {
                Swal.fire(res.success ? 'Saved' : 'Error', res.message, res.success ? 'success' : 'error');
                if (res.success) {
                    transmittalFormGuard?.forceClean();
                    modal('transmittalFormModal').hide();
                    table.ajax.reload(null, false);
                }
            },
            error: xhr => Swal.fire('Error', (xhr.responseJSON && xhr.responseJSON.message) || 'Unable to save transmittal.', 'error')
        });
    });

    $(document).on('click', '.view-transmittal-btn,.edit-transmittal-btn', function () {
        const id = $(this).data('id');
        const isEdit = $(this).hasClass('edit-transmittal-btn');
        $.getJSON(actionUrl, { action: 'get', id }, function (res) {
            if (!res.success) return Swal.fire('Error', res.message, 'error');
            if (!isEdit) {
                $('#transmittalViewBody').html(`
                    <div class="row g-3">
                        <div class="col-md-4"><strong>Transmittal ID</strong><div>${escapeHtml(res.data.transmittal_id)}</div></div>
                        <div class="col-md-4"><strong>Department</strong><div>${escapeHtml(res.data.department_name)}</div></div>
                        <div class="col-md-4"><strong>Designation</strong><div>${escapeHtml(res.data.designation_name || res.data.department_name || '')}</div></div>
                        <div class="col-md-4"><strong>Accountable Officer</strong><div>${escapeHtml(res.data.accountable_officer)}</div></div>
                    </div><hr>
                    <h6 class="fw-bold mb-2">Equipment / Supplies / Manual Items</h6>
                    ${renderViewItemsTable(res.items || [])}
                `);
                modal('transmittalViewModal').show();
                return;
            }
            loadMasterlists(function () {
                resetForm();
                $('#transmittalModalTitle').text('Edit Transmittal');
                Object.entries(res.data).forEach(([key, value]) => $('#' + key).val(value ?? ''));
                $('#transmittal_pk').val(res.data.id);
                $('#department_id').val(res.data.department_id).trigger('change.select2');
                $('#designation_id').val(res.data.designation_id || '').trigger('change.select2');
                $('#accountable_officer').val(res.data.accountable_officer || '');
                setTransmittalItems((res.items || []).filter(item => ['equipment', 'supply'].includes(item.item_type || 'equipment')));
                renderItems();
                loadDepartmentItems(res.data.department_id, res.data.transmittal_id);
                $('#manualContainer').empty();
                (res.items || []).filter(item => item.item_type === 'manual').forEach(addManualRow);
                if (!$('#manualContainer .manual-row').length) addManualRow();
                modal('transmittalFormModal').show();
            });
        });
    });

    $(document).on('click', '.archive-transmittal-btn', function () {
        const id = $(this).data('id');
        Swal.fire({ title: 'Archive transmittal?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#f59e0b' }).then(result => {
            if (!result.isConfirmed) return;
            $.post(actionUrl, { action: 'archive', id }, function (res) {
                Swal.fire(res.success ? 'Archived' : 'Error', res.message, res.success ? 'success' : 'error');
                table.ajax.reload(null, false);
            }, 'json');
        });
    });

    $(document).on('click', '.upload-transmittal-pdf-btn', function () {
        const panel = $(this).closest('.ims-upload-panel');
        const input = panel.find('.transmittal-pdf-input')[0];
        const file = input && input.files ? input.files[0] : null;
        const id = $(this).data('id');
        if (!file) {
            Swal.fire('Select file', 'Choose a PDF file first.', 'info');
            return;
        }
        if (file.size > 1024 * 1024) {
            Swal.fire('Error', 'PDF must not exceed 1MB.', 'error');
            return;
        }
        const formData = new FormData();
        formData.append('action', 'upload_pdf');
        formData.append('id', id);
        formData.append('attachment_pdf', file);
        $.ajax({
            url: actionUrl,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function (res) {
                Swal.fire(res.success ? 'Uploaded' : 'Error', res.message || 'Unable to upload PDF.', res.success ? 'success' : 'error');
                if (res.success) table.ajax.reload(null, false);
            },
            error: xhr => Swal.fire('Error', (xhr.responseJSON && xhr.responseJSON.message) || 'Unable to upload PDF.', 'error')
        });
    });

    $(document).on('click', '.remove-transmittal-pdf', function () {
        const id = $(this).data('id');
        Swal.fire({ title: 'Delete attachment?', text: 'This PDF file will be permanently removed.', icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33' }).then(result => {
            if (!result.isConfirmed) return;
            $.post(actionUrl, { action: 'remove_pdf', id }, function (res) {
                Swal.fire(res.success ? 'Removed' : 'Error', res.message || 'Unable to remove PDF.', res.success ? 'success' : 'error');
                if (res.success) table.ajax.reload(null, false);
            }, 'json');
        });
    });

    $(document).on('click', '.preview-transmittal-pdf', function () {
        $('#transmittalPdfFrame').attr('src', $(this).data('pdf'));
        modal('transmittalPdfModal').show();
    });

    $('#transmittalPdfModal').on('hidden.bs.modal', function () {
        $('#transmittalPdfFrame').attr('src', '');
    });
});
</script>

<?php require_once ROOT_PATH . '/modules/layout/footer.php'; ?>
