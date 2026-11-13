<?php
$page_title = 'Service Request Status';
require_once ROOT_PATH . '/modules/layout/header.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/functions.php';

require_login();
require_any_permission(['ictd.request.manage', 'ictd.request.view']);

if (!is_admin() && !can('ictd.request.manage')) {
    http_response_code(403);
    echo '<div class="container-fluid py-4"><div class="card border-0 shadow-sm"><div class="card-body">Access denied. Only administrators or allowed personnel can manage service request status.</div></div></div>';
    require_once ROOT_PATH . '/modules/layout/footer.php';
    exit;
}
?>

<style>
  .request-shell { display: grid; gap: 1.05rem; }
  .request-head { display: flex; justify-content: space-between; gap: 1rem; align-items: flex-start; }
  .request-eyebrow { color: #667085; font-size: .78rem; font-weight: 750; }
  .request-title { color: #1f2937; font-size: 1.35rem; font-weight: 850; margin: 0; }
  .request-tabs { display: flex; gap: .35rem; flex-wrap: wrap; border-bottom: 1px solid #eaecf0; }
  .request-tabs .btn { border: 0; border-bottom: 2px solid transparent; border-radius: 0; min-height: 38px; font-size: .78rem; background: transparent; color: #475467; }
  .request-tabs .btn.active { border-bottom-color: #3657f6; color: #3657f6; font-weight: 750; }
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
  .request-action-view { color: #3657f6; }
  .request-action-restore { color: #16803d; }
  .request-action-delete { color: #e26060; }
  .request-table-card .row:first-child { display: none; }
  .request-table-card .row:last-child { margin: 0; padding: .9rem .25rem 0; align-items: center; color: #475467; font-size: .78rem; }
</style>

<div class="container-fluid py-3">
    <div class="request-shell">
        <div class="request-head">
            <div>
                <div class="request-eyebrow">Requests</div>
                <h2 class="request-title">Service Request Status</h2>
                <div class="text-muted small">Acknowledge, reject, assign technicians, and close service requests.</div>
            </div>
            <a href="<?= BASE_URL ?>/ictd/requests/service" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back to Requests</a>
        </div>
        <div class="request-tabs">
            <button class="btn filter-status active" data-status="">All (<span id="count-all">0</span>)</button>
            <button class="btn filter-status" data-status="Pending">Pending (<span id="count-pending">0</span>)</button>
            <button class="btn filter-status" data-status="acknowledged">Acknowledged (<span id="count-acknowledged">0</span>)</button>
            <button class="btn filter-status" data-status="assigned">Assigned (<span id="count-assigned">0</span>)</button>
            <button class="btn filter-status" data-status="Accomplished">Accomplished (<span id="count-accomplished">0</span>)</button>
            <button class="btn filter-status" data-status="Rejected">Rejected (<span id="count-rejected">0</span>)</button>
            <button class="btn filter-status" data-status="Cancelled">Cancelled (<span id="count-cancelled">0</span>)</button>
        </div>
        <div class="request-toolbar">
            <div class="request-search"><i class="bi bi-search"></i><input type="search" class="form-control" id="statusSearch" placeholder="Search service request status"></div>
        </div>
        <div class="card request-panel request-table-card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="serviceStatusTable" class="table align-middle nowrap mb-0 request-table" style="width:100%">
                        <thead><tr><th>Action</th><th>ID</th><th>End User</th><th>Department</th><th>Request Type</th><th>Description</th><th>Status</th><th>Technician</th><th>Remarks / Reason</th><th>Created</th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const actionUrl = '<?= BASE_URL ?>/ictd/requests/service-action';
    let selectedStatus = '';
    let technicians = [];
    $.getJSON(actionUrl, { action: 'options' }, res => { technicians = (res.data && res.data.technicians) || []; });
    const table = $('#serviceStatusTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: { url: '<?= BASE_URL ?>/ictd/requests/service-data', type: 'POST', data: d => { d.manage = 1; d.status = selectedStatus; }, dataSrc: json => { updateCounts(json.counts || {}); return json.data || []; } },
        order: [[9, 'desc']],
        pageLength: 12,
        scrollX: true,
        dom: 'rt<"row align-items-center mt-3 px-3 pb-3"<"col-md-4"i><"col-md-4 text-center"l><"col-md-4"p>>',
        columnDefs: [{ targets: [0], orderable: false, searchable: false }]
    });
    function updateCounts(c) {
        $('#count-all').text(c.all || 0);
        $('#count-pending').text(c.Pending || 0);
        $('#count-acknowledged').text(c.Acknowledged || 0);
        $('#count-assigned').text(c.Assigned || 0);
        $('#count-accomplished').text(c.Accomplished || 0);
        $('#count-rejected').text(c.Rejected || 0);
        $('#count-cancelled').text(c.Cancelled || 0);
    }
    $('.filter-status').on('click', function () { selectedStatus = $(this).data('status') || ''; $('.filter-status').removeClass('active'); $(this).addClass('active'); table.ajax.reload(); });
    $('#statusSearch').on('input', function () { table.search(this.value).draw(); });
    $(document).on('click', '.acknowledge-service-btn', function () {
        $.post(actionUrl, { action: 'acknowledge', id: $(this).data('id') }, res => { Swal.fire(res.success ? 'Acknowledged' : 'Error', res.message, res.success ? 'success' : 'error'); table.ajax.reload(null, false); }, 'json');
    });
    $(document).on('click', '.reject-service-btn', function () {
        const id = $(this).data('id');
        Swal.fire({ title: 'Reject request?', input: 'textarea', inputLabel: 'Reason for rejection', showCancelButton: true, confirmButtonColor: '#dc3545', confirmButtonText: 'Reject', inputValidator: v => !v.trim() ? 'Please enter a reason.' : undefined }).then(result => {
            if (!result.isConfirmed) return;
            $.post(actionUrl, { action: 'reject', id, reason: result.value }, res => { Swal.fire(res.success ? 'Rejected' : 'Error', res.message, res.success ? 'success' : 'error'); table.ajax.reload(null, false); }, 'json');
        });
    });
    $(document).on('click', '.assign-service-btn', function () {
        const id = $(this).data('id');
        if (!technicians.length) {
            Swal.fire('No technicians', 'Add technicians first in Masterlist > Technical.', 'info');
            return;
        }
        const options = technicians.map(t => `<option value="${String(t.id).replace(/"/g, '&quot;')}">${String(t.text)}</option>`).join('');
        Swal.fire({
            title: 'Assign technician',
            html: `<select id="technicianName" class="form-select" style="width:100%">${options}</select>`,
            showCancelButton: true,
            confirmButtonText: 'Assign',
            didOpen: () => {
                if ($.fn.select2) {
                    $('#technicianName').select2({
                        dropdownParent: $('.swal2-popup'),
                        width: '100%',
                        placeholder: 'Select technician'
                    });
                }
            },
            preConfirm: () => {
                const value = $('#technicianName').val();
                if (!value) {
                    Swal.showValidationMessage('Select a technician.');
                    return false;
                }
                return value;
            }
        }).then(result => {
            if (!result.isConfirmed) return;
            $.post(actionUrl, { action: 'assign', id, technician_name: result.value }, res => { Swal.fire(res.success ? 'Assigned' : 'Error', res.message, res.success ? 'success' : 'error'); table.ajax.reload(null, false); }, 'json');
        });
    });
    $(document).on('click', '.accomplish-service-btn', function () {
        const id = $(this).data('id');
        $.post(actionUrl, { action: 'accomplish', id }, res => { Swal.fire(res.success ? 'Accomplished' : 'Error', res.message, res.success ? 'success' : 'error'); table.ajax.reload(null, false); }, 'json');
    });
});
</script>

<?php require_once ROOT_PATH . '/modules/layout/footer.php'; ?>
