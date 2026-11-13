<?php
$page_title = 'Maintenance Reports';
require_once ROOT_PATH . '/modules/layout/header.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/functions.php';

require_login();
require_permission('ictd.maintenance.view');

$maintenanceDepartments = $pdo->query('SELECT department_id AS id, department_name AS text FROM list_department ORDER BY department_name ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
$maintenanceUsers = $pdo->query("
    SELECT eu.end_user_id AS id, eu.end_user_name AS text, eu.department_id, dep.department_name
    FROM list_end_user eu
    LEFT JOIN list_department dep ON dep.department_id = eu.department_id
    ORDER BY eu.end_user_name ASC
")->fetchAll(PDO::FETCH_ASSOC) ?: [];
$maintenanceTechnicians = db_table_exists($pdo, 'list_technical')
    ? ($pdo->query('SELECT technical_name AS id, technical_name AS text FROM list_technical ORDER BY technical_name ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [])
    : [];
?>

<style>
  .maintenance-shell { display: grid; gap: 1rem; }
  .maintenance-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; }
  .maintenance-eyebrow { color: #667085; font-size: .78rem; font-weight: 750; }
  .maintenance-title { margin: 0; color: #1f2937; font-size: 1.35rem; font-weight: 850; }
  .maintenance-toolbar { display: flex; justify-content: space-between; align-items: center; gap: .75rem; flex-wrap: wrap; }
  .maintenance-filter { min-width: min(260px, 100%); }
  .maintenance-search { width: min(360px, 100%); }
  .maintenance-search .input-group-text { background: #fff; border-color: #e4e7ec; color: #667085; }
  .maintenance-search .form-control { border-color: #e4e7ec; font-size: .82rem; }
  .maintenance-search .form-control:focus { border-color: #0aa99a; box-shadow: 0 0 0 .18rem rgba(10,169,154,.12); }
  .maintenance-card { border: 0; border-radius: 16px; background: rgba(255,255,255,.9); box-shadow: 0 18px 44px rgba(15,23,42,.08); overflow: hidden; }
  .maintenance-table { font-size: .8rem; }
  .maintenance-table thead th { white-space: nowrap; background: #f3f4f6 !important; border: 0 !important; color: #5f6672; font-size: .72rem; padding: .72rem .8rem !important; }
  .maintenance-table tbody td { border: 0 !important; border-bottom: 1px solid #f2f4f7 !important; padding: .72rem .8rem !important; white-space: nowrap; vertical-align: middle; }
  .maintenance-control { font-weight: 850; color: #1f2937; }
  .maintenance-actions { display: inline-flex; align-items: center; gap: .55rem; }
  .maintenance-action-btn { border: 0; background: transparent; padding: 0; line-height: 1; font-size: .95rem; }
  .maintenance-action-view { color: #16a085; }
  .maintenance-action-edit { color: #1f9bb4; }
  .maintenance-action-pdf { color: #d92d20; }
  .maintenance-action-delete { color: #e26060; }
  .maintenance-action-toggle { color: #3657f6; }
  .maintenance-item-toggle { border: 0; background: transparent; padding: 0; }
  .maintenance-items-child { margin: .25rem 0 .35rem 3rem; border: 1px solid #e7eefc; border-radius: 8px; overflow: hidden; background: #fff; }
  .maintenance-items-child table { width: 100%; margin: 0; font-size: .76rem; }
  .maintenance-items-child th { background: #f8fafc; color: #667085; font-size: .68rem; text-transform: uppercase; padding: .55rem .65rem; }
  .maintenance-items-child td { padding: .55rem .65rem; border-top: 1px solid #f2f4f7; }
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
  .maintenance-form-section { border: 1px solid #edf0f5; border-radius: 14px; background: #fbfcff; padding: 1rem; margin-bottom: 1rem; }
  .maintenance-section-title { display: flex; align-items: center; gap: .45rem; color: #263241; font-size: .82rem; font-weight: 900; text-transform: uppercase; letter-spacing: .02em; margin-bottom: .8rem; }
  .maintenance-section-title i { color: #0aa99a; }
  .maintenance-check-tabs { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: .85rem; }
  .maintenance-check-tab { border: 1px solid #e4e7ec; background: #fff; color: #344054; border-radius: 10px; padding: .5rem .85rem; font-size: .8rem; font-weight: 800; }
  .maintenance-check-tab.active { background: #0aa99a; color: #fff; border-color: #0aa99a; box-shadow: 0 10px 20px rgba(10,169,154,.18); }
  .maintenance-check-panel { display: none; background: #fff; border: 1px solid #edf0f5; border-radius: 14px; padding: 1rem; }
  .maintenance-check-panel.active { display: block; }
  .maintenance-check-row { display: flex; align-items: center; gap: .55rem; margin-bottom: .55rem; color: #1f2937; }
  .maintenance-item-row { border: 1px solid #edf0f5; border-radius: 12px; background: #fff; padding: .8rem; margin-bottom: .7rem; }
  .maintenance-item-head { display: flex; justify-content: space-between; align-items: center; gap: .75rem; padding-bottom: .6rem; margin-bottom: .75rem; border-bottom: 1px solid #f2f4f7; }
  .maintenance-remove-item { border: 0; width: 34px; height: 34px; border-radius: 10px; background: #fff1f1; color: #d92d20; display: inline-flex; align-items: center; justify-content: center; }
  .maintenance-selected-table { border: 1px solid #edf0f5; border-radius: 12px; overflow: auto; background: #fff; }
  .maintenance-selected-table table { margin: 0; min-width: 860px; font-size: .77rem; }
  .maintenance-selected-table th { background: #f8fafc; color: #667085; white-space: nowrap; }
  .maintenance-selected-table td { vertical-align: middle; white-space: nowrap; }
  .maintenance-selected-table .form-control { min-width: 130px; height: 34px; font-size: .77rem; }
  .maintenance-selected-table .maintenance-item-description { min-width: 180px; }
  .maintenance-assigned-wrap { display: none; margin-bottom: 1rem; }
  .maintenance-assigned-wrap.active { display: block; }
  .maintenance-assigned-table { max-height: 230px; overflow: auto; border: 1px solid #edf0f5; border-radius: 12px; background: #fff; }
  .maintenance-assigned-table table { margin: 0; font-size: .76rem; }
  .maintenance-assigned-table th { position: sticky; top: 0; background: #f8fafc; z-index: 1; color: #667085; white-space: nowrap; }
  .maintenance-assigned-table td { white-space: nowrap; vertical-align: middle; }
  #maintenanceFormModal .modal-dialog { width: min(1220px, calc(100vw - 24px)); max-width: min(1220px, calc(100vw - 24px)); margin: .75rem auto; }
  #maintenanceFormModal .modal-content { max-height: calc(100dvh - 1.5rem); display: flex; flex-direction: column; overflow: hidden; }
  #maintenanceFormModal form { flex: 1 1 auto; min-height: 0; display: flex; flex-direction: column; }
  #maintenanceFormModal .modal-body { flex: 1 1 auto; min-height: 0; overflow-y: auto; }
  #maintenanceFormModal .modal-footer { flex: 0 0 auto; background: #fff; box-shadow: 0 -10px 24px rgba(15,23,42,.08); }
  @media (max-width: 767px) {
    .maintenance-head { flex-direction: column; }
    .maintenance-head .btn, .maintenance-filter, .maintenance-search { width: 100%; }
  }
</style>

<div class="container-fluid py-3">
    <div class="maintenance-shell">
        <div class="maintenance-head">
            <div>
                <div class="maintenance-eyebrow">Reports</div>
                <h2 class="maintenance-title">Maintenance Reports</h2>
                <div class="text-muted small">Track maintenance checklist, documentation, linked equipment, and uploaded PDFs.</div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="<?= BASE_URL ?>/ictd/maintenance/archive" class="btn btn-outline-secondary btn-sm"><i class="bi bi-archive"></i> Archive</a>
                <button type="button" class="btn btn-primary btn-sm" id="openMaintenanceFormBtn" data-bs-toggle="modal" data-bs-target="#maintenanceFormModal">
                    <i class="bi bi-plus-lg"></i> New Maintenance
                </button>
            </div>
        </div>

        <div class="maintenance-toolbar">
            <div class="d-flex gap-2 flex-wrap align-items-center">
                <div class="maintenance-search">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="search" id="maintenanceSearch" class="form-control" placeholder="Search ID, control no, serial, user, department...">
                    </div>
                </div>
                <div class="maintenance-filter">
                    <select id="maintenanceDeptFilter" class="form-select form-select-sm">
                        <option value="">All Departments</option>
                        <?php foreach ($maintenanceDepartments as $department): ?>
                            <option value="<?= e($department['text']) ?>"><?= e($department['text']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="d-flex gap-2 align-items-center flex-wrap">
                <button type="button" class="btn btn-outline-primary btn-sm" id="maintenanceMineOnlyBtn" data-active="0">
                    <i class="bi bi-person-check"></i> My Records
                </button>
                <div class="text-muted small"><i class="bi bi-bar-chart-line me-1"></i>Total Records: <strong id="maintenanceTotalCount">0</strong></div>
            </div>
        </div>

        <div class="card maintenance-card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="maintenanceTable" class="table align-middle nowrap mb-0 maintenance-table" style="width:100%">
                        <thead>
                            <tr>
                                <th>Action</th>
                                <th>Upload Report</th>
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
</div>

<div class="modal fade" id="maintenanceFormModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title fw-bold" id="maintenanceModalTitle">New Maintenance Report</h5>
                    <div class="text-muted small">Maintenance ID is generated from the accomplished date year.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="maintenanceForm" enctype="multipart/form-data">
                <input type="hidden" id="maintenance_pk" name="id">
                <div class="modal-body">
                    <div class="maintenance-form-section">
                        <div class="maintenance-section-title"><i class="bi bi-ui-checks"></i> Maintenance Checklist</div>
                        <div class="d-flex justify-content-end gap-2 mb-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="selectAllChecks"><i class="bi bi-check2-all"></i> Select All</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="deselectAllChecks"><i class="bi bi-x-circle"></i> Deselect All</button>
                        </div>
                        <div class="maintenance-check-tabs" id="maintenanceCheckTabs"></div>
                        <div id="maintenanceCheckPanels"></div>
                    </div>

                    <div class="row g-3">
                        <div class="col-lg-5">
                            <div class="maintenance-form-section h-100">
                                <div class="maintenance-section-title"><i class="bi bi-card-text"></i> Documentation</div>
                                <div class="row g-3">
                                    <div class="col-12"><label class="form-label">Maintenance ID</label><input type="text" class="form-control" id="maintenance_id" name="maintenance_id" placeholder="Auto generated if blank"></div>
                                    <div class="col-md-6"><label class="form-label">Control No</label><input type="text" class="form-control" id="control_no" name="control_no" placeholder="Auto from first selected assigned item"></div>
                                    <div class="col-md-6">
                                        <label class="form-label">Control No Option</label>
                                        <select class="form-select" id="control_no_source">
                                            <option value="current">Use New Generated Control No</option>
                                            <option value="old">Use Old Control No</option>
                                        </select>
                                        <div class="text-muted small">Applies to the main report control no from the first selected item.</div>
                                    </div>
                                    <div class="col-md-6"><label class="form-label">Branded</label><input type="text" class="form-control" id="branded" name="branded" readonly></div>
                                    <div class="col-md-6"><label class="form-label">Assembled</label><input type="text" class="form-control" id="assemble" name="assemble" readonly></div>
                                    <div class="col-12">
                                        <label class="form-label">End User</label>
                                        <select class="form-select maintenance-select2" id="end_user_id" name="end_user_id">
                                            <option value="">Select end user</option>
                                            <?php foreach ($maintenanceUsers as $endUser): ?>
                                                <option value="<?= e($endUser['id']) ?>" data-department-id="<?= e($endUser['department_id']) ?>" data-department-name="<?= e($endUser['department_name']) ?>"><?= e($endUser['text']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-12"><label class="form-label">Department</label><input type="hidden" id="department_id" name="department_id"><input type="text" class="form-control" id="department_name" readonly></div>
                                    <div class="col-12">
                                        <label class="form-label">Designation</label>
                                        <select class="form-select maintenance-select2" id="designation_id" name="designation_id">
                                            <option value="">Select designation</option>
                                            <?php foreach ($maintenanceDepartments as $department): ?>
                                                <option value="<?= e($department['id']) ?>"><?= e($department['text']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6"><label class="form-label">Date Accomplished *</label><input type="date" class="form-control" id="date_accomplished" name="date_accomplished" required></div>
                                    <div class="col-md-6">
                                        <label class="form-label">Inspected By *</label>
                                        <select class="form-select maintenance-select2" id="inspected_by" name="inspected_by" required>
                                            <option value="">Select technician</option>
                                            <?php foreach ($maintenanceTechnicians as $technician): ?>
                                                <option value="<?= e($technician['id']) ?>"><?= e($technician['text']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-7">
                            <div class="maintenance-form-section h-100">
                                <div class="maintenance-section-title"><i class="bi bi-pc-display"></i> Assigned Equipment / Supplies</div>
                                <div class="alert alert-info py-2 small mb-3" id="maintenanceUserHint">Select an end user first. The department and assigned equipment/supplies will load automatically.</div>
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" role="switch" id="maintenanceAllItemsMode">
                                    <label class="form-check-label small fw-bold" for="maintenanceAllItemsMode">Select from all equipment and supplies</label>
                                    <div class="text-muted small">Use this for old/manual records where the equipment is now assigned to another end user.</div>
                                </div>
                                <div class="row g-2 align-items-end mb-3">
                                    <div class="col-md-6"><label class="form-label">Equipment</label><select class="form-select maintenance-select2" id="maintenanceEquipmentPicker"></select></div>
                                    <div class="col-md-6"><label class="form-label">Supplies</label><select class="form-select maintenance-select2" id="maintenanceSupplyPicker"></select></div>
                                </div>
                                <div class="maintenance-assigned-wrap" id="maintenanceAssignedWrap">
                                    <div class="row g-3">
                                        <div class="col-lg-6">
                                            <div class="fw-bold small mb-2">Assigned Equipment</div>
                                            <div class="maintenance-assigned-table" id="maintenanceEquipmentTable"></div>
                                        </div>
                                        <div class="col-lg-6">
                                            <div class="fw-bold small mb-2">Assigned Supplies</div>
                                            <div class="maintenance-assigned-table" id="maintenanceSupplyTable"></div>
                                        </div>
                                    </div>
                                </div>
                                <div id="maintenanceItems"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-save"></i> Save Report</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="maintenanceViewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Maintenance Report Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="maintenanceViewBody"></div>
        </div>
    </div>
</div>

<div class="modal fade" id="maintenancePdfModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-pdf"></i> Maintenance Report PDF</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0"><iframe id="maintenancePdfFrame" style="width:100%;height:75vh;border:0"></iframe></div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const actionUrl = '<?= BASE_URL ?>/ictd/maintenance-action';
    let masterlists = { departments: [], technicians: [], users: [], equipment: [], supplies: [] };
    let maintenanceItems = [];
    const checklist = {
        'Hardware Maintenance': [
            'Physically inspect for damage, loose cables, or components.',
            'Clean the computer and peripherals.',
            'Remove dust and debris from inside using compressed air.',
            'Assess hardware upgrades (e.g., RAM, hard drive).',
            'Inspect and clean peripheral devices (e.g., mouse, keyboard, printer).'
        ],
        'Software Maintenance': [
            'Install operating system updates and patches.',
            'Update antivirus software and run a full system scan.',
            'Update and patch all software applications.',
            'Regularly back up important data.',
            'Perform disk cleanup to optimize storage.',
            'Review and update security settings and passwords.',
            'Organize files and folders for efficient data retrieval.'
        ],
        'Performance Testing': [
            'Run system performance tests or diagnostics.',
            'Test internet speed and connection stability.'
        ],
        'Backup and Recovery': [
            'Ensure there is a data recovery plan.',
            'Test data recovery procedures.'
        ],
        'Security Checks': [
            'Confirm the firewall is active and configured correctly.',
            'Verify that security software is functioning.',
            'Monitor for security alerts and threats.'
        ],
        'Internet Connection': [],
        Documentation: [
            'Maintain records of all maintenance activities.'
        ]
    };

    const table = $('#maintenanceTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '<?= BASE_URL ?>/ictd/maintenance-data',
            type: 'POST',
            data: d => {
                d.department = $('#maintenanceDeptFilter').val();
                d.mine_only = $('#maintenanceMineOnlyBtn').data('active') === 1 ? 1 : 0;
            },
            dataSrc: json => {
                $('#maintenanceTotalCount').text(json.recordsTotal || 0);
                return json.data || [];
            }
        },
        order: [[12, 'desc']],
        scrollX: true,
        dom: 'rt<"row align-items-center mt-3 px-3 pb-3"<"col-md-4"i><"col-md-4 text-center"l><"col-md-4"p>>',
        columnDefs: [{ targets: [0, 1], orderable: false, searchable: false }]
    });

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
    }

    function showMaintenanceModal(modalId) {
        const element = document.getElementById(modalId);
        if (!element || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
            return;
        }
        const modal = bootstrap.Modal.getInstance(element) || new bootstrap.Modal(element);
        modal.show();
    }

    function hideMaintenanceModal(modalId) {
        const element = document.getElementById(modalId);
        if (!element || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
            return;
        }
        const modal = bootstrap.Modal.getInstance(element) || new bootstrap.Modal(element);
        modal.hide();
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

    function optionHtml(items, placeholder = 'Select') {
        return '<option value="">' + placeholder + '</option>' + (items || []).map(item => {
            const departmentAttrs = item.department_id
                ? ` data-department-id="${escapeHtml(item.department_id)}" data-department-name="${escapeHtml(item.department_name || '')}"`
                : '';
            return `<option value="${escapeHtml(item.id)}"${departmentAttrs}>${escapeHtml(item.text)}</option>`;
        }).join('');
    }

    function initSelect2() {
        if (!$.fn.select2) return;
        $('.maintenance-select2').each(function () {
            if ($(this).hasClass('select2-hidden-accessible')) {
                $(this).select2('destroy');
            }
            const isEndUser = this.id === 'end_user_id';
            $(this).select2({
                width: '100%',
                dropdownParent: $('#maintenanceFormModal'),
                placeholder: isEndUser ? 'Search and select end user' : ($(this).find('option:first').text() || 'Select'),
                allowClear: true,
                minimumInputLength: 0
            });
        });
    }

    function loadMasterlists(callback) {
        $.getJSON(actionUrl, { action: 'masterlists' }, function (res) {
            if (!res.success) return;
            const selectedDepartmentFilter = $('#maintenanceDeptFilter').val();
            const selectedEndUser = $('#end_user_id').val();
            const selectedTechnician = $('#inspected_by').val();
            const selectedDesignation = $('#designation_id').val();
            masterlists = res.data;
            if (masterlists.departments && masterlists.departments.length) {
                $('#maintenanceDeptFilter').html('<option value="">All Departments</option>' + masterlists.departments.map(d => `<option value="${escapeHtml(d.text)}">${escapeHtml(d.text)}</option>`).join(''));
                $('#maintenanceDeptFilter').val(selectedDepartmentFilter);
                $('#designation_id').html(optionHtml(masterlists.departments, 'Select designation'));
                $('#designation_id').val(selectedDesignation);
            }
            if (masterlists.users && masterlists.users.length) {
                $('#end_user_id').html(optionHtml(masterlists.users, 'Select end user'));
                $('#end_user_id').val(selectedEndUser);
            }
            if (masterlists.technicians && masterlists.technicians.length) {
                $('#inspected_by').html(optionHtml(masterlists.technicians, 'Select technician'));
                $('#inspected_by').val(selectedTechnician);
            }
            setUserItems([], []);
            initSelect2();
            if (typeof callback === 'function') callback();
        }).fail(function (xhr) {
            Swal.fire('Error', (xhr.responseJSON && xhr.responseJSON.message) || 'Unable to load maintenance form data.', 'error');
        });
    }

    function setUserItems(equipment, supplies) {
        masterlists.equipment = equipment || [];
        masterlists.supplies = supplies || [];
        $('#maintenanceEquipmentPicker').html(optionHtml(masterlists.equipment, 'Select equipment'));
        $('#maintenanceSupplyPicker').html(optionHtml(masterlists.supplies, 'Select supply'));
        $('#maintenanceEquipmentPicker,#maintenanceSupplyPicker').val(null).trigger('change.select2');
        renderAssignedTables();
    }

    function renderAssignedTables() {
        const tableHtml = (items, emptyText) => {
            if (!items.length) return `<div class="text-muted small p-3">${emptyText}</div>`;
            return `
                <table class="table table-sm table-hover align-middle">
                    <thead><tr><th>New Generated Control</th><th>Old Control</th><th>Serial</th><th>Description</th><th></th></tr></thead>
                    <tbody>
                        ${items.map(item => `
                            <tr>
                                <td><strong>${escapeHtml(item.control_no || '')}</strong></td>
                                <td>${escapeHtml(item.old_control_no || 'No old control')}</td>
                                <td>${escapeHtml(item.serialno || '')}</td>
                                <td>${escapeHtml(item.description_name || '')}</td>
                                <td><button type="button" class="btn btn-sm btn-outline-primary add-maintenance-assigned-item" data-id="${escapeHtml(item.id)}">Add</button></td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `;
        };
        const allMode = $('#maintenanceAllItemsMode').is(':checked');
        $('#maintenanceEquipmentTable').html(tableHtml(masterlists.equipment, allMode ? 'No equipment found.' : 'No assigned equipment for this user.'));
        $('#maintenanceSupplyTable').html(tableHtml(masterlists.supplies, allMode ? 'No supplies found.' : 'No assigned supplies for this user.'));
    }

    function loadAllMaintenanceItems(callback) {
        $('#maintenanceUserHint').text('Loading all equipment and supplies...').show();
        $.getJSON(actionUrl, { action: 'all_items' }, function (res) {
            if (res.success) {
                setUserItems(res.data.equipment || [], res.data.supplies || []);
                $('#maintenanceAssignedWrap').addClass('active');
                $('#maintenanceUserHint').text('All active equipment and supplies are available. Select the old item even if it is now assigned to another end user.').show();
            } else {
                setUserItems([], []);
                $('#maintenanceUserHint').text(res.message || 'No equipment or supplies found.').show();
            }
            if (typeof callback === 'function') callback();
        }).fail(function (xhr) {
            setUserItems([], []);
            $('#maintenanceUserHint').text((xhr.responseJSON && xhr.responseJSON.message) || 'Unable to load all equipment and supplies.').show();
            if (typeof callback === 'function') callback();
        });
    }

    function loadUserItems(endUserId, callback) {
        if (!endUserId) {
            setUserItems([], []);
            $('#maintenanceAssignedWrap').removeClass('active');
            $('#maintenanceUserHint').text('Select an end user first. The department and assigned equipment/supplies will load automatically.').show();
            if (typeof callback === 'function') callback();
            return;
        }
        $('#maintenanceUserHint').text('Loading assigned equipment and supplies...').show();
        $.getJSON(actionUrl, { action: 'user_items', end_user_id: endUserId }, function (res) {
            if (res.success) {
                setUserItems(res.data.equipment || [], res.data.supplies || []);
                $('#maintenanceAssignedWrap').addClass('active');
                $('#maintenanceUserHint').hide();
            } else {
                setUserItems([], []);
                $('#maintenanceUserHint').text(res.message || 'No assigned items found.').show();
            }
            if (typeof callback === 'function') callback();
        }).fail(function (xhr) {
            setUserItems([], []);
            $('#maintenanceUserHint').text((xhr.responseJSON && xhr.responseJSON.message) || 'Unable to load assigned items.').show();
            if (typeof callback === 'function') callback();
        });
    }

    function loadMaintenanceItemOptions(callback) {
        if ($('#maintenanceAllItemsMode').is(':checked')) {
            loadAllMaintenanceItems(callback);
            return;
        }
        loadUserItems($('#end_user_id').val(), callback);
    }

    function renderChecklist(saved = []) {
        const savedMap = new Map(saved.map(item => [item.category + '|' + item.item_label, Number(item.is_checked) === 1]));
        $('#maintenanceCheckTabs').html(Object.keys(checklist).map((category, index) => `<button type="button" class="maintenance-check-tab ${index === 0 ? 'active' : ''}" data-category="${escapeHtml(category)}">${escapeHtml(category)}</button>`).join(''));
        $('#maintenanceCheckPanels').html(Object.entries(checklist).map(([category, items], index) => `
            <div class="maintenance-check-panel ${index === 0 ? 'active' : ''}" data-category="${escapeHtml(category)}">
                <div class="d-flex justify-content-end mb-2"><label class="small text-muted"><input type="checkbox" class="form-check-input select-tab-checks me-1"> Select all in this tab</label></div>
                ${items.map(label => {
                    const checked = savedMap.get(category + '|' + label) ? 'checked' : '';
                    return `<label class="maintenance-check-row"><input type="checkbox" class="form-check-input maintenance-check" data-category="${escapeHtml(category)}" data-label="${escapeHtml(label)}" ${checked}> ${escapeHtml(label)}</label>`;
                }).join('')}
            </div>
        `).join(''));
        syncMaintenanceStatus();
    }

    function collectChecks() {
        const checks = [];
        $('.maintenance-check').each(function () {
            checks.push({ category: $(this).data('category'), item_label: $(this).data('label'), is_checked: this.checked ? 1 : 0 });
        });
        return checks;
    }

    function calculateMaintenanceStatus() {
        const checks = collectChecks();
        const requiredDetailsComplete = Boolean(
            $('#end_user_id').val()
            && $('#department_id').val()
            && $('#date_accomplished').val()
            && $('#inspected_by').val()
        );
        const checklistComplete = checks.length > 0 && checks.every(item => Number(item.is_checked) === 1);
        const itemsComplete = maintenanceItems.length > 0;
        return requiredDetailsComplete && checklistComplete && itemsComplete ? 'completed' : 'incomplete';
    }

    function syncMaintenanceStatus() {
        const status = calculateMaintenanceStatus();
        return status;
    }

    function syncBuildType() {
        const text = maintenanceItems.map(item => [
            item.description_name,
            item.brand_name
        ].join(' ')).join(' ').toLowerCase();
        const isAssembled = text.includes('assembled');
        const brandNames = [...new Set(maintenanceItems
            .map(item => String(item.brand_name || '').trim())
            .filter(Boolean)
        )];
        $('#assemble').val(isAssembled ? 'Yes' : 'No');
        $('#branded').val(isAssembled ? 'No' : (maintenanceItems.length ? (brandNames.join(', ') || 'Branded') : ''));
    }

    function renderItems() {
        if (!maintenanceItems.length) {
            $('#maintenanceItems').html('<div class="text-muted small">No selected equipment/supplies yet.</div>');
            syncBuildType();
            syncMaintenanceStatus();
            return;
        }

        $('#maintenanceItems').html(`
            <div class="maintenance-selected-table">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>Action</th>
                            <th>Type</th>
                            <th>Control No</th>
                            <th>Old Control No</th>
                            <th>Description</th>
                            <th>Brand</th>
                            <th>Serial No</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${maintenanceItems.map((item, index) => `
                            <tr class="maintenance-item-row" data-index="${index}">
                                <td class="text-center"><button type="button" class="maintenance-remove-item remove-maintenance-item" title="Remove"><i class="bi bi-x-lg"></i> Remove</button></td>
                                <td>
                                    <span class="badge ${item.item_type === 'supply' ? 'bg-success-subtle text-success' : 'bg-primary-subtle text-primary'}">${escapeHtml((item.item_type || 'equipment').toUpperCase())}</span>
                                    <input type="hidden" class="maintenance-item-type" value="${escapeHtml(item.item_type || 'equipment')}">
                                    <input type="hidden" class="maintenance-item-source" value="${escapeHtml(item.source_id || '')}">
                                    <input type="hidden" class="maintenance-item-description-id" value="${escapeHtml(item.description_id || '')}">
                                    <input type="hidden" class="maintenance-item-brand-id" value="${escapeHtml(item.brand_id || '')}">
                                </td>
                                <td><input type="text" class="form-control maintenance-item-control" value="${escapeHtml(item.control_no || '')}"></td>
                                <td><input type="text" class="form-control maintenance-item-old-control" value="${escapeHtml(item.old_control_no || '')}"></td>
                                <td><input type="text" class="form-control maintenance-item-description" value="${escapeHtml(item.description_name || '')}"></td>
                                <td><input type="text" class="form-control maintenance-item-brand" value="${escapeHtml(item.brand_name || '')}"></td>
                                <td><input type="text" class="form-control maintenance-item-serial" value="${escapeHtml(item.serialno || '')}"></td>
                                <td><input type="text" class="form-control maintenance-item-remarks" value="${escapeHtml(item.remarks || '')}"></td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `);
        syncBuildType();
        syncMaintenanceStatus();
    }

    function preferredControlNo(item) {
        return $('#control_no_source').val() === 'old' && item.old_control_no
            ? item.old_control_no
            : (item.control_no || item.old_control_no || '');
    }

    function syncReportControlNo(force = false) {
        if (!maintenanceItems.length) return;
        if (force || !$('#control_no').val()) {
            $('#control_no').val(preferredControlNo(maintenanceItems[0]));
        }
    }

    function collectItems() {
        const items = [];
        $('.maintenance-item-row').each(function () {
            const row = $(this);
            items.push({
                item_type: row.find('.maintenance-item-type').val(),
                source_id: row.find('.maintenance-item-source').val(),
                control_no: row.find('.maintenance-item-control').val(),
                old_control_no: row.find('.maintenance-item-old-control').val(),
                description_id: row.find('.maintenance-item-description-id').val(),
                description_name: row.find('.maintenance-item-description').val(),
                brand_id: row.find('.maintenance-item-brand-id').val(),
                brand_name: row.find('.maintenance-item-brand').val(),
                serialno: row.find('.maintenance-item-serial').val(),
                remarks: row.find('.maintenance-item-remarks').val()
            });
        });
        return items;
    }

    function resetForm() {
        $('#maintenanceForm')[0].reset();
        $('#maintenance_pk').val('');
        $('#maintenanceModalTitle').text('New Maintenance Report');
        $('#date_accomplished').val(new Date().toISOString().slice(0, 10));
        $('#department_id,#department_name').val('');
        $('#designation_id').val(null).trigger('change.select2');
        $('#control_no_source').val('current');
        $('#maintenanceAllItemsMode').prop('checked', false);
        maintenanceItems = [];
        renderItems();
        setUserItems([], []);
        $('#maintenanceAssignedWrap').removeClass('active');
        $('#maintenanceUserHint').text('Select an end user first. The department and assigned equipment/supplies will load automatically.').show();
        renderChecklist();
        $('.maintenance-select2').val(null).trigger('change.select2');
    }

    function addMaintenanceItem(selected) {
        if (!selected) return;
        const exists = maintenanceItems.some(item => String(item.item_type) === String(selected.item_type) && String(item.source_id || '') === String(selected.source_id || ''));
        if (exists && selected.source_id) {
            Swal.fire('Already Added', 'This item is already linked to the maintenance report.', 'info');
            return;
        }
        maintenanceItems.push(selected);
        syncReportControlNo();
        renderItems();
        syncMaintenanceStatus();
    }

    function addSelectedItem(listName, picker) {
        const value = $(picker).val();
        if (!value) {
            $(picker).trigger('change.select2');
            return;
        }
        const selected = (masterlists[listName] || []).find(item => String(item.id) === String(value));
        addMaintenanceItem(selected);
        $(picker).val(null).trigger('change.select2');
    }

    initSelect2();
    const maintenanceFormGuard = window.IMSUnsavedGuard?.create({
        form: '#maintenanceForm',
        modal: '#maintenanceFormModal',
        dirtySelectors: [
            '.add-maintenance-assigned-item',
            '.remove-maintenance-item',
            '.maintenance-check-tab',
            '#selectAllChecks',
            '#deselectAllChecks'
        ]
    });

    $('#openMaintenanceFormBtn').on('click', function () {
        resetForm();
        showMaintenanceModal('maintenanceFormModal');
        loadMasterlists();
    });
    let maintenanceSearchTimer = null;
    $('#maintenanceSearch').on('input', function () {
        clearTimeout(maintenanceSearchTimer);
        const value = this.value;
        maintenanceSearchTimer = setTimeout(() => table.search(value).draw(), 250);
    });
    $('#maintenanceDeptFilter').on('change', () => table.ajax.reload());
    $('#maintenanceMineOnlyBtn').on('click', function () {
        const isActive = $(this).data('active') === 1;
        $(this).data('active', isActive ? 0 : 1).toggleClass('active', !isActive);
        table.ajax.reload();
    });

    function maintenanceItemsChild(items) {
        if (!items || !items.length) {
            return '<div class="maintenance-items-child"><div class="p-3 text-muted small">No linked items.</div></div>';
        }
        const rows = items.map(item => `
            <tr>
                <td><span class="badge ${item.item_type === 'supply' ? 'bg-success-subtle text-success' : 'bg-primary-subtle text-primary'}">${escapeHtml(item.item_type || '')}</span></td>
                <td><strong>${escapeHtml(item.control_no || '')}</strong></td>
                <td>${escapeHtml(item.old_control_no || '')}</td>
                <td>${escapeHtml(item.description_name || '')}</td>
                <td>${escapeHtml(item.brand_name || '')}</td>
                <td>${escapeHtml(item.serialno || '')}</td>
                <td>${escapeHtml(item.remarks || '')}</td>
            </tr>
        `).join('');
        return `<div class="maintenance-items-child"><table><thead><tr><th>Type</th><th>Code / Control</th><th>Old Control</th><th>Description</th><th>Brand</th><th>Serial No</th><th>Remarks</th></tr></thead><tbody>${rows}</tbody></table></div>`;
    }

    function toggleMaintenanceItems(button) {
        const tr = $(button).closest('tr');
        const row = table.row(tr);
        const id = $(button).data('id');
        if (row.child.isShown()) {
            row.child.hide();
            tr.removeClass('shown');
            tr.find('.toggle-maintenance-items-btn i').removeClass('bi-chevron-down').addClass('bi-chevron-right');
            return;
        }
        tr.find('.toggle-maintenance-items-btn i').removeClass('bi-chevron-right').addClass('bi-chevron-down');
        row.child('<div class="maintenance-items-child"><div class="p-3 text-muted small">Loading items...</div></div>').show();
        $.getJSON(actionUrl, { action: 'get', id }).done(res => row.child(maintenanceItemsChild(res.success ? (res.items || []) : [])).show())
            .fail(() => row.child('<div class="maintenance-items-child"><div class="p-3 text-danger small">Unable to load items.</div></div>').show());
    }

    $(document).on('click', '.toggle-maintenance-items-btn', function (event) {
        event.stopPropagation();
        toggleMaintenanceItems(this);
    });

    $('#maintenanceTable tbody').on('click', 'tr', function (event) {
        if ($(event.target).closest('button, a, input, select, textarea, label').length) return;
        const toggle = $(this).find('.toggle-maintenance-items-btn').first();
        if (toggle.length) toggleMaintenanceItems(toggle[0]);
    });
    $('#end_user_id').on('change', function () {
        const selected = $(this).find(':selected');
        $('#department_id').val(selected.data('department-id') || selected.attr('data-department-id') || '');
        $('#department_name').val(selected.data('department-name') || selected.attr('data-department-name') || '');
        maintenanceItems = [];
        $('#control_no').val('');
        renderItems();
        loadMaintenanceItemOptions();
    });
    $('#maintenanceAllItemsMode').on('change', function () {
        loadMaintenanceItemOptions();
    });
    $('#maintenanceEquipmentPicker').on('change', function () { addSelectedItem('equipment', this); });
    $('#maintenanceSupplyPicker').on('change', function () { addSelectedItem('supplies', this); });
    $('#control_no_source').on('change', function () { syncReportControlNo(true); });
    $(document).on('click', '.add-maintenance-assigned-item', function () {
        const id = String($(this).data('id'));
        const selected = [...masterlists.equipment, ...masterlists.supplies].find(item => String(item.id) === id);
        addMaintenanceItem(selected);
    });
    $(document).on('click', '.remove-maintenance-item', function () {
        maintenanceItems.splice(Number($(this).closest('.maintenance-item-row').data('index')), 1);
        renderItems();
        syncMaintenanceStatus();
    });
    $(document).on('click', '.maintenance-check-tab', function () {
        $('.maintenance-check-tab,.maintenance-check-panel').removeClass('active');
        $(this).addClass('active');
        $(`.maintenance-check-panel[data-category="${$(this).data('category')}"]`).addClass('active');
    });
    $('#selectAllChecks').on('click', () => $('.maintenance-check').prop('checked', true));
    $('#selectAllChecks').on('click', syncMaintenanceStatus);
    $('#deselectAllChecks').on('click', () => $('.maintenance-check,.select-tab-checks').prop('checked', false));
    $('#deselectAllChecks').on('click', syncMaintenanceStatus);
    $(document).on('change', '.select-tab-checks', function () {
        $(this).closest('.maintenance-check-panel').find('.maintenance-check').prop('checked', this.checked);
        syncMaintenanceStatus();
    });
    $(document).on('change', '.maintenance-check,#end_user_id,#department_id,#date_accomplished,#inspected_by', syncMaintenanceStatus);

    $('#maintenanceForm').on('submit', function (event) {
        event.preventDefault();
        const submitBtn = $(this).find('button[type="submit"]');
        if (submitBtn.prop('disabled')) {
            return;
        }
        const formData = new FormData(this);
        formData.set('maintenance_status', syncMaintenanceStatus());
        formData.append('action', $('#maintenance_pk').val() ? 'update' : 'create');
        formData.append('items', JSON.stringify(collectItems()));
        formData.append('checks', JSON.stringify(collectChecks()));
        $.ajax({
            url: actionUrl,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            beforeSend: function () {
                submitBtn.prop('disabled', true).data('original-text', submitBtn.html()).html('<span class="spinner-border spinner-border-sm me-1"></span>Saving...');
            },
            success: function (res) {
                if (res.success) {
                    maintenanceFormGuard?.forceClean();
                    Swal.fire('Saved', res.message, 'success');
                    hideMaintenanceModal('maintenanceFormModal');
                    table.ajax.reload(null, false);
                } else {
                    Swal.fire('Error', res.message || 'Unable to save report.', 'error');
                }
            },
            error: xhr => Swal.fire('Error', (xhr.responseJSON && xhr.responseJSON.message) || 'Unable to save report.', 'error'),
            complete: function () {
                submitBtn.prop('disabled', false).html(submitBtn.data('original-text') || '<i class="bi bi-save"></i> Save Report');
            }
        });
    });

    $(document).on('click', '.edit-maintenance-btn,.view-maintenance-btn', function () {
        const id = $(this).data('id');
        const isView = $(this).hasClass('view-maintenance-btn');
        $.getJSON(actionUrl, { action: 'get', id }, function (res) {
            if (!res.success) return Swal.fire('Error', res.message, 'error');
            if (isView) {
                const r = res.data;
                const linkedItems = res.items || [];
                const linkedItemsHtml = linkedItems.length ? `
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Control No</th>
                                    <th>Old Control No</th>
                                    <th>Description</th>
                                    <th>Brand</th>
                                    <th>Serial No</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${linkedItems.map(i => `
                                    <tr>
                                        <td><span class="badge ${i.item_type === 'supply' ? 'bg-success-subtle text-success' : 'bg-primary-subtle text-primary'}">${escapeHtml(i.item_type || 'equipment')}</span></td>
                                        <td>${escapeHtml(i.control_no || '')}</td>
                                        <td>${escapeHtml(i.old_control_no || '')}</td>
                                        <td>${escapeHtml(i.description_name || '')}</td>
                                        <td>${escapeHtml(i.brand_name || '')}</td>
                                        <td>${escapeHtml(i.serialno || '')}</td>
                                        <td>${escapeHtml(i.remarks || '')}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                ` : '<div class="text-muted">No linked items.</div>';

                $('#maintenanceViewBody').html(`
                    <div class="row g-3">
                        <div class="col-md-4"><strong>Maintenance ID</strong><div>${escapeHtml(r.maintenance_id)}</div></div>
                        <div class="col-md-4"><strong>Control No</strong><div>${escapeHtml(r.control_no)}</div></div>
                        <div class="col-md-4"><strong>Status</strong><div>${escapeHtml(r.maintenance_status)}</div></div>
                        <div class="col-md-4"><strong>End User</strong><div>${escapeHtml(r.end_user_name)}</div></div>
                        <div class="col-md-4"><strong>Department</strong><div>${escapeHtml(r.department_name)}</div></div>
                        <div class="col-md-4"><strong>Designation</strong><div>${escapeHtml(r.designation_name || r.department_name || '')}</div></div>
                        <div class="col-md-4"><strong>Inspected By</strong><div>${escapeHtml(r.inspected_by_name || r.inspected_by)}</div></div>
                        <div class="col-md-4"><strong>Date Accomplished</strong><div>${escapeHtml(r.date_accomplished)}</div></div>
                        <div class="col-md-4"><strong>Branded</strong><div>${escapeHtml(r.branded || 'No Info')}</div></div>
                        <div class="col-md-4"><strong>Assembled</strong><div>${escapeHtml(r.assemble || 'No Info')}</div></div>
                    </div>
                    <hr>
                    <h6>Linked Equipment / Supplies Details</h6>
                    ${linkedItemsHtml}
                `);
                showMaintenanceModal('maintenanceViewModal');
                return;
            }
            resetForm();
            $('#maintenanceModalTitle').text('Edit Maintenance Report');
            showMaintenanceModal('maintenanceFormModal');
            loadMasterlists(function () {
                Object.entries(res.data).forEach(([key, value]) => $('#' + key).val(value ?? ''));
                $('#maintenance_pk').val(res.data.id);
                $('#end_user_id').val(res.data.end_user_id).trigger('change');
                $('#designation_id').val(res.data.designation_id || '').trigger('change.select2');
                $('#inspected_by').val(res.data.inspected_by).trigger('change');
                maintenanceItems = res.items || [];
                renderItems();
                renderChecklist(res.checks || []);
                syncMaintenanceStatus();
            });
        }).fail(function (xhr) {
            Swal.fire('Error', (xhr.responseJSON && xhr.responseJSON.message) || 'Unable to load maintenance report.', 'error');
        });
    });

    $(document).on('click', '.delete-maintenance-btn', function () {
        const id = $(this).data('id');
        Swal.fire({
            title: 'Archive Maintenance?',
            text: 'This record will be moved to the archive.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#f59e0b',
            confirmButtonText: 'Archive'
        })
            .then(result => {
                if (!result.isConfirmed) return;
                $.post(actionUrl, { action: 'archive', id }, function (res) {
                    if (res.success) {
                        Swal.fire('Archived', res.message, 'success');
                        table.ajax.reload(null, false);
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                }, 'json').fail(function (xhr) {
                    Swal.fire('Error', (xhr.responseJSON && xhr.responseJSON.message) || 'Unable to archive maintenance report.', 'error');
                });
            });
    });

    $(document).on('click', '.upload-maintenance-pdf-btn', function () {
        const panel = $(this).closest('.ims-upload-panel');
        const input = panel.find('.maintenance-pdf-input')[0];
        const file = input && input.files ? input.files[0] : null;
        const id = $(this).data('id');
        if (!file) return Swal.fire('Select file', 'Choose a PDF file first.', 'info');
        if (file.size > 1024 * 1024) return Swal.fire('Error', 'PDF must not exceed 1MB.', 'error');
        const fd = new FormData();
        fd.append('action', 'upload_pdf');
        fd.append('id', id);
        fd.append('attachment_pdf', file);
        $.ajax({ url: actionUrl, method: 'POST', data: fd, processData: false, contentType: false, dataType: 'json',
            success: res => { Swal.fire(res.success ? 'Uploaded' : 'Error', res.message, res.success ? 'success' : 'error'); table.ajax.reload(null, false); }
        });
    });
    $(document).on('click', '.view-maintenance-pdf', function () {
        $('#maintenancePdfFrame').attr('src', $(this).data('pdf'));
        showMaintenanceModal('maintenancePdfModal');
    });
    $(document).on('click', '.export-maintenance-pdf-btn', function () {
        const exportUrl = $(this).data('export-url');
        openMaintenanceExportPicker($(this).data('id'), exportUrl);
    });
    $('#maintenancePdfModal').on('hidden.bs.modal', () => $('#maintenancePdfFrame').attr('src', ''));
    $(document).on('click', '.remove-maintenance-pdf', function () {
        const id = $(this).data('id');
        Swal.fire({ title: 'Delete Attachment?', text: 'This PDF file will be permanently removed.', icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33' })
            .then(result => {
                if (!result.isConfirmed) return;
                $.post(actionUrl, { action: 'remove_pdf', id }, function (res) {
                    Swal.fire(res.success ? 'Deleted' : 'Error', res.message, res.success ? 'success' : 'error');
                    table.ajax.reload(null, false);
                }, 'json');
            });
    });
});
</script>

<?php require_once ROOT_PATH . '/modules/layout/footer.php'; ?>
