<?php
$page_title = 'Software Subscriptions';
require_once ROOT_PATH . '/modules/layout/header.php';
require_once __DIR__ . '/subscription-common.php';

require_login();
require_permission('ictd.subscription.view');
subscription_ensure_schema($pdo);

$canCreateSubscription = can('ictd.subscription.create') || can('ictd.subscription.view');
?>

<style>
  .subscription-shell { display: grid; gap: 1.05rem; }
  .subscription-page-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; }
  .subscription-eyebrow { color: #667085; font-size: .78rem; font-weight: 750; }
  .subscription-title { color: #1f2937; font-size: 1.35rem; font-weight: 850; margin: 0; }
  .subscription-head-actions { display: flex; align-items: center; gap: .65rem; flex-wrap: wrap; }
  .subscription-head-actions .btn { border-radius: 4px; min-height: 38px; padding-inline: 1rem; }
  .subscription-head-actions .btn-primary { background: #3657f6; border-color: #3657f6; }
  .subscription-toolbar { display: flex; align-items: center; justify-content: flex-end; gap: .65rem; flex-wrap: wrap; }
  .subscription-search { position: relative; min-width: min(380px, 100%); }
  .subscription-search i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #98a2b3; font-size: .85rem; }
  .subscription-search input { height: 38px; padding-left: 34px; border-radius: 4px; border-color: #eaecf0; font-size: .78rem; }
  .subscription-soft-btn { height: 38px; border: 1px solid #eaecf0; background: #fff; color: #344054; border-radius: 4px; padding: 0 .8rem; font-size: .78rem; font-weight: 650; }
  .subscription-panel { border: 0; border-radius: 10px; background: rgba(255,255,255,.78); box-shadow: none; }
  .subscription-table-container { overflow: hidden; border-radius: 8px; background: rgba(255,255,255,.92); }
  .subscription-table { border-collapse: separate !important; border-spacing: 0 !important; font-size: .8rem; color: #1f2937; }
  .subscription-table thead th { white-space: nowrap; font-size: .72rem; color: #5f6672; font-weight: 760; background: #f3f4f6 !important; border: 0 !important; padding: .72rem .8rem !important; }
  .subscription-table tbody td { border: 0 !important; border-bottom: 1px solid #f2f4f7 !important; padding: .72rem .8rem !important; vertical-align: middle; white-space: nowrap; }
  .subscription-table tbody tr:nth-child(even) td { background: rgba(249,250,251,.72); }
  .subscription-table tbody tr:hover td { background: #f8fbff; }
  .subscription-actions { display: inline-flex; align-items: center; gap: .6rem; white-space: nowrap; }
  .subscription-action-btn { border: 0; background: transparent; padding: 0; line-height: 1; font-size: .9rem; font-weight: 750; }
  .subscription-action-edit { color: #1f9bb4; }
  .subscription-action-delete { color: #e26060; }
  .subscription-days { margin-left: .4rem; color: #667085; font-size: .7rem; font-weight: 800; }
  .subscription-table-card .card-body { padding: 0; }
  .subscription-table-card .dataTables_wrapper { padding: 0; }
  .subscription-table-card .row:first-child { display: none; }
  .subscription-table-card .row:last-child { margin: 0; padding: .9rem .25rem 0; align-items: center; color: #475467; font-size: .78rem; }
  .subscription-table-card .dataTables_paginate .pagination { gap: .35rem; margin: 0; justify-content: flex-end; }
  .subscription-table-card .page-link { min-width: 34px; height: 34px; border-radius: 4px !important; display: inline-flex; align-items: center; justify-content: center; border-color: #eaecf0; color: #344054; font-size: .78rem; box-shadow: none; }
  .subscription-table-card .page-item.active .page-link { background: #3657f6; border-color: #3657f6; color: #fff; }
  @media (max-width: 767px) {
    .subscription-page-head { flex-direction: column; }
    .subscription-toolbar { justify-content: stretch; }
    .subscription-search, .subscription-soft-btn { width: 100%; }
  }
</style>

<div class="container-fluid py-3">
    <div class="subscription-shell">
        <div class="subscription-page-head">
            <div>
                <div class="subscription-eyebrow">Manage</div>
                <h2 class="subscription-title">Software Subscriptions</h2>
            </div>
            <div class="subscription-head-actions">
                <a href="<?= BASE_URL ?>/ictd/subscription/archive" class="btn btn-outline-secondary btn-sm"><i class="bi bi-archive"></i> Archive</a>
                <?php if ($canCreateSubscription): ?>
                    <button type="button" class="btn btn-primary btn-sm" id="openSubscriptionModal"><i class="bi bi-plus-lg"></i> New Subscription</button>
                <?php endif; ?>
            </div>
        </div>

        <div class="subscription-toolbar">
            <div class="subscription-search">
                <i class="bi bi-search"></i>
                <input type="search" class="form-control" id="subscriptionSearch" placeholder="Search subscriptions, users, departments">
            </div>
            <button type="button" class="subscription-soft-btn" id="subscriptionRefreshBtn"><i class="bi bi-arrow-clockwise"></i></button>
        </div>

        <div class="card subscription-panel subscription-table-card">
            <div class="card-body p-0">
                <div class="subscription-table-container">
                    <div class="table-responsive">
                        <table id="subscriptionTable" class="table align-middle nowrap mb-0 subscription-table" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Action</th>
                                    <th>Software</th>
                                    <th>End User</th>
                                    <th>Department</th>
                                    <th>Purchase</th>
                                    <th>Expiry</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="subscriptionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form id="subscriptionForm">
                <input type="hidden" name="subscription_id" id="subscription_id">
                <input type="hidden" name="department_id" id="department_id">
                <div class="modal-header bg-light text-dark">
                    <h5 class="modal-title" id="subscriptionModalTitle">New Subscription</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">End User</label>
                        <select name="end_user_id" id="end_user_id" class="form-select subscription-select2" required></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Department</label>
                        <input type="text" id="department_name" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Software Description</label>
                        <input type="text" class="form-control" name="software_description" id="software_description" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Purchase Date</label>
                            <input type="date" class="form-control" name="purchase_date" id="purchase_date" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Expiry Date</label>
                            <input type="date" class="form-control" name="expiry_date" id="expiry_date" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" id="subscriptionSaveBtn"><i class="bi bi-save"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(function () {
    const actionUrl = '<?= BASE_URL ?>/ictd/subscription-action';
    const table = $('#subscriptionTable').DataTable({
        ajax: { url: '<?= BASE_URL ?>/ictd/subscription-data', type: 'POST' },
        order: [[5, 'asc']],
        scrollX: true,
        dom: 'rt<"row align-items-center mt-3 px-3 pb-3"<"col-md-4"i><"col-md-4 text-center"l><"col-md-4"p>>',
        columnDefs: [{ targets: [0], orderable: false, searchable: false }],
        language: { emptyTable: 'No subscriptions recorded yet.', processing: 'Loading subscriptions...' }
    });

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
    }

    function optionHtml(items) {
        return '<option value="">Select End User</option>' + (items || []).map(item =>
            `<option value="${escapeHtml(item.id)}" data-department-id="${escapeHtml(item.department_id || '')}" data-department-name="${escapeHtml(item.department_name || '')}">${escapeHtml(item.text)}</option>`
        ).join('');
    }

    function initSelect2() {
        if (!$.fn.select2) return;
        $('#end_user_id').select2({ width: '100%', dropdownParent: $('#subscriptionModal'), placeholder: 'Select End User', allowClear: true });
    }

    function loadMasterlists(callback) {
        $.getJSON(actionUrl, { action: 'masterlists' }, function (res) {
            $('#end_user_id').html(optionHtml(res.data.users || []));
            initSelect2();
            if (typeof callback === 'function') callback();
        });
    }

    function showModal() {
        const el = document.getElementById('subscriptionModal');
        (bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el)).show();
    }

    function resetForm() {
        $('#subscriptionForm')[0].reset();
        $('#subscription_id,#department_id,#department_name').val('');
        $('#subscriptionModalTitle').text('New Subscription');
        $('#end_user_id').val(null).trigger('change');
    }

    $('#subscriptionSearch').on('input', function () { table.search(this.value).draw(); });
    $('#subscriptionRefreshBtn').on('click', function () { $('#subscriptionSearch').val(''); table.search('').ajax.reload(); });
    $('#openSubscriptionModal').on('click', function () { resetForm(); loadMasterlists(showModal); });

    $('#end_user_id').on('change', function () {
        const selected = $(this).find(':selected');
        $('#department_id').val(selected.data('department-id') || '');
        $('#department_name').val(selected.data('department-name') || '');
    });

    $(document).on('click', '.edit-subscription-btn', function () {
        const id = $(this).data('id');
        loadMasterlists(function () {
            $.getJSON(actionUrl, { action: 'get', id }, function (res) {
                if (!res.success) return Swal.fire('Error', res.message, 'error');
                const r = res.data;
                $('#subscriptionModalTitle').text('Edit Subscription');
                $('#subscription_id').val(r.subscription_id);
                $('#software_description').val(r.software_description);
                $('#purchase_date').val(r.purchase_date);
                $('#expiry_date').val(r.expiry_date);
                $('#end_user_id').val(r.end_user_id).trigger('change');
                $('#department_id').val(r.department_id);
                $('#department_name').val(r.department_name);
                showModal();
            });
        });
    });

    $('#subscriptionForm').on('submit', function (event) {
        event.preventDefault();
        const submitBtn = $('#subscriptionSaveBtn');
        const fd = new FormData(this);
        fd.append('action', $('#subscription_id').val() ? 'update' : 'create');
        $.ajax({
            url: actionUrl,
            method: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            dataType: 'json',
            beforeSend: () => submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Saving...'),
            success: function (res) {
                Swal.fire(res.success ? 'Saved' : 'Error', res.message, res.success ? 'success' : 'error');
                if (res.success) {
                    bootstrap.Modal.getInstance(document.getElementById('subscriptionModal')).hide();
                    table.ajax.reload(null, false);
                }
            },
            error: xhr => Swal.fire('Error', (xhr.responseJSON && xhr.responseJSON.message) || 'Unable to save subscription.', 'error'),
            complete: () => submitBtn.prop('disabled', false).html('<i class="bi bi-save"></i> Save')
        });
    });

    $(document).on('click', '.archive-subscription-btn', function () {
        const id = $(this).data('id');
        Swal.fire({ title: 'Archive subscription?', text: 'This record will be moved to archive.', icon: 'warning', showCancelButton: true, confirmButtonColor: '#f59e0b' }).then(result => {
            if (!result.isConfirmed) return;
            $.post(actionUrl, { action: 'archive', id }, function (res) {
                Swal.fire(res.success ? 'Archived' : 'Error', res.message, res.success ? 'success' : 'error');
                table.ajax.reload(null, false);
            }, 'json');
        });
    });
});
</script>

<?php require_once ROOT_PATH . '/modules/layout/footer.php'; ?>
