<?php
    $page_title = 'Equipment Archive';
    require_once ROOT_PATH . '/modules/layout/header.php';
    require_once ROOT_PATH . '/config/database.php';
    require_once ROOT_PATH . '/helpers/functions.php';

    require_login();
    require_permission('ictd.equipment.archive');

    $canPermanentDelete = can('ictd.equipment.delete');
?>

<style>
  .equipment-shell { display: grid; gap: 1.05rem; }
  .equipment-page-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; }
  .equipment-eyebrow { color: #667085; font-size: .78rem; font-weight: 750; letter-spacing: .01em; }
  .equipment-title { color: #1f2937; font-size: 1.35rem; font-weight: 850; margin: 0; }
  .equipment-head-actions { display: flex; align-items: center; gap: .65rem; flex-wrap: wrap; }
  .equipment-head-actions .btn { border-radius: 4px; min-height: 38px; padding-inline: 1rem; }
  .equipment-toolbar { display: flex; align-items: center; justify-content: flex-end; gap: .65rem; flex-wrap: wrap; }
  .equipment-search { position: relative; min-width: min(340px, 100%); }
  .equipment-search i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #98a2b3; font-size: .85rem; }
  .equipment-search input { height: 38px; padding-left: 34px; border-radius: 4px; border-color: #eaecf0; font-size: .78rem; }
  .equipment-soft-btn { height: 38px; border: 1px solid #eaecf0; background: #fff; color: #344054; border-radius: 4px; padding: 0 .8rem; font-size: .78rem; font-weight: 650; }
  .equipment-panel { border: 0; border-radius: 10px; background: rgba(255,255,255,.78); box-shadow: none; }
  .table-container { overflow: hidden; border-radius: 8px; background: rgba(255,255,255,.92); }
  .equipment-table { border-collapse: separate !important; border-spacing: 0 !important; font-size: .8rem; color: #1f2937; }
  .equipment-table thead th {
    white-space: nowrap;
    font-size: .72rem;
    color: #5f6672;
    font-weight: 760;
    background: #f3f4f6 !important;
    border: 0 !important;
    padding: .72rem .8rem !important;
  }
  .equipment-table tbody td {
    border: 0 !important;
    border-bottom: 1px solid #f2f4f7 !important;
    padding: .72rem .8rem !important;
    vertical-align: middle;
    white-space: nowrap;
  }
  .equipment-table tbody tr:nth-child(even) td { background: rgba(249,250,251,.72); }
  .equipment-table tbody tr:hover td { background: #f8fbff; }
  .equipment-control { font-weight: 850; color: #1f2937; }
  .equipment-actions { display: inline-flex; align-items: center; gap: .55rem; }
  .equipment-action-btn { border: 0; background: transparent; padding: 0; line-height: 1; font-size: .95rem; }
  .equipment-action-restore { color: #16803d; }
  .equipment-action-reuse { color: #3657f6; }
  .equipment-action-delete { color: #e26060; }
  .equipment-badge {
    display: inline-flex;
    align-items: center;
    min-height: 24px;
    padding: .18rem .55rem;
    border-radius: 999px;
    font-size: .72rem;
    font-weight: 800;
  }
  .equipment-table-card .dataTables_filter,
  .equipment-table-card .dataTables_length { display: none; }
  .equipment-table-card .row:last-child {
    margin: 0;
    padding: .9rem .25rem 0;
    align-items: center;
    color: #475467;
    font-size: .78rem;
  }
  .equipment-table-card .dataTables_info { padding-top: .45rem !important; }
  .equipment-table-card .dataTables_paginate .pagination { gap: .35rem; margin: 0; justify-content: flex-end; }
  .equipment-table-card .page-link {
    min-width: 34px;
    height: 34px;
    border-radius: 4px !important;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #475467;
    border-color: #eaecf0;
    font-size: .78rem;
  }
  .equipment-table-card .page-item.active .page-link { background: #3657f6; border-color: #3657f6; color: #fff; }
  @media (max-width: 767px) {
    .equipment-page-head { flex-direction: column; }
    .equipment-toolbar, .equipment-search, .equipment-soft-btn { width: 100%; }
  }
</style>

<div class="container-fluid py-3">
    <div class="equipment-shell">
        <div class="equipment-page-head">
            <div>
                <div class="equipment-eyebrow">Archive</div>
                <h2 class="equipment-title">Archived Equipment</h2>
            </div>
            <div class="equipment-head-actions">
                <a href="<?= BASE_URL ?>/ictd/equipment" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left"></i> Back to Equipment
                </a>
            </div>
        </div>

        <div class="equipment-toolbar">
            <div class="equipment-search">
                <i class="bi bi-search"></i>
                <input type="search" class="form-control" id="equipmentArchiveSearch" placeholder="Search archived equipment by control or serial no">
            </div>
            <button type="button" class="equipment-soft-btn" id="equipmentArchiveRefreshBtn" title="Refresh">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
        </div>

        <div class="card equipment-panel equipment-table-card">
            <div class="card-body p-0">
                <div class="table-container">
                    <div class="table-responsive">
                        <table id="equipmentArchiveTable" class="table align-middle nowrap mb-0 equipment-table" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Action</th>
                                    <th>Control No</th>
                                    <th>Description</th>
                                    <th>Brand</th>
                                    <th>Serial No</th>
                                    <th>Delivery Status</th>
                                    <th>Status</th>
                                    <th>Price</th>
                                    <th>Property No</th>
                                    <th>Assigned User</th>
                                    <th>Assigned Department</th>
                                    <th>Reuse Status</th>
                                    <th>Created At</th>
                                    <th>Archived At</th>
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
    const actionUrl = '<?= BASE_URL ?>/ictd/equipment-action';
    const equipmentUrl = '<?= BASE_URL ?>/ictd/equipment';
    const canPermanentDelete = <?= $canPermanentDelete ? 'true' : 'false' ?>;

    const table = $('#equipmentArchiveTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: '<?= BASE_URL ?>/ictd/equipment-archive-data',
        order: [[13, 'desc']],
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        responsive: false,
        scrollX: true,
        columnDefs: [
            { targets: [0], orderable: false, searchable: false }
        ],
        language: {
            processing: 'Loading archive...',
            emptyTable: 'No archived equipment yet.'
        },
        dom: "<'row'<'col-sm-12'tr>>" +
             "<'row align-items-center mt-3'<'col-md-4'i><'col-md-4 equipment-length text-center'l><'col-md-4'p>>"
    });

    let searchTimer = null;
    $('#equipmentArchiveSearch').on('input', function () {
        clearTimeout(searchTimer);
        const value = this.value;
        searchTimer = setTimeout(function () {
            table.search(value).draw();
        }, 250);
    });

    $('#equipmentArchiveRefreshBtn').on('click', function () {
        $('#equipmentArchiveSearch').val('');
        table.search('').ajax.reload();
    });

    $(document).on('click', '.reuse-control-btn', function () {
        const id = $(this).data('id');
        const controlNo = $(this).data('control') || 'this control number';
        Swal.fire({
            title: 'Reuse control no?',
            text: 'This will allow ' + controlNo + ' to be used again for a new equipment record. The archived data will remain in archive.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3657f6',
            confirmButtonText: 'Reuse control no'
        }).then(function (result) {
            if (!result.isConfirmed) return;
            $.ajax({
                url: actionUrl,
                method: 'POST',
                contentType: 'application/json',
                dataType: 'json',
                data: JSON.stringify({ action: 'reuse_control_no', id }),
                success: function (res) {
                    if (!res.success) {
                        Swal.fire('Error', res.message || 'Unable to reuse control number.', 'error');
                        return;
                    }
                    Swal.fire('Reusable', res.message, 'success').then(function () {
                        window.location.href = equipmentUrl + '?reuse_control_no=' + encodeURIComponent(res.control_no || controlNo);
                    });
                },
                error: function (xhr) {
                    Swal.fire('Error', xhr.responseJSON?.message || 'Unable to reuse control number.', 'error');
                }
            });
        });
    });

    $(document).on('click', '.restore-equipment-btn', function () {
        const id = $(this).data('id');
        Swal.fire({
            title: 'Restore equipment?',
            text: 'This will return the record to active equipment.',
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
                        Swal.fire('Error', res.message || 'Unable to restore equipment.', 'error');
                        return;
                    }
                    Swal.fire('Restored', res.message, 'success');
                    table.ajax.reload(null, false);
                },
                error: function (xhr) {
                    Swal.fire('Error', xhr.responseJSON?.message || 'Unable to restore equipment.', 'error');
                }
            });
        });
    });

    $(document).on('click', '.permanent-delete-equipment-btn', function () {
        if (!canPermanentDelete) {
            Swal.fire('Unavailable', 'You do not have permission to permanently delete equipment.', 'info');
            return;
        }

        const id = $(this).data('id');
        Swal.fire({
            title: 'Permanently delete?',
            text: 'This cannot be undone. The archived equipment record will be removed forever.',
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
                        Swal.fire('Error', res.message || 'Unable to permanently delete equipment.', 'error');
                        return;
                    }
                    Swal.fire('Deleted', res.message, 'success');
                    table.ajax.reload(null, false);
                },
                error: function (xhr) {
                    Swal.fire('Error', xhr.responseJSON?.message || 'Unable to permanently delete equipment.', 'error');
                }
            });
        });
    });
});
</script>

<?php require_once ROOT_PATH . '/modules/layout/footer.php'; ?>
