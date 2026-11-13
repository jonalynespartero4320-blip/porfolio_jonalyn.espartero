<?php
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/functions.php';
require_once ROOT_PATH . '/modules/Reports/report_common.php';
require_once ROOT_PATH . '/modules/layout/header.php';

$canReportExport = can('reports.export');
$canReportPrint = can('reports.print');
$modules = gr_modules($pdo);
$activeKey = gr_active_module($pdo, trim((string) ($_GET['module'] ?? 'equipment')));
$active = $modules[$activeKey];
$filters = [
    'search' => trim((string) ($_GET['search'] ?? '')),
    'date_from' => trim((string) ($_GET['date_from'] ?? '')),
    'date_to' => trim((string) ($_GET['date_to'] ?? '')),
    'description_name' => trim((string) ($_GET['description_name'] ?? '')),
    'status' => trim((string) ($_GET['status'] ?? '')),
];
$rows = gr_fetch_rows($pdo, $active, $filters);
$descriptions = gr_descriptions($pdo);
$statusOptions = gr_status_options($pdo, $active, $filters);
$labels = gr_field_labels($active);
$totalRows = count($rows);
$totalPrice = array_reduce($rows, static fn($sum, $row) => $sum + (float) ($row['price'] ?? 0), 0.0);
$withStatus = count(array_filter($rows, static fn($row) => trim((string) ($row['status'] ?? $row['delivery_stat'] ?? $row['warranty_status'] ?? '')) !== ''));
$recentRows = count(array_filter($rows, static function ($row) {
    $date = $row['date_recorded'] ?? $row['created_at'] ?? $row['report_date'] ?? $row['date_accomplished'] ?? $row['purchase_date'] ?? $row['date_borrowed'] ?? null;
    return $date && strtotime((string) $date) >= strtotime('-30 days');
}));
$modulePayload = [];
foreach ($modules as $key => $config) {
    $modulePayload[$key] = [
        'title' => $config['title'],
        'label' => $config['label'],
        'fields' => gr_field_labels($config),
        'hasDescription' => !empty($config['description']),
        'hasStatus' => !empty($config['status']),
    ];
}
?>

