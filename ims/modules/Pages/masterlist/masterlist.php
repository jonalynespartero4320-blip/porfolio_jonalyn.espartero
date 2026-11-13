<?php
$page_title = 'Masterlist';
require_once ROOT_PATH . '/modules/layout/header.php';
require_once ROOT_PATH . '/helpers/functions.php';

require_login();
require_permission('ictd.masterlist.view');

$masterlistActiveTab = $masterlistActiveTab ?? 'equipment-status';
$masterlistTabs = [
    ['slug' => 'equipment-status', 'title' => 'Equipment Status', 'icon' => 'bi-ui-checks-grid'],
    ['slug' => 'descriptions', 'title' => 'Descriptions', 'icon' => 'bi-card-list'],
    ['slug' => 'brands', 'title' => 'Brands', 'icon' => 'bi-tags-fill'],
    ['slug' => 'suppliers', 'title' => 'Suppliers', 'icon' => 'bi-truck'],
    ['slug' => 'departments', 'title' => 'Departments', 'icon' => 'bi-building'],
    ['slug' => 'end-users', 'title' => 'End Users', 'icon' => 'bi-person-vcard-fill'],
    ['slug' => 'positions', 'title' => 'Positions', 'icon' => 'bi-person-badge'],
    ['slug' => 'employee-status', 'title' => 'Employee Status', 'icon' => 'bi-person-check'],
    ['slug' => 'funds', 'title' => 'Funds', 'icon' => 'bi-cash-coin'],
    ['slug' => 'technical', 'title' => 'Technical', 'icon' => 'bi-tools'],
    ['slug' => 'complain', 'title' => 'Complain', 'icon' => 'bi-chat-square-text'],
    ['slug' => 'troubleshoot', 'title' => 'Troubleshoot', 'icon' => 'bi-wrench-adjustable'],
    ['slug' => 'service', 'title' => 'Service', 'icon' => 'bi-clipboard2-check'],
];
?>

