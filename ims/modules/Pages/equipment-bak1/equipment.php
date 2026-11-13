<?php
    $page_title = 'Equipment Records';
    require_once ROOT_PATH . '/modules/layout/header.php';
    require_once ROOT_PATH . '/config/database.php';
    require_once ROOT_PATH . '/helpers/functions.php';

    require_login();
    require_permission('ictd.equipment.view');

    $requiredTables = ['equipment', 'list_description', 'list_brand'];
    $missingTables = array_values(array_filter($requiredTables, static fn(string $table): bool => !db_table_exists($pdo, $table)));

    $canCreateEquipment = can('ictd.equipment.create');
    $canArchiveEquipment = can('ictd.equipment.archive');
    $canImportEquipment = can('ictd.equipment.import');
    $canDeleteEquipment = can('ictd.equipment.delete');
?>

<style>
  .equipment-shell { display: grid; gap: 1.05rem; }
  .equipment-page-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; }
  .equipment-eyebrow { color: #667085; font-size: .78rem; font-weight: 750; letter-spacing: .01em; }
  .equipment-title { color: #1f2937; font-size: 1.35rem; font-weight: 850; margin: 0; }
  .equipment-head-actions { display: flex; align-items: center; gap: .65rem; flex-wrap: wrap; }
  .equipment-head-actions .btn { border-radius: 4px; min-height: 38px; padding-inline: 1rem; }
  .equipment-head-actions .btn-primary { background: #3657f6; border-color: #3657f6; }
  .equipment-toolbar { display: flex; align-items: center; justify-content: flex-end; gap: .65rem; flex-wrap: wrap; }
  .equipment-search { position: relative; min-width: min(320px, 100%); }
  .equipment-search i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #98a2b3; font-size: .85rem; }
  .equipment-search input { height: 38px; padding-left: 34px; border-radius: 4px; border-color: #eaecf0; font-size: .78rem; }
  .equipment-filter { height: 38px; min-width: 150px; border-radius: 4px; border-color: #eaecf0; font-size: .78rem; color: #667085; }
  .equipment-soft-btn { height: 38px; border: 1px solid #eaecf0; background: #fff; color: #344054; border-radius: 4px; padding: 0 .8rem; font-size: .78rem; font-weight: 650; }
  .equipment-stat-grid { display: none; }
  .equipment-panel { border: 0; border-radius: 10px; background: rgba(255,255,255,.78); box-shadow: none; }
  .table-container { overflow: hidden; border-radius: 8px; background: rgba(255,255,255,.92); }
  .equipment-table { border-collapse: separate !important; border-spacing: 0 0 !important; font-size: .8rem; color: #1f2937; }
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
  .equipment-table tbody tr.equipment-row-assigned td {
    background: linear-gradient(90deg, rgba(54,87,246,.13), rgba(54,87,246,.04)) !important;
    border-bottom-color: rgba(54,87,246,.18) !important;
  }
  .equipment-control { font-weight: 850; color: #1f2937; }
  .equipment-old-control { color: #667085; font-weight: 750; }
  .equipment-assigned-note {
    display: inline-flex;
    align-items: center;
    gap: .25rem;
    margin-left: .45rem;
    color: #3657f6;
    font-size: .68rem;
    font-weight: 850;
  }
  .equipment-muted { color: #667085; font-size: .74rem; }
  .equipment-actions { display: inline-flex; align-items: center; gap: .55rem; }
  .equipment-action-btn { border: 0; background: transparent; padding: 0; line-height: 1; font-size: .95rem; }
  .equipment-action-view { color: #16a085; }
  .equipment-action-edit { color: #1f9bb4; }
  .equipment-action-delete { color: #e26060; }
  .equipment-batch-row {
    position: relative;
    border: 1px solid #eaecf0;
    border-radius: 14px;
    padding: 3.65rem 1rem 1rem;
    background: #fff;
  }
  .equipment-batch-row + .equipment-batch-row { margin-top: .9rem; }
  .equipment-batch-remove {
    position: absolute;
    top: .85rem;
    right: .85rem;
    border: 0;
    background: #fff1f1;
    color: #d92d20;
    width: 34px;
    height: 34px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: background .2s ease, color .2s ease, transform .2s ease;
  }
  .equipment-batch-duplicate {
    position: absolute;
    top: .85rem;
    right: 3.35rem;
    border: 0;
    background: #eef4ff;
    color: #3657f6;
    min-width: 112px;
    height: 34px;
    border-radius: 10px;
    padding: 0 .8rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .35rem;
    font-size: .75rem;
    font-weight: 800;
    transition: background .2s ease, color .2s ease, transform .2s ease;
  }
  .equipment-batch-duplicate:hover {
    background: #dfe8ff;
    color: #2448dc;
    transform: translateY(-1px);
  }
  .equipment-batch-remove:hover {
    background: #ffe4e4;
    color: #b42318;
    transform: translateY(-1px);
  }
  .equipment-batch-meta {
    border: 1px solid #dbe4ff;
    border-radius: 16px;
    padding: 1rem;
    margin-bottom: 1rem;
    background: linear-gradient(135deg, rgba(54,87,246,.06), rgba(25,140,54,.05));
  }
  .equipment-batch-meta-title {
    font-weight: 850;
    color: #1f2937;
    margin-bottom: .8rem;
  }
  .equipment-form-section {
    margin-top: 1rem;
  }
  .equipment-form-section:first-of-type {
    margin-top: 0;
  }
  .equipment-form-section-title {
    display: flex;
    align-items: center;
    gap: .45rem;
    margin-bottom: .75rem;
    color: #263241;
    font-size: .82rem;
    font-weight: 900;
    letter-spacing: .02em;
    text-transform: uppercase;
  }
  .equipment-form-section-title i {
    color: #3657f6;
  }
  .equipment-form-section-body {
    padding: .9rem;
    border: 1px solid #edf0f5;
    border-radius: 12px;
    background: #fbfcff;
  }
  .generated-control-no[readonly] {
    background: #f8fafc;
    color: #1d4ed8;
    font-weight: 850;
    letter-spacing: .03em;
  }
  .equipment-crud-modal {
    overflow-y: auto !important;
  }
  .equipment-crud-modal .modal-dialog {
    width: min(1180px, calc(100vw - 24px));
    max-width: min(1180px, calc(100vw - 24px));
    height: calc(100vh - 24px);
    height: calc(100dvh - 24px);
    max-height: calc(100vh - 24px);
    max-height: calc(100dvh - 24px);
    margin: 12px auto;
    display: flex;
    align-items: stretch;
  }
  #equipmentBatchModal .modal-content,
  #equipmentEditModal .modal-content,
  #equipmentViewModal .modal-content {
    height: 100%;
    min-height: 0;
    max-height: 100%;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border-radius: 18px;
  }
  #equipmentBatchModal .modal-body,
  #equipmentEditModal .modal-body,
  #equipmentViewModal .modal-body {
    flex: 1 1 auto;
    height: auto;
    min-height: 0 !important;
    max-height: calc(100vh - 170px) !important;
    max-height: calc(100dvh - 170px) !important;
    overflow-y: scroll !important;
    overflow-x: hidden !important;
    overscroll-behavior: contain;
    -webkit-overflow-scrolling: touch;
  }
  #equipmentBatchModal .modal-header,
  #equipmentBatchModal .modal-footer,
  #equipmentEditModal .modal-header,
  #equipmentEditModal .modal-footer,
  #equipmentViewModal .modal-header {
    flex: 0 0 auto;
  }
  #equipmentBatchModal .modal-footer,
  #equipmentEditModal .modal-footer {
    box-shadow: 0 -10px 24px rgba(15,23,42,.05);
  }
  .equipment-detail-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: .75rem 1rem;
  }
  .equipment-detail-item {
    padding: .75rem;
    border-radius: 10px;
    background: #f9fafb;
  }
  .equipment-detail-label {
    color: #667085;
    font-size: .72rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .04em;
  }
  .equipment-detail-value { margin-top: .25rem; color: #1f2937; font-weight: 650; }
  .equipment-status-list { display: grid; gap: .6rem; }
  .equipment-status-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
    padding: .7rem .8rem;
    border: 1px solid #edf0f5;
    border-radius: 10px;
    background: #fff;
  }
  .equipment-inline-status {
    min-width: 145px;
    height: 30px;
    border-radius: 999px;
    border-color: #dbe4ff;
    background-color: #f8fbff;
    color: #344054;
    font-size: .74rem;
    font-weight: 750;
    padding-top: .2rem;
    padding-bottom: .2rem;
  }
  .equipment-empty {
    border-radius: 20px;
    background: rgba(239, 68, 68, .08);
    border: 1px solid rgba(239, 68, 68, .12);
    padding: 1rem 1.1rem;
  }
  .equipment-badge {
    border-radius: 999px;
    padding: .28rem .55rem;
    font-weight: 700;
    font-size: .72rem;
  }
  .equipment-table-card .card-body { padding: 0; }
  .equipment-table-card .dataTables_wrapper { padding: 0; }
  .equipment-table-card .row:first-child { display: none; }
  .equipment-table-card .row:last-child {
    margin: 0;
    padding: .9rem .25rem 0;
    align-items: center;
    color: #475467;
    font-size: .78rem;
  }
  .equipment-table-card .dataTables_info { padding-top: .45rem !important; }
  .equipment-table-card .equipment-length { text-align: center; }
  .equipment-table-card .dataTables_length label {
    display: inline-flex;
    align-items: center;
    gap: .55rem;
    color: #475467;
    font-size: .78rem;
    margin: 0;
  }
  .equipment-table-card .dataTables_length select {
    min-width: 68px;
    height: 34px;
    border-radius: 4px;
    border-color: #eaecf0;
    font-size: .78rem;
  }
  .equipment-table-card .dataTables_paginate .pagination { gap: .35rem; margin: 0; justify-content: flex-end; }
  .equipment-table-card .page-link {
    min-width: 34px;
    height: 34px;
    border-radius: 4px !important;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-color: #eaecf0;
    color: #344054;
    font-size: .78rem;
    box-shadow: none;
  }
  .equipment-table-card .page-item.active .page-link { background: #3657f6; border-color: #3657f6; color: #fff; }
  .equipment-table-card .page-item.disabled .page-link { color: #98a2b3; background: #f9fafb; }
  .equipment-table-card .dataTables_processing {
    border: 0;
    border-radius: 12px;
    box-shadow: 0 12px 28px rgba(15,23,42,.12);
  }
  @media (max-width: 1199px) {
    .equipment-stat-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  }
  @media (max-width: 767px) {
    .equipment-page-head { flex-direction: column; }
    .equipment-toolbar { justify-content: stretch; }
    .equipment-search, .equipment-filter, .equipment-soft-btn { width: 100%; }
    .equipment-detail-grid { grid-template-columns: 1fr; }
    .equipment-crud-modal .modal-dialog {
      width: 100%;
      max-width: none;
      height: 100dvh;
      margin: 0;
    }
    .equipment-crud-modal .modal-content {
      height: 100dvh;
      max-height: 100dvh;
      border-radius: 0;
    }
    #equipmentBatchModal .modal-body,
    #equipmentEditModal .modal-body,
    #equipmentViewModal .modal-body {
      padding: .9rem;
    }
    .equipment-batch-row {
      padding: .9rem;
      padding-top: 4rem;
    }
    .equipment-batch-duplicate {
      left: .9rem;
      right: auto;
      top: .85rem;
    }
    .equipment-batch-remove {
      right: .9rem;
      top: .85rem;
    }
  }
  @media (max-width: 1399px) {
    .equipment-batch-row .col-md-3 {
      flex: 0 0 33.333333%;
      max-width: 33.333333%;
    }
  }
  @media (max-width: 991px) {
    .equipment-crud-modal .modal-dialog {
      width: 100%;
      max-width: none;
      min-height: 100vh;
      min-height: 100dvh;
      height: 100dvh;
      max-height: 100dvh;
      margin: 0;
    }
    .equipment-crud-modal .modal-content {
      height: 100dvh;
      max-height: 100dvh;
      border-radius: 0;
    }
    .equipment-batch-row .col-md-3,
    .equipment-batch-meta .col-md-6 {
      flex: 0 0 50%;
      max-width: 50%;
    }
    #equipmentBatchModal .modal-body,
    #equipmentEditModal .modal-body,
    #equipmentViewModal .modal-body {
      max-height: calc(100vh - 145px) !important;
      max-height: calc(100dvh - 145px) !important;
    }
  }
  @media (max-width: 575px) {
    .equipment-batch-row .col-md-3,
    .equipment-batch-meta .col-md-6 {
      flex: 0 0 100%;
      max-width: 100%;
    }
    #equipmentBatchModal .modal-header,
    #equipmentEditModal .modal-header,
    #equipmentViewModal .modal-header,
    #equipmentBatchModal .modal-footer,
    #equipmentEditModal .modal-footer {
      padding: .85rem;
    }
    #equipmentBatchModal .modal-footer,
    #equipmentEditModal .modal-footer {
      flex-direction: column-reverse;
      align-items: stretch;
    }
    #equipmentBatchModal .modal-footer .btn,
    #equipmentEditModal .modal-footer .btn {
      width: 100%;
    }
    #equipmentBatchModal .modal-body,
    #equipmentEditModal .modal-body,
    #equipmentViewModal .modal-body {
      max-height: calc(100vh - 132px) !important;
      max-height: calc(100dvh - 132px) !important;
    }
  }
</style>

<div class="container-fluid py-3">
    <div class="equipment-shell">
        <div class="equipment-page-head">
            <div>
                <div class="equipment-eyebrow">Manage</div>
                <h2 class="equipment-title">Manage Equipment Details</h2>
            </div>
            <div class="equipment-head-actions">
                <?php if ($canArchiveEquipment): ?>
                    <a href="<?= BASE_URL ?>/ictd/equipment/archive" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-archive"></i> Archive
                    </a>
                <?php endif; ?>
                <?php if ($canImportEquipment): ?>
                    <a href="<?= BASE_URL ?>/ictd/equipment/import" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-filetype-csv"></i> Import CSV
                    </a>
                <?php endif; ?>
                <?php if ($canCreateEquipment): ?>
                    <button type="button" class="btn btn-primary btn-sm" id="openAddEquipmentModal">
                        <i class="bi bi-plus-lg"></i> Add Equipment
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($missingTables)): ?>
            <div class="equipment-empty">
                <div class="fw-semibold mb-1">Equipment module cannot load yet</div>
                <div class="text-muted small">The following database tables are missing in the current environment: <?= e(implode(', ', $missingTables)) ?>.</div>
            </div>
        <?php else: ?>
            <div class="equipment-stat-grid" aria-hidden="true">
                <div class="card equipment-stat-card"><div class="card-body"><div class="d-flex justify-content-between align-items-start"><div><div class="text-muted small">Total Printers</div><div class="display-6 fw-bold mb-0" id="total-printers">0</div></div><div class="equipment-stat-icon bg-primary-subtle text-primary"><i class="bi bi-printer"></i></div></div></div></div>
                <div class="card equipment-stat-card"><div class="card-body"><div class="d-flex justify-content-between align-items-start"><div><div class="text-muted small">Delivery Status (To Follow)</div><div class="display-6 fw-bold mb-0" id="total-tofollow">0</div></div><div class="equipment-stat-icon bg-warning-subtle text-warning"><i class="bi bi-truck"></i></div></div></div></div>
                <div class="card equipment-stat-card"><div class="card-body"><div class="d-flex justify-content-between align-items-start"><div><div class="text-muted small">Under Warranty</div><div class="display-6 fw-bold mb-0" id="total-warranty">0</div></div><div class="equipment-stat-icon bg-success-subtle text-success"><i class="bi bi-shield-check"></i></div></div></div></div>
                <div class="card equipment-stat-card"><div class="card-body"><div class="d-flex justify-content-between align-items-start"><div><div class="text-muted small">Expired Warranty</div><div class="display-6 fw-bold mb-0" id="total-expired">0</div></div><div class="equipment-stat-icon bg-danger-subtle text-danger"><i class="bi bi-exclamation-triangle"></i></div></div></div></div>
            </div>

            <div class="equipment-toolbar">
                <div class="equipment-search">
                    <i class="bi bi-search"></i>
                    <input type="search" class="form-control" id="equipmentSearch" placeholder="Search in equipment table">
                </div>
                <div class="equipment-search">
                <select class="form-select equipment-filter" id="equipmentStatusFilter">
                    <option value="">Filter by Status</option>
                    <option value="Delivered">Delivered</option>
                    <option value="To Follow">To Follow</option>
                    <option value="Under Warranty">Under Warranty</option>
                    <option value="Expired">Expired</option>
                </select>
                </div>
                <div class="equipment-search">
                <select class="form-select equipment-filter" id="equipmentConditionFilter">
                    <option value="">Equipment Status</option>
                </select>
                </div>
                <?php if (can('ictd.equipment.edit')): ?>
                    <button type="button" class="equipment-soft-btn" id="manageEquipmentStatusBtn">
                        Status Masterlist <i class="bi bi-gear ms-1"></i>
                    </button>
                <?php endif; ?>
                <button type="button" class="equipment-soft-btn" id="equipmentExportBtn">
                    Export Data <i class="bi bi-download ms-1"></i>
                </button>
                <button type="button" class="equipment-soft-btn" id="equipmentMineOnlyBtn" data-active="0">
                    My Records <i class="bi bi-person-check ms-1"></i>
                </button>
                <button type="button" class="equipment-soft-btn" id="equipmentRefreshBtn" title="Refresh">
                    <i class="bi bi-sliders"></i>
                </button>
            </div>

            <div class="card equipment-panel equipment-table-card">
                <div class="card-body p-0">
                    <div class="table-container">
                        <div class="table-responsive">
                            <table id="equipmentTable" class="table align-middle nowrap mb-0 equipment-table" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>Action</th>
                                        <th>Control No</th>
                                        <th>Old Control No</th>
                                        <th>Description</th>
                                        <th>Brand</th>
                                        <th>Serial No</th>
                                        <th>Warranty</th>
                                        <th>Delivery Status</th>
                                        <th>Status</th>
                                        <th>Price</th>
                                        <th>Property No</th>
                                        <th>Assigned Flag</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (empty($missingTables)): ?>
<div class="modal fade equipment-crud-modal" id="equipmentBatchModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title fw-bold">Add Equipment Batch</h5>
                    <div class="text-muted small">Add multiple equipment records, then save them all at once.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="equipmentBatchForm">
                <div class="modal-body">
                    <div class="equipment-batch-meta">
                        <div class="equipment-batch-meta-title">Batch Details</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Supplier *</label>
                                <select class="form-select equipment-select2" id="batch_supplier_id" required></select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Date Delivered *</label>
                                <input type="date" class="form-control" id="batch_date_delivered" required>
                            </div>
                        </div>
                        <div class="text-muted small mt-2">These batch details will be applied to every equipment entry below.</div>
                    </div>
                    <div id="equipmentBatchRows"></div>
                    <button type="button" class="btn btn-outline-primary btn-sm mt-3" id="addEquipmentRowBtn">
                        <i class="bi bi-plus-lg"></i> Add Another Equipment
                    </button>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Save Batch
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade equipment-crud-modal" id="equipmentEditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Edit Equipment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="equipmentEditForm">
                <input type="hidden" name="id" id="edit_equipment_id">
                <div class="modal-body">
                    <div id="equipmentEditFields"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade equipment-crud-modal" id="equipmentViewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Equipment Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="equipment-detail-grid" id="equipmentViewDetails"></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="equipmentStatusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content border-0">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Equipment Status Masterlist</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="equipmentStatusForm" class="d-flex gap-2 mb-3">
                    <input type="hidden" id="status_master_id">
                    <input type="text" class="form-control" id="status_master_name" placeholder="Condemned, For Repair, Working..." required>
                    <button type="submit" class="btn btn-primary">Save</button>
                </form>
                <div class="equipment-status-list" id="equipmentStatusList"></div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const tableElement = $('#equipmentTable');
    if (!tableElement.length) {
        return;
    }

    const canDeleteEquipment = <?= $canDeleteEquipment ? 'true' : 'false' ?>;
    const canCreateEquipment = <?= $canCreateEquipment ? 'true' : 'false' ?>;
    const canEditEquipment = <?= can('ictd.equipment.edit') ? 'true' : 'false' ?>;
    const equipmentActionUrl = '<?= BASE_URL ?>/ictd/equipment-action';
    let reuseControlNo = new URLSearchParams(window.location.search).get('reuse_control_no') || '';
    let equipmentMasterlists = {
        descriptions: [],
        brands: [],
        suppliers: [],
        statuses: []
    };
    const equipmentBatchGuard = window.IMSUnsavedGuard?.create({
        form: '#equipmentBatchForm',
        modal: '#equipmentBatchModal',
        dirtySelectors: ['#addEquipmentRowBtn', '.duplicate-equipment-row', '.remove-equipment-row']
    });
    const equipmentEditGuard = window.IMSUnsavedGuard?.create({
        form: '#equipmentEditForm',
        modal: '#equipmentEditModal'
    });

    $('#equipmentBatchModal, #equipmentEditModal, #equipmentViewModal').on('shown.bs.modal', function () {
        $(this).find('.modal-body').scrollTop(0);
    });

    $('#equipmentBatchModal, #equipmentEditModal, #equipmentViewModal').on('wheel touchmove', '.modal-body', function (event) {
        event.stopPropagation();
    });

    const table = tableElement.DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '<?= BASE_URL ?>/ictd/equipment-data',
            data: function (data) {
                data.action = 'table';
                data.status_filter = $('#equipmentStatusFilter').val();
                data.equipment_status_filter = $('#equipmentConditionFilter').val();
                data.mine_only = $('#equipmentMineOnlyBtn').data('active') === 1 ? 1 : 0;
            }
        },
        dom: 'rt<"row align-items-center equipment-table-footer"<"col-md-4"i><"col-md-4 equipment-length"l><"col-md-4"p>>',
        order: [[1, 'desc']],
        responsive: true,
        pageLength: 10,
        columnDefs: [
            { targets: [11], visible: false, searchable: false },
            { targets: [0], orderable: false, searchable: false }
        ],
        createdRow: function (row, data) {
            $(row).toggleClass('equipment-row-assigned', Number(data[11]) === 1);
        },
        language: {
            info: '_START_ - _END_ of _TOTAL_',
            lengthMenu: 'Rows per page _MENU_',
            paginate: {
                previous: '<i class="bi bi-chevron-left"></i>',
                next: '<i class="bi bi-chevron-right"></i>'
            },
            processing: 'Loading equipment...'
        }
    });

    $('#equipmentSearch').on('input', function () {
        table.search(this.value).draw();
    });

    $('#equipmentStatusFilter').on('change', function () {
        table.ajax.reload();
    });

    $('#equipmentConditionFilter').on('change', function () {
        table.ajax.reload();
    });

    $('#equipmentMineOnlyBtn').on('click', function () {
        const isActive = $(this).data('active') === 1;
        $(this)
            .data('active', isActive ? 0 : 1)
            .toggleClass('active btn-primary text-white', !isActive);
        table.ajax.reload();
    });

    $('#equipmentRefreshBtn').on('click', function () {
        $('#equipmentSearch').val('');
        $('#equipmentStatusFilter').val('');
        $('#equipmentConditionFilter').val('');
        table.search('').ajax.reload();
        loadPrinterCount();
    });

    $(document).on('focus mousedown', '.equipment-inline-status', function () {
        $(this).data('previous-value', $(this).val());
    });

    $(document).on('change', '.equipment-inline-status', function () {
        if (!canEditEquipment) {
            Swal.fire('Unavailable', 'You do not have permission to update equipment status.', 'info');
            return;
        }

        const select = $(this);
        const equipmentId = select.data('id');
        const previousValue = select.data('previous-value') ?? '';

        select.prop('disabled', true);

        $.ajax({
            url: equipmentActionUrl,
            method: 'POST',
            contentType: 'application/json',
            dataType: 'json',
            data: JSON.stringify({
                action: 'mark_status',
                id: equipmentId,
                equipment_status_id: select.val()
            }),
            success: function (res) {
                if (!res.success) {
                    select.val(previousValue);
                    Swal.fire('Error', res.message || 'Unable to update equipment status.', 'error');
                    return;
                }

                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: res.message || 'Equipment status updated.',
                    showConfirmButton: false,
                    timer: 1300
                });
                table.ajax.reload(null, false);
            },
            error: function (xhr) {
                select.val(previousValue);
                Swal.fire('Error', xhr.responseJSON?.message || 'Unable to update equipment status.', 'error');
            },
            complete: function () {
                select.prop('disabled', false);
            }
        });
    });

    $('#equipmentExportBtn').on('click', function () {
        Swal.fire('Export pending', 'Equipment export will be wired after the import/export flow is migrated.', 'info');
    });

    table.on('draw', function () {
        let toFollowCount = 0;
        let underWarrantyCount = 0;
        let expiredWarrantyCount = 0;

        table.rows({ page: 'current' }).every(function () {
            const row = this.data();
            if (!row) return;

            const delivery = String(row[5] || '').toLowerCase();
            const warranty = String(row[4] || '').toLowerCase();

            if (delivery.includes('to follow')) toFollowCount++;
            if (warranty.includes('under warranty')) underWarrantyCount++;
            if (warranty.includes('expired')) expiredWarrantyCount++;
        });

        $('#total-tofollow').text(toFollowCount);
        $('#total-warranty').text(underWarrantyCount);
        $('#total-expired').text(expiredWarrantyCount);
    });

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, function (char) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[char];
        });
    }

    function masterOptions(list, selectedValue = '') {
        return ['<option value="">Select</option>'].concat((list || []).map(function (item) {
            const selected = String(item.id) === String(selectedValue ?? '') ? 'selected' : '';
            return `<option value="${escapeHtml(item.id)}" ${selected}>${escapeHtml(item.text)}</option>`;
        })).join('');
    }

    function equipmentFieldsHtml(prefix, data = {}, options = {}) {
        const removable = Boolean(options.removable);
        const duplicable = Boolean(options.duplicable);
        const includeBatchFields = options.includeBatchFields !== false;
        const isBatchEntry = !includeBatchFields;
        const controlReadonly = isBatchEntry ? 'readonly' : '';
        const controlPlaceholder = isBatchEntry ? 'Auto-generated after date delivered' : '';
        const removeButton = removable ? '<button type="button" class="equipment-batch-remove remove-equipment-row" title="Remove"><i class="bi bi-x-lg"></i> Remove</button>' : '';
        const duplicateButton = duplicable ? '<button type="button" class="equipment-batch-duplicate duplicate-equipment-row" title="Duplicate this entry"><i class="bi bi-files"></i> Duplicate</button>' : '';
        const batchFields = includeBatchFields ? `
                    <div class="col-md-3"><label class="form-label">Supplier</label><select class="form-select equipment-select2" name="${prefix}[supplier_id]">${masterOptions(equipmentMasterlists.suppliers, data.supplier_id)}</select></div>
                    <div class="col-md-3"><label class="form-label">Date Delivered</label><input type="date" class="form-control" name="${prefix}[date_delivered]" value="${escapeHtml(data.date_delivered)}"></div>
        ` : '';
        return `
            <div class="equipment-batch-row">
                ${removeButton}
                ${duplicateButton}
                <div class="equipment-form-section">
                    <div class="equipment-form-section-title"><i class="bi bi-upc-scan"></i> Identification</div>
                    <div class="equipment-form-section-body">
                        <div class="row g-3">
                            <div class="col-md-3"><label class="form-label">Control No *</label><input class="form-control generated-control-no" name="${prefix}[control_no]" value="${escapeHtml(data.control_no)}" placeholder="${controlPlaceholder}" ${controlReadonly} required></div>
                            <div class="col-md-3"><label class="form-label">Old Control No</label><input class="form-control" name="${prefix}[old_control_no]" value="${escapeHtml(data.old_control_no)}"></div>
                            <div class="col-md-6"><label class="form-label">Computer Name</label><input class="form-control" name="${prefix}[computer_name]" value="${escapeHtml(data.computer_name)}"></div>
                        </div>
                    </div>
                </div>

                <div class="equipment-form-section">
                    <div class="equipment-form-section-title"><i class="bi bi-list-check"></i>Equipment Details</div>
                    <div class="equipment-form-section-body">
                        <div class="row g-3">
                            <div class="col-md-5"><label class="form-label">Description</label><select class="form-select equipment-select2" name="${prefix}[description_id]">${masterOptions(equipmentMasterlists.descriptions, data.description_id)}</select></div>
                            <div class="col-md-4"><label class="form-label">Brand</label><select class="form-select equipment-select2" name="${prefix}[brand_id]">${masterOptions(equipmentMasterlists.brands, data.brand_id)}</select></div>
                            <div class="col-md-3"><label class="form-label">Equipment Status</label><select class="form-select equipment-select2" name="${prefix}[equipment_status_id]">${masterOptions(equipmentMasterlists.statuses, data.equipment_status_id)}</select></div>
                            ${batchFields}
                            <div class="col-md-3"><label class="form-label">Serial No</label><input class="form-control" name="${prefix}[serialno]" value="${escapeHtml(data.serialno)}"></div>

                        </div>
                    </div>
                </div>

                <div class="equipment-form-section">
                    <div class="equipment-form-section-title"><i class="bi bi-windows"></i> Software</div>
                    <div class="equipment-form-section-body">
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label">OS</label><input class="form-control" name="${prefix}[os]" value="${escapeHtml(data.os)}"></div>
                            <div class="col-md-6"><label class="form-label">OS Product Key</label><input class="form-control" name="${prefix}[os_prokey]" value="${escapeHtml(data.os_prokey)}"></div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4"><label class="form-label">MS Software</label><input class="form-control" name="${prefix}[ms_software]" value="${escapeHtml(data.ms_software)}"></div>
                            <div class="col-md-4"><label class="form-label">MS Username</label><input class="form-control" name="${prefix}[ms_uname]" value="${escapeHtml(data.ms_uname)}"></div>
                            <div class="col-md-4"><label class="form-label">MS Password</label><input class="form-control" name="${prefix}[ms_pass]" value="${escapeHtml(data.ms_pass)}"></div>
                        </div>
                    </div>
                </div>

                <div class="equipment-form-section">
                    <div class="equipment-form-section-title"><i class="bi bi-cpu"></i> Hardware</div>
                    <div class="equipment-form-section-body">
                        <div class="row g-3">
                            <div class="col-md-3"><label class="form-label">Processor</label><input class="form-control" name="${prefix}[processor]" value="${escapeHtml(data.processor)}"></div>
                            <div class="col-md-3"><label class="form-label">Memory</label><input class="form-control" name="${prefix}[memory]" value="${escapeHtml(data.memory)}"></div>
                            <div class="col-md-3"><label class="form-label">HDD</label><input class="form-control" name="${prefix}[hdd]" value="${escapeHtml(data.hdd)}"></div>
                            <div class="col-md-3"><label class="form-label">Video Card</label><input class="form-control" name="${prefix}[video_card]" value="${escapeHtml(data.video_card)}"></div>
                        </div>
                    </div>
                </div>

                <div class="equipment-form-section">
                    <div class="equipment-form-section-title"><i class="bi bi-truck"></i> Warranty and Delivery</div>
                    <div class="equipment-form-section-body">
                        <div class="row g-3">
                            <div class="col-md-3"><label class="form-label">Warranty Date</label><input type="date" class="form-control warranty-date-field" name="${prefix}[warranty_date]" value="${escapeHtml(data.warranty_date)}"></div>
                            <div class="col-md-3"><label class="form-label">Years Warranty</label><input type="number" min="0" class="form-control warranty-years-field" name="${prefix}[no_yrs_warranty]" value="${escapeHtml(data.no_yrs_warranty)}"></div>
                            <div class="col-md-3"><label class="form-label">Delivery Status</label><select class="form-select delivery-status-field" name="${prefix}[delivery_stat]"><option ${data.delivery_stat === 'Delivered' ? 'selected' : ''}>Delivered</option><option ${data.delivery_stat !== 'Delivered' ? 'selected' : ''}>To Follow</option></select></div>
                            <div class="col-md-3"><label class="form-label">ETA Date</label><input type="date" class="form-control eta-date-field" name="${prefix}[eta_date]" value="${escapeHtml(data.eta_date)}"></div>
                        </div>
                    </div>
                </div>

                <div class="equipment-form-section">
                    <div class="equipment-form-section-title"><i class="bi bi-tag"></i> Property No. and Cost</div>
                    <div class="equipment-form-section-body">
                        <div class="row g-3">
                            <div class="col-md-3"><label class="form-label">Price</label><input type="number" min="0" step="1" class="form-control" name="${prefix}[price]" value="${escapeHtml(data.price)}"></div>
                            <div class="col-md-3"><label class="form-label">Property No</label><input class="form-control" name="${prefix}[property_no]" value="${escapeHtml(data.property_no)}"></div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    function initSelect2(scope) {
        $(scope).find('.equipment-select2').select2({
            width: '100%',
            dropdownParent: $(scope).closest('.modal')
        });
    }

    function syncEquipmentRowRules(row) {
        const container = $(row);
        const warrantyDate = container.find('.warranty-date-field');
        const warrantyYears = container.find('.warranty-years-field');
        const deliveryStatus = container.find('.delivery-status-field');
        const etaDate = container.find('.eta-date-field');

        const hasWarrantyYears = String(warrantyYears.val() || '').trim() !== '';
        const hasWarrantyDate = String(warrantyDate.val() || '').trim() !== '';
        const isDelivered = deliveryStatus.val() === 'Delivered';

        warrantyDate.prop('disabled', hasWarrantyYears);
        warrantyYears.prop('disabled', hasWarrantyDate);

        if (hasWarrantyYears) {
            warrantyDate.val('');
        }

        if (hasWarrantyDate) {
            warrantyYears.val('');
        }

        etaDate.prop('disabled', isDelivered);
        if (isDelivered) {
            etaDate.val('');
        }
    }

    function syncEquipmentRules(scope) {
        $(scope).find('.equipment-batch-row').each(function () {
            syncEquipmentRowRules(this);
        });
    }

    function refreshBatchControlNumbers() {
        if (reuseControlNo) {
            $('#equipmentBatchRows .generated-control-no').val(reuseControlNo);
            return;
        }
        const dateDelivered = $('#batch_date_delivered').val();
        const rows = $('#equipmentBatchRows .equipment-batch-row');

        if (!rows.length) return;

        if (!dateDelivered) {
            rows.find('.generated-control-no').val('').attr('placeholder', 'Select Date Delivered first');
            return;
        }

        $.getJSON(equipmentActionUrl, {
            action: 'next_control_numbers',
            date_delivered: dateDelivered,
            count: rows.length
        }).done(function (res) {
            if (!res.success) return;
            rows.each(function (index) {
                $(this).find('.generated-control-no').val(res.numbers[index] || '');
            });
        }).fail(function () {
            rows.find('.generated-control-no').val('').attr('placeholder', 'Unable to generate');
        });
    }

    function serializeEquipmentRow(container) {
        const data = {};
        $(container).find(':input[name]').each(function () {
            const match = this.name.match(/\[([^\]]+)\]$/);
            if (match) data[match[1]] = $(this).val();
        });
        return data;
    }

    function renderViewDetails(data) {
        const fields = [
            ['Control No', data.control_no],
            ['Old Control No', data.old_control_no],
            ['Computer Name', data.computer_name],
            ['Description', data.description_name],
            ['Brand', data.brand_name],
            ['Supplier', data.supplier_name],
            ['Department', data.department_name],
            ['Serial No', data.serialno],
            ['OS', data.os],
            ['OS Product Key', data.os_prokey],
            ['MS Software', data.ms_software],
            ['MS Username', data.ms_uname],
            ['MS Password', data.ms_pass],
            ['Processor', data.processor],
            ['Memory', data.memory],
            ['HDD', data.hdd],
            ['Video Card', data.video_card],
            ['Warranty Date', data.warranty_date],
            ['Years Warranty', data.no_yrs_warranty],
            ['Delivery Status', data.delivery_stat],
            ['Equipment Status', data.equipment_status],
            ['ETA Date', data.eta_date],
            ['Date Delivered', data.date_delivered],
            ['Price', data.price],
            ['Property No', data.property_no],
            ['Assigned ID', data.assigned_id],
            ['Transmittal ID', data.transmittal_id],
            ['Batch ID', data.batch_id],
            ['Created At', data.created_at]
        ];

        $('#equipmentViewDetails').html(fields.map(function ([label, value]) {
            return `<div class="equipment-detail-item"><div class="equipment-detail-label">${escapeHtml(label)}</div><div class="equipment-detail-value">${escapeHtml(value || 'N/A')}</div></div>`;
        }).join(''));
    }

    function fetchEquipment(id) {
        return $.getJSON(equipmentActionUrl, { action: 'view', id });
    }

    function loadMasterlists(callback) {
        if (equipmentMasterlists.descriptions.length) {
            callback?.();
            return;
        }

        $.getJSON(equipmentActionUrl, { action: 'masterlists' })
            .done(function (res) {
                if (res.success) {
                    equipmentMasterlists = res.data;
                    renderEquipmentStatusOptions();
                }
                callback?.();
            })
            .fail(function () {
                Swal.fire('Error', 'Unable to load equipment masterlists.', 'error');
            });
    }

    function renderEquipmentStatusOptions() {
        $('#equipmentConditionFilter').html('<option value="">Equipment Status</option>' + (equipmentMasterlists.statuses || []).map(function (item) {
            return `<option value="${escapeHtml(item.id)}">${escapeHtml(item.text)}</option>`;
        }).join(''));
    }

    function renderEquipmentStatusList() {
        const html = (equipmentMasterlists.statuses || []).map(function (item) {
            return `
                <div class="equipment-status-item">
                    <span class="fw-semibold">${escapeHtml(item.text)}</span>
                    <span class="equipment-actions">
                        <button type="button" class="equipment-action-btn equipment-action-edit edit-status-btn" data-id="${escapeHtml(item.id)}" data-name="${escapeHtml(item.text)}"><i class="bi bi-pencil-square"></i> Edit</button>
                        <button type="button" class="equipment-action-btn equipment-action-delete delete-status-btn" data-id="${escapeHtml(item.id)}"><i class="bi bi-trash"></i> Delete</button>
                    </span>
                </div>
            `;
        }).join('');
        $('#equipmentStatusList').html(html || '<div class="text-muted small">No status records yet.</div>');
    }

    function reloadMasterlists(callback) {
        equipmentMasterlists = { descriptions: [], brands: [], suppliers: [], statuses: [] };
        loadMasterlists(function () {
            renderEquipmentStatusOptions();
            renderEquipmentStatusList();
            callback?.();
        });
    }

    $('#manageEquipmentStatusBtn').on('click', function () {
        loadMasterlists(function () {
            renderEquipmentStatusList();
            $('#status_master_id').val('');
            $('#status_master_name').val('');
            $('#equipmentStatusModal').modal('show');
        });
    });

    $('#equipmentStatusForm').on('submit', function (event) {
        event.preventDefault();
        const id = $('#status_master_id').val();
        $.ajax({
            url: equipmentActionUrl,
            method: 'POST',
            contentType: 'application/json',
            dataType: 'json',
            data: JSON.stringify({
                action: id ? 'status_update' : 'status_create',
                id,
                equipment_status: $('#status_master_name').val()
            }),
            success: function (res) {
                if (!res.success) {
                    Swal.fire('Error', res.message || 'Unable to save status.', 'error');
                    return;
                }
                $('#status_master_id').val('');
                $('#status_master_name').val('');
                reloadMasterlists();
                Swal.fire('Saved', res.message, 'success');
            },
            error: function (xhr) {
                Swal.fire('Error', xhr.responseJSON?.message || 'Unable to save status.', 'error');
            }
        });
    });

    $(document).on('click', '.edit-status-btn', function () {
        $('#status_master_id').val($(this).data('id'));
        $('#status_master_name').val($(this).data('name')).trigger('focus');
    });

    $(document).on('click', '.delete-status-btn', function () {
        const id = $(this).data('id');
        Swal.fire({
            title: 'Delete status?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Delete',
            confirmButtonColor: '#d92d20'
        }).then(function (result) {
            if (!result.isConfirmed) return;
            $.ajax({
                url: equipmentActionUrl,
                method: 'POST',
                contentType: 'application/json',
                dataType: 'json',
                data: JSON.stringify({ action: 'status_delete', id }),
                success: function (res) {
                    if (!res.success) {
                        Swal.fire('Error', res.message || 'Unable to delete status.', 'error');
                        return;
                    }
                    reloadMasterlists();
                    Swal.fire('Deleted', res.message, 'success');
                },
                error: function (xhr) {
                    Swal.fire('Error', xhr.responseJSON?.message || 'Unable to delete status.', 'error');
                }
            });
        });
    });

    loadMasterlists();

    function openAddEquipmentModal(prefillControlNo = '') {
        loadMasterlists(function () {
            $('#batch_supplier_id').html(masterOptions(equipmentMasterlists.suppliers));
            $('#batch_date_delivered').val('');
            $('#equipmentBatchRows').html(equipmentFieldsHtml('rows[0]', { control_no: prefillControlNo }, {
                removable: false,
                duplicable: !prefillControlNo,
                includeBatchFields: false
            }));
            $('#addEquipmentRowBtn').toggle(!prefillControlNo);
            initSelect2('#equipmentBatchModal');
            syncEquipmentRules('#equipmentBatchModal');
            if (prefillControlNo) {
                $('#equipmentBatchModal .modal-title').text('Add Equipment Using Reused Control No');
                $('#equipmentBatchModal .modal-header .text-muted.small').text('Complete the equipment details for Control No ' + prefillControlNo + '.');
            } else {
                $('#equipmentBatchModal .modal-title').text('Add Equipment Batch');
                $('#equipmentBatchModal .modal-header .text-muted.small').text('Add multiple equipment records, then save them all at once.');
                refreshBatchControlNumbers();
            }
            $('#equipmentBatchModal').modal('show');
        });
    }

    $('#openAddEquipmentModal').on('click', function () {
        reuseControlNo = '';
        openAddEquipmentModal('');
    });

    $('#addEquipmentRowBtn').on('click', function () {
        if (reuseControlNo) {
            return;
        }
        const index = $('#equipmentBatchRows .equipment-batch-row').length;
        $('#equipmentBatchRows').append(equipmentFieldsHtml(`rows[${index}]`, {}, {
            removable: true,
            duplicable: true,
            includeBatchFields: false
        }));
        initSelect2($('#equipmentBatchRows .equipment-batch-row').last());
        syncEquipmentRules($('#equipmentBatchRows .equipment-batch-row').last());
        refreshBatchControlNumbers();
    });

    $(document).on('click', '.duplicate-equipment-row', function () {
        const sourceRow = $(this).closest('.equipment-batch-row');
        const clonedData = serializeEquipmentRow(sourceRow);
        clonedData.control_no = '';
        clonedData.serialno = '';
        clonedData.property_no = '';

        const index = $('#equipmentBatchRows .equipment-batch-row').length;
        $('#equipmentBatchRows').append(equipmentFieldsHtml(`rows[${index}]`, clonedData, {
            removable: true,
            duplicable: true,
            includeBatchFields: false
        }));
        initSelect2($('#equipmentBatchRows .equipment-batch-row').last());
        syncEquipmentRules($('#equipmentBatchRows .equipment-batch-row').last());
        refreshBatchControlNumbers();
    });

    $(document).on('click', '.remove-equipment-row', function () {
        $(this).closest('.equipment-batch-row').remove();
        refreshBatchControlNumbers();
    });

    $('#batch_date_delivered').on('change', refreshBatchControlNumbers);
    $('#equipmentBatchModal').on('hidden.bs.modal', function () {
        if (reuseControlNo) {
            const cleanUrl = new URL(window.location.href);
            cleanUrl.searchParams.delete('reuse_control_no');
            window.history.replaceState({}, document.title, cleanUrl.toString());
        }
    });

    $('#equipmentBatchForm').on('submit', function (event) {
        event.preventDefault();
        const rows = $('#equipmentBatchRows .equipment-batch-row').map(function () {
            return serializeEquipmentRow(this);
        }).get();
        const batchSupplierId = $('#batch_supplier_id').val();
        const batchDateDelivered = $('#batch_date_delivered').val();

        if (!batchSupplierId || !batchDateDelivered) {
            Swal.fire('Missing batch details', 'Please select a supplier and date delivered for this batch.', 'warning');
            return;
        }

        $.ajax({
            url: equipmentActionUrl,
            method: 'POST',
            contentType: 'application/json',
            dataType: 'json',
            data: JSON.stringify({
                action: 'batch_create',
                batch_supplier_id: batchSupplierId,
                batch_date_delivered: batchDateDelivered,
                reuse_control_no: reuseControlNo,
                rows
            }),
            success: function (res) {
                if (!res.success) {
                    Swal.fire('Error', res.message || 'Unable to save equipment.', 'error');
                    return;
                }
                equipmentBatchGuard?.forceClean();
                $('#equipmentBatchModal').modal('hide');
                Swal.fire('Saved', res.message, 'success');
                reuseControlNo = '';
                table.ajax.reload(null, false);
                loadPrinterCount();
            },
            error: function (xhr) {
                Swal.fire('Error', xhr.responseJSON?.message || 'Unable to save equipment batch.', 'error');
            }
        });
    });

    if (reuseControlNo) {
        openAddEquipmentModal(reuseControlNo);
    }

    $(document).on('click', '.view-equipment-btn', function () {
        fetchEquipment($(this).data('id')).done(function (res) {
            if (!res.success) {
                Swal.fire('Error', res.message || 'Unable to load equipment.', 'error');
                return;
            }
            renderViewDetails(res.data);
            $('#equipmentViewModal').modal('show');
        });
    });

    $(document).on('click', '.edit-equipment-btn', function () {
        const id = $(this).data('id');
        loadMasterlists(function () {
            fetchEquipment(id).done(function (res) {
                if (!res.success) {
                    Swal.fire('Error', res.message || 'Unable to load equipment.', 'error');
                    return;
                }
                $('#edit_equipment_id').val(id);
                $('#equipmentEditFields').html(equipmentFieldsHtml('equipment', res.data, {
                    removable: false,
                    duplicable: false,
                    includeBatchFields: true
                }));
                initSelect2('#equipmentEditFields');
                syncEquipmentRules('#equipmentEditFields');
                $('#equipmentEditModal').modal('show');
            });
        });
    });

    $(document).on('input change', '.warranty-date-field, .warranty-years-field, .delivery-status-field', function () {
        syncEquipmentRowRules($(this).closest('.equipment-batch-row'));
    });

    $('#equipmentEditForm').on('submit', function (event) {
        event.preventDefault();
        const payload = serializeEquipmentRow('#equipmentEditFields .equipment-batch-row');
        payload.action = 'update';
        payload.id = $('#edit_equipment_id').val();

        function submitEquipmentUpdate(updatePayload) {
            $.ajax({
                url: equipmentActionUrl,
                method: 'POST',
                contentType: 'application/json',
                dataType: 'json',
                data: JSON.stringify(updatePayload),
                success: function (res) {
                    if (!res.success) {
                        Swal.fire('Error', res.message || 'Unable to update equipment.', 'error');
                        return;
                    }
                    equipmentEditGuard?.forceClean();
                    $('#equipmentEditModal').modal('hide');
                    Swal.fire('Updated', res.message, 'success');
                    table.ajax.reload(null, false);
                },
                error: function (xhr) {
                    const response = xhr.responseJSON || {};
                    if (response.archived_control_conflict) {
                        Swal.fire({
                            title: 'Reuse archived control no?',
                            text: response.message || 'This control number already exists in the archive. Continue only if this is intentionally a new item using the same control no.',
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: 'Reuse control no',
                            confirmButtonColor: '#3657f6'
                        }).then(function (result) {
                            if (!result.isConfirmed) return;
                            updatePayload.reuse_archived_control_no = 1;
                            submitEquipmentUpdate(updatePayload);
                        });
                        return;
                    }
                    Swal.fire('Error', response.message || 'Unable to update equipment.', 'error');
                }
            });
        }

        submitEquipmentUpdate(payload);
    });

    $(document).on('click', '.delete-equipment-btn', function () {
        confirmDelete($(this).data('id'));
    });

    window.confirmDelete = function (id) {
        if (!canDeleteEquipment) {
            Swal.fire('Unavailable', 'You do not have permission to delete equipment.', 'info');
            return;
        }

        Swal.fire({
            title: 'Archive equipment?',
            text: 'If this equipment is assigned, the system will automatically unassign it first and save the assignment details in the archive.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d92d20',
            confirmButtonText: 'Move to archive'
        }).then(function (result) {
            if (!result.isConfirmed) return;

            $.ajax({
                url: equipmentActionUrl,
                method: 'POST',
                contentType: 'application/json',
                dataType: 'json',
                data: JSON.stringify({ action: 'delete', id }),
                success: function (res) {
                    if (!res.success) {
                        Swal.fire('Error', res.message || 'Unable to delete equipment.', 'error');
                        return;
                    }
                    Swal.fire('Archived', res.message, 'success');
                    table.ajax.reload(null, false);
                    loadPrinterCount();
                },
                error: function (xhr) {
                    Swal.fire('Error', xhr.responseJSON?.message || 'Unable to delete equipment.', 'error');
                }
            });
        });
    };

    function loadPrinterCount() {
        $.ajax({
            url: '<?= BASE_URL ?>/ictd/equipment-data',
            type: 'GET',
            data: { action: 'printer_count' },
            dataType: 'json',
            success: function (res) {
                $('#total-printers').text(res.count || 0);
            },
            error: function () {
                $('#total-printers').text('0');
            }
        });
    }

    loadPrinterCount();
});
</script>

<?php require_once ROOT_PATH . '/modules/layout/footer.php'; ?>
 
