<?php
$page_title = 'Supplies Archive';
require_once ROOT_PATH . '/modules/layout/header.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/functions.php';

require_login();
require_permission('ictd.supplies.view');

$canPermanentDelete = can('ictd.supplies.delete');
?>

<style>
  .supplies-shell { display: grid; gap: 1.05rem; }
  .supplies-page-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; }
  .supplies-eyebrow { color: #667085; font-size: .78rem; font-weight: 750; letter-spacing: .01em; }
  .supplies-title { color: #1f2937; font-size: 1.35rem; font-weight: 850; margin: 0; }
  .supplies-head-actions { display: flex; align-items: center; gap: .65rem; flex-wrap: wrap; }
  .supplies-head-actions .btn { border-radius: 4px; min-height: 38px; padding-inline: 1rem; }
  .supplies-toolbar { display: flex; align-items: center; justify-content: flex-end; gap: .65rem; flex-wrap: wrap; }
  .supplies-search { position: relative; min-width: min(340px, 100%); }
  .supplies-search i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #98a2b3; font-size: .85rem; }
  .supplies-search input { height: 38px; padding-left: 34px; border-radius: 4px; border-color: #eaecf0; font-size: .78rem; }
  .supplies-soft-btn { height: 38px; border: 1px solid #eaecf0; background: #fff; color: #344054; border-radius: 4px; padding: 0 .8rem; font-size: .78rem; font-weight: 650; }
  .supplies-panel { border: 0; border-radius: 10px; background: rgba(255,255,255,.78); box-shadow: none; }
  .supplies-table-container { overflow: hidden; border-radius: 8px; background: rgba(255,255,255,.92); }
  .supplies-table { border-collapse: separate !important; border-spacing: 0 !important; font-size: .8rem; color: #1f2937; }
  .supplies-table thead th { white-space: nowrap; font-size: .72rem; color: #5f6672; font-weight: 760; background: #f3f4f6 !important; border: 0 !important; padding: .72rem .8rem !important; }
  .supplies-table tbody td { border: 0 !important; border-bottom: 1px solid #f2f4f7 !important; padding: .72rem .8rem !important; vertical-align: middle; white-space: nowrap; }
  .supplies-table tbody tr:nth-child(even) td { background: rgba(249,250,251,.72); }
  .supplies-table tbody tr:hover td { background: #f8fbff; }
  .supplies-code { font-weight: 850; color: #1f2937; }
  .supplies-badge { border-radius: 999px; padding: .28rem .55rem; font-weight: 750; font-size: .72rem; }
  .supplies-actions { display: inline-flex; align-items: center; gap: .55rem; }
  .supplies-action-btn { border: 0; background: transparent; padding: 0; line-height: 1; font-size: .95rem; }
  .supplies-action-restore { color: #3657f6; }
  .supplies-action-delete { color: #e26060; }
  .supplies-table-card .dataTables_filter,
  .supplies-table-card .dataTables_length { display: none; }
  .supplies-table-card .row:last-child { margin: 0; padding: .9rem .25rem 0; align-items: center; color: #475467; font-size: .78rem; }
  .supplies-table-card .dataTables_paginate .pagination { gap: .35rem; margin: 0; justify-content: flex-end; }
  .supplies-table-card .page-link { min-width: 34px; height: 34px; border-radius: 4px !important; display: inline-flex; align-items: center; justify-content: center; border-color: #eaecf0; color: #344054; font-size: .78rem; box-shadow: none; }
  .supplies-table-card .page-item.active .page-link { background: #3657f6; border-color: #3657f6; color: #fff; }
  @media (max-width: 767px) {
    .supplies-page-head { flex-direction: column; }
    .supplies-toolbar, .supplies-search, .supplies-soft-btn { width: 100%; }
  }
</style>

<div class="container-fluid py-3">
    <div class="supplies-shell">
        <div class="supplies-page-head">
            <div>
                <div class="supplies-eyebrow">Archive</div>
                <h2 class="supplies-title">Archived Supplies</h2>
            </div>
            <div class="supplies-head-actions">
                <a href="<?= BASE_URL ?>/ictd/supplies" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left"></i> Back to Supplies
                </a>
            </div>
        </div>

        <div class="supplies-toolbar">
            <div class="supplies-search">
                <i class="bi bi-search"></i>
                <input type="search" class="form-control" id="suppliesArchiveSearch" placeholder="Search archived supplies by code, control, or serial no">
            </div>
            <button type="button" class="supplies-soft-btn" id="suppliesArchiveRefreshBtn">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
        </div>

        <div class="card supplies-panel supplies-table-card">
            <div class="card-body p-0">
                <div class="supplies-table-container">
                    <div class="table-responsive">
                        <table id="suppliesArchiveTable" class="table align-middle nowrap mb-0 supplies-table" style="width:100%">
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
                                    <th>ETA</th>
                                    <th>Delivered</th>
                                    <th>Warranty</th>
                                    <th>Assigned User</th>
                                    <th>Assigned Department</th>
                                    <th>Created</th>
                                    <th>Archived</th>
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

<script>
document.addEventListener('DOMContentLoaded', function () {
    const actionUrl = '<?= BASE_URL ?>/ictd/supplies-action';
    const canPermanentDelete = <?= $canPermanentDelete ? 'true' : 'false' ?>;

    const table = $('#suppliesArchiveTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: '<?= BASE_URL ?>/ictd/supplies-archive-data',
        order: [[14, 'desc']],
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        responsive: false,
        scrollX: true,
        columnDefs: [{ targets: [0], orderable: false, searchable: false }],
        language: {
            processing: 'Loading archive...',
            emptyTable: 'No archived supplies yet.'
        },
        dom: "<'row'<'col-sm-12'tr>>" +
             "<'row align-items-center mt-3'<'col-md-4'i><'col-md-4 text-center'l><'col-md-4'p>>"
    });

    let searchTimer = null;
    $('#suppliesArchiveSearch').on('input', function () {
        clearTimeout(searchTimer);
        const value = this.value;
        searchTimer = setTimeout(function () {
            table.search(value).draw();
        }, 250);
    });

    $('#suppliesArchiveRefreshBtn').on('click', function () {
        $('#suppliesArchiveSearch').val('');
        table.search('').ajax.reload();
    });

    $(document).on('click', '.restore-supply-btn', function () {
        const id = $(this).data('id');
        Swal.fire({
            title: 'Restore supply?',
            text: 'This will return the record to active supplies.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#16803d',
            confirmButtonText: 'Restore'
        }).then(function (result) {
            if (!result.isConfirmed) return;
            $.ajax({
                url: actionUrl,
                method: 'POST',
                contentType: 'application/json',
                dataType: 'json',
                data: JSON.stringify({ action: 'restore', id }),
                success: function (res) {
                    if (!res.success) {
                        Swal.fire('Error', res.message || 'Unable to restore supply.', 'error');
                        return;
                    }
                    Swal.fire('Restored', res.message, 'success');
                    table.ajax.reload(null, false);
                },
                error: function (xhr) {
                    Swal.fire('Error', xhr.responseJSON?.message || 'Unable to restore supply.', 'error');
                }
            });
        });
    });

    $(document).on('click', '.permanent-delete-supply-btn', function () {
        if (!canPermanentDelete) {
            Swal.fire('Unavailable', 'You do not have permission to permanently delete supplies.', 'info');
            return;
        }

        const id = $(this).data('id');
        Swal.fire({
            title: 'Permanently delete?',
            text: 'This cannot be undone. The archived supply record will be removed forever.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d92d20',
            confirmButtonText: 'Delete permanently'
        }).then(function (result) {
            if (!result.isConfirmed) return;
            $.ajax({
                url: actionUrl,
                method: 'POST',
                contentType: 'application/json',
                dataType: 'json',
                data: JSON.stringify({ action: 'permanent_delete', id }),
                success: function (res) {
                    if (!res.success) {
                        Swal.fire('Error', res.message || 'Unable to permanently delete supply.', 'error');
                        return;
                    }
                    Swal.fire('Deleted', res.message, 'success');
                    table.ajax.reload(null, false);
                },
                error: function (xhr) {
                    Swal.fire('Error', xhr.responseJSON?.message || 'Unable to permanently delete supply.', 'error');
                }
            });
        });
    });
});
</script>

<?php require_once ROOT_PATH . '/modules/layout/footer.php'; ?>
