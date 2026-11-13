<?php
$page_title = 'Bidding Records';
require_once ROOT_PATH . '/modules/layout/header.php';
require_once __DIR__ . '/bidding-common.php';

require_login();
require_permission('ictd.bidding.view');
bidding_ensure_schema($pdo);

$canCreateBidding = can('ictd.bidding.create') || can('ictd.bidding.view');
?>

<style>
  .bidding-shell { display: grid; gap: 1.05rem; }
  .bidding-page-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; }
  .bidding-eyebrow { color: #667085; font-size: .78rem; font-weight: 750; }
  .bidding-title { color: #1f2937; font-size: 1.35rem; font-weight: 850; margin: 0; }
  .bidding-head-actions { display: flex; align-items: center; gap: .65rem; flex-wrap: wrap; }
  .bidding-head-actions .btn { border-radius: 4px; min-height: 38px; padding-inline: 1rem; }
  .bidding-head-actions .btn-primary { background: #3657f6; border-color: #3657f6; }
  .bidding-toolbar { display: flex; align-items: center; justify-content: flex-end; gap: .65rem; flex-wrap: wrap; }
  .bidding-export-filters { width: 100%; display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .65rem; align-items: center; }
  .bidding-search { position: relative; min-width: min(340px, 100%); }
  .bidding-search i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #98a2b3; font-size: .85rem; }
  .bidding-search input { height: 38px; padding-left: 34px; border-radius: 4px; border-color: #eaecf0; font-size: .78rem; }
  .bidding-filter { height: 38px; min-width: 150px; border-radius: 4px; border-color: #eaecf0; font-size: .78rem; color: #667085; }
  .bidding-soft-btn { height: 38px; border: 1px solid #eaecf0; background: #fff; color: #344054; border-radius: 4px; padding: 0 .8rem; font-size: .78rem; font-weight: 650; }
  .bidding-panel { border: 0; border-radius: 10px; background: rgba(255,255,255,.78); box-shadow: none; }
  .bidding-table-container { overflow: hidden; border-radius: 8px; background: rgba(255,255,255,.92); }
  .bidding-table { border-collapse: separate !important; border-spacing: 0 !important; font-size: .8rem; color: #1f2937; }
  .bidding-table thead th { white-space: nowrap; font-size: .72rem; color: #5f6672; font-weight: 760; background: #f3f4f6 !important; border: 0 !important; padding: .72rem .8rem !important; }
  .bidding-table tbody td { border: 0 !important; border-bottom: 1px solid #f2f4f7 !important; padding: .72rem .8rem !important; vertical-align: middle; white-space: nowrap; }
  .bidding-table tbody tr:nth-child(even) td { background: rgba(249,250,251,.72); }
  .bidding-table tbody tr:hover td { background: #f8fbff; }
  .bidding-actions { display: inline-flex; align-items: center; gap: .6rem; white-space: nowrap; }
  .bidding-action-btn, .bidding-item-toggle { border: 0; background: transparent; padding: 0; line-height: 1; font-size: .9rem; font-weight: 750; }
  .bidding-action-toggle { color: #3657f6; width: 22px; }
  .bidding-action-edit { color: #1f9bb4; }
  .bidding-action-delete { color: #e26060; }
  .bidding-child { padding: .9rem; background: #f8fbff; border: 1px solid #eef2ff; border-radius: 8px; }
  .bidding-child table { margin: 0; font-size: .78rem; }
  .bidding-table-card .row:first-child { display: none; }
  .bidding-table-card .row:last-child { margin: 0; padding: .9rem .25rem 0; align-items: center; color: #475467; font-size: .78rem; }
  .bidding-table-card .dataTables_paginate .pagination { gap: .35rem; margin: 0; justify-content: flex-end; }
  .bidding-table-card .page-link { min-width: 34px; height: 34px; border-radius: 4px !important; display: inline-flex; align-items: center; justify-content: center; border-color: #eaecf0; color: #344054; font-size: .78rem; box-shadow: none; }
  .bidding-table-card .page-item.active .page-link { background: #3657f6; border-color: #3657f6; color: #fff; }
  .bidding-item-row { border: 1px solid #edf0f5; border-radius: 8px; padding: .7rem; background: #fff; margin-bottom: .65rem; }
  @media (max-width: 767px) { .bidding-page-head { flex-direction: column; } .bidding-toolbar, .bidding-head-actions { justify-content: stretch; } .bidding-search, .bidding-filter, .bidding-soft-btn { width: 100%; } }
</style>

<div class="container-fluid py-3">
    <div class="bidding-shell">
        <div class="bidding-page-head">
            <div>
                <div class="bidding-eyebrow">Manage</div>
                <h2 class="bidding-title">Bidding Records</h2>
            </div>
            <div class="bidding-head-actions">
                <a href="<?= BASE_URL ?>/ictd/bidding/archive" class="btn btn-outline-secondary btn-sm"><i class="bi bi-archive"></i> Archive</a>
                <?php if ($canCreateBidding): ?>
                    <button type="button" class="btn btn-primary btn-sm" id="openBiddingModal"><i class="bi bi-plus-lg"></i> New Bidding</button>
                <?php endif; ?>
            </div>
        </div>

        <form class="bidding-toolbar" method="GET" action="<?= BASE_URL ?>/ictd/bidding-export" target="_blank">
            <div class="bidding-search"><i class="bi bi-search"></i><input type="search" class="form-control" id="biddingSearch" placeholder="Search bidding records"></div>
            <div class="bidding-export-filters">
                <input type="date" name="from" id="biddingDateFrom" class="form-control bidding-filter">
                <input type="date" name="to" id="biddingDateTo" class="form-control bidding-filter">
                <select name="department_id" id="biddingExportDepartment" class="form-select bidding-filter"><option value="">All Departments</option></select>
            </div>
            <label class="bidding-soft-btn d-inline-flex align-items-center gap-2"><input type="checkbox" name="export_all" value="1" id="biddingExportAll"> Export All</label>
            <button type="submit" class="bidding-soft-btn"><i class="bi bi-file-earmark-spreadsheet"></i> Export</button>
            <button type="button" class="bidding-soft-btn" id="biddingRefreshBtn"><i class="bi bi-arrow-clockwise"></i></button>
        </form>

        <div class="card bidding-panel bidding-table-card">
            <div class="card-body p-0">
                <div class="bidding-table-container">
                    <div class="table-responsive">
                        <table id="biddingTable" class="table align-middle nowrap mb-0 bidding-table" style="width:100%">
                            <thead><tr><th>Action</th><th>Date Requested</th><th>Source of Funds</th><th>Department</th><th>Head</th><th>Items</th></tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="biddingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form id="biddingForm">
                <input type="hidden" name="id" id="bidding_id">
                <div class="modal-header bg-light text-dark">
                    <h5 class="modal-title" id="biddingModalTitle">New Bidding</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Date Requested</label><input type="date" name="date_requested" id="date_requested" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label">Source of Funds</label><select name="fund_id" id="fund_id" class="form-select bidding-select2" required></select></div>
                        <div class="col-md-6"><label class="form-label">Department</label><select name="department_id" id="department_id" class="form-select bidding-select2" required></select></div>
                        <div class="col-md-6"><label class="form-label">Department Head</label><input type="text" name="department_head" id="department_head" class="form-control" readonly required></div>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label mb-0">Items</label>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="addBiddingItem"><i class="bi bi-plus-circle"></i> Add Item</button>
                    </div>
                    <div id="biddingItems"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="biddingSaveBtn"><i class="bi bi-save"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(function () {
    const actionUrl = '<?= BASE_URL ?>/ictd/bidding-action';
    let masterlists = { departments: [], funds: [] };
    let biddingItems = [];
    const esc = v => String(v ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));

    const table = $('#biddingTable').DataTable({
        ajax: { url: '<?= BASE_URL ?>/ictd/bidding-data', type: 'POST' },
        order: [[1, 'desc']],
        scrollX: true,
        dom: 'rt<"row align-items-center mt-3 px-3 pb-3"<"col-md-4"i><"col-md-4 text-center"l><"col-md-4"p>>',
        columnDefs: [{ targets: [0, 5], orderable: false, searchable: false }],
        language: { emptyTable: 'No bidding records yet.' }
    });

    function optionHtml(items, placeholder) {
        return `<option value="">${placeholder}</option>` + (items || []).map(i => `<option value="${esc(i.id)}" data-head="${esc(i.department_head || '')}">${esc(i.text)}</option>`).join('');
    }
    function initSelect2() { if ($.fn.select2) $('.bidding-select2').select2({ width: '100%', dropdownParent: $('#biddingModal'), allowClear: true }); }
    function loadMasterlists(cb) {
        $.getJSON(actionUrl, { action: 'masterlists' }, res => {
            masterlists = res.data || masterlists;
            $('#fund_id').html(optionHtml(masterlists.funds, 'Select Fund'));
            $('#department_id').html(optionHtml(masterlists.departments, 'Select Department'));
            $('#biddingExportDepartment').html(optionHtml(masterlists.departments, 'All Departments'));
            initSelect2();
            if (typeof cb === 'function') cb();
        });
    }
    function showModal() { const el = document.getElementById('biddingModal'); (bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el)).show(); }
    function renderItems() {
        $('#biddingItems').html(biddingItems.map((item, idx) => `
            <div class="bidding-item-row" data-index="${idx}">
                <div class="row g-2">
                    <div class="col-md-8"><input type="text" class="form-control bidding-item-description" value="${esc(item.description || '')}" placeholder="Description"></div>
                    <div class="col-md-3"><input type="number" min="1" class="form-control bidding-item-qty" value="${esc(item.qty || 1)}" placeholder="Qty"></div>
                    <div class="col-md-1 d-grid"><button type="button" class="btn btn-outline-danger remove-bidding-item"><i class="bi bi-x-lg"></i></button></div>
                </div>
            </div>
        `).join('') || '<div class="text-muted small">No items yet.</div>');
    }
    function syncItemsFromDom() {
        biddingItems = [];
        $('.bidding-item-row').each(function () {
            biddingItems.push({ description: $(this).find('.bidding-item-description').val(), qty: $(this).find('.bidding-item-qty').val() || 1 });
        });
    }
    function resetForm() {
        $('#biddingForm')[0].reset();
        $('#bidding_id').val('');
        $('#biddingModalTitle').text('New Bidding');
        $('#date_requested').val(new Date().toISOString().slice(0, 10));
        biddingItems = [{ description: '', qty: 1 }];
        renderItems();
        $('.bidding-select2').val(null).trigger('change');
    }
    function childHtml(items) {
        return `<div class="bidding-child"><table class="table table-sm"><thead><tr><th>Description</th><th>Qty</th></tr></thead><tbody>${items.map(i => `<tr><td>${esc(i.description)}</td><td>${esc(i.qty)}</td></tr>`).join('')}</tbody></table></div>`;
    }

    $('#biddingSearch').on('input', function () { table.search(this.value).draw(); });
    $('#biddingRefreshBtn').on('click', function () { $('#biddingSearch').val(''); table.search('').ajax.reload(); });
    $('#biddingExportAll').on('change', function () { $('#biddingDateFrom,#biddingDateTo,#biddingExportDepartment').prop('disabled', this.checked); });
    $('#openBiddingModal').on('click', () => { resetForm(); loadMasterlists(showModal); });
    $('#department_id').on('change', function () { $('#department_head').val($(this).find(':selected').data('head') || ''); });
    $('#addBiddingItem').on('click', function () { syncItemsFromDom(); biddingItems.push({ description: '', qty: 1 }); renderItems(); });
    $(document).on('click', '.remove-bidding-item', function () { syncItemsFromDom(); biddingItems.splice($(this).closest('.bidding-item-row').data('index'), 1); renderItems(); });

    $(document).on('click', '.toggle-bidding-items-btn', function () {
        const tr = $(this).closest('tr');
        const row = table.row(tr);
        const id = $(this).data('id');
        if (row.child.isShown()) { row.child.hide(); tr.removeClass('shown'); return; }
        $.getJSON(actionUrl, { action: 'get', id }, res => { row.child(childHtml(res.items || [])).show(); tr.addClass('shown'); });
    });
    $(document).on('click', '.edit-bidding-btn', function () {
        const id = $(this).data('id');
        loadMasterlists(() => $.getJSON(actionUrl, { action: 'get', id }, res => {
            if (!res.success) return Swal.fire('Error', res.message, 'error');
            const r = res.data;
            $('#biddingModalTitle').text('Edit Bidding');
            $('#bidding_id').val(r.id);
            $('#date_requested').val(r.date_requested);
            $('#fund_id').val(r.fund_id).trigger('change');
            $('#department_id').val(r.department_id).trigger('change');
            $('#department_head').val(r.department_head);
            biddingItems = res.items && res.items.length ? res.items : [{ description: '', qty: 1 }];
            renderItems();
            showModal();
        }));
    });
    $('#biddingForm').on('submit', function (e) {
        e.preventDefault();
        syncItemsFromDom();
        const btn = $('#biddingSaveBtn');
        const fd = new FormData(this);
        fd.append('action', $('#bidding_id').val() ? 'update' : 'create');
        fd.append('items', JSON.stringify(biddingItems));
        $.ajax({ url: actionUrl, method: 'POST', data: fd, processData: false, contentType: false, dataType: 'json',
            beforeSend: () => btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Saving...'),
            success: res => { Swal.fire(res.success ? 'Saved' : 'Error', res.message, res.success ? 'success' : 'error'); if (res.success) { bootstrap.Modal.getInstance(document.getElementById('biddingModal')).hide(); table.ajax.reload(null, false); } },
            error: xhr => Swal.fire('Error', (xhr.responseJSON && xhr.responseJSON.message) || 'Unable to save bidding record.', 'error'),
            complete: () => btn.prop('disabled', false).html('<i class="bi bi-save"></i> Save')
        });
    });
    $(document).on('click', '.archive-bidding-btn', function () {
        const id = $(this).data('id');
        Swal.fire({ title: 'Archive bidding record?', text: 'This record will be moved to archive.', icon: 'warning', showCancelButton: true, confirmButtonColor: '#f59e0b' }).then(result => {
            if (!result.isConfirmed) return;
            $.post(actionUrl, { action: 'archive', id }, res => { Swal.fire(res.success ? 'Archived' : 'Error', res.message, res.success ? 'success' : 'error'); table.ajax.reload(null, false); }, 'json');
        });
    });
    loadMasterlists();
});
</script>

<?php require_once ROOT_PATH . '/modules/layout/footer.php'; ?>
