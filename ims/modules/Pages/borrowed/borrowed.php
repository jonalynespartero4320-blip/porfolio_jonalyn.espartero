<?php
$page_title = 'Borrowed Items';
require_once ROOT_PATH . '/modules/layout/header.php';
require_once __DIR__ . '/borrowed-common.php';

require_login();
require_permission('ictd.borrowed.view');
borrowed_ensure_schema($pdo);

$canCreateBorrowed = can('ictd.borrowed.create') || can('ictd.borrowed.view');
?>

<style>
  .borrowed-shell { display: grid; gap: 1.05rem; }
  .borrowed-page-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; }
  .borrowed-eyebrow { color: #667085; font-size: .78rem; font-weight: 750; }
  .borrowed-title { color: #1f2937; font-size: 1.35rem; font-weight: 850; margin: 0; }
  .borrowed-head-actions { display: flex; align-items: center; gap: .65rem; flex-wrap: wrap; }
  .borrowed-head-actions .btn { border-radius: 4px; min-height: 38px; padding-inline: 1rem; }
  .borrowed-head-actions .btn-primary { background: #3657f6; border-color: #3657f6; }
  .borrowed-toolbar { display: flex; align-items: center; justify-content: flex-end; gap: .65rem; flex-wrap: wrap; }
  .borrowed-search { position: relative; min-width: min(340px, 100%); }
  .borrowed-search i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #98a2b3; font-size: .85rem; }
  .borrowed-search input { height: 38px; padding-left: 34px; border-radius: 4px; border-color: #eaecf0; font-size: .78rem; }
  .borrowed-filter { height: 38px; min-width: 190px; border-radius: 4px; border-color: #eaecf0; font-size: .78rem; color: #667085; }
  .borrowed-soft-btn { height: 38px; border: 1px solid #eaecf0; background: #fff; color: #344054; border-radius: 4px; padding: 0 .8rem; font-size: .78rem; font-weight: 650; }
  .borrowed-panel { border: 0; border-radius: 10px; background: rgba(255,255,255,.78); box-shadow: none; }
  .borrowed-table-container { overflow: hidden; border-radius: 8px; background: rgba(255,255,255,.92); }
  .borrowed-table { border-collapse: separate !important; border-spacing: 0 !important; font-size: .8rem; color: #1f2937; }
  .borrowed-table thead th { white-space: nowrap; font-size: .72rem; color: #5f6672; font-weight: 760; background: #f3f4f6 !important; border: 0 !important; padding: .72rem .8rem !important; }
  .borrowed-table tbody td { border: 0 !important; border-bottom: 1px solid #f2f4f7 !important; padding: .72rem .8rem !important; vertical-align: middle; white-space: nowrap; }
  .borrowed-table tbody tr:nth-child(even) td { background: rgba(249,250,251,.72); }
  .borrowed-table tbody tr:hover td { background: #f8fbff; }
  .borrowed-actions { display: inline-flex; align-items: center; gap: .6rem; white-space: nowrap; }
  .borrowed-action-btn { border: 0; background: transparent; padding: 0; line-height: 1; font-size: .9rem; font-weight: 750; }
  .borrowed-action-edit { color: #1f9bb4; }
  .borrowed-action-delete { color: #e26060; }
  .borrowed-table-card .row:first-child { display: none; }
  .borrowed-table-card .row:last-child { margin: 0; padding: .9rem .25rem 0; align-items: center; color: #475467; font-size: .78rem; }
  .borrowed-table-card .dataTables_paginate .pagination { gap: .35rem; margin: 0; justify-content: flex-end; }
  .borrowed-table-card .page-link { min-width: 34px; height: 34px; border-radius: 4px !important; display: inline-flex; align-items: center; justify-content: center; border-color: #eaecf0; color: #344054; font-size: .78rem; box-shadow: none; }
  .borrowed-table-card .page-item.active .page-link { background: #3657f6; border-color: #3657f6; color: #fff; }
  @media (max-width: 767px) { .borrowed-page-head { flex-direction: column; } .borrowed-toolbar { justify-content: stretch; } .borrowed-search, .borrowed-filter, .borrowed-soft-btn { width: 100%; } }
</style>

<div class="container-fluid py-3">
    <div class="borrowed-shell">
        <div class="borrowed-page-head">
            <div>
                <div class="borrowed-eyebrow">Manage</div>
                <h2 class="borrowed-title">Borrowed Items</h2>
            </div>
            <div class="borrowed-head-actions">
                <a href="<?= BASE_URL ?>/ictd/borrowed/archive" class="btn btn-outline-secondary btn-sm"><i class="bi bi-archive"></i> Archive</a>
                <?php if ($canCreateBorrowed): ?>
                    <button type="button" class="btn btn-primary btn-sm" id="openBorrowedModal"><i class="bi bi-plus-lg"></i> New Borrowed Item</button>
                <?php endif; ?>
            </div>
        </div>

        <div class="borrowed-toolbar">
            <div class="borrowed-search"><i class="bi bi-search"></i><input type="search" class="form-control" id="borrowedSearch" placeholder="Search borrowed items"></div>
            <select class="form-select borrowed-filter" id="borrowedStatusFilter">
                <option value="">All Status</option>
                <option value="Returned">Returned</option>
                <option value="Not Yet Returned">Not Yet Returned</option>
            </select>
            <button type="button" class="borrowed-soft-btn" id="borrowedRefreshBtn"><i class="bi bi-arrow-clockwise"></i></button>
        </div>

        <div class="card borrowed-panel borrowed-table-card">
            <div class="card-body p-0">
                <div class="borrowed-table-container">
                    <div class="table-responsive">
                        <table id="borrowedTable" class="table align-middle nowrap mb-0 borrowed-table" style="width:100%">
                            <thead><tr><th>Action</th><th>Status</th><th>End User</th><th>Department</th><th>Item Borrowed</th><th>Serial No</th><th>Date Borrowed</th><th>Date Returned</th></tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="borrowedModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form id="borrowedForm">
                <input type="hidden" name="id" id="borrowed_id">
                <div class="modal-header bg-light text-dark">
                    <h5 class="modal-title" id="borrowedModalTitle">New Borrowed Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-md-6"><label class="form-label">End User</label><select name="end_user_id" id="borrowed_end_user_id" class="form-select borrowed-select2" required></select></div>
                    <div class="col-md-6"><label class="form-label">Department</label><select name="department_id" id="borrowed_department_id" class="form-select borrowed-select2" required></select></div>
                    <div class="col-md-6"><label class="form-label">Item Borrowed</label><input type="text" name="item_borrowed" id="item_borrowed" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label">Serial No</label><input type="text" name="serialno" id="serialno" class="form-control"></div>
                    <div class="col-md-6"><label class="form-label">Date Borrowed</label><input type="date" name="date_borrowed" id="date_borrowed" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label">Date Returned</label><input type="date" name="date_returned" id="date_returned" class="form-control"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" id="borrowedSaveBtn"><i class="bi bi-save"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(function () {
    const actionUrl = '<?= BASE_URL ?>/ictd/borrowed-action';
    const table = $('#borrowedTable').DataTable({
        ajax: { url: '<?= BASE_URL ?>/ictd/borrowed-data', type: 'POST' },
        order: [[6, 'desc']],
        scrollX: true,
        dom: 'rt<"row align-items-center mt-3 px-3 pb-3"<"col-md-4"i><"col-md-4 text-center"l><"col-md-4"p>>',
        columnDefs: [{ targets: [0], orderable: false, searchable: false }],
        language: { emptyTable: 'No borrowed items recorded yet.' }
    });
    const esc = v => String(v ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
    const modalEl = document.getElementById('borrowedModal');
    const showModal = () => (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).show();
    const options = (items, label) => `<option value="">${label}</option>` + (items || []).map(i => `<option value="${esc(i.id)}" data-department-id="${esc(i.department_id || '')}">${esc(i.text)}</option>`).join('');

    function initSelect2() { if ($.fn.select2) $('.borrowed-select2').select2({ width: '100%', dropdownParent: $('#borrowedModal'), allowClear: true }); }
    function loadMasterlists(cb) {
        $.getJSON(actionUrl, { action: 'masterlists' }, res => {
            $('#borrowed_end_user_id').html(options(res.data.users, 'Select User'));
            $('#borrowed_department_id').html(options(res.data.departments, 'Select Department'));
            initSelect2();
            if (typeof cb === 'function') cb();
        });
    }
    function resetForm() {
        $('#borrowedForm')[0].reset();
        $('#borrowed_id').val('');
        $('#borrowedModalTitle').text('New Borrowed Item');
        $('.borrowed-select2').val(null).trigger('change');
        $('#date_borrowed').val(new Date().toISOString().slice(0, 10));
    }

    $('#borrowedSearch').on('input', function () { table.search(this.value).draw(); });
    $('#borrowedStatusFilter').on('change', function () { table.column(1).search(this.value).draw(); });
    $('#borrowedRefreshBtn').on('click', function () { $('#borrowedSearch,#borrowedStatusFilter').val(''); table.search('').column(1).search('').ajax.reload(); });
    $('#openBorrowedModal').on('click', () => { resetForm(); loadMasterlists(showModal); });
    $('#borrowed_end_user_id').on('change', function () { $('#borrowed_department_id').val($(this).find(':selected').data('department-id') || '').trigger('change'); });

    $(document).on('click', '.edit-borrowed-btn', function () {
        const id = $(this).data('id');
        loadMasterlists(() => $.getJSON(actionUrl, { action: 'get', id }, res => {
            if (!res.success) return Swal.fire('Error', res.message, 'error');
            const r = res.data;
            $('#borrowedModalTitle').text('Edit Borrowed Item');
            $('#borrowed_id').val(r.id);
            $('#borrowed_end_user_id').val(r.end_user_id).trigger('change');
            $('#borrowed_department_id').val(r.department_id).trigger('change');
            $('#item_borrowed').val(r.item_borrowed);
            $('#serialno').val(r.serialno);
            $('#date_borrowed').val(r.date_borrowed);
            $('#date_returned').val(r.date_returned);
            showModal();
        }));
    });

    $('#borrowedForm').on('submit', function (e) {
        e.preventDefault();
        const btn = $('#borrowedSaveBtn');
        const fd = new FormData(this);
        fd.append('action', $('#borrowed_id').val() ? 'update' : 'create');
        $.ajax({ url: actionUrl, method: 'POST', data: fd, processData: false, contentType: false, dataType: 'json',
            beforeSend: () => btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Saving...'),
            success: res => { Swal.fire(res.success ? 'Saved' : 'Error', res.message, res.success ? 'success' : 'error'); if (res.success) { bootstrap.Modal.getInstance(modalEl).hide(); table.ajax.reload(null, false); } },
            error: xhr => Swal.fire('Error', (xhr.responseJSON && xhr.responseJSON.message) || 'Unable to save borrowed item.', 'error'),
            complete: () => btn.prop('disabled', false).html('<i class="bi bi-save"></i> Save')
        });
    });

    $(document).on('click', '.archive-borrowed-btn', function () {
        const id = $(this).data('id');
        Swal.fire({ title: 'Archive borrowed item?', text: 'This record will be moved to archive.', icon: 'warning', showCancelButton: true, confirmButtonColor: '#f59e0b' }).then(result => {
            if (!result.isConfirmed) return;
            $.post(actionUrl, { action: 'archive', id }, res => { Swal.fire(res.success ? 'Archived' : 'Error', res.message, res.success ? 'success' : 'error'); table.ajax.reload(null, false); }, 'json');
        });
    });
});
</script>

<?php require_once ROOT_PATH . '/modules/layout/footer.php'; ?>
