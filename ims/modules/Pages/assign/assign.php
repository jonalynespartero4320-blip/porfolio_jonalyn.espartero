<?php
$page_title = 'Assign Equipment and Supplies';
require_once ROOT_PATH . '/modules/layout/header.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/functions.php';

require_login();
require_permission('ictd.assign.view');

$requiredTables = ['assigned_record', 'list_end_user', 'list_department'];
$missingTables = array_values(array_filter($requiredTables, static fn(string $table): bool => !db_table_exists($pdo, $table)));
$canCreateAssign = can('ictd.assign.create');
$canDeleteAssign = can('ictd.assign.delete');
?>

<style>
  .assign-shell { display: grid; gap: 1rem; }
  .assign-head { display: flex; justify-content: space-between; gap: 1rem; align-items: flex-start; }
  .assign-eyebrow { color: #667085; font-size: .78rem; font-weight: 750; }
  .assign-title { margin: 0; color: #1f2937; font-size: 1.35rem; font-weight: 850; }
  .assign-card { border: 0; border-radius: 18px; background: rgba(255,255,255,.88); box-shadow: 0 18px 44px rgba(15,23,42,.08); overflow: hidden; }
  .assign-card .card-body { padding: 1rem; }
  .assign-section-title { display: flex; align-items: center; gap: .45rem; color: #263241; font-size: .82rem; font-weight: 900; text-transform: uppercase; letter-spacing: .02em; margin-bottom: .75rem; }
  .assign-section-title i { color: #3657f6; }
  .assign-muted-box { border: 1px solid #e7eefc; background: linear-gradient(135deg, rgba(54,87,246,.06), rgba(25,140,54,.05)); border-radius: 14px; padding: .9rem; }
  .assign-toolbar { display: flex; justify-content: flex-end; gap: .65rem; flex-wrap: wrap; margin-bottom: .8rem; }
  .assign-search { position: relative; min-width: min(320px, 100%); }
  .assign-search i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #98a2b3; }
  .assign-search input { height: 38px; padding-left: 34px; border-radius: 4px; border-color: #eaecf0; font-size: .78rem; }
  .assign-table { font-size: .8rem; }
  .assign-table thead th { white-space: nowrap; background: #f3f4f6 !important; border: 0 !important; color: #5f6672; font-size: .72rem; padding: .72rem .8rem !important; }
  .assign-table tbody td { border: 0 !important; border-bottom: 1px solid #f2f4f7 !important; padding: .72rem .8rem !important; white-space: nowrap; vertical-align: middle; }
  .assign-type-badge { display: inline-flex; border-radius: 999px; padding: .22rem .5rem; background: #eef4ff; color: #3657f6; font-weight: 800; font-size: .68rem; margin-right: .35rem; }
  .assign-actions { display: inline-flex; align-items: center; gap: .6rem; white-space: nowrap; }
  .assign-action-btn { border: 0; background: transparent; padding: 0; line-height: 1; font-size: .98rem; }
  .assign-action-edit { color: #1f9bb4; }
  .assign-action-reassign { color: #1f9bb4; }
  .assign-action-pdf { color: #d92d20; }
  .assign-action-delete { color: #e26060; }
  .assign-action-toggle { color: #3657f6; }
  .assign-record-toggle { border: 0; background: transparent; padding: 0; color: inherit; text-align: left; font: inherit; }
  .assign-record-toggle .text-muted { display: inline-block; max-width: 420px; overflow: hidden; text-overflow: ellipsis; vertical-align: bottom; white-space: nowrap; }
  .assign-items-child { margin: .25rem 0 .35rem 3rem; border: 1px solid #e7eefc; border-radius: 8px; overflow: hidden; background: #fff; }
  .assign-items-child table { width: 100%; margin: 0; font-size: .76rem; }
  .assign-items-child th { background: #f8fafc; color: #667085; font-size: .68rem; text-transform: uppercase; padding: .55rem .65rem; }
  .assign-items-child td { padding: .55rem .65rem; border-top: 1px solid #f2f4f7; }
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
  .assign-empty { border-radius: 20px; background: rgba(239,68,68,.08); border: 1px solid rgba(239,68,68,.12); padding: 1rem; }
  #assignFormModal .modal-content { border-radius: 18px; }
  #assignFormModal .modal-body { max-height: calc(100dvh - 170px); overflow-y: auto; }
  @media (max-width: 767px) {
    .assign-head { flex-direction: column; }
    .assign-head .btn, .assign-search { width: 100%; }
  }
</style>

<div class="container-fluid py-3">
    <div class="assign-shell">
        <div class="assign-head">
            <div>
                <div class="assign-eyebrow">Assignment</div>
                <h2 class="assign-title">Assign Equipment and Supplies</h2>
            </div>
            <?php if (empty($missingTables) && $canCreateAssign): ?>
                <button type="button" class="btn btn-primary btn-sm" id="openAssignFormModal">
                    <i class="bi bi-person-plus"></i> New Assignment
                </button>
            <?php endif; ?>
        </div>

        <?php if (!empty($missingTables)): ?>
            <div class="assign-empty">
                <div class="fw-semibold mb-1">Assignment module cannot load yet</div>
                <div class="text-muted small">Missing tables: <?= e(implode(', ', $missingTables)) ?>.</div>
            </div>
        <?php else: ?>
            <div class="card assign-card">
                <div class="card-body">
                    <div class="assign-toolbar">
                        <div class="assign-search">
                            <i class="bi bi-search"></i>
                            <input type="search" class="form-control" id="assignSearch" placeholder="Search assignment records">
                        </div>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="assignMineOnlyBtn" data-active="0">
                            <i class="bi bi-person-check"></i> My Records
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table id="assignTable" class="table align-middle nowrap mb-0 assign-table" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Action</th>
                                    <th>Upload File</th>
                                    <th>ID</th>
                                    <th>End User</th>
                                    <th>Department</th>
                                    <th>Item</th>
                                    <th>IP</th>
                                    <th>Internet</th>
                                    <th>WiFi</th>
                                    <th>Assigned Date</th>
                                    <th>Duration</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (empty($missingTables)): ?>
<?php if ($canCreateAssign): ?>
<div class="modal fade" id="assignFormModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title fw-bold">New Assignment</h5>
                    <div class="text-muted small">Select one user, then assign one or many equipment/supplies.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="assignForm">
                <div class="modal-body">
                            <div class="assign-section-title"><i class="bi bi-person-check"></i> End User and Items</div>
                            <div class="assign-muted-box mb-3">
                                <div class="row g-3">
                                    <div class="col-lg-4">
                                        <label class="form-label">End User *</label>
                                        <select class="form-select assign-select2" id="assign_end_user_id" required></select>
                                    </div>
                                    <div class="col-lg-4">
                                        <label class="form-label">Department</label>
                                        <input type="hidden" id="assign_department_id">
                                        <input type="text" class="form-control" id="assign_department_name" readonly>
                                    </div>
                                    <div class="col-lg-4">
                                        <label class="form-label">Assignment Date *</label>
                                        <input type="date" class="form-control" id="assign_assigned_date" required>
                                    </div>
                                    <div class="col-lg-6">
                                        <label class="form-label">Equipment</label>
                                        <select class="form-select assign-select2" id="assign_equipment_items" multiple></select>
                                        <div class="text-muted small mt-1">Search by equipment control no or serial no.</div>
                                    </div>
                                    <div class="col-lg-6">
                                        <label class="form-label">Supplies</label>
                                        <select class="form-select assign-select2" id="assign_supply_items" multiple></select>
                                        <div class="text-muted small mt-1">Search by supply code, control no, or serial no.</div>
                                    </div>
                                </div>
                            </div>

                            <div class="assign-section-title"><i class="bi bi-wifi"></i> Network and Software</div>
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Connection IP</label>
                                    <input type="text" class="form-control" id="assign_connection_ip" placeholder="192.168.1.10">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Internet</label>
                                    <select class="form-select" id="assign_internet">
                                        <option value="No">No</option>
                                        <option value="Yes">Yes</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">WiFi</label>
                                    <select class="form-select" id="assign_wifi">
                                        <option value="No">No</option>
                                        <option value="Yes">Yes</option>
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label">Usage Duration</label>
                                    <input type="text" class="form-control" id="assign_months_since_assigned" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Program Installed</label>
                                    <textarea class="form-control" id="assign_program_installed" rows="2" placeholder="Chrome, MS Office, AnyDesk..."></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Equipment Analysis</label>
                                    <textarea class="form-control" id="assign_equipment_analysis" rows="2"></textarea>
                                </div>
                            </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-person-plus"></i> Save Assignment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
<div class="modal fade" id="editAssignModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title fw-bold">Edit Assignment Details</h5>
                    <div class="text-muted small" id="edit_assign_control_no"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editAssignForm">
                <input type="hidden" id="edit_assign_assigned_id">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Connection IP</label>
                            <input type="text" class="form-control" id="edit_assign_connection_ip">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Internet</label>
                            <select class="form-select" id="edit_assign_internet">
                                <option value="No">No</option>
                                <option value="Yes">Yes</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">WiFi</label>
                            <select class="form-select" id="edit_assign_wifi">
                                <option value="No">No</option>
                                <option value="Yes">Yes</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Assigned Date</label>
                            <input type="date" class="form-control" id="edit_assign_assigned_date">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Usage Duration</label>
                            <input type="text" class="form-control" id="edit_assign_months_since_assigned" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Program Installed</label>
                            <textarea class="form-control" id="edit_assign_program_installed" rows="3"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Equipment Analysis</label>
                            <textarea class="form-control" id="edit_assign_equipment_analysis" rows="3"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Equipment Items</label>
                            <select class="form-select assign-select2" id="edit_assign_equipment_items" multiple></select>
                            <div class="text-muted small mt-1">Select equipment that should remain in this assignment.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Supply Items</label>
                            <select class="form-select assign-select2" id="edit_assign_supply_items" multiple></select>
                            <div class="text-muted small mt-1">Select supply items that should remain in this assignment.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="reassignModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content border-0">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title fw-bold">Reassign Item</h5>
                    <div class="text-muted small" id="reassign_control_no"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="reassignForm">
                <input type="hidden" id="reassign_assigned_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">New End User *</label>
                        <select class="form-select assign-select2" id="reassign_end_user_id" required></select>
                    </div>
                    <div>
                        <label class="form-label">New Department</label>
                        <input type="hidden" id="reassign_department_id">
                        <input type="text" class="form-control" id="reassign_department_name" readonly>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Reassign</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="assignPdfModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-pdf"></i> Assignment PDF Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <iframe id="assignPdfFrame" style="width:100%;height:75vh;border:0"></iframe>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const assignActionUrl = '<?= BASE_URL ?>/ictd/assign-action';
    const canDeleteAssign = <?= $canDeleteAssign ? 'true' : 'false' ?>;
    let assignMasterlists = { users: [], equipment: [], supplies: [] };

    const table = $('#assignTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '<?= BASE_URL ?>/ictd/assign-data',
            data: d => { d.mine_only = $('#assignMineOnlyBtn').data('active') === 1 ? 1 : 0; }
        },
        dom: 'rt<"row align-items-center"<"col-md-4"i><"col-md-4"l><"col-md-4"p>>',
        order: [[2, 'desc']],
        responsive: true,
        pageLength: 10,
        columnDefs: [{ targets: [0, 1], orderable: false, searchable: false }],
        language: {
            info: '_START_ - _END_ of _TOTAL_',
            lengthMenu: 'Rows per page _MENU_',
            paginate: {
                previous: '<i class="bi bi-chevron-left"></i>',
                next: '<i class="bi bi-chevron-right"></i>'
            },
            processing: 'Loading assignments...'
        }
    });

    $('#assignSearch').on('input', function () {
        table.search(this.value).draw();
    });

    $('#assignMineOnlyBtn').on('click', function () {
        const isActive = $(this).data('active') === 1;
        $(this).data('active', isActive ? 0 : 1).toggleClass('active', !isActive);
        table.ajax.reload();
    });

    $(document).on('click', '.toggle-items-btn', function (event) {
        event.stopPropagation();
        toggleAssignmentItems(this);
    });

    $('#assignTable tbody').on('click', 'tr', function (event) {
        if ($(event.target).closest('button, a, input, select, textarea, label').length) return;
        const toggle = $(this).find('.toggle-items-btn').first();
        if (toggle.length) {
            toggleAssignmentItems(toggle[0]);
        }
    });

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, function (char) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char];
        });
    }

    function options(items, placeholder) {
        let html = `<option value="">${escapeHtml(placeholder)}</option>`;
        (items || []).forEach(function (item) {
            html += `<option value="${escapeHtml(item.id)}" data-department-id="${escapeHtml(item.department_id || '')}" data-department-name="${escapeHtml(item.department_name || '')}">${escapeHtml(item.text)}</option>`;
        });
        return html;
    }

    function itemOptions(items) {
        return (items || []).map(function (item) {
            return `<option value="${escapeHtml(item.id)}">${escapeHtml(item.text)}</option>`;
        }).join('');
    }

    function itemOptionLabel(item) {
        if (!item) return 'Unknown item';
        const title = item.control_no || item.serialno || item.code || item.text || 'Item';
        const details = [];
        if (item.description_name) details.push(item.description_name);
        if (item.brand_name) details.push(item.brand_name);
        return title + (details.length ? ' | ' + details.join(' ') : '');
    }

    function formatAssignmentItems(items) {
        if (!items || items.length === 0) {
            return '<div class="assign-items-child"><div class="p-3 text-muted small">No item details found for this assignment.</div></div>';
        }
        const rows = items.map(item => `
            <tr>
                <td><span class="assign-type-badge">${escapeHtml(item.item_type || '')}</span></td>
                <td><strong>${escapeHtml(item.control_no || '')}</strong></td>
                <td>${escapeHtml(item.old_control_no || '')}</td>
                <td>${escapeHtml(item.description_name || '')}</td>
                <td>${escapeHtml(item.brand_name || '')}</td>
                <td>${escapeHtml(item.serialno || '')}</td>
                <td>${escapeHtml(item.item_status || '')}</td>
            </tr>
        `).join('');
        return `
            <div class="assign-items-child">
                <table>
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Code / Control</th>
                            <th>Old Control</th>
                            <th>Description</th>
                            <th>Brand</th>
                            <th>Serial No</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>
        `;
    }

    function toggleAssignmentItems(button) {
        const tr = $(button).closest('tr');
        const row = table.row(tr);
        const id = $(button).data('id');
        if (row.child.isShown()) {
            row.child.hide();
            tr.removeClass('shown');
            tr.find('.toggle-items-btn i').removeClass('bi-chevron-down').addClass('bi-chevron-right');
            return;
        }
        tr.find('.toggle-items-btn i').removeClass('bi-chevron-right').addClass('bi-chevron-down');
        row.child('<div class="assign-items-child"><div class="p-3 text-muted small">Loading items...</div></div>').show();
        $.getJSON(assignActionUrl, { action: 'view', id: id }).done(function (res) {
            row.child(formatAssignmentItems(res.success ? (res.data.items || []) : [])).show();
            tr.addClass('shown');
        }).fail(function () {
            row.child('<div class="assign-items-child"><div class="p-3 text-danger small">Unable to load items.</div></div>').show();
        });
    }

    function initSelect2() {
        if (!$.fn.select2) return;
        $('.assign-select2').each(function () {
            const parentModal = $(this).closest('.modal');
            $(this).select2({
                width: '100%',
                dropdownParent: parentModal.length ? parentModal : $(document.body)
            });
        });
    }

    function durationLabel(dateValue) {
        if (!dateValue) return '';
        const start = new Date(`${dateValue}T00:00:00`);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        if (Number.isNaN(start.getTime()) || start > today) return '0 days';
        const days = Math.floor((today - start) / 86400000);
        if (days >= 365) {
            const years = Math.floor(days / 365);
            return `${years} year${years === 1 ? '' : 's'}`;
        }
        if (days >= 30) {
            const months = Math.floor(days / 30);
            return `${months} month${months === 1 ? '' : 's'}`;
        }
        return `${days} day${days === 1 ? '' : 's'}`;
    }

    function loadMasterlists(callback) {
        $.getJSON(assignActionUrl, { action: 'masterlists' }).done(function (res) {
            if (!res.success) return;
            assignMasterlists = res.data || assignMasterlists;
            $('#assign_end_user_id').html(options(assignMasterlists.users, 'Select user'));
            $('#reassign_end_user_id').html(options(assignMasterlists.users, 'Select user'));
            $('#assign_equipment_items').html(itemOptions(assignMasterlists.equipment));
            $('#assign_supply_items').html(itemOptions(assignMasterlists.supplies));
            $('#edit_assign_equipment_items').html(itemOptions(assignMasterlists.equipment));
            $('#edit_assign_supply_items').html(itemOptions(assignMasterlists.supplies));
            initSelect2();
            callback?.();
        });
    }

    $('#assign_assigned_date').val(new Date().toISOString().slice(0, 10));
    $('#assign_months_since_assigned').val(durationLabel($('#assign_assigned_date').val()));
    loadMasterlists();
    const assignFormGuard = window.IMSUnsavedGuard?.create({
        form: '#assignForm',
        modal: '#assignFormModal'
    });
    const editAssignFormGuard = window.IMSUnsavedGuard?.create({
        form: '#editAssignForm',
        modal: '#editAssignModal'
    });
    const reassignFormGuard = window.IMSUnsavedGuard?.create({
        form: '#reassignForm',
        modal: '#reassignModal'
    });

    $('#openAssignFormModal').on('click', function () {
        loadMasterlists(function () {
            const modal = document.getElementById('assignFormModal');
            if (window.bootstrap && modal) {
                bootstrap.Modal.getOrCreateInstance(modal).show();
            } else {
                $('#assignFormModal').modal('show');
            }
        });
    });

    $('#assign_end_user_id').on('change', function () {
        const selected = $(this).find(':selected');
        $('#assign_department_id').val(selected.data('department-id') || '');
        $('#assign_department_name').val(selected.data('department-name') || '');
    });

    $('#assign_assigned_date').on('change', function () {
        $('#assign_months_since_assigned').val(durationLabel(this.value));
    });

    $('#edit_assign_assigned_date').on('change', function () {
        $('#edit_assign_months_since_assigned').val(durationLabel(this.value));
    });

    $('#assignForm').on('submit', function (event) {
        event.preventDefault();
        const selectedEquipment = $('#assign_equipment_items').val() || [];
        const selectedSupplies = $('#assign_supply_items').val() || [];

        if (selectedEquipment.length === 0 && selectedSupplies.length === 0) {
            Swal.fire('Select items', 'Choose at least one equipment or supply item to assign.', 'info');
            return;
        }

        $.ajax({
            url: assignActionUrl,
            method: 'POST',
            contentType: 'application/json',
            dataType: 'json',
            data: JSON.stringify({
                action: 'create',
                end_user_id: $('#assign_end_user_id').val(),
                department_id: $('#assign_department_id').val(),
                equipment_items: selectedEquipment,
                supply_items: selectedSupplies,
                connection_ip: $('#assign_connection_ip').val(),
                internet: $('#assign_internet').val(),
                wifi: $('#assign_wifi').val(),
                program_installed: $('#assign_program_installed').val(),
                assigned_date: $('#assign_assigned_date').val(),
                months_since_assigned: $('#assign_months_since_assigned').val(),
                equipment_analysis: $('#assign_equipment_analysis').val()
            }),
            beforeSend: function () {
                Swal.fire({ title: 'Saving assignment...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            },
            success: function (res) {
                if (!res.success) {
                    Swal.fire('Error', res.message || 'Unable to save assignment.', 'error');
                    return;
                }

                Swal.fire('Assigned', res.message, 'success');
                assignFormGuard?.forceClean();
                $('#assign_equipment_items').val(null).trigger('change');
                $('#assign_supply_items').val(null).trigger('change');
                $('#assignFormModal').modal('hide');
                table.ajax.reload(null, false);
                loadMasterlists();
            },
            error: function (xhr) {
                Swal.fire('Error', xhr.responseJSON?.message || 'Unable to save assignment.', 'error');
            }
        });
    });

    $('#reassign_end_user_id').on('change', function () {
        const selected = $(this).find(':selected');
        $('#reassign_department_id').val(selected.data('department-id') || '');
        $('#reassign_department_name').val(selected.data('department-name') || '');
    });

    $(document).on('click', '.edit-record-btn', function () {
        const id = $(this).data('id');
        loadMasterlists(function () {
            $.getJSON(assignActionUrl, { action: 'view', id: id }).done(function (res) {
                if (!res.success) {
                    Swal.fire('Error', res.message || 'Unable to load assignment.', 'error');
                    return;
                }

                $('#edit_assign_assigned_id').val(id);
                $('#edit_assign_control_no').text(`Assignment record: #${id}`);
                $('#edit_assign_connection_ip').val(res.data.connection_ip || '');
                $('#edit_assign_internet').val(res.data.internet || 'No');
                $('#edit_assign_wifi').val(res.data.wifi || 'No');
                $('#edit_assign_program_installed').val(res.data.program_installed || '');
                $('#edit_assign_assigned_date').val(res.data.assigned_date || '');
                $('#edit_assign_months_since_assigned').val(res.data.months_since_assigned || durationLabel(res.data.assigned_date || ''));
                $('#edit_assign_equipment_analysis').val(res.data.equipment_analysis || '');

                const selectedEquipment = [];
                const selectedSupplies = [];
                (res.data.items || []).forEach(function (item) {
                    if (!item.item_key) return;
                    if (item.item_type === 'equipment') {
                        if ($('#edit_assign_equipment_items option[value="' + escapeHtml(item.item_key) + '"]').length === 0) {
                            $('#edit_assign_equipment_items').append(`<option value="${escapeHtml(item.item_key)}">${escapeHtml(itemOptionLabel(item))}</option>`);
                        }
                        selectedEquipment.push(item.item_key);
                    }
                    if (item.item_type === 'supply') {
                        if ($('#edit_assign_supply_items option[value="' + escapeHtml(item.item_key) + '"]').length === 0) {
                            $('#edit_assign_supply_items').append(`<option value="${escapeHtml(item.item_key)}">${escapeHtml(itemOptionLabel(item))}</option>`);
                        }
                        selectedSupplies.push(item.item_key);
                    }
                });

                $('#edit_assign_equipment_items').val(selectedEquipment).trigger('change');
                $('#edit_assign_supply_items').val(selectedSupplies).trigger('change');
                bootstrap.Modal.getOrCreateInstance(document.getElementById('editAssignModal')).show();
            });
        });
    });

    $('#editAssignForm').on('submit', function (event) {
        event.preventDefault();
        const selectedEquipment = $('#edit_assign_equipment_items').val() || [];
        const selectedSupplies = $('#edit_assign_supply_items').val() || [];
        if (selectedEquipment.length === 0 && selectedSupplies.length === 0) {
            Swal.fire('Select items', 'Choose at least one equipment or supply item to keep assigned.', 'info');
            return;
        }

        $.ajax({
            url: assignActionUrl,
            method: 'POST',
            contentType: 'application/json',
            dataType: 'json',
            data: JSON.stringify({
                action: 'update',
                assigned_id: $('#edit_assign_assigned_id').val(),
                connection_ip: $('#edit_assign_connection_ip').val(),
                internet: $('#edit_assign_internet').val(),
                wifi: $('#edit_assign_wifi').val(),
                program_installed: $('#edit_assign_program_installed').val(),
                assigned_date: $('#edit_assign_assigned_date').val(),
                months_since_assigned: $('#edit_assign_months_since_assigned').val(),
                equipment_analysis: $('#edit_assign_equipment_analysis').val(),
                equipment_items: selectedEquipment,
                supply_items: selectedSupplies
            }),
            beforeSend: function () {
                Swal.fire({ title: 'Saving assignment...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            },
            success: function (res) {
                if (!res.success) {
                    Swal.fire('Error', res.message || 'Unable to update assignment.', 'error');
                    return;
                }
                editAssignFormGuard?.forceClean();
                bootstrap.Modal.getOrCreateInstance(document.getElementById('editAssignModal')).hide();
                Swal.fire('Saved', res.message, 'success');
                table.ajax.reload(null, false);
            },
            error: function (xhr) {
                Swal.fire('Error', xhr.responseJSON?.message || 'Unable to update assignment.', 'error');
            }
        });
    });

    $(document).on('click', '.reassign-record-btn', function () {
        const id = $(this).data('id');
        loadMasterlists(function () {
            $.getJSON(assignActionUrl, { action: 'view', id: id }).done(function (res) {
                if (!res.success) {
                    Swal.fire('Error', res.message || 'Unable to load assignment.', 'error');
                    return;
                }
                $('#reassign_assigned_id').val(id);
                $('#reassign_control_no').text(`Assignment record: #${id}`);
                $('#reassign_end_user_id').val(res.data.end_user_id || '').trigger('change');
                $('#reassign_department_id').val(res.data.department_id || '');
                $('#reassign_department_name').val(res.data.department_name || '');
                bootstrap.Modal.getOrCreateInstance(document.getElementById('reassignModal')).show();
            });
        });
    });

    $('#reassignForm').on('submit', function (event) {
        event.preventDefault();
        $.ajax({
            url: assignActionUrl,
            method: 'POST',
            contentType: 'application/json',
            dataType: 'json',
            data: JSON.stringify({
                action: 'reassign',
                assigned_id: $('#reassign_assigned_id').val(),
                end_user_id: $('#reassign_end_user_id').val(),
                department_id: $('#reassign_department_id').val()
            }),
            beforeSend: function () {
                Swal.fire({ title: 'Reassigning...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            },
            success: function (res) {
                if (!res.success) {
                    Swal.fire('Error', res.message || 'Unable to reassign item.', 'error');
                    return;
                }
                reassignFormGuard?.forceClean();
                bootstrap.Modal.getOrCreateInstance(document.getElementById('reassignModal')).hide();
                Swal.fire('Reassigned', res.message, 'success');
                table.ajax.reload(null, false);
            },
            error: function (xhr) {
                Swal.fire('Error', xhr.responseJSON?.message || 'Unable to reassign item.', 'error');
            }
        });
    });

    $(document).on('click', '.unassign-record-btn', function () {
        if (!canDeleteAssign) {
            Swal.fire('Unavailable', 'You do not have permission to unassign records.', 'info');
            return;
        }

        const id = $(this).data('id');
        Swal.fire({
            title: 'Unassign record?',
            text: 'This will remove the assignment and make all included equipment/supplies available again.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, unassign it',
            confirmButtonColor: '#d92d20'
        }).then(function (result) {
            if (!result.isConfirmed) return;

            $.ajax({
                url: assignActionUrl,
                method: 'POST',
                contentType: 'application/json',
                dataType: 'json',
                data: JSON.stringify({ action: 'delete', assigned_id: id }),
                success: function (res) {
                    if (!res.success) {
                        Swal.fire('Error', res.message || 'Unable to unassign item.', 'error');
                        return;
                    }
                    Swal.fire('Unassigned', res.message, 'success');
                    table.ajax.reload(null, false);
                    loadMasterlists();
                },
                error: function (xhr) {
                    Swal.fire('Error', xhr.responseJSON?.message || 'Unable to unassign item.', 'error');
                }
            });
        });
    });

    $(document).on('click', '.upload-assign-pdf-btn', function () {
        const panel = $(this).closest('.ims-upload-panel');
        const input = panel.find('.assign-pdf-input')[0];
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
        formData.append('assigned_id', id);
        formData.append('attachment_pdf', file);
        $.ajax({
            url: assignActionUrl,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function (res) {
                Swal.fire(res.success ? 'Uploaded' : 'Error', res.message || 'Unable to upload PDF.', res.success ? 'success' : 'error');
                if (res.success) table.ajax.reload(null, false);
            },
            error: function (xhr) {
                Swal.fire('Error', xhr.responseJSON?.message || 'Unable to upload PDF.', 'error');
            }
        });
    });

    $(document).on('click', '.remove-assign-pdf', function () {
        const id = $(this).data('id');
        Swal.fire({ title: 'Delete attachment?', text: 'This PDF file will be permanently removed.', icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33' })
            .then(function (result) {
                if (!result.isConfirmed) return;
                $.post(assignActionUrl, { action: 'remove_pdf', assigned_id: id }, function (res) {
                    Swal.fire(res.success ? 'Removed' : 'Error', res.message || 'Unable to remove PDF.', res.success ? 'success' : 'error');
                    if (res.success) table.ajax.reload(null, false);
                }, 'json');
            });
    });

    $(document).on('click', '.preview-assign-pdf', function () {
        $('#assignPdfFrame').attr('src', $(this).data('pdf'));
        bootstrap.Modal.getOrCreateInstance(document.getElementById('assignPdfModal')).show();
    });

    $('#assignPdfModal').on('hidden.bs.modal', function () {
        $('#assignPdfFrame').attr('src', '');
    });
});
</script>
<?php endif; ?>

<?php require_once ROOT_PATH . '/modules/layout/footer.php'; ?>
