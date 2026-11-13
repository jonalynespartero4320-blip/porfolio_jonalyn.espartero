<?php
$page_title = 'Service Requests';
require_once ROOT_PATH . '/modules/layout/header.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/functions.php';

require_login();
require_permission('ictd.request.view');
?>

<style>
  .request-shell { display: grid; gap: 1.05rem; }
  .request-head { display: flex; justify-content: space-between; gap: 1rem; align-items: flex-start; }
  .request-eyebrow { color: #667085; font-size: .78rem; font-weight: 750; }
  .request-title { color: #1f2937; font-size: 1.35rem; font-weight: 850; margin: 0; }
  .request-toolbar { display: flex; justify-content: flex-end; gap: .65rem; flex-wrap: wrap; }
  .request-search { position: relative; min-width: min(340px, 100%); }
  .request-search i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #98a2b3; }
  .request-search input { height: 38px; padding-left: 34px; border-radius: 4px; border-color: #eaecf0; font-size: .78rem; }
  .request-panel { border: 0; border-radius: 10px; background: rgba(255,255,255,.78); box-shadow: none; }
  .request-table { font-size: .8rem; color: #1f2937; }
  .request-table thead th { white-space: nowrap; font-size: .72rem; color: #5f6672; background: #f3f4f6 !important; border: 0 !important; padding: .72rem .8rem !important; }
  .request-table tbody td { border: 0 !important; border-bottom: 1px solid #f2f4f7 !important; padding: .72rem .8rem !important; vertical-align: middle; white-space: nowrap; }
  .request-actions { display: inline-flex; gap: .55rem; align-items: center; }
  .request-action-btn { border: 0; background: transparent; padding: 0; line-height: 1; font-size: .95rem; }
  .request-action-edit { color: #1f9bb4; }
  .request-action-delete { color: #e26060; }
  .request-action-archive { color: #667085; }
  .request-table-card .row:first-child { display: none; }
  .request-table-card .row:last-child { margin: 0; padding: .9rem .25rem 0; align-items: center; color: #475467; font-size: .78rem; }
  .request-table-card .page-link { min-width: 34px; height: 34px; border-radius: 4px !important; display: inline-flex; align-items: center; justify-content: center; border-color: #eaecf0; color: #344054; font-size: .78rem; }
</style>

<div class="container-fluid py-3">
    <div class="request-shell">
        <div class="request-head">
            <div>
                <div class="request-eyebrow">Requests</div>
                <h2 class="request-title">Service Requests</h2>
                <div class="text-muted small">Create ICTD service requests and track admin responses.</div>
            </div>
            <button type="button" class="btn btn-primary btn-sm" id="openServiceModal"><i class="bi bi-plus-lg"></i> New Service Request</button>
        </div>

        <div class="request-toolbar">
            <div class="request-search">
                <i class="bi bi-search"></i>
                <input type="search" class="form-control" id="serviceSearch" placeholder="Search service requests">
            </div>
        </div>

        <div class="card request-panel request-table-card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="serviceRequestTable" class="table align-middle nowrap mb-0 request-table" style="width:100%">
                        <thead>
                            <tr>
                                <th>Action</th>
                                <th>ID</th>
                                <th>End User</th>
                                <th>Department</th>
                                <th>Request Type</th>
                                <th>Description</th>
                                <th>Status</th>
                                <th>Technician</th>
                                <th>Remarks / Reason</th>
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

<div class="modal fade" id="serviceRequestModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0">
            <form id="serviceRequestForm">
                <input type="hidden" id="service_id">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title fw-bold" id="serviceModalTitle">New Service Request</h5>
                        <div class="text-muted small">Department fills from the selected end user.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Request Type *</label>
                            <select class="form-select" id="request_type" required>
                                <option value="Service Request">Service Request</option>
                                <option value="Personal Request">Personal Request</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">End User *</label>
                            <select class="form-select" id="end_user_id" required></select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Department *</label>
                            <input type="text" class="form-control" id="department_name" readonly>
                            <input type="hidden" id="department_id">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Remarks</label>
                            <textarea class="form-control" id="remarks" rows="2"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Request Description *</label>
                            <textarea class="form-control" id="request_description" rows="3" required></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Complaint / Problem *</label>
                            <textarea class="form-control" id="complain_problem" rows="3" required></textarea>
                        </div>
                    </div>
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
    const actionUrl = '<?= BASE_URL ?>/ictd/requests/service-action';
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('serviceRequestModal'));
    let optionUsers = [];

    const table = $('#serviceRequestTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: { url: '<?= BASE_URL ?>/ictd/requests/service-data', type: 'POST' },
        order: [[9, 'desc']],
        pageLength: 12,
        scrollX: true,
        dom: 'rt<"row align-items-center mt-3 px-3 pb-3"<"col-md-4"i><"col-md-4 text-center"l><"col-md-4"p>>',
        columnDefs: [{ targets: [0], orderable: false, searchable: false }]
    });

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
    }
    function fillUsers() {
        $('#end_user_id').html('<option value="">Select end user</option>' + optionUsers.map(u => `<option value="${escapeHtml(u.id)}" data-department="${escapeHtml(u.department_id || '')}" data-department-name="${escapeHtml(u.department_name || '')}">${escapeHtml(u.text)}</option>`).join(''));
    }
    function loadOptions(callback) {
        $.getJSON(actionUrl, { action: 'options' }, function (res) {
            optionUsers = (res.data && res.data.users) || [];
            fillUsers();
            if (callback) callback();
        });
    }
    function resetForm() {
        $('#serviceRequestForm')[0].reset();
        $('#service_id').val('');
        $('#department_id,#department_name').val('');
        $('#serviceModalTitle').text('New Service Request');
    }
    function payload() {
        return {
            action: 'save',
            id: $('#service_id').val(),
            request_type: $('#request_type').val(),
            end_user_id: $('#end_user_id').val(),
            department_id: $('#department_id').val(),
            request_description: $('#request_description').val(),
            complain_problem: $('#complain_problem').val(),
            remarks: $('#remarks').val()
        };
    }

    $('#serviceSearch').on('input', function () { table.search(this.value).draw(); });
    $('#openServiceModal').on('click', function () { loadOptions(function () { resetForm(); modal.show(); }); });
    $('#end_user_id').on('change', function () {
        const option = $(this).find(':selected');
        $('#department_id').val(option.data('department') || '');
        $('#department_name').val(option.data('department-name') || '');
    });
    $('#serviceRequestForm').on('submit', function (event) {
        event.preventDefault();
        $.post(actionUrl, payload(), function (res) {
            Swal.fire(res.success ? 'Saved' : 'Error', res.message || 'Unable to save request.', res.success ? 'success' : 'error');
            if (res.success) { modal.hide(); table.ajax.reload(null, false); }
        }, 'json');
    });
    $(document).on('click', '.edit-service-btn', function () {
        const id = $(this).data('id');
        loadOptions(function () {
            $.getJSON(actionUrl, { action: 'get', id }, function (res) {
                if (!res.success) return Swal.fire('Error', res.message || 'Request not found.', 'error');
                resetForm();
                $('#serviceModalTitle').text('Edit Service Request');
                $('#service_id').val(res.data.id);
                $('#request_type').val(res.data.request_type);
                $('#end_user_id').val(res.data.end_user_id).trigger('change');
                $('#department_id').val(res.data.department_id);
                $('#department_name').val($('#end_user_id').find(':selected').data('department-name') || '');
                $('#request_description').val(res.data.request_description);
                $('#complain_problem').val(res.data.complain_problem);
                $('#remarks').val(res.data.remarks);
                modal.show();
            });
        });
    });
    $(document).on('click', '.cancel-service-btn', function () {
        const id = $(this).data('id');
        Swal.fire({ title: 'Cancel service request?', input: 'textarea', inputLabel: 'Cancellation reason', showCancelButton: true, confirmButtonColor: '#dc3545', confirmButtonText: 'Cancel Request', inputValidator: v => !v.trim() ? 'Please enter a reason.' : undefined }).then(result => {
            if (!result.isConfirmed) return;
            $.post(actionUrl, { action: 'cancel', id, reason: result.value }, function (res) {
                Swal.fire(res.success ? 'Cancelled' : 'Error', res.message || 'Unable to cancel request.', res.success ? 'success' : 'error');
                if (res.success) table.ajax.reload(null, false);
            }, 'json');
        });
    });
    $(document).on('click', '.archive-service-btn', function () {
        const id = $(this).data('id');
        Swal.fire({ title: 'Archive request?', text: 'This will move the request to archive.', icon: 'question', showCancelButton: true, confirmButtonText: 'Archive' }).then(result => {
            if (!result.isConfirmed) return;
            $.post(actionUrl, { action: 'archive', id }, function (res) {
                Swal.fire(res.success ? 'Archived' : 'Error', res.message || 'Unable to archive request.', res.success ? 'success' : 'error');
                if (res.success) table.ajax.reload(null, false);
            }, 'json');
        });
    });
});
</script>

<?php require_once ROOT_PATH . '/modules/layout/footer.php'; ?>