<style>
  .masterlist-shell { display: grid; gap: 1rem; }
  .masterlist-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; }
  .masterlist-eyebrow { color: #667085; font-size: .78rem; font-weight: 800; text-transform: uppercase; letter-spacing: .08em; }
  .masterlist-title { margin: 0; color: #1f2937; font-size: 1.45rem; font-weight: 900; }
  .masterlist-subtitle { color: #667085; font-size: .86rem; }
  .masterlist-tabs-wrap { border-top: 1px solid #d8dde6; border-bottom: 1px solid #d8dde6; background: rgba(235,238,243,.78); overflow-x: auto; overflow-y: hidden; }
  .masterlist-tabs { display: flex; align-items: flex-end; gap: 0; min-width: max-content; padding: .45rem .75rem 0; }
  .masterlist-tab { border: 1px solid transparent; border-bottom: 0; background: transparent; border-radius: 8px 8px 0 0; padding: .72rem 1rem .68rem; text-align: center; color: #0d6efd; font-size: .78rem; line-height: 1; white-space: nowrap; transition: .15s ease; }
  .masterlist-tab:hover { background: rgba(255,255,255,.54); color: #0a58ca; }
  .masterlist-tab.active { background: #fff; border-color: #d8dde6; color: #1f2937; box-shadow: 0 -1px 0 #fff inset; }
  .masterlist-tab i { display: none; }
  .masterlist-tab span { display: block; font-weight: 500; }
  .masterlist-card { border: 0; border-radius: 20px; background: rgba(255,255,255,.92); box-shadow: 0 20px 48px rgba(15,23,42,.08); overflow: hidden; }
  .masterlist-card-head { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1rem; border-bottom: 1px solid #edf0f5; flex-wrap: wrap; }
  .masterlist-current { display: flex; align-items: center; gap: .75rem; }
  .masterlist-current i { width: 42px; height: 42px; border-radius: 14px; display: inline-flex; align-items: center; justify-content: center; background: #eaf8ef; color: #157347; font-size: 1.1rem; }
  .masterlist-current h3 { margin: 0; font-size: 1.05rem; font-weight: 900; color: #1f2937; }
  .masterlist-current p { margin: .1rem 0 0; color: #667085; font-size: .8rem; }
  .masterlist-toolbar { display: flex; gap: .55rem; flex-wrap: wrap; }
  .masterlist-search { width: min(360px, 100%); }
  .masterlist-search .input-group-text { background: #fff; color: #667085; border-color: #e4e7ec; }
  .masterlist-search .form-control { border-color: #e4e7ec; font-size: .82rem; }
  .masterlist-card .dataTables_wrapper { width: 100%; }
  .masterlist-table { margin: 0; font-size: .82rem; width: 100% !important; }
  .masterlist-table th { background: #f8fafc !important; border: 0 !important; color: #667085; font-size: .74rem; text-transform: uppercase; letter-spacing: .03em; white-space: nowrap; padding: .8rem !important; }
  .masterlist-table td { border: 0 !important; border-bottom: 1px solid #f2f4f7 !important; padding: .82rem !important; vertical-align: middle; white-space: nowrap; }
  .masterlist-table th:first-child, .masterlist-table td:first-child { width: 96px; min-width: 96px; }
  .masterlist-table th:nth-child(2), .masterlist-table td:nth-child(2) { width: 72px; min-width: 72px; }
  .masterlist-table th:nth-child(n+3), .masterlist-table td:nth-child(n+3) { min-width: 190px; }
  .masterlist-actions { display: inline-flex; gap: .55rem; align-items: center; }
  .masterlist-icon-btn { border: 0; background: transparent; padding: 0; line-height: 1; }
  .masterlist-empty { padding: 2.5rem 1rem; text-align: center; color: #667085; }
  @media (max-width: 767px) {
    .masterlist-head { flex-direction: column; }
    .masterlist-toolbar, .masterlist-search, .masterlist-toolbar .btn { width: 100%; }
    .masterlist-tabs { padding-left: .5rem; padding-right: .5rem; }
    .masterlist-tab { padding: .68rem .85rem .62rem; }
  }
</style>

<div class="container-fluid py-3">
    <div class="masterlist-shell">
        <div class="masterlist-head">
            <div>
                <div class="masterlist-eyebrow">Reference Data</div>
                <h2 class="masterlist-title">Masterlist Records</h2>
                <div class="masterlist-subtitle">Manage the shared lists used by equipment, supplies, assignment, service, maintenance, and transmittal modules.</div>
            </div>
        </div>

        <div class="masterlist-tabs-wrap">
            <div class="masterlist-tabs" id="masterlistTabs">
                <?php foreach ($masterlistTabs as $tab): ?>
                    <button type="button" class="masterlist-tab <?= $tab['slug'] === $masterlistActiveTab ? 'active' : '' ?>" data-tab="<?= e($tab['slug']) ?>">
                        <i class="bi <?= e($tab['icon']) ?>"></i>
                        <span><?= e($tab['title']) ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card masterlist-card">
            <div class="masterlist-card-head">
                <div class="masterlist-current">
                    <i class="bi bi-collection" id="masterlistCurrentIcon"></i>
                    <div>
                        <h3 id="masterlistCurrentTitle">Masterlist</h3>
                        <p id="masterlistCurrentSubtitle">Loading records...</p>
                    </div>
                </div>
                <div class="masterlist-toolbar">
                    <div class="masterlist-search">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="search" class="form-control" id="masterlistSearch" placeholder="Search current masterlist...">
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" id="masterlistAddBtn"><i class="bi bi-plus-lg"></i> Add Record</button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table masterlist-table align-middle" id="masterlistTable" style="width:100%">
                    <thead></thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="masterlistModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="masterlistForm">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="masterlistModalTitle">Add Record</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="masterlistRecordId" name="id">
                    <div class="row g-3" id="masterlistFields"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-save2"></i> Save Record</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const actionUrl = '<?= BASE_URL ?>/ictd/masterlist-action';
    const routeBase = '<?= BASE_URL ?>/ictd/masterlists';
    const tabs = <?= json_encode($masterlistTabs) ?>;
    let activeTab = '<?= e($masterlistActiveTab) ?>';
    let currentConfig = null;
    let masterlistDataTable = null;
    let searchTimer = null;

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
    }

    function modal() {
        const element = document.getElementById('masterlistModal');
        return bootstrap.Modal.getInstance(element) || new bootstrap.Modal(element);
    }

    function optionLabel(source, value) {
        const option = ((currentConfig.options || {})[source] || []).find(item => String(item.id) === String(value));
        return option ? option.text : (value || '');
    }

    function renderFields(row = {}) {
        $('#masterlistFields').html((currentConfig.fields || []).map(field => {
            const required = field.required ? 'required' : '';
            const value = row[field.name] ?? '';
            if (field.type === 'select') {
                const options = ((currentConfig.options || {})[field.source] || []).map(option => {
                    const selected = String(option.id) === String(value) ? 'selected' : '';
                    return `<option value="${escapeHtml(option.id)}" ${selected}>${escapeHtml(option.text)}</option>`;
                }).join('');
                return `
                    <div class="col-md-6">
                        <label class="form-label">${escapeHtml(field.label)}${field.required ? ' *' : ''}</label>
                        <select class="form-select masterlist-field" name="${escapeHtml(field.name)}" ${required}>
                            <option value="">Select ${escapeHtml(field.label)}</option>
                            ${options}
                        </select>
                    </div>
                `;
            }
            return `
                <div class="col-md-6">
                    <label class="form-label">${escapeHtml(field.label)}${field.required ? ' *' : ''}</label>
                    <input type="text" class="form-control masterlist-field" name="${escapeHtml(field.name)}" value="${escapeHtml(value)}" ${required}>
                </div>
            `;
        }).join(''));
    }

    function initMasterlistTable() {
        const pk = currentConfig.pk;
        $('#masterlistTable thead').html(`
            <tr>
                <th style="width:110px;">Action</th>
                <th style="width:80px;">ID</th>
                ${(currentConfig.fields || []).map(field => `<th>${escapeHtml(field.label)}</th>`).join('')}
            </tr>
        `);

        if (masterlistDataTable) {
            masterlistDataTable.destroy();
            masterlistDataTable = null;
        }
        $('#masterlistTable tbody').empty();

        const columns = [
            {
                data: null,
                orderable: false,
                searchable: false,
                render: () => `
                    <span class="masterlist-actions">
                        <button type="button" class="masterlist-icon-btn text-info edit-masterlist" title="Edit"><i class="bi bi-pencil-square"></i> Edit</button>
                        <button type="button" class="masterlist-icon-btn text-danger delete-masterlist" title="Delete"><i class="bi bi-trash3"></i> Delete</button>
                    </span>
                `
            },
            {
                data: pk,
                render: data => `<strong>${escapeHtml(data)}</strong>`
            },
            ...(currentConfig.fields || []).map(field => ({
                data: field.name,
                render: data => escapeHtml(field.type === 'select' ? optionLabel(field.source, data) : data)
            }))
        ];

        masterlistDataTable = $('#masterlistTable').DataTable({
            processing: true,
            serverSide: true,
            destroy: true,
            autoWidth: false,
            pageLength: 10,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            ajax: {
                url: actionUrl,
                type: 'POST',
                data: d => {
                    d.action = 'data';
                    d.tab = activeTab;
                }
            },
            columns,
            columnDefs: [
                { targets: 0, width: '110px' },
                { targets: 1, width: '90px' }
            ],
            order: [[1, 'asc']],
            scrollX: true,
            dom: 'rt<"row align-items-center mt-3 px-3 pb-3"<"col-md-4"i><"col-md-4 text-center"l><"col-md-4"p>>',
            createdRow: (row, data) => $(row).attr('data-id', data[pk]),
            language: {
                emptyTable: 'No records found.',
                processing: 'Loading records...'
            }
        });
    }

    function setActiveTab(tab) {
        activeTab = tab;
        $('#masterlistTabs .masterlist-tab').removeClass('active');
        $(`#masterlistTabs .masterlist-tab[data-tab="${tab}"]`).addClass('active');
        const tabMeta = tabs.find(item => item.slug === tab) || {};
        history.replaceState(null, '', `${routeBase}/${tab}`);
        $('#masterlistCurrentIcon').attr('class', `bi ${tabMeta.icon || 'bi-collection'}`);
    }

    function loadTab(tab) {
        setActiveTab(tab);
        $('#masterlistCurrentTitle').text('Loading...');
        $('#masterlistCurrentSubtitle').text('Please wait while records are prepared.');
        $('#masterlistTable thead').empty();
        $('#masterlistTable tbody').html('<tr><td class="masterlist-empty">Loading records...</td></tr>');
        if (masterlistDataTable) {
            masterlistDataTable.destroy();
            masterlistDataTable = null;
        }
        $.getJSON(actionUrl, { action: 'list', tab }, function (res) {
            if (!res.success) {
                Swal.fire('Error', res.message || 'Unable to load masterlist.', 'error');
                return;
            }
            currentConfig = res.config;
            $('#masterlistCurrentTitle').text(currentConfig.title);
            $('#masterlistCurrentSubtitle').text(currentConfig.subtitle);
            $('#masterlistSearch').val('');
            initMasterlistTable();
        });
    }

    $('#masterlistTabs').on('click', '.masterlist-tab', function () {
        loadTab($(this).data('tab'));
    });

    $('#masterlistSearch').on('input', function () {
        clearTimeout(searchTimer);
        const value = this.value;
        searchTimer = setTimeout(() => {
            if (masterlistDataTable) {
                masterlistDataTable.search(value).draw();
            }
        }, 250);
    });

    $('#masterlistAddBtn').on('click', function () {
        $('#masterlistRecordId').val('');
        $('#masterlistModalTitle').text('Add ' + currentConfig.title);
        renderFields();
        modal().show();
    });

    $('#masterlistTable').on('click', '.edit-masterlist', function () {
        const tr = $(this).closest('tr');
        const row = masterlistDataTable ? masterlistDataTable.row(tr).data() : null;
        if (!row) return;
        $('#masterlistRecordId').val(row[currentConfig.pk]);
        $('#masterlistModalTitle').text('Edit ' + currentConfig.title);
        renderFields(row);
        modal().show();
    });

    $('#masterlistTable').on('click', '.delete-masterlist', function () {
        const id = $(this).closest('tr').data('id');
        Swal.fire({
            title: 'Delete record?',
            text: 'If this record is already used by another module, the database may prevent deletion.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Delete'
        }).then(result => {
            if (!result.isConfirmed) return;
            $.post(actionUrl, { action: 'delete', tab: activeTab, id }, function (res) {
                Swal.fire(res.success ? 'Deleted' : 'Error', res.message, res.success ? 'success' : 'error');
                if (res.success && masterlistDataTable) masterlistDataTable.ajax.reload(null, false);
            }, 'json');
        });
    });

    $('#masterlistForm').on('submit', function (e) {
        e.preventDefault();
        const formData = $(this).serializeArray().reduce((carry, item) => {
            carry[item.name] = item.value;
            return carry;
        }, { action: 'save', tab: activeTab });
        $.post(actionUrl, formData, function (res) {
            if (res.success) {
                Swal.fire('Saved', res.message, 'success');
                modal().hide();
                if (masterlistDataTable) masterlistDataTable.ajax.reload(null, false);
            } else {
                Swal.fire('Error', res.message || 'Unable to save record.', 'error');
            }
        }, 'json');
    });

    loadTab(activeTab);
});
</script>

<?php require_once ROOT_PATH . '/modules/layout/footer.php'; ?>
