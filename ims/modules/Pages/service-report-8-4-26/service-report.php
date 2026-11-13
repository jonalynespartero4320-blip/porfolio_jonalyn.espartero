<?php
$page_title = 'Service Reports';
require_once ROOT_PATH . '/modules/layout/header.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/functions.php';

require_login();
require_permission('ictd.service-report.view');
?>

<style>
  .service-shell { display: grid; gap: 1rem; }
  .service-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; }
  .service-eyebrow { color: #667085; font-size: .78rem; font-weight: 750; }
  .service-title { margin: 0; color: #1f2937; font-size: 1.35rem; font-weight: 850; }
  .service-toolbar { display: flex; justify-content: flex-end; gap: .65rem; flex-wrap: wrap; }
  .service-search { position: relative; min-width: min(340px, 100%); }
  .service-search i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #98a2b3; }
  .service-search input { height: 38px; padding-left: 34px; border-radius: 4px; border-color: #eaecf0; font-size: .78rem; }
  .service-card { border: 0; border-radius: 16px; background: rgba(255,255,255,.88); box-shadow: 0 18px 44px rgba(15,23,42,.08); overflow: hidden; }
  .service-table { font-size: .8rem; }
  .service-table thead th { white-space: nowrap; background: #f3f4f6 !important; border: 0 !important; color: #5f6672; font-size: .72rem; padding: .72rem .8rem !important; }
  .service-table tbody td { border: 0 !important; border-bottom: 1px solid #f2f4f7 !important; padding: .72rem .8rem !important; white-space: nowrap; vertical-align: middle; }
  .service-control { font-weight: 850; color: #1f2937; }
  .service-badge { border-radius: 999px; padding: .25rem .55rem; font-size: .72rem; font-weight: 800; }
  .service-actions { display: inline-flex; align-items: center; gap: .55rem; }
  .service-action-btn { border: 0; background: transparent; padding: 0; line-height: 1; font-size: .95rem; }
  .service-action-view { color: #16a085; }
  .service-action-edit { color: #1f9bb4; }
  .service-action-pdf { color: #d92d20; }
  .service-action-delete { color: #e26060; }
  .service-action-toggle { color: #3657f6; }
  .service-item-toggle { border: 0; background: transparent; padding: 0; }
  .service-items-child { margin: .25rem 0 .35rem 3rem; border: 1px solid #e7eefc; border-radius: 8px; overflow: hidden; background: #fff; }
  .service-items-child table { width: 100%; margin: 0; font-size: .76rem; }
  .service-items-child th { background: #f8fafc; color: #667085; font-size: .68rem; text-transform: uppercase; padding: .55rem .65rem; }
  .service-items-child td { padding: .55rem .65rem; border-top: 1px solid #f2f4f7; }
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
  .service-form-section { border: 1px solid #edf0f5; border-radius: 14px; background: #fbfcff; padding: 1rem; margin-bottom: 1rem; }
  .service-section-title { display: flex; align-items: center; gap: .45rem; color: #263241; font-size: .82rem; font-weight: 900; text-transform: uppercase; letter-spacing: .02em; margin-bottom: .8rem; }
  .service-section-title i { color: #3657f6; }
  .service-item-row { border: 1px solid #edf0f5; border-radius: 12px; padding: .85rem; background: #fff; margin-bottom: .75rem; }
  .service-item-row-existing { background: #eaf6ff; border-color: #b9e4ff; }
  .service-item-row-new { background: #ecfdf3; border-color: #b7efc5; }
  .service-item-legend { display: flex; flex-wrap: wrap; gap: .65rem; margin-bottom: .8rem; font-size: .76rem; font-weight: 800; color: #475467; }
  .service-item-legend span { display: inline-flex; align-items: center; gap: .4rem; }
  .service-item-legend-dot { width: 14px; height: 14px; border-radius: 4px; border: 1px solid transparent; }
  .service-item-legend-existing { background: #eaf6ff; border-color: #b9e4ff; }
  .service-item-legend-new { background: #ecfdf3; border-color: #b7efc5; }
  .service-item-row-head { display: flex; align-items: center; justify-content: space-between; gap: .75rem; margin-bottom: .8rem; padding-bottom: .65rem; border-bottom: 1px solid #f2f4f7; }
  .service-item-row-title { display: inline-flex; align-items: center; gap: .45rem; color: #344054; font-size: .8rem; font-weight: 850; text-transform: uppercase; letter-spacing: .02em; }
  .service-remove-item { border: 0; width: 34px; height: 34px; border-radius: 10px; background: #fff1f1; color: #d92d20; display: inline-flex; align-items: center; justify-content: center; flex: 0 0 auto; }
  .service-remove-item:hover { background: #ffe4e4; color: #b42318; }
  .service-detail-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; }
  .service-detail-item { padding: .75rem; background: #f9fafb; border-radius: 10px; }
  .service-detail-label { color: #667085; font-size: .72rem; font-weight: 800; text-transform: uppercase; }
  .service-detail-value { margin-top: .2rem; font-weight: 650; color: #1f2937; }
  #serviceFormModal .modal-dialog { width: min(1180px, calc(100vw - 24px)); max-width: min(1180px, calc(100vw - 24px)); margin: .75rem auto; }
  #serviceFormModal .modal-content { max-height: calc(100dvh - 1.5rem); display: flex; flex-direction: column; overflow: hidden; }
  #serviceFormModal form { flex: 1 1 auto; min-height: 0; display: flex; flex-direction: column; }
  #serviceFormModal .modal-body { flex: 1 1 auto; min-height: 0; overflow-y: auto; padding-bottom: 1.25rem; }
  #serviceFormModal .modal-footer { flex: 0 0 auto; position: sticky; bottom: 0; z-index: 5; background: #fff; box-shadow: 0 -10px 24px rgba(15, 23, 42, .08); }
  #serviceViewModal .modal-body { max-height: calc(100dvh - 170px); overflow-y: auto; }
  @media (max-width: 767px) {
    .service-head { flex-direction: column; }
    .service-head .btn, .service-search { width: 100%; }
    .service-detail-grid { grid-template-columns: 1fr; }
  }
</style>

<div class="container-fluid py-3">
    <div class="service-shell">
        <div class="service-head">
            <div>
                <div class="service-eyebrow">Reports</div>
                <h2 class="service-title">Service Report Records</h2>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="<?= BASE_URL ?>/ictd/service-report/archive" class="btn btn-outline-secondary btn-sm"><i class="bi bi-archive"></i> Archive</a>
                <button type="button" class="btn btn-primary btn-sm" id="openServiceFormBtn">
                    <i class="bi bi-plus-lg"></i> New Service Report
                </button>
            </div>
        </div>

        <div class="service-toolbar">
            <div class="service-search">
                <i class="bi bi-search"></i>
                <input type="search" class="form-control" id="serviceSearch" placeholder="Search service reports">
            </div>
            <button type="button" class="btn btn-outline-primary btn-sm" id="serviceMineOnlyBtn" data-active="0">
                <i class="bi bi-person-check"></i> My Records
            </button>
        </div>

        <div class="card service-card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="serviceTable" class="table align-middle nowrap mb-0 service-table" style="width:100%">
                        <thead>
                            <tr>
                                <th>Action</th>
                                <th>Upload File</th>
                                <th>ID</th>
                                <th>Service CN</th>
                                <th>Old Service CN</th>
                                <th>End User</th>
                                <th>Employee Designation</th>
                                <th>Report Date</th>
                                <th>Report Time</th>
                                <th>Satisfactory</th>
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

<div class="modal fade" id="serviceFormModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title fw-bold" id="serviceModalTitle">New Service Report</h5>
                    <div class="text-muted small">Service control number is generated from the report date year.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="serviceForm" enctype="multipart/form-data">
                <input type="hidden" id="service_id" name="id">
                <input type="hidden" id="request_id" name="request_id">
                <div class="modal-body">
                    <div class="service-form-section">
                        <div class="service-section-title"><i class="bi bi-file-earmark-text"></i> Report Details</div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Service Control No</label>
                                <input type="text" class="form-control" id="service_controlno" name="service_controlno" placeholder="Auto generated if blank">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Old Service CN</label>
                                <input type="text" class="form-control" id="old_service_cn" name="old_service_cn">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">End User *</label>
                                <select class="form-select service-select2" id="end_user_id" name="end_user_id" required></select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Department</label>
                                <input type="hidden" id="department_id" name="department_id">
                                <input type="text" class="form-control" id="department_name" readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Employee Designation</label>
                                <select class="form-select service-select2" id="employee_designation_id" name="employee_designation_id"></select>
                            </div>
                        </div>
                    </div>

                    <div class="service-form-section">
                        <div class="service-section-title"><i class="bi bi-pc-display"></i> Equipment, Supplies, Others</div>
                        <div class="row g-3 align-items-end mb-3">
                            <div class="col-lg-4">
                                <label class="form-label">Equipment</label>
                                <select class="form-select service-select2" id="service_equipment_picker"></select>
                            </div>
                            <div class="col-lg-4">
                                <label class="form-label">Supplies</label>
                                <select class="form-select service-select2" id="service_supply_picker"></select>
                            </div>
                            <div class="col-lg-4">
                                <label class="form-label">Subscriptions</label>
                                <select class="form-select service-select2" id="service_subscription_picker"></select>
                            </div>
                            <div class="col-lg-12 d-flex flex-wrap gap-2">
                                <button type="button" class="btn btn-outline-primary" id="addNewEquipmentItem"><i class="bi bi-plus"></i> Add New Equipment</button>
                                <button type="button" class="btn btn-outline-success" id="addNewSupplyItem"><i class="bi bi-plus"></i> Add New Supply</button>
                                <button type="button" class="btn btn-outline-info" id="addNewSubscriptionItem"><i class="bi bi-plus"></i> Add Subscription</button>
                                <button type="button" class="btn btn-outline-secondary" id="addOtherServiceItem"><i class="bi bi-plus"></i> Add Other</button>
                            </div>
                        </div>
                        <div class="service-item-legend">
                            <span><i class="service-item-legend-dot service-item-legend-existing"></i> Blue is for existing equipment/supplies assigned to the end user</span>
                            <span><i class="service-item-legend-dot service-item-legend-new"></i> Light green is for entering new items</span>
                        </div>
                        <div id="serviceItems"></div>
                    </div>

                    <div class="service-form-section">
                        <div class="service-section-title"><i class="bi bi-geo-alt"></i> Travel</div>
                        <div class="row g-3">
                            <div class="col-md-3"><label class="form-label">Vicinity</label><input type="text" class="form-control" id="vicinity" name="vicinity"></div>
                            <div class="col-md-3"><label class="form-label">Distance</label><input type="text" class="form-control" id="distance" name="distance"></div>
                            <div class="col-md-2"><label class="form-label">Travel Date</label><input type="date" class="form-control" id="travel_date" name="travel_date"></div>
                            <div class="col-md-2"><label class="form-label">Departure</label><input type="time" class="form-control" id="travel_departure" name="travel_departure" value="12:00"></div>
                            <div class="col-md-2"><label class="form-label">Arrival</label><input type="time" class="form-control" id="travel_arrival" name="travel_arrival" value="12:00"></div>
                        </div>
                    </div>

                    <div class="service-form-section">
                        <div class="service-section-title"><i class="bi bi-clipboard-pulse"></i> Complaint and Troubleshooting</div>
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label">Complain</label><select class="form-select service-select2" id="complain" name="complain[]" multiple></select></div>
                            <div class="col-md-3"><label class="form-label">Inspection Start</label><input type="time" class="form-control" id="complain_inspection_time_start" name="complain_inspection_time_start" value="12:00"></div>
                            <div class="col-md-3"><label class="form-label">Inspection Finish</label><input type="time" class="form-control" id="complain_inspection_time_finish" name="complain_inspection_time_finish" value="12:00"></div>
                            <div class="col-md-6"><label class="form-label">Troubleshoot</label><select class="form-select service-select2" id="troubleshoot" name="troubleshoot[]" multiple></select></div>
                            <div class="col-md-3"><label class="form-label">Troubleshoot Start</label><input type="time" class="form-control" id="troubleshoot_start" name="troubleshoot_start" value="12:00"></div>
                            <div class="col-md-3"><label class="form-label">Troubleshoot Finish</label><input type="time" class="form-control" id="troubleshoot_finish" name="troubleshoot_finish" value="12:00"></div>
                        </div>
                    </div>

                    <div class="service-form-section">
                        <div class="service-section-title"><i class="bi bi-journal-check"></i> Remarks and Pullout</div>
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label">Remarks Service Details</label><select class="form-select service-select2" id="remarks_service_details" name="remarks_service_details[]" multiple></select></div>
                            <div class="col-md-3"><label class="form-label">Pullout Date</label><input type="date" class="form-control" id="remarks_date_pullout" name="remarks_date_pullout"></div>
                            <div class="col-md-3"><label class="form-label">Pullout Time</label><input type="time" class="form-control" id="remarks_time_pullout" name="remarks_time_pullout" value="12:00"></div>
                            <div class="col-md-3"><label class="form-label">Returned Date</label><input type="date" class="form-control" id="remarks_date_returned" name="remarks_date_returned"></div>
                            <div class="col-md-3"><label class="form-label">Returned Time</label><input type="time" class="form-control" id="remarks_time_returned" name="remarks_time_returned" value="12:00"></div>
                        </div>
                    </div>

                    <div class="service-form-section">
                        <div class="service-section-title"><i class="bi bi-people"></i> Technician</div>
                        <div class="row g-3">
                            <div class="col-md-8"><label class="form-label">Attending Technician</label><select class="form-select service-select2" id="attending_technician" name="attending_technician[]" multiple></select></div>
                            <div class="col-md-4"><label class="form-label">Technician Designation</label><input type="text" class="form-control" id="technician_designation" name="technician_designation"></div>
                            <div class="col-md-3"><label class="form-label">Report Date *</label><input type="date" class="form-control" id="report_date" name="report_date" required></div>
                            <div class="col-md-3"><label class="form-label">Report Time</label><input type="time" class="form-control" id="report_time" name="report_time" value="12:00"></div>
                            <div class="col-md-3">
                                <label class="form-label">Employee Satisfactory</label>
                                <select class="form-select service-select2" id="employee_satisfactory" name="employee_satisfactory">
                                    <option value="">Select</option>
                                    <option>EXCELLENT</option>
                                    <option>VERY SATISFACTORY</option>
                                    <option>SATISFACTORY</option>
                                    <option>POOR</option>
                                </select>
                            </div>
                            <div class="col-md-3"><label class="form-label">PDF Attachment</label><input type="file" class="form-control" id="attachment_pdf" name="attachment_pdf" accept="application/pdf"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Service Report</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="serviceViewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Service Report Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="serviceViewBody"></div>
        </div>
    </div>
</div>

<div class="modal fade" id="servicePdfModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-pdf"></i> Service Report PDF Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <iframe id="servicePdfFrame" style="width:100%;height:75vh;border:0"></iframe>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const actionUrl = '<?= BASE_URL ?>/ictd/service-report-action';
    const defaultTimeValue = '12:00';
    const serviceTimeFields = [
        'travel_departure',
        'travel_arrival',
        'complain_inspection_time_start',
        'complain_inspection_time_finish',
        'troubleshoot_start',
        'troubleshoot_finish',
        'remarks_time_pullout',
        'remarks_time_returned',
        'report_time'
    ];
    let masterlists = { users: [], departments: [], complaints: [], troubleshoots: [], remarks: [], technicians: [], descriptions: [], brands: [], statuses: [], equipment: [], supplies: [], subscriptions: [] };
    let masterlistsLoaded = false;
    let serviceItems = [];
    let assignedItemsEndUserId = '';
    let removedAssignedItemKeys = new Set();

    const table = $('#serviceTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '<?= BASE_URL ?>/ictd/service-report-data',
            data: d => { d.mine_only = $('#serviceMineOnlyBtn').data('active') === 1 ? 1 : 0; }
        },
        order: [[2, 'desc']],
        scrollX: true,
        dom: 'rt<"row align-items-center mt-3"<"col-md-4"i><"col-md-4 text-center"l><"col-md-4"p>>',
        columnDefs: [{ targets: [0, 1], orderable: false, searchable: false }]
    });

    let searchTimer = null;
    $('#serviceSearch').on('input', function () {
        clearTimeout(searchTimer);
        const value = this.value;
        searchTimer = setTimeout(() => table.search(value).draw(), 250);
    });

    $('#serviceMineOnlyBtn').on('click', function () {
        const isActive = $(this).data('active') === 1;
        $(this).data('active', isActive ? 0 : 1).toggleClass('active', !isActive);
        table.ajax.reload();
    });

    function serviceItemsChild(items) {
        if (!items || !items.length) {
            return '<div class="service-items-child"><div class="p-3 text-muted small">No connected items.</div></div>';
        }
        const rows = items.map(item => `
            <tr>
                <td><span class="service-badge bg-info-subtle text-info">${escapeHtml(item.type || '')}</span></td>
                <td><strong>${escapeHtml(item.control_no || '')}</strong></td>
                <td>${escapeHtml(item.date_delivered || '')}</td>
                <td>${escapeHtml(item.other_description || item.description_name || '')}</td>
                <td>${escapeHtml(item.brand_name || '')}</td>
                <td>${escapeHtml(item.serialno || '')}</td>
                <td>${escapeHtml(item.item_status || '')}</td>
                <td>${escapeHtml(item.remarks || '')}</td>
            </tr>
        `).join('');
        return `<div class="service-items-child"><table><thead><tr><th>Type</th><th>Code / Control</th><th>Date Delivered</th><th>Description</th><th>Brand</th><th>Serial No</th><th>Status</th><th>Remarks</th></tr></thead><tbody>${rows}</tbody></table></div>`;
    }

    function toggleServiceItems(button) {
        const tr = $(button).closest('tr');
        const row = table.row(tr);
        const id = $(button).data('id');
        if (row.child.isShown()) {
            row.child.hide();
            tr.removeClass('shown');
            tr.find('.toggle-service-items-btn i').removeClass('bi-chevron-down').addClass('bi-chevron-right');
            return;
        }
        tr.find('.toggle-service-items-btn i').removeClass('bi-chevron-right').addClass('bi-chevron-down');
        row.child('<div class="service-items-child"><div class="p-3 text-muted small">Loading items...</div></div>').show();
        $.getJSON(actionUrl, { action: 'view', id }).done(res => row.child(serviceItemsChild(res.success ? (res.items || []) : [])).show())
            .fail(() => row.child('<div class="service-items-child"><div class="p-3 text-danger small">Unable to load items.</div></div>').show());
    }

    $(document).on('click', '.toggle-service-items-btn', function (event) {
        event.stopPropagation();
        toggleServiceItems(this);
    });

    $('#serviceTable tbody').on('click', 'tr', function (event) {
        if ($(event.target).closest('button, a, input, select, textarea, label').length) return;
        const toggle = $(this).find('.toggle-service-items-btn').first();
        if (toggle.length) toggleServiceItems(toggle[0]);
    });

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
    }

    function normalizeItemType(value) {
        const type = String(value || 'equipment').toLowerCase();
        const legacyTypes = { '1': 'equipment', '2': 'supply', '3': 'subscription', '4': 'other', supplies: 'supply' };
        const normalized = legacyTypes[type] || type;
        return ['equipment', 'supply', 'subscription', 'other'].includes(normalized) ? normalized : 'other';
    }

    function normalizeServiceItem(item) {
        item = item || {};
        return { ...item, type: normalizeItemType(item.type) };
    }

    function serviceItemKey(item) {
        const normalized = normalizeServiceItem({ ...item, type: item.type || item.item_type });
        const source = normalized.source_id || normalized.control_no || normalized.other_description || '';
        return `${normalized.type}:${source}`;
    }

    function serviceItemFromSource(selected, extra = {}) {
        return normalizeServiceItem({
            type: selected.item_type,
            source_id: selected.source_id,
            control_no: selected.control_no,
            other_description: selected.other_description || selected.description_name || '',
            description_id: selected.description_id,
            brand_id: selected.brand_id,
            serialno: selected.serialno,
            date_delivered: selected.date_delivered,
            item_status: selected.item_status,
            unit_warranty: selected.unit_warranty,
            ...extra
        });
    }

    function appendServiceItem(item) {
        const key = serviceItemKey(item);
        if (key && serviceItems.some(existing => serviceItemKey(existing) === key)) {
            return false;
        }
        serviceItems.push(item);
        return true;
    }

    function options(list, placeholder = 'Select') {
        return `<option value="">${placeholder}</option>` + (list || []).map(item => `<option value="${escapeHtml(item.id)}" data-department-id="${escapeHtml(item.department_id || '')}" data-department-name="${escapeHtml(item.department_name || '')}" data-designation="${escapeHtml(item.designation || '')}">${escapeHtml(item.text)}</option>`).join('');
    }

    function initSelect2() {
        if (!$.fn.select2) return;
        $('select.service-select2').each(function () {
            if ($(this).hasClass('select2-hidden-accessible')) {
                return;
            }
            const parentModal = $(this).closest('.modal');
            const allowTags = ['complain', 'troubleshoot', 'remarks_service_details'].includes(this.id);
            const isLargePicker = ['service_equipment_picker', 'service_supply_picker', 'service_subscription_picker'].includes(this.id);
            $(this).select2({
                width: '100%',
                dropdownParent: parentModal.length ? parentModal : $(document.body),
                tags: allowTags,
                allowClear: !this.required,
                minimumInputLength: isLargePicker ? 1 : 0,
                placeholder: $(this).find('option:first').text() || 'Select'
            });
        });
    }

    function loadMasterlists(callback) {
        if (masterlistsLoaded) {
            initSelect2();
            callback?.();
            return;
        }
        $.getJSON(actionUrl, { action: 'masterlists' }).done(function (res) {
            if (!res.success) return;
            masterlists = res.data || masterlists;
            masterlistsLoaded = true;
            $('#end_user_id').html(options(masterlists.users, 'Select user'));
            $('#employee_designation_id').html(options(masterlists.departments, 'Select designation'));
            $('#complain').html(options(masterlists.complaints, 'Select complaint'));
            $('#troubleshoot').html(options(masterlists.troubleshoots, 'Select troubleshoot'));
            $('#remarks_service_details').html(options(masterlists.remarks, 'Select remarks'));
            $('#attending_technician').html(options(masterlists.technicians, 'Select technician'));
            $('#service_equipment_picker').html(options(masterlists.equipment, 'Select equipment'));
            $('#service_supply_picker').html(options(masterlists.supplies, 'Select supply'));
            $('#service_subscription_picker').html(options(masterlists.subscriptions, 'Select subscription'));
            initSelect2();
            callback?.();
        });
    }

    function initItemSelect2(select, openAfterInit = false) {
        if (!$.fn.select2) return;
        const $select = $(select);
        if ($select.hasClass('select2-hidden-accessible')) {
            if (openAfterInit) {
                $select.select2('open');
            }
            return;
        }
        $select.select2({
            width: '100%',
            dropdownParent: $('#serviceFormModal'),
            allowClear: !$select.hasClass('item-type'),
            minimumInputLength: $select.hasClass('item-description') || $select.hasClass('item-brand') ? 1 : 0,
            placeholder: $select.find('option:first').text() || 'Select'
        });
        if (openAfterInit) {
            window.setTimeout(() => $select.select2('open'), 0);
        }
    }

    $(document).on('mousedown', '#serviceItems select.item-select2', function (event) {
        if ($.fn.select2 && !$(this).hasClass('select2-hidden-accessible')) {
            event.preventDefault();
            initItemSelect2(this, true);
        }
    });

    $(document).on('focus', '#serviceItems select.item-select2', function () {
        initItemSelect2(this);
    });

    function renderItems() {
        const descOptions = options(masterlists.descriptions, 'Description');
        const brandOptions = options(masterlists.brands, 'Brand');
        const statusOptions = options(masterlists.statuses, 'No status');
        const typeMeta = {
            equipment: { title: 'Equipment', icon: 'bi-pc-display', accent: 'text-primary' },
            supply: { title: 'Supplies', icon: 'bi-box-seam', accent: 'text-success' },
            subscription: { title: 'Subscription', icon: 'bi-receipt', accent: 'text-info' },
            other: { title: 'Other / As Is', icon: 'bi-pencil-square', accent: 'text-secondary' }
        };
        const typeField = (item) => `
            <select class="form-select item-type item-select2">
                <option value="equipment"${item.type === 'equipment' ? ' selected' : ''}>Equipment</option>
                <option value="supply"${item.type === 'supply' ? ' selected' : ''}>Supply</option>
                <option value="subscription"${item.type === 'subscription' ? ' selected' : ''}>Subscription</option>
                <option value="other"${item.type === 'other' ? ' selected' : ''}>Other</option>
            </select>
            <input type="hidden" class="item-source-id" value="${escapeHtml(item.source_id || '')}">
        `;
        const statusField = (item) => `<select class="form-select item-status item-status-select item-select2">${statusOptions}</select>`;
        const commonNotes = (item) => `
            <div class="col-md-6"><label class="form-label">Warranty/Status Remarks</label><input type="text" class="form-control item-warranty-remarks" value="${escapeHtml(item.warranty_remarks || '')}"></div>
            <div class="col-md-6"><label class="form-label">Service Remarks</label><input type="text" class="form-control item-remarks" value="${escapeHtml(item.remarks || '')}"></div>
        `;
        const equipmentFields = (item) => `
            <div class="row g-3">
                <div class="col-md-2"><label class="form-label">Type</label>${typeField(item)}</div>
                <div class="col-md-3"><label class="form-label">Control No</label><input type="text" class="form-control item-control" value="${escapeHtml(item.control_no || '')}" placeholder="Auto if blank"></div>
                <div class="col-md-3"><label class="form-label">Date Delivered</label><input type="date" class="form-control item-date-delivered" value="${escapeHtml((item.date_delivered || '').slice(0, 10))}"><div class="text-muted small">Used for auto control no year.</div></div>
                <div class="col-md-3"><label class="form-label">Description</label><select class="form-select item-description item-select2">${descOptions}</select></div>
                <div class="col-md-2"><label class="form-label">Brand</label><select class="form-select item-brand item-select2">${brandOptions}</select></div>
                <div class="col-md-2"><label class="form-label">Serial No</label><input type="text" class="form-control item-serial" value="${escapeHtml(item.serialno || '')}"></div>
                <div class="col-md-3"><label class="form-label">Equipment Status</label>${statusField(item)}<div class="text-muted small">Syncs to equipment table.</div></div>
                <div class="col-md-3"><label class="form-label">Unit Warranty</label><input type="date" class="form-control item-warranty" value="${escapeHtml((item.unit_warranty || '').slice(0, 10))}"></div>
                <input type="hidden" class="item-other-description" value="${escapeHtml(item.other_description || '')}">
                ${commonNotes(item)}
            </div>
        `;
        const supplyFields = (item) => `
            <div class="row g-3">
                <div class="col-md-2"><label class="form-label">Type</label>${typeField(item)}</div>
                <div class="col-md-3"><label class="form-label">Supply Code</label><input type="text" class="form-control item-control" value="${escapeHtml(item.control_no || '')}" placeholder="Auto if blank"></div>
                <div class="col-md-3"><label class="form-label">Description</label><select class="form-select item-description item-select2">${descOptions}</select></div>
                <div class="col-md-2"><label class="form-label">Brand</label><select class="form-select item-brand item-select2">${brandOptions}</select></div>
                <div class="col-md-2"><label class="form-label">Serial No</label><input type="text" class="form-control item-serial" value="${escapeHtml(item.serialno || '')}"></div>
                <div class="col-md-3"><label class="form-label">Supply Status</label>${statusField(item)}<div class="text-muted small">Syncs to supplies table.</div></div>
                <div class="col-md-3"><label class="form-label">Warranty Date</label><input type="date" class="form-control item-warranty" value="${escapeHtml((item.unit_warranty || '').slice(0, 10))}"></div>
                <input type="hidden" class="item-other-description" value="${escapeHtml(item.other_description || '')}">
                ${commonNotes(item)}
            </div>
        `;
        const subscriptionFields = (item) => `
            <div class="row g-3">
                <div class="col-md-3"><label class="form-label">Type</label>${typeField(item)}</div>
                <div class="col-md-5"><label class="form-label">Software / Subscription Description</label><input type="text" class="form-control item-other-description" value="${escapeHtml(item.other_description || item.control_no || '')}" placeholder="Example: Microsoft 365 License"></div>
                <div class="col-md-4"><label class="form-label">Expiry Date</label><input type="date" class="form-control item-warranty" value="${escapeHtml((item.unit_warranty || '').slice(0, 10))}"></div>
                <input type="hidden" class="item-control" value="${escapeHtml(item.control_no || item.other_description || '')}">
                <input type="hidden" class="item-description" value="">
                <input type="hidden" class="item-brand" value="">
                <input type="hidden" class="item-serial" value="">
                <input type="hidden" class="item-date-delivered" value="">
                <input type="hidden" class="item-status" value="">
                <div class="col-12"><label class="form-label">Subscription Remarks</label><input type="text" class="form-control item-remarks" value="${escapeHtml(item.remarks || '')}"></div>
                <input type="hidden" class="item-warranty-remarks" value="${escapeHtml(item.warranty_remarks || '')}">
            </div>
        `;
        const otherFields = (item) => `
            <div class="row g-3">
                <div class="col-md-3"><label class="form-label">Type</label>${typeField(item)}</div>
                <div class="col-md-9"><label class="form-label">Description</label><input type="text" class="form-control item-other-description" value="${escapeHtml(item.other_description || '')}" placeholder="Type the exact item or description"></div>
                <input type="hidden" class="item-control" value="${escapeHtml(item.control_no || '')}">
                <input type="hidden" class="item-description" value="">
                <input type="hidden" class="item-brand" value="">
                <input type="hidden" class="item-serial" value="">
                <input type="hidden" class="item-date-delivered" value="">
                <input type="hidden" class="item-status" value="">
                <input type="hidden" class="item-warranty" value="">
                <input type="hidden" class="item-warranty-remarks" value="${escapeHtml(item.warranty_remarks || '')}">
                <div class="col-12"><label class="form-label">Remarks</label><input type="text" class="form-control item-remarks" value="${escapeHtml(item.remarks || '')}"></div>
            </div>
        `;
        serviceItems = (serviceItems || []).map(normalizeServiceItem);
        const html = serviceItems.map((item, index) => {
            const meta = typeMeta[item.type] || typeMeta.other;
            const rowClass = (item.auto_fetched || item.source_id) ? 'service-item-row-existing' : 'service-item-row-new';
            const fields = item.type === 'equipment'
                ? equipmentFields(item)
                : item.type === 'supply'
                    ? supplyFields(item)
                    : item.type === 'subscription'
                        ? subscriptionFields(item)
                        : otherFields(item);
            return `
            <div class="service-item-row ${rowClass}" data-index="${index}">
                <div class="service-item-row-head">
                    <div class="service-item-row-title"><i class="bi ${meta.icon} ${meta.accent}"></i><span>${meta.title}</span></div>
                    <button type="button" class="service-remove-item remove-service-item" title="Remove item"><i class="bi bi-x-lg"></i></button>
                </div>
                ${fields}
            </div>
        `}).join('');
        $('#serviceItems').html(html || '<div class="text-muted small">No equipment/supplies/other items added yet.</div>');
        serviceItems.forEach((item, index) => {
            const row = $(`.service-item-row[data-index="${index}"]`);
            row.find('.item-description').val(item.description_id || '');
            row.find('.item-brand').val(item.brand_id || '');
            row.find('.item-status').val(item.item_status || '');
        });
    }

    function collectItems() {
        const items = [];
        $('.service-item-row').each(function () {
            const row = $(this);
            items.push({
                type: row.find('.item-type').val(),
                source_id: row.find('.item-source-id').val(),
                control_no: row.find('.item-control').val(),
                other_description: row.find('.item-other-description').val(),
                description_id: row.find('.item-description').val(),
                brand_id: row.find('.item-brand').val(),
                serialno: row.find('.item-serial').val(),
                date_delivered: row.find('.item-date-delivered').val(),
                item_status: row.find('.item-status').val(),
                unit_warranty: row.find('.item-warranty').val(),
                warranty_remarks: row.find('.item-warranty-remarks').val(),
                remarks: row.find('.item-remarks').val()
            });
        });
        return items;
    }

    function resetForm() {
        $('#serviceForm')[0].reset();
        $('#service_id').val('');
        $('#request_id').val('');
        $('#serviceModalTitle').text('New Service Report');
        $('#report_date').val(new Date().toISOString().slice(0, 10));
        serviceTimeFields.forEach(id => $('#' + id).val(defaultTimeValue));
        $('#department_id').val('');
        $('#department_name').val('');
        $('#employee_designation_id').val(null).trigger('change');
        assignedItemsEndUserId = '';
        removedAssignedItemKeys = new Set();
        serviceItems = [];
        renderItems();
        $('.service-select2').val(null).trigger('change');
    }
    const serviceFormGuard = window.IMSUnsavedGuard?.create({
        form: '#serviceForm',
        modal: '#serviceFormModal',
        dirtySelectors: [
            '#addNewEquipmentItem',
            '#addNewSupplyItem',
            '#addNewSubscriptionItem',
            '#addOtherServiceItem',
            '.remove-service-item'
        ]
    });

    $('#openServiceFormBtn').on('click', function () {
        loadMasterlists(function () {
            resetForm();
            bootstrap.Modal.getOrCreateInstance(document.getElementById('serviceFormModal')).show();
        });
    });

    $('#end_user_id').on('change', function () {
        const selected = $(this).find(':selected');
        $('#department_id').val(selected.data('department-id') || '');
        $('#department_name').val(selected.data('department-name') || '');
        if (!$('#employee_designation_id').val()) {
            $('#employee_designation_id').val(selected.data('department-id') || '').trigger('change');
        }
        loadAssignedItemsForEndUser($(this).val());
    });

    function loadAssignedItemsForEndUser(endUserId) {
        endUserId = String(endUserId || '');

        serviceItems = serviceItems.filter(item => !item.auto_fetched);
        if (!endUserId) {
            assignedItemsEndUserId = '';
            removedAssignedItemKeys = new Set();
            renderItems();
            return;
        }

        if (assignedItemsEndUserId !== endUserId) {
            assignedItemsEndUserId = endUserId;
            removedAssignedItemKeys = new Set();
        }

        $.getJSON(actionUrl, { action: 'assigned_items', end_user_id: endUserId })
            .done(function (res) {
                if (String($('#end_user_id').val() || '') !== endUserId) {
                    return;
                }
                if (!res.success) {
                    renderItems();
                    return;
                }

                let added = 0;
                (res.items || []).forEach(function (sourceItem) {
                    const item = serviceItemFromSource(sourceItem, {
                        auto_fetched: true,
                        assigned_key: serviceItemKey(sourceItem)
                    });
                    if (removedAssignedItemKeys.has(item.assigned_key)) {
                        return;
                    }
                    if (appendServiceItem(item)) {
                        added++;
                    }
                });
                renderItems();
                if (added > 0) {
                    $('#serviceItems').prepend(`<div class="alert alert-info py-2 mb-3 small"><i class="bi bi-info-circle"></i> Assigned item(s) under this end user were added automatically. Remove any item you do not want to include.</div>`);
                }
            })
            .fail(function () {
                renderItems();
            });
    }

    $('#attending_technician').on('change', function () {
        const designations = $(this).find(':selected').map(function () { return $(this).data('designation'); }).get().filter(Boolean);
        $('#technician_designation').val([...new Set(designations)].join(', '));
    });

    function addSelectedItem(listName, picker) {
        const selected = (masterlists[listName] || []).find(item => String(item.id) === String($(picker).val()));
        if (!selected) return;
        appendServiceItem(serviceItemFromSource(selected));
        $(picker).val(null).trigger('change');
        renderItems();
    }

    $('#service_equipment_picker').on('change', function () {
        addSelectedItem('equipment', this);
    });

    $('#service_supply_picker').on('change', function () {
        addSelectedItem('supplies', this);
    });

    $('#service_subscription_picker').on('change', function () {
        addSelectedItem('subscriptions', this);
    });

    $('#addNewEquipmentItem').on('click', function () {
        serviceItems.unshift({ type: 'equipment', other_description: '', item_status: 'Working' });
        renderItems();
    });

    $('#addNewSupplyItem').on('click', function () {
        serviceItems.unshift({ type: 'supply', other_description: '', item_status: 'Working' });
        renderItems();
    });

    $('#addNewSubscriptionItem').on('click', function () {
        serviceItems.unshift({ type: 'subscription', other_description: '', item_status: '' });
        renderItems();
    });

    $('#addOtherServiceItem').on('click', function () {
        serviceItems.unshift({ type: 'other', other_description: '' });
        renderItems();
    });

    $(document).on('click', '.remove-service-item', function () {
        const index = Number($(this).closest('.service-item-row').data('index'));
        const item = serviceItems[index];
        if (item && item.auto_fetched && item.assigned_key) {
            removedAssignedItemKeys.add(item.assigned_key);
        }
        serviceItems.splice(index, 1);
        renderItems();
    });

    $(document).on('change', '.item-type', function () {
        const row = $(this).closest('.service-item-row');
        const index = Number(row.data('index'));
        if (!serviceItems[index]) return;
        const selectedType = $(this).val();
        serviceItems[index] = {
            ...serviceItems[index],
            type: selectedType,
            source_id: '',
            control_no: row.find('.item-control').val() || '',
            other_description: row.find('.item-other-description').val() || '',
            description_id: row.find('.item-description').val() || '',
            brand_id: row.find('.item-brand').val() || '',
            serialno: row.find('.item-serial').val() || '',
            date_delivered: row.find('.item-date-delivered').val() || '',
            item_status: row.find('.item-status').val() || (selectedType === 'equipment' || selectedType === 'supply' ? 'Working' : ''),
            unit_warranty: row.find('.item-warranty').val() || '',
            warranty_remarks: row.find('.item-warranty-remarks').val() || '',
            remarks: row.find('.item-remarks').val() || ''
        };
        renderItems();
    });

    $('#serviceForm').on('submit', function (event) {
        event.preventDefault();
        const formData = new FormData(this);
        formData.append('action', $('#service_id').val() ? 'update' : 'create');
        formData.set('complain', JSON.stringify($('#complain').val() || []));
        formData.set('troubleshoot', JSON.stringify($('#troubleshoot').val() || []));
        formData.set('remarks_service_details', JSON.stringify($('#remarks_service_details').val() || []));
        formData.set('attending_technician', JSON.stringify($('#attending_technician').val() || []));
        formData.append('items', JSON.stringify(collectItems()));

        $.ajax({
            url: actionUrl,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            beforeSend: () => Swal.fire({ title: 'Saving service report...', allowOutsideClick: false, didOpen: () => Swal.showLoading() }),
            success: function (res) {
                if (!res.success) {
                    Swal.fire('Error', res.message || 'Unable to save service report.', 'error');
                    return;
                }
                serviceFormGuard?.forceClean();
                $('#serviceFormModal').modal('hide');
                Swal.fire('Saved', res.message, 'success');
                table.ajax.reload(null, false);
            },
            error: xhr => Swal.fire('Error', xhr.responseJSON?.message || 'Unable to save service report.', 'error')
        });
    });

    function fillForm(record, items) {
        $('#service_id').val(record.id);
        $('#request_id').val(record.request_id || '');
        $('#service_controlno').val(record.service_controlno);
        $('#old_service_cn').val(record.old_service_cn);
        $('#report_date').val(record.report_date);
        $('#report_time').val(record.report_time || defaultTimeValue);
        $('#end_user_id').val(record.end_user_id).trigger('change');
        $('#department_id').val(record.department_id);
        $('#department_name').val(record.department_name || '');
        $('#employee_designation_id').val(record.employee_designation_id || record.department_id || '').trigger('change');
        ['vicinity','distance','travel_date','remarks_date_pullout','remarks_date_returned','technician_designation','employee_satisfactory'].forEach(id => $('#' + id).val(record[id] || ''));
        serviceTimeFields
            .filter(id => id !== 'report_time')
            .forEach(id => $('#' + id).val(record[id] || defaultTimeValue));
        $('#complain').val(record.complain_values || []).trigger('change');
        $('#troubleshoot').val(record.troubleshoot_values || []).trigger('change');
        $('#remarks_service_details').val(record.remarks_service_details_values || []).trigger('change');
        $('#attending_technician').val(record.attending_technician_values || []).trigger('change');
        assignedItemsEndUserId = '';
        removedAssignedItemKeys = new Set();
        serviceItems = (items || []).map(normalizeServiceItem);
        renderItems();
        loadAssignedItemsForEndUser(record.end_user_id);
    }

    $(document).on('click', '.edit-service-btn', function () {
        const id = $(this).data('id');
        loadMasterlists(function () {
            $.getJSON(actionUrl, { action: 'view', id }).done(function (res) {
                if (!res.success) return Swal.fire('Error', res.message || 'Unable to load service report.', 'error');
                $('#serviceModalTitle').text('Edit Service Report');
                fillForm(res.data, res.items);
                bootstrap.Modal.getOrCreateInstance(document.getElementById('serviceFormModal')).show();
            });
        });
    });

    $(document).on('click', '.view-service-btn', function () {
        const id = $(this).data('id');
        $.getJSON(actionUrl, { action: 'view', id }).done(function (res) {
            if (!res.success) return Swal.fire('Error', res.message || 'Unable to load service report.', 'error');
            const r = res.data;
            const items = (res.items || []).map(item => `<tr><td>${escapeHtml(item.type)}</td><td>${escapeHtml(item.control_no)}</td><td>${escapeHtml(item.date_delivered || '')}</td><td>${escapeHtml(item.other_description || item.description_name)}</td><td>${escapeHtml(item.brand_name)}</td><td>${escapeHtml(item.serialno)}</td><td>${escapeHtml(item.item_status)}</td><td>${escapeHtml(item.remarks)}</td></tr>`).join('');
            $('#serviceViewBody').html(`
                <div class="service-detail-grid mb-3">
                    <div class="service-detail-item"><div class="service-detail-label">Service CN</div><div class="service-detail-value">${escapeHtml(r.service_controlno)}</div></div>
                    <div class="service-detail-item"><div class="service-detail-label">Old Service CN</div><div class="service-detail-value">${escapeHtml(r.old_service_cn || '')}</div></div>
                    <div class="service-detail-item"><div class="service-detail-label">End User</div><div class="service-detail-value">${escapeHtml(r.end_user_name || '')}</div></div>
                    <div class="service-detail-item"><div class="service-detail-label">Department</div><div class="service-detail-value">${escapeHtml(r.department_name || '')}</div></div>
                    <div class="service-detail-item"><div class="service-detail-label">Employee Designation</div><div class="service-detail-value">${escapeHtml(r.employee_designation_name || r.department_name || '')}</div></div>
                    <div class="service-detail-item"><div class="service-detail-label">Complaints</div><div class="service-detail-value">${escapeHtml((r.complain_values || []).join(', '))}</div></div>
                    <div class="service-detail-item"><div class="service-detail-label">Troubleshoot</div><div class="service-detail-value">${escapeHtml((r.troubleshoot_values || []).join(', '))}</div></div>
                    <div class="service-detail-item"><div class="service-detail-label">Technician</div><div class="service-detail-value">${escapeHtml((r.attending_technician_values || []).join(', '))}</div></div>
                    <div class="service-detail-item"><div class="service-detail-label">Report Date/Time</div><div class="service-detail-value">${escapeHtml(r.report_date || '')} ${escapeHtml(r.report_time || '')}</div></div>
                </div>
                <h6 class="fw-bold">Connected Items</h6>
                <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Type</th><th>Control</th><th>Date Delivered</th><th>Description</th><th>Brand</th><th>Serial</th><th>Status</th><th>Remarks</th></tr></thead><tbody>${items || '<tr><td colspan="8" class="text-muted">No items.</td></tr>'}</tbody></table></div>
            `);
            bootstrap.Modal.getOrCreateInstance(document.getElementById('serviceViewModal')).show();
        });
    });

    $(document).on('click', '.delete-service-btn', function () {
        const id = $(this).data('id');
        Swal.fire({ title: 'Archive service report?', text: 'This will move the report to the archive.', icon: 'warning', showCancelButton: true, confirmButtonColor: '#f59e0b', confirmButtonText: 'Archive' }).then(function (result) {
            if (!result.isConfirmed) return;
            $.ajax({
                url: actionUrl,
                method: 'POST',
                contentType: 'application/json',
                dataType: 'json',
                data: JSON.stringify({ action: 'delete', id }),
                success: function (res) {
                    if (!res.success) return Swal.fire('Error', res.message || 'Unable to archive service report.', 'error');
                    Swal.fire('Archived', res.message, 'success');
                    table.ajax.reload(null, false);
                },
                error: xhr => Swal.fire('Error', xhr.responseJSON?.message || 'Unable to delete service report.', 'error')
            });
        });
    });

    $(document).on('click', '.upload-service-pdf-btn', function () {
        const panel = $(this).closest('.ims-upload-panel');
        const input = panel.find('.service-pdf-input')[0];
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
            error: xhr => Swal.fire('Error', xhr.responseJSON?.message || 'Unable to upload PDF.', 'error')
        });
    });

    $(document).on('click', '.remove-service-pdf', function () {
        const id = $(this).data('id');
        Swal.fire({ title: 'Delete attachment?', text: 'This PDF file will be permanently removed.', icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33' })
            .then(function (result) {
                if (!result.isConfirmed) return;
                $.post(actionUrl, { action: 'remove_pdf', id }, function (res) {
                    Swal.fire(res.success ? 'Removed' : 'Error', res.message || 'Unable to remove PDF.', res.success ? 'success' : 'error');
                    if (res.success) table.ajax.reload(null, false);
                }, 'json');
            });
    });

    $(document).on('click', '.preview-service-pdf', function () {
        $('#servicePdfFrame').attr('src', $(this).data('pdf'));
        bootstrap.Modal.getOrCreateInstance(document.getElementById('servicePdfModal')).show();
    });

    $('#servicePdfModal').on('hidden.bs.modal', function () {
        $('#servicePdfFrame').attr('src', '');
    });

    const requestIdFromUrl = new URLSearchParams(window.location.search).get('request_id');
    if (requestIdFromUrl) {
        loadMasterlists(function () {
            $.getJSON('<?= BASE_URL ?>/ictd/requests/service-action', { action: 'get', id: requestIdFromUrl }, function (res) {
                if (!res.success || !res.data) {
                    Swal.fire('Error', res.message || 'Service request not found.', 'error');
                    return;
                }
                resetForm();
                const request = res.data;
                $('#request_id').val(request.id);
                $('#end_user_id').val(request.end_user_id).trigger('change');
                $('#department_id').val(request.department_id);
                $('#department_name').val($('#end_user_id').find(':selected').data('department-name') || '');
                $('#employee_designation_id').val(request.department_id || '').trigger('change');
                function setMultiValue(selector, value) {
                    if (!value) return;
                    const select = $(selector);
                    if (!select.find('option').filter(function () { return this.value === value; }).length) {
                        select.append(new Option(value, value, true, true));
                    }
                    select.val([value]).trigger('change');
                }
                setMultiValue('#complain', request.complain_problem || request.request_description || '');
                setMultiValue('#remarks_service_details', request.remarks || '');
                $('#serviceModalTitle').text('New Service Report for Request #' + request.id);
                bootstrap.Modal.getOrCreateInstance(document.getElementById('serviceFormModal')).show();
            });
        });
    }
});
</script>

<?php require_once ROOT_PATH . '/modules/layout/footer.php'; ?>
