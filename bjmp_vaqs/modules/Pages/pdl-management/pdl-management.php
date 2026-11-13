<?php
require_once ROOT_PATH . '/modules/layout/header.php';
require_once ROOT_PATH . '/config/database.php';

require_permission('pdl.view');

$PHOTO_PATH    = ROOT_PATH . '/public/demo-uploads/pdl/';
$PHOTO_URL     = BASE_URL . '/demo-uploads/pdl/';
$DEFAULT_PHOTO = BASE_URL . '/assets/images/demo-scene.svg';

function getPDL($pdo, array $genders)
{
    $placeholders = implode(',', array_fill(0, count($genders), '?'));

    $sql = "
        SELECT
            p.*,
            d.dorm_no AS dorm_code,
            d.description AS dorm_description,
            COUNT(pav.id) AS visitor_count
        FROM pdl_info p
        LEFT JOIN pdl_authorized_visitors pav ON p.id = pav.pdl_id
        LEFT JOIN dorm_masterlist d
            ON TRIM(CAST(p.dorm_no AS CHAR)) = TRIM(CAST(d.id AS CHAR))
            OR TRIM(CAST(p.dorm_no AS CHAR)) = TRIM(d.dorm_no)
        WHERE LOWER(TRIM(p.pdl_sex)) IN ($placeholders)
        GROUP BY
            p.id,
            p.pdl_id,
            p.pdl_fname,
            p.pdl_mname,
            p.pdl_lname,
            p.pdl_suffix,
            p.dorm_no,
            p.pdl_age,
            p.pdl_sex,
            p.case_no,
            p.case_type,
            p.court,
            p.pdl_photo,
            d.dorm_no,
            d.description
        ORDER BY p.id DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($genders);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$male   = getPDL($pdo, ['m', 'male']);
$female = getPDL($pdo, ['f', 'female']);
$totalMale = count($male);
$totalFemale = count($female);
$totalPdl = $totalMale + $totalFemale;
$totalVisitors = array_sum(array_map(static fn($row) => (int) ($row['visitor_count'] ?? 0), array_merge($male, $female)));
$highWatchDorms = count(array_unique(array_filter(array_map(static fn($row) => trim((string) ($row['dorm_no'] ?? '')), array_merge($male, $female)))));

function renderTable($data, $PHOTO_PATH, $PHOTO_URL, $DEFAULT_PHOTO, string $tableId)
{
    ?>
    <div class="table-responsive">
        <table id="<?= htmlspecialchars($tableId) ?>" class="table table-hover align-middle w-100 modern-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Photo</th>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Dorm</th>
                    <th>Age</th>
                    <th>Case</th>
                    <th>Court</th>
                    <th>Visitors</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; ?>
                <?php foreach ($data as $row): ?>
                    <?php
                    $photoFile = trim((string) ($row['pdl_photo'] ?? ''));
                    $photo = (!empty($photoFile) && file_exists($PHOTO_PATH . $photoFile))
                        ? $PHOTO_URL . rawurlencode($photoFile)
                        : $DEFAULT_PHOTO;

                    $fullName = trim(
                        ($row['pdl_fname'] ?? '') . ' ' .
                        ($row['pdl_mname'] ?? '') . ' ' .
                        ($row['pdl_lname'] ?? '') . ' ' .
                        ($row['pdl_suffix'] ?? '')
                    );
                    $visitorCount = (int) ($row['visitor_count'] ?? 0);
                    $dormCode = trim((string) ($row['dorm_code'] ?? ''));
                    $dormDescription = trim((string) ($row['dorm_description'] ?? ''));
                    $dormDisplay = trim($dormCode . ($dormDescription !== '' ? ' - ' . $dormDescription : ''));
                    if ($dormDisplay === '') {
                        $dormDisplay = trim((string) ($row['dorm_no'] ?? ''));
                    }
                    ?>
                    <tr data-pdl-id="<?= (int) ($row['id'] ?? 0) ?>" data-visitors="<?= $visitorCount ?>">
                        <td><?= $i++; ?></td>
                        <td>
                            <img
                                src="<?= htmlspecialchars($photo) ?>"
                                class="rounded-circle shadow-sm pdl-avatar"
                                alt="Resident Photo"
                            >
                        </td>
                        <td>
                            <div class="fw-semibold"><?= htmlspecialchars($row['pdl_id'] ?? '') ?></div>
                            <div class="small text-muted"><?= htmlspecialchars($row['pdl_sex'] ?? '') ?></div>
                        </td>
                        <td>
                            <div class="fw-semibold"><?= htmlspecialchars(preg_replace('/\s+/', ' ', trim($fullName))) ?></div>
                            <div class="small text-muted"><?= htmlspecialchars($row['case_type'] ?? 'No case type') ?></div>
                        </td>
                        <td>
                            <div class="fw-semibold"><?= htmlspecialchars($dormDisplay) ?></div>
                        </td>
                        <td><?= htmlspecialchars($row['pdl_age'] ?? '') ?></td>
                        <td>
                            <div class="fw-semibold"><?= htmlspecialchars($row['case_no'] ?? '') ?></div>
                            <div class="small text-muted"><?= htmlspecialchars($row['case_type'] ?? '') ?></div>
                        </td>
                        <td><?= htmlspecialchars($row['court'] ?? '') ?></td>
                        <td>
                            <span class="badge bg-primary rounded-pill visitor-badge"><?= $visitorCount ?></span>
                        </td>
                        <td class="text-center">
                            <div class="d-flex justify-content-center gap-1 flex-wrap pdl-action-group">
                                <?php if (can('pdl.view')): ?>
                                    <button class="btn btn-info btn-sm viewVisitorsBtn" data-id="<?= (int) ($row['id'] ?? 0) ?>">
                                        <i class="bi bi-people me-1"></i> Visitors
                                    </button>
                                <?php endif; ?>

                                <?php if (can('pdl.import')): ?>
                                    <a href="<?= BASE_URL ?>/pdl-import?mode=visitors&pdl_id=<?= (int) ($row['id'] ?? 0) ?>" class="btn pdl-import-btn btn-sm">
                                        <i class="bi bi-upload me-1"></i> Import Visitors
                                    </a>
                                <?php endif; ?>

                                <?php if (can('pdl.edit')): ?>
                                    <a href="<?= BASE_URL ?>/pdl-form?id=<?= (int) ($row['id'] ?? 0) ?>" class="btn btn-success btn-sm">
                                        <i class="bi bi-pencil me-1"></i> Edit
                                    </a>
                                <?php endif; ?>

                                <?php if (can('pdl.delete')): ?>
                                    <button class="btn btn-danger btn-sm deleteBtn" data-id="<?= (int) ($row['id'] ?? 0) ?>">
                                        <i class="bi bi-trash me-1"></i> Delete
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>

            </tbody>
        </table>
    </div>
    <?php
}
?>

<style>
  .pdl-shell { display: grid; gap: .8rem; 
    /* background:
      linear-gradient(180deg, rgba(255, 255, 255, 0.86) 0%, rgba(248, 250, 252, 0.96) 100%); */
    border-radius: 28px;
    padding: 24px;}
  .pdl-hero {
    position: relative;
    overflow: hidden;
    border: 0;
    border-radius: 22px;
    background:
      linear-gradient(135deg, #062150, #447A61);
    box-shadow: 0 18px 42px rgba(15, 23, 42, 0.14);
  }
  .pdl-hero::after {
    content: "";
    position: absolute;
    right: -70px;
    bottom: -80px;
    width: 190px;
    height: 190px;
    border-radius: 50%;
    background: rgba(255,255,255,0.08);
  }
  .pdl-hero .card-body { position: relative; z-index: 1; padding: 1.05rem 1.25rem; }
  .pdl-hero h2 { font-size: clamp(1.45rem, 2vw, 2rem); }
  .pdl-hero p { font-size: .92rem; }
  .pdl-hero .btn { min-height: 38px; padding-inline: .95rem; }
  .pdl-stat-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .75rem; }
  .pdl-stat-card { border: 0; border-radius: 24px; box-shadow: 0 16px 38px rgba(15, 23, 42, 0.08); }
  .pdl-stat-card {
    border-radius: 20px;
    margin-bottom: 0;
  }
  .pdl-stat-card .card-body {
    padding: .9rem 1rem;
  }
  .pdl-stat-card .display-6 {
    font-size: 2rem;
    line-height: 1;
  }
  .pdl-directory-card {
    border: 0;
    border-radius: 22px;
    background: rgba(255,255,255,0.96);
    box-shadow: 0 14px 34px rgba(15, 23, 42, 0.09);
    overflow: hidden;
  }
  .pdl-directory-card > .card-header {
    background:
      linear-gradient(135deg, rgba(255,255,255,0.98), rgba(241,247,244,0.98));
    border-bottom: 1px solid rgba(8,19,31,0.08);
    padding: .7rem .85rem 0;
  }
  .pdl-directory-card > .card-body {
    padding: .75rem .85rem .85rem;
  }
  .pdl-directory-card .pdl-toolbar {
    border-bottom: 1px solid rgba(8,19,31,0.08);
    background: rgba(248,250,252,0.72);
    padding: .75rem .85rem;
  }
  .pdl-directory-card .pdl-toolbar .row {
    --bs-gutter-y: .5rem;
  }
  .pdl-directory-card h4 {
    font-size: 1.05rem;
  }
  .pdl-table-wrap {
    background: transparent;
  }
  .pdl-table-title {
    padding: .45rem .15rem .6rem;
  }
  .pdl-table-title h5 {
    font-size: 1.05rem;
  }
  .pdl-table-wrap .table-responsive {
    /* border: 1px solid rgba(8,19,31,0.08); */
    border-radius: 16px;
    background: #fff;
    padding: .35rem;
  }
  .pdl-table-wrap .dataTables_wrapper .top,
  .pdl-table-wrap .dataTables_wrapper .bottom {
    margin-bottom: .4rem !important;
    margin-top: .55rem !important;
  }
  .pdl-table-wrap table.dataTable {
    margin-top: .35rem !important;
    margin-bottom: .35rem !important;
  }
  .pdl-table-wrap .form-select,
  .pdl-table-wrap .form-control {
    min-height: 42px;
  }
  .pdl-stat-icon {
    width: 42px; height: 42px; border-radius: 14px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.1rem;
  }
 .form-control { border-radius: 14px; }
  .pdl-avatar { width: 45px; height: 45px; object-fit: cover; }
  .visitor-badge { font-size: .8rem; padding: .45rem .7rem; }
  .pdl-action-group .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    white-space: nowrap;
  }
  .pdl-import-btn {
    background: #d4a93a;
    border-color: #d4a93a;
    color: #fff;
  }
  .pdl-import-btn:hover {
    background: #bf972f;
    border-color: #bf972f;
    color: #fff;
  }
  .pdl-tab-nav {
    gap: .45rem;
    border-bottom: 0;
    margin-top: .45rem;
  }
  .pdl-tab-nav .nav-item { margin-bottom: -1px; }
  .pdl-tab-nav .nav-link {
    position: relative;
    border: 1px solid rgba(8,19,31,0.08);
    border-bottom: 0;
    border-radius: 14px 14px 0 0;
    padding: .55rem .8rem .68rem;
    background: linear-gradient(180deg, #f8fafc, #edf3f1);
    color: #334155;
    font-weight: 800;
    min-width: 110px;
    justify-content: center;
    box-shadow: inset 0 -8px 14px rgba(15,23,42,0.04);
  }
  .pdl-tab-nav .nav-link::after {
    content: "";
    position: absolute;
    left: 16px;
    right: 16px;
    bottom: 5px;
    height: 3px;
    border-radius: 999px;
    background: transparent;
  }
  .pdl-tab-nav .nav-link.active {
    background: #fff;
    color: #447A61;
    border-color: rgba(15,91,52,0.18);
    box-shadow: 0 -8px 22px rgba(15,23,42,0.08);
  }
  .pdl-tab-nav .nav-link.active::after {
    background: linear-gradient(90deg, #062150, #447A61);
  }
  @media (max-width: 1199px) {
    .pdl-stat-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  }
  @media (max-width: 767px) {
    .pdl-stat-grid { grid-template-columns: 1fr; }
  }
</style>

<div class="container-fluid py-3">
    <div class="pdl-shell">
        <div class="card pdl-hero text-white">
            <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <div class="text-uppercase small fw-semibold opacity-75 mb-2">Custodial Records</div>
                    <h2 class="fw-bold mb-1"><i class="bi bi-person-badge me-2"></i>Resident Management</h2>
                    <p class="mb-0 opacity-75">Manage person deprived of liberty profiles, dorm assignments, case references, and linked authorized visitor coverage from one operations workspace.</p>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <?php if (can('pdl.import')): ?>
                        <a href="<?= BASE_URL ?>/pdl-import?mode=pdl" class="btn pdl-import-btn btn-sm">
                            <i class="bi bi-upload me-1"></i> Import Resident
                        </a>
                    <?php endif; ?>
                    <?php if (can('pdl.create')): ?>
                        <a href="<?= BASE_URL ?>/pdl-form" class="btn btn-light btn-sm">
                            <i class="bi bi-plus-circle me-1"></i> Add New
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="pdl-stat-grid">
            <div class="card pdl-stat-card"><div class="card-body"><div class="d-flex justify-content-between align-items-start"><div><div class="text-muted small">Total Resident</div><div class="display-6 fw-bold mb-0" id="statTotalPdl"><?= $totalPdl ?></div></div><div class="pdl-stat-icon bg-primary-subtle text-primary"><i class="bi bi-people-fill"></i></div></div></div></div>
            <div class="card pdl-stat-card"><div class="card-body"><div class="d-flex justify-content-between align-items-start"><div><div class="text-muted small">Male Records</div><div class="display-6 fw-bold mb-0" id="statMalePdl"><?= $totalMale ?></div></div><div class="pdl-stat-icon bg-info-subtle text-info"><i class="bi bi-gender-male"></i></div></div></div></div>
            <div class="card pdl-stat-card"><div class="card-body"><div class="d-flex justify-content-between align-items-start"><div><div class="text-muted small">Female Records</div><div class="display-6 fw-bold mb-0" id="statFemalePdl"><?= $totalFemale ?></div></div><div class="pdl-stat-icon bg-danger-subtle text-danger"><i class="bi bi-gender-female"></i></div></div></div></div>
            <div class="card pdl-stat-card"><div class="card-body"><div class="d-flex justify-content-between align-items-start"><div><div class="text-muted small">Authorized Visitors</div><div class="display-6 fw-bold mb-0" id="statVisitors"><?= $totalVisitors ?></div></div><div class="pdl-stat-icon bg-success-subtle text-success"><i class="bi bi-person-vcard"></i></div></div></div></div>
        </div>

       

    <div class="card pdl-directory-card">
        <div class="pdl-toolbar">
            <div class="row g-3 align-items-end">
                <div class="col-lg-8">
                    <label class="form-label fw-semibold mb-1" for="pdlQuickSearch">Quick Search</label>
                    <input type="text" id="pdlQuickSearch" class="form-control" placeholder="Search by Resident ID, name, dorm, case, or court">
                </div>
                <div class="col-lg-4">
                    <div class="small text-muted">Active Dorm Coverage</div>
                    <div class="fw-semibold fs-5"><?= $highWatchDorms ?></div>
                </div>
            </div>
        </div>
        <!-- <div class="card-header">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div>
                    <div class="text-uppercase small fw-semibold text-muted mb-1">Resident Directory</div>
                    <h4 class="fw-bold mb-1">Custodial Record List</h4>
                        <span class="badge text-bg-light text-dark"><?= $totalPdl ?> total record(s)</span>

                    <p class="text-muted mb-0">Browse Resident records by category using the bookmark tabs below.</p>
                </div>
                <span class="badge text-bg-light text-dark"><?= $totalPdl ?> total record(s)</span>
            </div>
        </div> -->
        <div class="card-body">
            <ul class="nav nav-tabs pdl-tab-nav">
                <li class="nav-item">
                    <!-- <button class="nav-link active d-inline-flex align-items-center gap-2" data-bs-toggle="tab" data-bs-target="#maleTab" type="button">
                        <i class="bi bi-bookmark-fill text-primary"></i> Male
                    </button> -->
                    <button class="nav-link active d-inline-flex align-items-center gap-2" data-bs-toggle="tab" data-bs-target="#maleTab" type="button">
                       Male
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link d-inline-flex align-items-center gap-2" data-bs-toggle="tab" data-bs-target="#femaleTab" type="button">
                        Female
                    </button>
                </li>
            </ul>
            <div class="tab-content">
                <div class="tab-pane fade show active" id="maleTab">
                    <div class="pdl-table-wrap">
                <div class="pdl-table-title d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h5 class="fw-bold mb-1"><i class="bi bi-gender-male me-2 text-primary"></i>Male Resident Records</h5>
                        <small class="text-muted">Custodial records, case references, dorm assignment, and visitor access.</small>
                    </div>
                    <span class="badge bg-primary rounded-pill"><?= $totalMale ?> record(s)</span>
                </div>
                <div class="pdl-table-area">
                    <?php renderTable($male, $PHOTO_PATH, $PHOTO_URL, $DEFAULT_PHOTO, 'pdlMaleTable'); ?>
                </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="femaleTab">
                    <div class="pdl-table-wrap">
                <div class="pdl-table-title d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h5 class="fw-bold mb-1"><i class="bi bi-gender-female me-2 text-danger"></i>Female Resident Records</h5>
                        <small class="text-muted">Custodial records, case references, dorm assignment, and visitor access.</small>
                    </div>
                    <span class="badge bg-danger rounded-pill"><?= $totalFemale ?> record(s)</span>
                </div>
                <div class="pdl-table-area">
                    <?php renderTable($female, $PHOTO_PATH, $PHOTO_URL, $DEFAULT_PHOTO, 'pdlFemaleTable'); ?>
                </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<div class="modal fade" id="visitorsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-0">Authorized Visitors</h5>
                    <small class="text-muted" id="visitorsModalSubtitle">Loading...</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="visitorsModalContent" class="text-muted">Loading authorized visitors...</div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    const tables = $('.modern-table').DataTable({
        responsive: true,
        pageLength: 10,
        lengthMenu: [5, 10, 25, 50],
        language: {
            search: '',
            searchPlaceholder: 'Search records...',
            emptyTable: 'No records found.'
        },
        dom: '<"top d-flex justify-content-between flex-wrap mb-2"lf>rt<"bottom d-flex justify-content-between mt-3"ip><"clear">',
        order: [[0, 'asc']]
    });

    $('#pdlQuickSearch').on('input', function () {
        tables.search(this.value).draw();
    });

    $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function () {
        tables.columns.adjust().responsive.recalc().draw(false);
    });

    const visitorsModalEl = document.getElementById('visitorsModal');
    const visitorsModal = visitorsModalEl ? new bootstrap.Modal(visitorsModalEl) : null;

    $(document).on('click', '.viewVisitorsBtn', function () {
        const id = $(this).data('id');
        if (!id || !visitorsModal) return;

        $('#visitorsModalSubtitle').text('Loading...');
        $('#visitorsModalContent').html('<div class="text-center py-4 text-muted">Loading authorized visitors...</div>');
        visitorsModal.show();

        $.ajax({
            url: APP_BASE_URL + '/pdl-action',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'get_visitors',
                id: id
            },
            success: function (res) {
                if (res.status !== 'success') {
                    $('#visitorsModalSubtitle').text('Unable to load visitors');
                    $('#visitorsModalContent').html('<div class="alert alert-danger mb-0">' + (res.message || 'Unable to load authorized visitors.') + '</div>');
                    return;
                }

                const pdl = res.pdl || {};
                const visitors = Array.isArray(res.visitors) ? res.visitors : [];
                const fullName = [pdl.pdl_fname, pdl.pdl_mname, pdl.pdl_lname, pdl.pdl_suffix].filter(Boolean).join(' ');

                $('#visitorsModalSubtitle').text((pdl.pdl_id || '') + (fullName ? ' - ' + fullName : ''));

                if (!visitors.length) {
                    $('#visitorsModalContent').html('<div class="text-center text-muted py-4">No authorized visitors found for this Resident.</div>');
                    return;
                }

                const activeVisitors = visitors.filter((visitor) => String(visitor.visitor_status || '').toLowerCase() !== 'inactive').length;
                const rows = visitors.map((visitor, index) => {
                    const visitorName = [
                        visitor.visitor_fname,
                        visitor.visitor_mname,
                        visitor.visitor_lname,
                        visitor.visitor_suffix
                    ].filter(Boolean).join(' ');
                    const age = visitor.visitor_age || '-';
                    const sex = visitor.visitor_sex || '-';
                    const relationship = visitor.visitor_relationship || '-';
                    const address = visitor.visitor_address || '-';
                    const contact = visitor.visitor_contact || '-';
                    const status = visitor.visitor_status || 'active';

                    return `
                        <tr>
                            <td>${index + 1}</td>
                            <td>${$('<div>').text(visitorName || '-').html()}</td>
                            <td>${$('<div>').text(age).html()}</td>
                            <td>${$('<div>').text(sex).html()}</td>
                            <td>${$('<div>').text(relationship).html()}</td>
                            <td>${$('<div>').text(address).html()}</td>
                            <td>${$('<div>').text(contact).html()}</td>
                            <td><span class="badge bg-${status === 'inactive' ? 'secondary' : 'success'}">${$('<div>').text(status).html()}</span></td>
                        </tr>
                    `;
                }).join('');

                $('#visitorsModalContent').html(`
                    <div class="d-flex gap-2 flex-wrap mb-3">
                        <span class="badge bg-primary rounded-pill">${visitors.length} total visitor(s)</span>
                        <span class="badge bg-success rounded-pill">${activeVisitors} active</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Name</th>
                                    <th>Age</th>
                                    <th>Sex</th>
                                    <th>Relationship</th>
                                    <th>Address</th>
                                    <th>Contact No.</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>${rows}</tbody>
                        </table>
                    </div>
                `);
            },
            error: function () {
                $('#visitorsModalSubtitle').text('Unable to load visitors');
                $('#visitorsModalContent').html('<div class="alert alert-danger mb-0">An unexpected server error occurred while loading authorized visitors.</div>');
            }
        });
    });

    $(document).on('click', '.deleteBtn', function () {
        const id = $(this).data('id');

        Swal.fire({
            title: 'Delete Resident Record?',
            text: 'This will permanently remove the Resident record and its authorized visitors.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, Delete',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (!result.isConfirmed) return;

            $.ajax({
                url: APP_BASE_URL + '/pdl-action',
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'delete',
                    id: id
                },
                success: function (res) {
                    if (res.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Deleted',
                            text: res.message || 'Resident record deleted successfully.',
                            confirmButtonColor: '#198754',
                            timer: 1800,
                            timerProgressBar: true
                        }).then(() => {
                            window.location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Failed',
                            text: res.message || 'Unable to delete the record.',
                            confirmButtonColor: '#198754'
                        });
                    }
                },
                error: function () {
                    Swal.fire({
                        icon: 'error',
                        title: 'Server Error',
                        text: 'An unexpected server error occurred. Please try again.',
                        confirmButtonColor: '#198754'
                    });
                }
            });
        });
    });
});
</script>

<?php require_once ROOT_PATH . '/modules/layout/footer.php'; ?>