<style>
  .reports-shell { display: grid; gap: 1rem; }
  .reports-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; }
  .reports-eyebrow { color: #667085; font-size: .78rem; font-weight: 750; }
  .reports-title { color: #1f2937; font-size: 1.35rem; font-weight: 850; margin: 0; }
  .reports-actions { display: flex; align-items: center; gap: .65rem; flex-wrap: wrap; }
  .reports-actions .btn { border-radius: 4px; min-height: 38px; padding-inline: 1rem; }
  .reports-tabs {
    border-bottom: 0;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(142px, 1fr));
    gap: .45rem;
    overflow: visible;
  }
  .reports-tabs .nav-item { min-width: 0; }
  .reports-tabs .nav-link {
    width: 100%;
    min-height: 38px;
    border: 1px solid rgba(20,154,160,.18);
    border-radius: 6px;
    background: rgba(255,255,255,.74);
    color: #0b5c66;
    font-size: .78rem;
    font-weight: 800;
    padding: .55rem .7rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .25rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    box-shadow: 0 8px 18px rgba(15,23,42,.05);
  }
  .reports-tabs .nav-link:hover {
    background: rgba(20,154,160,.09);
    border-color: rgba(20,154,160,.35);
    color: #07346c;
  }
  .reports-tabs .nav-link.active {
    color: #fff;
    background: linear-gradient(90deg, var(--ims-old-teal, #149aa0), var(--ims-old-navy, #07346c));
    border-color: transparent;
    box-shadow: 0 10px 20px rgba(7,52,108,.18);
  }
  .reports-stat-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .8rem; }
  .reports-stat { border: 0; border-radius: 10px; background: #fff; }
  .reports-stat .card-body { padding: .95rem; }
  .reports-stat-label { color: #667085; font-size: .72rem; font-weight: 800; text-transform: uppercase; }
  .reports-stat-value { color: #1f2937; font-size: 1.35rem; font-weight: 850; }
  .reports-toolbar { display: flex; align-items: center; justify-content: flex-end; gap: .65rem; flex-wrap: wrap; }
  .reports-search { position: relative; min-width: min(320px, 100%); }
  .reports-search i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #98a2b3; font-size: .85rem; }
  .reports-search input { height: 38px; padding-left: 34px; border-radius: 4px; border-color: #eaecf0; font-size: .78rem; }
  .reports-panel { border: 0; border-radius: 10px; background: rgba(255,255,255,.78); box-shadow: none; overflow: hidden; }
  .reports-table { border-collapse: separate !important; border-spacing: 0 !important; font-size: .8rem; color: #1f2937; }
  .reports-table thead th { white-space: nowrap; font-size: .72rem; color: #5f6672; font-weight: 760; background: #f3f4f6 !important; border: 0 !important; padding: .72rem .8rem !important; }
  .reports-table tbody td { border: 0 !important; border-bottom: 1px solid #f2f4f7 !important; padding: .72rem .8rem !important; vertical-align: middle; white-space: nowrap; }
  .reports-table tbody tr:nth-child(even) td { background: rgba(249,250,251,.72); }
  .reports-table tbody tr:hover td { background: #f8fbff; }
  .reports-panel .row:first-child { display: none; }
  .reports-panel .row:last-child { margin: 0; padding: .9rem .25rem 0; align-items: center; color: #475467; font-size: .78rem; }
  .reports-panel .dataTables_paginate .pagination { gap: .35rem; margin: 0; justify-content: flex-end; }
  .reports-panel .page-link { min-width: 34px; height: 34px; border-radius: 4px !important; display: inline-flex; align-items: center; justify-content: center; border-color: #eaecf0; color: #344054; font-size: .78rem; box-shadow: none; }
  .reports-panel .page-item.active .page-link { background: #3657f6; border-color: #3657f6; color: #fff; }
  .field-list { display: grid; gap: .35rem; }
  .field-item { cursor: grab; border: 1px solid #e4e7ec; border-radius: 6px; background: #fff; padding: .55rem .65rem; display: flex; align-items: center; justify-content: space-between; gap: .7rem; }
  .field-item.dragging { opacity: .55; }
  @media (max-width: 991px) { .reports-stat-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .reports-tabs { grid-template-columns: repeat(auto-fit, minmax(128px, 1fr)); } }
  @media (max-width: 767px) { .reports-head { flex-direction: column; } .reports-stat-grid { grid-template-columns: 1fr; } .reports-search { width: 100%; } .reports-tabs { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
</style>

<div class="container-fluid py-3">
    <div class="reports-shell">
        <div class="reports-head">
            <div>
                <div class="reports-eyebrow">General Report</div>
                <h2 class="reports-title" id="reportTitle"><?= e($active['title']) ?></h2>
                <div class="text-muted small">Generate, filter, print, and export records per module.</div>
            </div>
            <div class="reports-actions">
                <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#reportExportModal">
                    <i class="bi bi-sliders2-vertical"></i> Filter & Export
                </button>
            </div>
        </div>

        <ul class="nav nav-tabs reports-tabs">
            <?php foreach ($modules as $key => $config): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $activeKey === $key ? 'active' : '' ?>" href="<?= BASE_URL ?>/ictd/reports?module=<?= e($key) ?>">
                        <i class="bi <?= e($config['icon']) ?> me-1"></i><?= e($config['label']) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>

        <div class="reports-stat-grid">
            <div class="card reports-stat"><div class="card-body"><div class="reports-stat-label">Total Records</div><div class="reports-stat-value"><?= number_format($totalRows) ?></div></div></div>
            <div class="card reports-stat"><div class="card-body"><div class="reports-stat-label">Visible Value</div><div class="reports-stat-value"><?= $totalPrice > 0 ? number_format($totalPrice, 2) : '-' ?></div></div></div>
            <div class="card reports-stat"><div class="card-body"><div class="reports-stat-label">With Status</div><div class="reports-stat-value"><?= number_format($withStatus) ?></div></div></div>
            <div class="card reports-stat"><div class="card-body"><div class="reports-stat-label">Last 30 Days</div><div class="reports-stat-value"><?= number_format($recentRows) ?></div></div></div>
        </div>

        <div class="reports-toolbar">
            <div class="reports-search">
                <i class="bi bi-search"></i>
                <input type="search" class="form-control" id="reportsSearch" placeholder="Search <?= e(strtolower($active['label'])) ?> reports" value="<?= e($filters['search']) ?>">
            </div>
        </div>

        <div class="card reports-panel">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="reportsTable" class="table align-middle nowrap mb-0 reports-table" style="width:100%">
                        <thead>
                            <tr>
                                <?php foreach ($labels as $label): ?><th><?= e($label) ?></th><?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <?php foreach (array_keys($labels) as $field): ?>
                                        <td><?= $field === 'price' ? number_format((float) ($row[$field] ?? 0), 2) : e($row[$field] ?? '') ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <?php if (array_key_exists('price', $labels)): ?>
                            <tfoot><tr><th colspan="<?= max(1, count($labels) - 4) ?>" class="text-end">Visible Total Price:</th><th id="visibleTotalPrice">0.00</th><th colspan="3"></th></tr></tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="reportExportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content border-0">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-spreadsheet"></i> Export & Filter Options</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="get" action="<?= BASE_URL ?>/ictd/reports" id="reportFilterForm" class="row g-3">
                    <input type="hidden" name="module" id="report_module" value="<?= e($activeKey) ?>">
                    <div class="col-md-6"><label class="form-label">From Date</label><input type="date" name="date_from" id="date_from" class="form-control" value="<?= e($filters['date_from']) ?>"></div>
                    <div class="col-md-6"><label class="form-label">To Date</label><input type="date" name="date_to" id="date_to" class="form-control" value="<?= e($filters['date_to']) ?>"></div>
                    <div class="col-md-6" id="descriptionFilterWrap">
                        <label class="form-label">Description</label>
                        <select class="form-select" name="description_name" id="description_name">
                            <option value="">All</option>
                            <?php foreach ($descriptions as $description): ?><option value="<?= e($description) ?>" <?= $filters['description_name'] === $description ? 'selected' : '' ?>><?= e($description) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6" id="statusFilterWrap">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status" id="status">
                            <option value="">All</option>
                            <?php foreach ($statusOptions as $statusOption): ?>
                                <option value="<?= e($statusOption) ?>" <?= $filters['status'] === $statusOption ? 'selected' : '' ?>><?= e($statusOption) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12"><label class="form-label">Search</label><input type="text" name="search" id="filter_search" class="form-control" value="<?= e($filters['search']) ?>"></div>
                    <div class="col-12"><label class="form-label">Custom Export Title</label><input type="text" id="export_title" class="form-control" placeholder="<?= e($active['title']) ?>"></div>
                </form>
                <hr>
                <label class="form-label d-block">Select and Reorder Export Fields</label>
                <div class="text-muted small mb-2">Drag fields to rearrange export columns. Checked fields will be included.</div>
                <div class="field-list" id="fieldList"></div>
            </div>
            <div class="modal-footer">
                <button type="submit" form="reportFilterForm" class="btn btn-outline-primary"><i class="bi bi-funnel"></i> Apply Filter</button>
                <?php if ($canReportPrint): ?><button type="button" class="btn btn-outline-secondary export-report-btn" data-format="print"><i class="bi bi-printer"></i> Print</button><?php endif; ?>
                <?php if ($canReportPrint): ?><button type="button" class="btn btn-outline-danger export-report-btn" data-format="pdf"><i class="bi bi-file-earmark-pdf"></i> PDF</button><?php endif; ?>
                <?php if ($canReportExport): ?><button type="button" class="btn btn-success export-report-btn" data-format="excel"><i class="bi bi-download"></i> Excel</button><?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
$(function () {
    const activeModule = <?= json_encode($activeKey) ?>;
    const reportModules = <?= json_encode($modulePayload) ?>;
    const labels = Object.keys(reportModules[activeModule].fields);
    const priceIndex = labels.indexOf('price');
    const dateIndex = labels.indexOf('date_recorded') >= 0 ? labels.indexOf('date_recorded') : Math.max(labels.length - 1, 0);

    const table = $('#reportsTable').DataTable({
        responsive: false,
        scrollX: true,
        pageLength: 10,
        order: [[dateIndex, 'desc']],
        dom: 'rt<"row align-items-center"<"col-md-4"i><"col-md-4"l><"col-md-4"p>>',
        language: {
            info: '_START_ - _END_ of _TOTAL_',
            lengthMenu: 'Rows per page _MENU_',
            paginate: { previous: '<i class="bi bi-chevron-left"></i>', next: '<i class="bi bi-chevron-right"></i>' },
            emptyTable: 'No records found for the selected filters.'
        },
        footerCallback: function () {
            if (priceIndex < 0) return;
            const total = this.api().column(priceIndex, { page: 'current' }).data().reduce((sum, value) => sum + (parseFloat(String(value).replace(/,/g, '')) || 0), 0);
            $('#visibleTotalPrice').text(total.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
        }
    });

    $('#reportsSearch').on('input', function () {
        table.search(this.value).draw();
        $('#filter_search').val(this.value);
    });

    function renderFieldList() {
        const fields = reportModules[activeModule].fields;
        $('#fieldList').html(Object.entries(fields).map(([key, label]) => `
            <div class="field-item" draggable="true" data-field="${key}">
                <label class="mb-0 fw-semibold"><input type="checkbox" class="form-check-input me-2 export-field" value="${key}" checked> ${label}</label>
                <i class="bi bi-grip-vertical text-muted"></i>
            </div>
        `).join(''));
        $('#descriptionFilterWrap').toggle(!!reportModules[activeModule].hasDescription);
        $('#statusFilterWrap').toggle(!!reportModules[activeModule].hasStatus);
    }
    renderFieldList();

    let dragged = null;
    $('#fieldList').on('dragstart', '.field-item', function () {
        dragged = this;
        this.classList.add('dragging');
    }).on('dragend', '.field-item', function () {
        this.classList.remove('dragging');
        dragged = null;
    }).on('dragover', '.field-item', function (event) {
        event.preventDefault();
        if (!dragged || dragged === this) return;
        const rect = this.getBoundingClientRect();
        this.parentNode.insertBefore(dragged, event.originalEvent.clientY > rect.top + rect.height / 2 ? this.nextSibling : this);
    });

    $('.export-report-btn').on('click', function () {
        const selected = $('#fieldList .export-field:checked').map((_, el) => el.value).get();
        if (!selected.length) {
            Swal.fire('Warning', 'Please select at least one field to export.', 'warning');
            return;
        }
        const order = $('#fieldList .field-item').map((_, el) => $(el).data('field')).get();
        const params = new URLSearchParams({
            module: activeModule,
            format: $(this).data('format'),
            fields: selected.join(','),
            order: order.join(','),
            title: $('#export_title').val() || reportModules[activeModule].title,
            search: $('#filter_search').val(),
            date_from: $('#date_from').val(),
            date_to: $('#date_to').val(),
            description_name: $('#description_name').val(),
            status: $('#status').val()
        });
        window.open('<?= BASE_URL ?>/ictd/reports-export?' + params.toString(), '_blank');
    });
});
</script>

<?php require_once ROOT_PATH . '/modules/layout/footer.php'; ?>
