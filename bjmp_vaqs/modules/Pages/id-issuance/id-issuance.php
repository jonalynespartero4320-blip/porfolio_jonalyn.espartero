<?php
$page_title = 'ID Issuance';
require_once ROOT_PATH . '/modules/layout/header.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/modules/Pages/id-issuance/id-issuance-common.php';

$canIssueIds = can('id-issuance.create');
$canViewIssuedCards = can('id-issuance.view');

issuance_ensure_history_table($pdo);

$pdlStmt = $pdo->query("
    SELECT
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
        SUM(CASE WHEN vr.visitor_status = 'Verified' THEN 1 ELSE 0 END) AS verified_visitors
    FROM pdl_info p
    LEFT JOIN visitors_registration vr ON vr.pdl_id = p.id
    GROUP BY
        p.id, p.pdl_id, p.pdl_fname, p.pdl_mname, p.pdl_lname, p.pdl_suffix,
        p.dorm_no, p.pdl_age, p.pdl_sex, p.case_no, p.case_type, p.court
    ORDER BY p.id DESC
");
$pdls = $pdlStmt->fetchAll(PDO::FETCH_ASSOC);
$malePdls = array_values(array_filter($pdls, static fn($row) => strtolower(trim((string) ($row['pdl_sex'] ?? ''))) === 'male' || strtolower(trim((string) ($row['pdl_sex'] ?? ''))) === 'm'));
$femalePdls = array_values(array_filter($pdls, static fn($row) => strtolower(trim((string) ($row['pdl_sex'] ?? ''))) === 'female' || strtolower(trim((string) ($row['pdl_sex'] ?? ''))) === 'f'));

function renderIssuancePdlTable(array $rows, string $tableId): void
{
    ?>
    <div class="table-responsive">
        <table id="<?= htmlspecialchars($tableId) ?>" class="table table-hover align-middle w-100 issuance-pdl-table">
            <thead class="table-light">
                <tr>
                    <th>Resident ID</th>
                    <th>Name</th>
                    <th>Dorm</th>
                    <th>Age</th>
                    <th>Sex</th>
                    <th>Case No.</th>
                    <th>Case Type</th>
                    <th>Court</th>
                    <th>Verified Visitors</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr class="pdl-row" data-id="<?= (int) $row['id'] ?>" style="cursor:pointer;">
                        <td><?= e($row['pdl_id'] ?? '') ?></td>
                        <td class="fw-semibold"><?= e(issuance_full_name([
                            'fname' => $row['pdl_fname'] ?? '',
                            'mname' => $row['pdl_mname'] ?? '',
                            'lname' => $row['pdl_lname'] ?? '',
                            'suffix' => $row['pdl_suffix'] ?? '',
                        ])) ?></td>
                        <td><?= e($row['dorm_no'] ?? '') ?></td>
                        <td><?= e($row['pdl_age'] ?? '') ?></td>
                        <td><?= e($row['pdl_sex'] ?? '') ?></td>
                        <td><?= e($row['case_no'] ?? '') ?></td>
                        <td><?= e($row['case_type'] ?? '') ?></td>
                        <td><?= e($row['court'] ?? '') ?></td>
                        <td><span class="badge bg-success rounded-pill"><?= (int) ($row['verified_visitors'] ?? 0) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}
?>

<style>
  .issuance-shell {
    /* background:
      linear-gradient(180deg, rgba(255, 255, 255, 0.92) 0%, rgba(248, 250, 252, 0.98) 100%); */
    border-radius: 28px;
    padding: 24px;
  }
  .issuance-hero {
    border: 0;
    border-radius: 26px;
     background:
      linear-gradient(135deg, #062150, #447A61);
    box-shadow: 0 24px 60px rgba(15, 23, 42, 0.16);
    color: #fff;
    overflow: hidden;
    position: relative;
  }
  .issuance-hero::after {
    content: "";
    position: absolute;
    width: 220px;
    height: 220px;
    right: -70px;
    bottom: -80px;
    border-radius: 50%;
    background: rgba(255,255,255,0.10);
  }
  .issuance-card {
    border: 0;
    border-radius: 24px;
    box-shadow: 0 20px 44px rgba(15, 23, 42, 0.08);
  }
  .issuance-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    border-radius: 999px;
    background: rgba(255,255,255,0.12);
    border: 1px solid rgba(255,255,255,0.16);
    font-weight: 700;
  }
  .doc-badge {
    min-width: 32px;
    display: inline-flex;
    justify-content: center;
    align-items: center;
    padding: 4px 8px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 800;
  }
  .doc-ok {
    background: #dcfce7;
    color: #062150;
  }
  .doc-missing {
    background: #fee2e2;
    color: #991b1b;
  }
</style>

<div class="container-fluid py-3">
    <div class="issuance-shell">
        <div class="card issuance-hero shadow-sm mb-4">
            <div class="card-body p-4 p-lg-5">
                <div class="row g-4 align-items-center">
                    <div class="col-lg-8">
                        <div class="d-flex gap-2 flex-wrap mb-3">
                            <span class="issuance-pill"><i class="bi bi-person-vcard-fill"></i> ID Issuance</span>
                            <span class="issuance-pill"><i class="bi bi-printer-fill"></i> PVC + A4 Ready</span>
                        </div>
                        <h2 class="fw-bold mb-2">ID Issuance</h2>
                        <p class="mb-0" style="max-width:760px; color:rgba(255,255,255,0.88);">
                            Select a Resident, review verified authorized visitors, issue or re-issue IDs, inspect issuance history, and open a print-ready PVC layout from one integrated workspace.
                        </p>
                    </div>
                    <div class="col-lg-4">
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="rounded-4 p-3" style="background:rgba(255,255,255,0.10); border:1px solid rgba(255,255,255,0.14);">
                                    <div class="small text-white-50 mb-1">Resident Records</div>
                                    <div class="fs-2 fw-bold"><?= count($pdls) ?></div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="rounded-4 p-3" style="background:rgba(255,255,255,0.10); border:1px solid rgba(255,255,255,0.14);">
                                    <div class="small text-white-50 mb-1">Today</div>
                                    <div class="fs-5 fw-bold"><?= date('M d, Y') ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card issuance-card mb-4">
            <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="fw-bold mb-1"><i class="bi bi-people me-2 text-success"></i>Select Resident</h5>
                    <small class="text-muted">Choose a Resident to load verified authorized visitors ready for ID issuance.</small>
                </div>
            </div>
            <div class="card-body p-4">
                <ul class="nav nav-tabs mb-3" id="issuancePdlTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active fw-semibold" data-bs-toggle="tab" data-bs-target="#malePdlPane" type="button">
                             Male Resident
                            <span class="badge bg-primary ms-1"><?= count($malePdls) ?></span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-semibold" data-bs-toggle="tab" data-bs-target="#femalePdlPane" type="button">
                             Female Resident
                            <span class="badge bg-danger ms-1"><?= count($femalePdls) ?></span>
                        </button>
                    </li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="malePdlPane">
                        <?php renderIssuancePdlTable($malePdls, 'malePdlTable'); ?>
                    </div>
                    <div class="tab-pane fade" id="femalePdlPane">
                        <?php renderIssuancePdlTable($femalePdls, 'femalePdlTable'); ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card issuance-card" id="visitorArea" style="display:none;">
            <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="fw-bold mb-1">
                        <i class="bi bi-person-check me-2 text-primary"></i>
                        Verified Visitors
                        <span class="badge bg-dark ms-2" id="visitorCount">0</span>
                    </h5>
                    <small class="text-muted" id="visitorAreaHint">Select a Resident above to view visitors.</small>
                </div>
            </div>
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table id="visitorTable" class="table table-hover align-middle w-100">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Age</th>
                                <th>Sex</th>
                                <th>Address</th>
                                <th>Contact</th>
                                <th>Birth</th>
                                <th>Marriage</th>
                                <th>Cohab</th>
                                <th>Status</th>
                                <th>Submitted</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="visitorBody">
                            <tr>
                                <td colspan="11" class="text-center text-muted py-4">Select a Resident above...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="historyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-clock-history me-2"></i>ID Issuance History</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="historyBody">
                <div class="text-center p-4"><div class="spinner-border text-success"></div></div>
            </div>
        </div>
    </div>
</div>

<script>
const ID_ISSUANCE_BOOTSTRAP = {
    actionUrl: <?= json_encode(BASE_URL . '/id-issuance-action') ?>
};
</script>

<script>
$(function () {
    const actionUrl = ID_ISSUANCE_BOOTSTRAP.actionUrl;
    const pdlTables = $('.issuance-pdl-table').DataTable({
        responsive: true,
        pageLength: 10,
        order: []
    });
    document.querySelectorAll('#issuancePdlTabs [data-bs-toggle="tab"]').forEach((tab) => {
        tab.addEventListener('shown.bs.tab', function () {
            pdlTables.columns.adjust().responsive.recalc();
        });
    });

    let visitorDT = null;

    function showBusyRows() {
        $('#visitorBody').html(`
            <tr>
                <td colspan="11" class="text-center py-4">
                    <div class="spinner-border text-success"></div>
                </td>
            </tr>
        `);
    }

    function docBadge(flag) {
        return flag
            ? '<span class="doc-badge doc-ok">OK</span>'
            : '<span class="doc-badge doc-missing">NO</span>';
    }

    function actionButtons(row) {
        const issueDate = row.last_issued_at || '';
        const hasIssuedId = row.has_issued_id === true || row.has_issued_id === 1 || row.has_issued_id === '1';
        const historyBtn = `<button type="button" class="btn btn-outline-secondary btn-sm history-btn" data-id="${row.id}"><i class="bi bi-clock-history"></i></button>`;

        if (!hasIssuedId) {
            if (!<?= $canIssueIds ? 'true' : 'false' ?>) {
                return `<div class="d-flex gap-1 justify-content-center flex-wrap">${historyBtn}</div>`;
            }
            return `
                <div class="d-flex gap-1 justify-content-center flex-wrap">
                    <button type="button" class="btn btn-success btn-sm issue-btn" data-id="${row.id}">
                        <i class="bi bi-person-vcard"></i> Issue ID
                    </button>
                    ${historyBtn}
                </div>
            `;
        }

        const openBtn = <?= $canViewIssuedCards ? 'true' : 'false' ?>
            ? `<a href="<?= BASE_URL ?>/id-card?visitor_id=${row.id}" class="btn btn-primary btn-sm">
                    <i class="bi bi-printer"></i> Open
               </a>`
            : '';
        const reissueBtn = <?= $canIssueIds ? 'true' : 'false' ?>
            ? `<button type="button" class="btn btn-warning btn-sm reissue-btn" data-id="${row.id}" data-date="${issueDate}">
                    <i class="bi bi-arrow-repeat"></i> Re-Issue
               </button>`
            : '';

        return `
            <div class="d-flex gap-1 justify-content-center flex-wrap">
                ${openBtn}
                ${reissueBtn}
                ${historyBtn}
            </div>
        `;
    }

    function renderVisitors(rows) {
        if (visitorDT) {
            visitorDT.destroy();
        }

        if (!rows.length) {
            $('#visitorBody').html('<tr><td colspan="11" class="text-center text-muted py-4">No verified visitors found for this Resident.</td></tr>');
            $('#visitorCount').text('0');
            return;
        }

        const html = rows.map((row) => `
            <tr>
                <td>
                    <div class="fw-semibold">${$('<div>').text(row.visitor_name || '').html()}</div>
                    <small class="text-muted">${$('<div>').text(row.relationship || '').html()}</small>
                </td>
                <td>${$('<div>').text(row.age || '').html()}</td>
                <td>${$('<div>').text(row.sex || '').html()}</td>
                <td>${$('<div>').text(row.address || '').html()}</td>
                <td>${$('<div>').text(row.contact || '').html()}</td>
                <td>${docBadge(row.birth_certificate)}</td>
                <td>${docBadge(row.marriage_contract)}</td>
                <td>${docBadge(row.cohabitation_certificate)}</td>
                <td>
                    <span class="badge ${parseInt(row.is_banned || 0, 10) === 1 ? 'bg-danger' : 'bg-success'}">
                        ${$('<div>').text(parseInt(row.is_banned || 0, 10) === 1 ? 'Banned' : (row.status || 'Verified')).html()}
                    </span>
                </td>
                <td>${$('<div>').text(row.submitted_at || '').html()}</td>
                <td class="text-center">${actionButtons(row)}</td>
            </tr>
        `).join('');

        $('#visitorBody').html(html);
        $('#visitorCount').text(rows.length);

        visitorDT = $('#visitorTable').DataTable({
            responsive: true,
            destroy: true,
            pageLength: 10,
            order: []
        });
    }

    $(document).on('click', '.issuance-pdl-table tbody tr.pdl-row', function () {
        const pdlId = $(this).data('id');
        if (!pdlId) return;

        $('.issuance-pdl-table tbody tr').removeClass('table-active');
        $(this).addClass('table-active');

        $('#visitorArea').show();
        $('#visitorAreaHint').text('Loading verified visitors...');
        $('#visitorCount').text('...');
        showBusyRows();

        $.post(actionUrl, { action: 'load_visitors', pdl_id: pdlId }, function (res) {
            if (!res.ok) {
                $('#visitorBody').html('<tr><td colspan="11" class="text-center text-danger py-4">' + (res.message || 'Failed to load visitors') + '</td></tr>');
                $('#visitorCount').text('0');
                return;
            }

            $('#visitorAreaHint').text('Verified visitor records linked to the selected Resident.');
            renderVisitors(Array.isArray(res.rows) ? res.rows : []);
        }, 'json').fail(function () {
            $('#visitorBody').html('<tr><td colspan="11" class="text-center text-danger py-4">Failed to load visitors</td></tr>');
            $('#visitorCount').text('0');
        });
    });

    $(document).on('click', '.issue-btn', function () {
        const visitorId = $(this).data('id');
        Swal.fire({
            title: 'Issue an ID to this visitor?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, Issue the ID'
        }).then((result) => {
            if (!result.isConfirmed) return;

            Swal.fire({
                title: 'Preparing ID...',
                html: 'Please wait...',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => Swal.showLoading()
            });

            $.post(actionUrl, { action: 'issue', visitor_id: visitorId, mode: 'issue' }, function (res) {
                if (!res.ok) {
                    Swal.fire('Error', res.message || 'Issuance failed.', 'error');
                    return;
                }
                setTimeout(() => { window.location.href = res.redirect_url; }, 900);
            }, 'json').fail(function () {
                Swal.fire('Error', 'Server error occurred.', 'error');
            });
        });
    });

    $(document).on('click', '.reissue-btn', function () {
        const visitorId = $(this).data('id');
        const lastDate = $(this).data('date') || 'Unknown date';

        Swal.fire({
            title: 'Re-Issue Visitor ID?',
            html: `The last issuance was recorded on <b>${$('<div>').text(lastDate).html()}</b>.<br>Continue re-issuing this ID?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Continue Re-Issue',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (!result.isConfirmed) return;

            Swal.fire({
                title: 'Preparing Re-Issue...',
                html: 'Please wait...',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => Swal.showLoading()
            });

            $.post(actionUrl, { action: 'issue', visitor_id: visitorId, mode: 'reissue' }, function (res) {
                if (!res.ok) {
                    Swal.fire('Error', res.message || 'Re-issuance failed.', 'error');
                    return;
                }
                setTimeout(() => { window.location.href = res.redirect_url; }, 900);
            }, 'json').fail(function () {
                Swal.fire('Error', 'Server error occurred.', 'error');
            });
        });
    });

    $(document).on('click', '.history-btn', function () {
        const visitorId = $(this).data('id');
        const historyModal = new bootstrap.Modal(document.getElementById('historyModal'));
        historyModal.show();

        $('#historyBody').html('<div class="text-center p-4"><div class="spinner-border text-success"></div></div>');

        $.post(actionUrl, { action: 'history', visitor_id: visitorId }, function (res) {
            if (!res.ok) {
                $('#historyBody').html('<div class="text-danger text-center p-3">Failed to load history</div>');
                return;
            }

            const rows = Array.isArray(res.rows) ? res.rows : [];
            if (!rows.length) {
                $('#historyBody').html('<div class="text-center text-muted p-4">No issuance history found.</div>');
                return;
            }

            const tableHtml = `
                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Mode</th>
                                <th>V-ID</th>
                                <th>Issued By</th>
                                <th>Issued At</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rows.map((row, index) => `
                                <tr>
                                    <td>${index + 1}</td>
                                    <td><span class="badge ${row.issue_mode === 'reissue' ? 'bg-warning text-dark' : 'bg-success'}">${$('<div>').text(row.issue_mode || '').html()}</span></td>
                                    <td>${$('<div>').text(row.v_id || '').html()}</td>
                                    <td>${$('<div>').text(row.issued_by_name || '').html()}</td>
                                    <td>${$('<div>').text(row.issued_at || '').html()}</td>
                                    <td>${$('<div>').text(row.notes || '').html()}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `;

            $('#historyBody').html(tableHtml);
        }, 'json').fail(function () {
            $('#historyBody').html('<div class="text-danger text-center p-3">Failed to load history</div>');
        });
    });
});
</script>

<?php require_once ROOT_PATH . '/modules/layout/footer.php'; ?>
