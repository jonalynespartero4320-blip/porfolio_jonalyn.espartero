<?php
$page_title = 'Visitor Management';
require_once ROOT_PATH . '/modules/layout/header.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/modules/Pages/id-issuance/id-issuance-common.php';

$canVisitorView = can('visitors.view');
$canVisitorEdit = can('visitors.edit');
$canVisitorBan = can('visitors.ban');
$canVisitorQueue = can('visitors.queue');
$canVisitorIssue = can('visitors.issue');

issuance_ensure_history_table($pdo);

$pdo->exec("
    UPDATE visitors_registration
    SET is_banned = 0,
        violation_type = NULL,
        effective_until = NULL,
        updated_at = NOW()
    WHERE is_banned = 1
      AND effective_until IS NOT NULL
      AND effective_until < CURDATE()
");

$stmt = $pdo->query("
    SELECT
        vr.*,
        p.pdl_fname,
        p.pdl_mname,
        p.pdl_lname,
        p.pdl_suffix,
        p.pdl_sex,
        p.case_type,
        p.court,
        p.dorm_no,
        (
            SELECT vih.issue_mode
            FROM visitor_id_issuance_history vih
            WHERE vih.visitor_id = vr.id
            ORDER BY vih.issued_at DESC, vih.id DESC
            LIMIT 1
        ) AS latest_issue_mode
    FROM visitors_registration vr
    LEFT JOIN pdl_info p ON p.id = vr.pdl_id
    WHERE vr.visitor_status = 'Verified'
    ORDER BY vr.is_banned DESC, vr.id DESC
");

$visitors = $stmt->fetchAll(PDO::FETCH_ASSOC);
$summary = $pdo->query("
    SELECT
        COUNT(*) AS total_verified,
        SUM(CASE WHEN is_banned = 1 THEN 1 ELSE 0 END) AS total_banned,
        SUM(CASE WHEN COALESCE(issued_at, '') <> '' THEN 1 ELSE 0 END) AS total_issued,
        SUM(CASE WHEN COALESCE(issued_at, '') = '' THEN 1 ELSE 0 END) AS total_pending_id
    FROM visitors_registration
    WHERE visitor_status = 'Verified'
")->fetch(PDO::FETCH_ASSOC) ?: [];

$totalVerified = (int) ($summary['total_verified'] ?? 0);
$totalBanned = (int) ($summary['total_banned'] ?? 0);
$totalIssued = (int) ($summary['total_issued'] ?? 0);
$totalPendingId = (int) ($summary['total_pending_id'] ?? 0);
$queueReady = max(0, $totalVerified - $totalBanned);
$malePdlVisitorCount = count(array_filter($visitors, static fn($visitor) => in_array(strtolower(trim((string) ($visitor['pdl_sex'] ?? ''))), ['male', 'm'], true)));
$femalePdlVisitorCount = count(array_filter($visitors, static fn($visitor) => in_array(strtolower(trim((string) ($visitor['pdl_sex'] ?? ''))), ['female', 'f'], true)));
?>

<style>
  .vm-shell { display: grid; gap: 1.25rem; 
    /* background:
      linear-gradient(180deg, rgba(255, 255, 255, 0.86) 0%, rgba(248, 250, 252, 0.96) 100%); */
    border-radius: 28px;
    padding: 24px;}
  .vm-hero {
    position: relative;
    overflow: hidden;
    border: 0;
    border-radius: 28px;
     background:
      linear-gradient(135deg, #062150, #447A61);
    box-shadow: 0 24px 60px rgba(15, 23, 42, 0.16);
  }
  .vm-hero::after {
    content: "";
    position: absolute;
    inset: auto -80px -80px auto;
    width: 240px;
    height: 240px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.08);
  }
  .vm-hero .card-body { position: relative; z-index: 1; padding: 1.6rem; }
  .vm-stat-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1rem; }
  .vm-stat, .vm-toolbar, .vm-table-card { border: 0; box-shadow: 0 16px 38px rgba(15, 23, 42, 0.08); }
  .vm-stat { border-radius: 22px; }
  .vm-toolbar { border-radius: 24px; }
  .vm-table-card { border-radius: 28px; overflow: hidden; }
  .vm-table-card .card-header {
    background: linear-gradient(135deg, #062150, #80A497);
    border: 0;
    padding: 1rem 1.25rem;
  }
  .vm-toolbar .form-select, .vm-toolbar .form-control { border-radius: 14px; }
  .icon-wrap {
    width: 48px; height: 48px; display: inline-flex; align-items: center; justify-content: center;
    border-radius: 16px; font-size: 1.25rem;
  }
  .chip-group { display: flex; flex-wrap: wrap; gap: .5rem; }
  .chip { padding: .45rem .8rem; border-radius: 999px; background: #f1f5f9; font-size: .82rem; color: #334155; }
  .vm-table tbody tr { transition: transform .15s ease; }
  .vm-table tbody tr:hover { transform: translateY(-1px); }
  .visitor-identity { display: flex; align-items: center; gap: .9rem; }
  .visitor-avatar {
    width: 52px; height: 52px; border-radius: 16px; display: flex; align-items: center; justify-content: center;
    background: linear-gradient(135deg, rgba(68,122,97,0.28), rgba(128,164,151,0.32)); color: #062150; font-weight: 700; flex-shrink: 0;
    overflow: hidden;
  }
  .visitor-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
  }
  .status-stack, .document-stack, .pdl-stack { display: grid; gap: .4rem; }
  .document-progress { height: 8px; border-radius: 999px; background: #e2e8f0; overflow: hidden; }
  .document-progress > span { display: block; height: 100%; border-radius: inherit; background: linear-gradient(90deg, #0ea5e9, #22c55e); }
  .action-grid { display: flex; gap: .45rem; flex-wrap: wrap; }
  .action-grid .btn { border-radius: 12px; }
  .vm-tab-nav {
    gap: .5rem;
    border-bottom: 0;
  }
  .vm-tab-nav .nav-link {
    border: 1px solid rgba(15, 23, 42, 0.10);
    border-radius: 16px 16px 0 0;
    background: rgba(248, 250, 252, 0.78);
    color: #334155;
    font-weight: 700;
  }
  .vm-tab-nav .nav-link.active {
    background: #fff;
    color: #14532d;
    border-bottom-color: #fff;
    box-shadow: 0 -8px 20px rgba(15, 23, 42, 0.05);
  }
  .details-photo-card { border: 0; border-radius: 24px; box-shadow: 0 12px 28px rgba(15, 23, 42, 0.08); }
  .requirement-frame { width: 100%; height: 500px; border: 1px solid #dbe2ea; border-radius: 16px; background: #f8fafc; }
  @media (max-width: 1199px) { .vm-stat-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
  @media (max-width: 767px) {
    .vm-stat-grid { grid-template-columns: 1fr; }
    .visitor-identity { align-items: flex-start; }
  }
</style>

<div class="container-fluid py-3">
    <div class="vm-shell">
        <div class="card vm-hero text-white">
            <div class="card-body">
                <div class="row g-4 align-items-center">
                    <div class="col-xl-7">
                        <div class="text-uppercase small fw-semibold opacity-75 mb-2">Visitor Operations</div>
                        <h2 class="fw-bold mb-2">Verified Visitors Details Management.</h2>
                        <p class="mb-0 opacity-75">Review verified visitors, control bans, maintain documents, prepare IDs, and hand off eligible visitors to the queueing module from one screen.</p>
                    </div>
                    <div class="col-xl-5">
                        <div class="chip-group justify-content-xl-end">
                            <span class="chip bg-white text-dark"><i class="bi bi-person-check me-1 text-success"></i><?= number_format($queueReady) ?> queue-ready</span>
                            <span class="chip bg-white text-dark"><i class="bi bi-card-heading me-1 text-primary"></i><?= number_format($totalIssued) ?> IDs issued</span>
                            <span class="chip bg-white text-dark"><i class="bi bi-slash-circle me-1 text-danger"></i><?= number_format($totalBanned) ?> active bans</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="vm-stat-grid">
            <div class="card vm-stat">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small">Verified Visitors</div>
                            <div class="display-6 fw-bold mb-0" id="statTotalVerified"><?= number_format($totalVerified) ?></div>
                        </div>
                        <div class="icon-wrap bg-success-subtle text-success"><i class="bi bi-people-fill"></i></div>
                    </div>
                </div>
            </div>
            <div class="card vm-stat">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small">Queue Ready</div>
                            <div class="display-6 fw-bold mb-0" id="statQueueReady"><?= number_format($queueReady) ?></div>
                        </div>
                        <div class="icon-wrap bg-primary-subtle text-primary"><i class="bi bi-person-plus-fill"></i></div>
                    </div>
                </div>
            </div>
            <div class="card vm-stat">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small">IDs Issued</div>
                            <div class="display-6 fw-bold mb-0" id="statIssued"><?= number_format($totalIssued) ?></div>
                        </div>
                        <div class="icon-wrap bg-info-subtle text-info"><i class="bi bi-card-checklist"></i></div>
                    </div>
                </div>
            </div>
            <div class="card vm-stat">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small">Pending ID Issuance</div>
                            <div class="display-6 fw-bold mb-0" id="statPendingId"><?= number_format($totalPendingId) ?></div>
                        </div>
                        <div class="icon-wrap bg-warning-subtle text-warning"><i class="bi bi-hourglass-split"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card vm-toolbar">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-lg-4">
                        <label for="visitorQuickSearch" class="form-label fw-semibold mb-1">Quick Search</label>
                        <input type="text" id="visitorQuickSearch" class="form-control" placeholder="Search visitor, Resident, V-ID, contact, or address">
                    </div>
                    <div class="col-lg-3">
                        <label for="banStatusFilter" class="form-label fw-semibold mb-1">Ban Status</label>
                        <select id="banStatusFilter" class="form-select">
                            <option value="">All visitors</option>
                            <option value="active">Active bans only</option>
                            <option value="clear">Not banned only</option>
                        </select>
                    </div>
                    <div class="col-lg-3">
                        <label for="issuanceFilter" class="form-label fw-semibold mb-1">ID Issuance</label>
                        <select id="issuanceFilter" class="form-select">
                            <option value="">All issuance states</option>
                            <option value="issued">With issued ID</option>
                            <option value="pending">Pending ID issuance</option>
                        </select>
                    </div>
                    <div class="col-lg-2 d-grid">
                        <button type="button" id="resetVisitorFilters" class="btn btn-outline-dark">
                            <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card vm-table-card">
        <div class="card-header bg-success text-white rounded-top-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="mb-1 fw-bold">Verified Visitors Records</h5>
                                    <!-- <span class="badge text-bg-light text-dark" id="visibleVisitorCount">Showing <?= number_format($totalVerified) ?> visitors</span> -->

                    <!-- <small class="opacity-75">Operational review, document maintenance, ID readiness, and queueing.</small> -->
                </div>
            </div>
        </div>

        <div class="card-body">
            <ul class="nav nav-tabs vm-tab-nav mb-3" id="visitorPdlSexTabs">
                <li class="nav-item">
                    <button type="button" class="nav-link active" data-pdl-sex-filter="male">
                       </i> Male Resident Visitors
                        <span class="badge bg-primary ms-1"><?= $malePdlVisitorCount ?></span>
                    </button>
                </li>
                <li class="nav-item">
                    <button type="button" class="nav-link" data-pdl-sex-filter="female">
                        </i> Female Resident Visitors
                        <span class="badge bg-danger ms-1"><?= $femalePdlVisitorCount ?></span>
                    </button>
                </li>
            </ul>
            <div class="table-responsive">
                <table id="visitorManagementTable" class="table vm-table align-middle w-100">
                    <thead class="table-light">
                        <tr>
                            <th width="28%">Visitor</th>
                            <th width="16%">Status</th>
                            <th width="16%">Requirements</th>
                            <th width="18%">Linked Resident</th>
                            <th width="22%">Operations</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($visitors as $visitor): ?>
                            <?php
                            $visitorId = (int) $visitor['id'];
                            $visitorName = preg_replace('/\s+/', ' ', trim(($visitor['firstname'] ?? '') . ' ' . ($visitor['middlename'] ?? '') . ' ' . ($visitor['lastname'] ?? '') . ' ' . ($visitor['suffix'] ?? '')));
                            $pdlName = preg_replace('/\s+/', ' ', trim(($visitor['pdl_fname'] ?? '') . ' ' . ($visitor['pdl_mname'] ?? '') . ' ' . ($visitor['pdl_lname'] ?? '') . ' ' . ($visitor['pdl_suffix'] ?? '')));
                            $isBanned = (int) ($visitor['is_banned'] ?? 0) === 1;
                            $hasIssuedId = trim((string) ($visitor['issued_at'] ?? '')) !== '';
                            $latestIssueMode = trim((string) ($visitor['latest_issue_mode'] ?? ''));
                            $isReIssued = $hasIssuedId && $latestIssueMode === 'reissue';
                            $issuanceLabel = $hasIssuedId ? ($isReIssued ? 'Re-Issued' : 'ID Issued') : 'Not Yet Issued';
                            $issuanceBadge = $hasIssuedId ? ($isReIssued ? 'primary' : 'info') : 'warning';
                            $documentCount = 0;
                            foreach (['birth_certificate_id', 'marriage_contract_id', 'cohabitation_certificate_id', 'Valid_ID'] as $documentColumn) {
                                if (trim((string) ($visitor[$documentColumn] ?? '')) !== '') {
                                    $documentCount++;
                                }
                            }
                            $documentPercent = (int) round(($documentCount / 3) * 100);
                            $visitorInitials = strtoupper(substr(trim((string) ($visitor['firstname'] ?? 'V')), 0, 1) . substr(trim((string) ($visitor['lastname'] ?? 'R')), 0, 1));
                            $visitorPhoto = trim((string) ($visitor['visitor_photo'] ?? ''));
                            $visitorPhotoUrl = $visitorPhoto !== '' ? BASE_URL . '/demo-uploads/visitors/' . rawurlencode($visitorPhoto) : '';
                            $issuedAt = trim((string) ($visitor['issued_at'] ?? ''));
                            $linkedPdlSex = strtolower(trim((string) ($visitor['pdl_sex'] ?? '')));
                            $linkedPdlSexKey = in_array($linkedPdlSex, ['female', 'f'], true) ? 'female' : 'male';
                            ?>
                            <tr
                                data-id="<?= $visitorId ?>"
                                data-pdl-id="<?= (int) ($visitor['pdl_id'] ?? 0) ?>"
                                data-pdl-sex="<?= e($linkedPdlSexKey) ?>"
                                data-ban-status="<?= $isBanned ? 'active' : 'clear' ?>"
                                data-issued-status="<?= $hasIssuedId ? 'issued' : 'pending' ?>"
                                data-document-count="<?= $documentCount ?>"
                                class="<?= $isBanned ? 'table-danger' : '' ?>"
                            >
                                <td>
                                    <div class="visitor-identity">
                                        <div class="visitor-avatar">
                                            <?php if ($visitorPhotoUrl !== ''): ?>
                                                <img src="<?= e($visitorPhotoUrl) ?>" alt="<?= e($visitorName) ?> photo">
                                            <?php else: ?>
                                                <?= e($visitorInitials) ?>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div class="fw-semibold"><?= e($visitorName) ?></div>
                                            <div class="small text-muted">Visitor ID #<?= $visitorId ?><?= $hasIssuedId ? ' | ' . e($visitor['v_id']) : '' ?></div>
                                            <div class="small text-muted"><?= e($visitor['contact_no'] ?? 'No contact number') ?></div>
                                            <div class="small text-muted"><?= e($visitor['loc_add'] ?? 'No address provided') ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="status-stack">
                                        <div>
                                            <span class="badge text-bg-<?= $isBanned ? 'danger' : 'success' ?> status-badge"><?= $isBanned ? 'Banned' : 'Active' ?></span>
                                            <span class="badge text-bg-<?= e($issuanceBadge) ?> issuance-badge"><?= e($issuanceLabel) ?></span>
                                        </div>
                                        <div class="small text-muted ban-meta"><?= $isBanned ? 'Until ' . e($visitor['effective_until'] ?? '-') : 'Cleared for admission' ?></div>
                                        <div class="small text-muted issue-meta"><?= $hasIssuedId ? e(($isReIssued ? 'Re-issued ' : 'Issued ') . date('M d, Y', strtotime($issuedAt))) : 'No date issued yet' ?></div>
                                    </div>
                                </td>
                                <td>
                                    <div class="document-stack">
                                        <div class="fw-semibold"><span class="doc-count"><?= $documentCount ?></span>/3 files on record</div>
                                        <div class="document-progress"><span style="width: <?= $documentPercent ?>%"></span></div>
                                        <div class="small text-muted"><?= $documentCount === 3 ? 'Complete requirements package' : 'Some requirements still need attention' ?></div>
                                    </div>
                                </td>
                                <td>
                                    <div class="pdl-stack">
                                        <div class="fw-semibold"><?= e($pdlName !== '' ? $pdlName : 'No linked Resident') ?></div>
                                        <div class="small text-muted"><?= e($visitor['dorm_no'] ?? 'Dorm not assigned') ?><?= !empty($visitor['case_type']) ? ' | ' . e($visitor['case_type']) : '' ?></div>
                                        <div class="small text-muted"><?= !empty($visitor['court']) ? e($visitor['court']) : 'Court not recorded' ?></div>
                                    </div>
                                </td>
                                <td>
                                    <div class="action-grid">
                                        <?php if ($canVisitorView): ?>
                                            <button type="button" class="btn btn-outline-info btn-sm viewDetailsBtn">
                                                <i class="bi bi-eye me-1"></i>Details
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($canVisitorQueue): ?>
                                            <a href="<?= BASE_URL ?>/queue?visitor_id=<?= $visitorId ?>" class="btn btn-outline-primary btn-sm queueBtn <?= $isBanned ? 'disabled' : '' ?>">
                                                <i class="bi bi-person-plus me-1"></i>Queue
                                            </a>
                                        <?php endif; ?>
                                        <!-- <?php if ($canVisitorIssue): ?>
                                            <a href="<?= BASE_URL ?>/<?= $hasIssuedId ? 'id-card?visitor_id=' . $visitorId : 'id-issuance' ?>" class="btn btn-outline-dark btn-sm">
                                                <i class="bi bi-card-heading me-1"></i><?= $hasIssuedId ? 'View ID' : 'Issue ID' ?>
                                            </a>
                                        <?php endif; ?> -->
                                        <?php if ($canVisitorBan): ?>
                                            <button type="button" class="btn btn-outline-danger btn-sm banBtn" <?= $isBanned ? 'disabled' : '' ?>>
                                                <i class="bi bi-slash-circle me-1"></i>Ban
                                            </button>
                                            <button type="button" class="btn btn-outline-success btn-sm liftBtn" <?= !$isBanned ? 'disabled' : '' ?>>
                                                <i class="bi bi-check-circle me-1"></i>Lift
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary btn-sm historyBtn">
                                                <i class="bi bi-clock-history me-1"></i>Ban History
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($canVisitorView): ?>
                                            <button type="button" class="btn btn-primary btn-sm viewPDLBtn" <?= empty($visitor['pdl_id']) ? 'disabled' : '' ?>>
                                                <i class="bi bi-person-vcard me-1"></i>Resident Details
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        </div>
    </div>
</div>

<div class="modal fade" id="banModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form id="banForm" class="modal-content rounded-4 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title text-black">Ban Visitor</h5>
                <button type="button" class="btn-close btn-close-black" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="ban_visitor_id" name="visitor_id">
                <div class="mb-3">
                    <label for="violation_type" class="form-label fw-bold">Violation</label>
                    <input type="text" id="violation_type" name="violation_type" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="effective_until" class="form-label fw-bold">Effective Until</label>
                    <input type="date" id="effective_until" name="effective_until" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-dark">Save</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="viewDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content rounded-4 shadow">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">Visitor Full Information</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="container-fluid">
                    <div class="row g-4">
                        <div class="col-lg-3 col-md-4 text-center">
                            <div class="card details-photo-card p-3">
                                <img id="viewVisitorPhoto" src="<?= e(BASE_URL . '/assets/images/demo-scene.svg') ?>" class="img-fluid rounded-3 mb-3" style="aspect-ratio:1/1; object-fit:cover; cursor:pointer;" alt="Visitor photo">
                                <span id="visitorStatusBadge" class="badge bg-secondary fs-6 mb-3 w-100">Status</span>
                                <input type="file" id="changePhotoInput" class="d-none" accept="image/jpeg,image/png">
                                <button type="button" class="btn btn-sm btn-outline-primary w-100" id="changePhotoBtn">
                                    <i class="bi bi-camera me-1"></i>Change Photo
                                </button>
                            </div>
                        </div>
                        <div class="col-lg-9 col-md-8">
                            <div id="visitorFullInfo"></div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div id="requirementsButtons" class="mb-3"></div>
                    <iframe id="requirementsFrame" class="requirement-frame"></iframe>

                    <div class="d-flex justify-content-end mt-3">
                        <input type="file" id="changeRequirementInput" class="d-none" accept=".pdf,.jpg,.jpeg,.png">
                        <button type="button" class="btn btn-sm btn-success" id="changeRequirementBtn">
                            <i class="bi bi-upload me-1"></i>Change File
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="emergencyContactModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form id="emergencyContactForm" class="modal-content rounded-4 shadow">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-telephone-plus me-1"></i>Update Emergency Contact</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="ec_registration_id" name="registration_id">
                <div class="mb-3">
                    <label for="ec_name_input" class="form-label fw-semibold">Emergency Contact Name</label>
                    <input type="text" id="ec_name_input" name="ec_name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="ec_contact_input" class="form-label fw-semibold">Emergency Contact No.</label>
                    <input type="text" id="ec_contact_input" name="ec_contactno" class="form-control" required>
                </div>
                <div class="mb-0">
                    <label for="ec_address_input" class="form-label fw-semibold">Emergency Address</label>
                    <textarea id="ec_address_input" name="ec_address" class="form-control" rows="3" required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-save me-1"></i>Save Emergency Contact
                </button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="banHistoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content rounded-4 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Ban History</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Violation</th>
                                <th>From</th>
                                <th>Until</th>
                                <th>Banned By</th>
                                <th>Lifted By</th>
                                <th>Lifted On</th>
                                <th>Reason</th>
                            </tr>
                        </thead>
                        <tbody id="banHistoryBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="viewPDLModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content rounded-4 shadow">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title fw-bold">Resident Information</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="pdlFullInfo"></div>
        </div>
    </div>
 </div>

<script>
const VISITOR_MANAGEMENT_BOOTSTRAP = {
    actionUrl: <?= json_encode(BASE_URL . '/visitor-management-action') ?>,
    defaultPhoto: <?= json_encode(BASE_URL . '/assets/images/demo-scene.svg') ?>
};
</script>

<script>
$(function () {
    const ACTION_URL = VISITOR_MANAGEMENT_BOOTSTRAP.actionUrl;
    const DEFAULT_PHOTO = VISITOR_MANAGEMENT_BOOTSTRAP.defaultPhoto;
    const banModal = new bootstrap.Modal(document.getElementById('banModal'));
    const viewDetailsModal = new bootstrap.Modal(document.getElementById('viewDetailsModal'));
    const emergencyContactModal = new bootstrap.Modal(document.getElementById('emergencyContactModal'));
    const banHistoryModal = new bootstrap.Modal(document.getElementById('banHistoryModal'));
    const viewPDLModal = new bootstrap.Modal(document.getElementById('viewPDLModal'));
    let currentRequirementType = null;
    let activePdlSexFilter = 'male';

    const table = $('#visitorManagementTable').DataTable({
        responsive: true,
        pageLength: 8,
        order: [],
        columnDefs: [{ orderable: false, targets: [4] }],
        language: {
            emptyTable: 'No verified visitors found.',
            search: '',
            searchPlaceholder: 'Search visitors'
        }
    });

    $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
        if (settings.nTable.id !== 'visitorManagementTable') {
            return true;
        }

        const rowNode = table.row(dataIndex).node();
        if (!rowNode) {
            return true;
        }

        const banFilter = $('#banStatusFilter').val();
        const issuanceFilter = $('#issuanceFilter').val();
        const banStatus = $(rowNode).data('ban-status');
        const issuedStatus = $(rowNode).data('issued-status');
        const pdlSex = $(rowNode).data('pdl-sex');

        if (activePdlSexFilter && pdlSex !== activePdlSexFilter) {
            return false;
        }

        if (banFilter && banStatus !== banFilter) {
            return false;
        }

        if (issuanceFilter && issuedStatus !== issuanceFilter) {
            return false;
        }

        return true;
    });

    function infoRow(label, value) {
        return `<div class="row mb-1"><div class="col-5 text-end fw-semibold">${label}</div><div class="col-7 text-start">: ${value || '-'}</div></div>`;
    }

    function updateVisibleCount() {
        const count = table.rows({ filter: 'applied' }).count();
        $('#visibleVisitorCount').text(`Showing ${count} visitor${count === 1 ? '' : 's'}`);
    }

    function updateSummaryCards() {
        let total = 0;
        let ready = 0;
        let issued = 0;
        let pending = 0;

        $('#visitorManagementTable tbody tr').each(function () {
            total += 1;
            const isBanned = $(this).data('ban-status') === 'active';
            const isIssued = $(this).data('issued-status') === 'issued';

            if (!isBanned) {
                ready += 1;
            }

            if (isIssued) {
                issued += 1;
            } else {
                pending += 1;
            }
        });

        $('#statTotalVerified').text(total);
        $('#statQueueReady').text(ready);
        $('#statIssued').text(issued);
        $('#statPendingId').text(pending);
    }

    function updateRowBanState($row, isBanned, effectiveUntil) {
        $row.attr('data-ban-status', isBanned ? 'active' : 'clear');
        $row.toggleClass('table-danger', isBanned);
        $row.find('.status-badge')
            .removeClass('text-bg-success text-bg-danger')
            .addClass(isBanned ? 'text-bg-danger' : 'text-bg-success')
            .text(isBanned ? 'Banned' : 'Active');
        $row.find('.ban-meta').text(isBanned ? `Until ${effectiveUntil || '-'}` : 'Cleared for admission');
        $row.find('.banBtn').prop('disabled', isBanned);
        $row.find('.liftBtn').prop('disabled', !isBanned);
        $row.find('.queueBtn').toggleClass('disabled', isBanned);
        updateSummaryCards();
        table.draw(false);
    }

    $('#visitorQuickSearch').on('input', function () {
        table.search(this.value).draw();
        updateVisibleCount();
    });

    $('#banStatusFilter, #issuanceFilter').on('change', function () {
        table.draw();
        updateVisibleCount();
    });

    $('#visitorPdlSexTabs').on('click', '[data-pdl-sex-filter]', function () {
        activePdlSexFilter = $(this).data('pdl-sex-filter');
        $('#visitorPdlSexTabs .nav-link').removeClass('active');
        $(this).addClass('active');
        table.draw();
        updateVisibleCount();
    });

    $('#resetVisitorFilters').on('click', function () {
        $('#visitorQuickSearch').val('');
        $('#banStatusFilter').val('');
        $('#issuanceFilter').val('');
        table.search('').draw();
        updateVisibleCount();
    });

    function renderRequirementButtons(requirements) {
        let buttonsHtml = '';
        let firstType = null;
        let firstUrl = '';

        Object.entries(requirements || {}).forEach(([key, item]) => {
            if (!item.file_url) return;
            const label = item.label || key.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());
            buttonsHtml += `<button type="button" class="btn btn-sm btn-outline-primary me-2 mb-2 reqBtn" data-type="${key}" data-file="${item.file_url}">${label}</button>`;
            if (!firstType) {
                firstType = key;
                firstUrl = item.file_url;
            }
        });

        if (!buttonsHtml) {
            currentRequirementType = null;
            $('#requirementsButtons').html('<div class="alert alert-secondary mt-2 mb-0">No uploaded requirements.</div>');
            $('#requirementsFrame').attr('src', '');
            return;
        }

        currentRequirementType = firstType;
        $('#requirementsButtons').html(buttonsHtml);
        $('#requirementsFrame').attr('src', firstUrl + '?t=' + Date.now());
        $('.reqBtn').first().addClass('active btn-primary').removeClass('btn-outline-primary');
    }

    $(document).on('click', '.viewDetailsBtn', function () {
        const registrationId = $(this).closest('tr').data('id');

        $.getJSON(ACTION_URL, { action: 'get_details', id: registrationId }).done((res) => {
            if (res.status !== 'success') {
                Swal.fire('Error', res.message || 'Visitor not found.', 'error');
                return;
            }

            const d = res.data || {};
            $('#viewDetailsModal').data('registration-id', registrationId);
            $('#viewDetailsModal').data('emergency-contact', {
                ec_name: d.ec_name || '',
                ec_contactno: d.ec_contactno || '',
                ec_address: d.ec_address || ''
            });
            currentRequirementType = null;
            $('#viewVisitorPhoto').attr('src', (d.visitor_photo_url || DEFAULT_PHOTO) + '?t=' + Date.now());

            let badgeClass = 'bg-secondary';
            let badgeText = d.visitor_status || 'No Status';
            if ((d.visitor_status || '').toLowerCase() === 'verified') badgeClass = 'bg-success';
            if ((d.visitor_status || '').toLowerCase().includes('incomplete')) badgeClass = 'bg-warning';
            if (parseInt(d.is_banned || 0, 10) === 1) {
                badgeClass = 'bg-danger';
                badgeText = `Banned until ${d.effective_until || ''}`;
            }

            $('#visitorStatusBadge').removeClass('bg-success bg-warning bg-secondary bg-danger').addClass(badgeClass).text(badgeText);
            $('#visitorFullInfo').html(`
                <div class="container">
                    <div class="chip-group mb-3">
                        <span class="chip"><i class="bi bi-person-vcard me-1 text-primary"></i>${d.v_id || 'No visitor ID issued yet'}</span>
                        <span class="chip"><i class="bi bi-shield-check me-1 text-success"></i>${d.visitor_status || 'No status'}</span>
                        <span class="chip"><i class="bi bi-files me-1 text-info"></i>${Object.values(d.requirements || {}).filter(item => item.file_url).length}/3 requirements uploaded</span>
                    </div>
                    <div class="row g-3">
                        <div class="col-lg-4 col-md-6">
                            <div class="card shadow-sm h-100 mb-2">
                                <div class="card-header bg-light py-2"><strong>Visitor Information</strong></div>
                                <div class="card-body py-2 px-3 small">
                                    ${infoRow('First Name', d.firstname)}
                                    ${infoRow('Middle Name', d.middlename)}
                                    ${infoRow('Last Name', d.lastname)}
                                    ${infoRow('Suffix', d.suffix)}
                                    ${infoRow('Age', d.age)}
                                    ${infoRow('Sex', d.sex)}
                                    ${infoRow('Address', d.loc_add)}
                                    ${infoRow('Contact No', d.contact_no)}
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <div class="card shadow-sm h-100 mb-2">
                                <div class="card-header bg-light py-2"><strong>Resident Information</strong></div>
                                <div class="card-body py-2 px-3 small">
                                    ${infoRow('Resident ID', d.pdl_id)}
                                    ${infoRow('Resident Name', d.pdl_name)}
                                    ${infoRow('Dorm', d.dorm_no)}
                                    ${infoRow('Case Type', d.case_type)}
                                    ${infoRow('Court', d.court)}
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-12">
                            <div class="card shadow-sm h-100 mb-2">
                                <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center gap-2">
                                    <strong>Emergency Contact</strong>
                                    <?php if ($canVisitorEdit): ?>
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-success editEmergencyContactBtn"
                                            data-id="${registrationId}">
                                            <i class="bi bi-pencil-square me-1"></i>Edit
                                        </button>
                                    <?php endif; ?>
                                </div>
                                <div class="card-body py-2 px-3 small">
                                    <div class="emergency-contact-display">
                                        ${infoRow('Name', d.ec_name)}
                                        ${infoRow('Contact No', d.ec_contactno)}
                                        ${infoRow('Address', d.ec_address)}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `);

            $('#viewVisitorPhoto').off('click').on('click', function () {
                Swal.fire({ imageUrl: $(this).attr('src'), showConfirmButton: false, width: 600 });
            });

            renderRequirementButtons(d.requirements || {});
            viewDetailsModal.show();
        }).fail(() => Swal.fire('Error', 'Failed to load visitor details.', 'error'));
    });

    $(document).on('click', '.reqBtn', function () {
        $('.reqBtn').removeClass('active btn-primary').addClass('btn-outline-primary');
        $(this).addClass('active btn-primary').removeClass('btn-outline-primary');
        currentRequirementType = $(this).data('type');
        $('#requirementsFrame').attr('src', $(this).data('file') + '?t=' + Date.now());
    });

    $(document).on('click', '.editEmergencyContactBtn', function () {
        const registrationId = $(this).data('id') || $('#viewDetailsModal').data('registration-id');
        const emergency = $('#viewDetailsModal').data('emergency-contact') || {};

        $('#ec_registration_id').val(registrationId || '');
        $('#ec_name_input').val(emergency.ec_name || '');
        $('#ec_contact_input').val(emergency.ec_contactno || '');
        $('#ec_address_input').val(emergency.ec_address || '');
        emergencyContactModal.show();
    });

    $(document).on('submit', '#emergencyContactForm', function (e) {
        e.preventDefault();
        const registrationId = $('#ec_registration_id').val();
        const payload = {
            action: 'update_emergency_contact',
            registration_id: registrationId,
            ec_name: $('#ec_name_input').val(),
            ec_contactno: $('#ec_contact_input').val(),
            ec_address: $('#ec_address_input').val()
        };

        $.ajax({
            url: ACTION_URL,
            type: 'POST',
            dataType: 'json',
            data: payload,
            beforeSend: function () { Swal.showLoading(); },
            success: function (res) {
                if (res.status !== 'success') {
                    Swal.fire('Error', res.message || 'Unable to update emergency contact.', 'error');
                    return;
                }

                const d = res.data || {};
                $('#viewDetailsModal').data('emergency-contact', d);
                $('.emergency-contact-display').html(`
                    ${infoRow('Name', d.ec_name)}
                    ${infoRow('Contact No', d.ec_contactno)}
                    ${infoRow('Address', d.ec_address)}
                `);

                emergencyContactModal.hide();
                Swal.fire({ icon: 'success', title: 'Emergency Contact Updated', timer: 1300, showConfirmButton: false });
            },
            error: function () {
                Swal.fire('Error', 'Server error occurred while updating emergency contact.', 'error');
            }
        });
    });

    $(document).on('click', '#changeRequirementBtn', function () {
        if (!currentRequirementType) {
            Swal.fire('Warning', 'Select a requirement first.', 'warning');
            return;
        }
        $('#changeRequirementInput').click();
    });

    $(document).on('change', '#changeRequirementInput', function () {
        const registrationId = $('#viewDetailsModal').data('registration-id');
        const file = this.files[0];
        if (!file) return;

        const formData = new FormData();
        formData.append('action', 'update_requirement');
        formData.append('registration_id', registrationId);
        formData.append('requirement_type', currentRequirementType);
        formData.append('file', file);

        $.ajax({
            url: ACTION_URL,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            beforeSend: function () { Swal.showLoading(); },
            success: function (res) {
                if (res.status !== 'success') {
                    Swal.fire('Error', res.message || 'Upload failed.', 'error');
                    return;
                }
                $('#requirementsFrame').attr('src', res.file_url + '?t=' + Date.now());
                $('.reqBtn.active').data('file', res.file_url);
                Swal.fire({ icon: 'success', title: 'Requirement Updated', timer: 1200, showConfirmButton: false });
            },
            error: function () { Swal.fire('Error', 'Upload failed.', 'error'); },
            complete: function () { $('#changeRequirementInput').val(''); }
        });
    });

    $(document).on('click', '#changePhotoBtn', function () {
        $('#changePhotoInput').click();
    });

    $(document).on('change', '#changePhotoInput', function () {
        const registrationId = $('#viewDetailsModal').data('registration-id');
        const file = this.files[0];
        if (!file) return;

        const formData = new FormData();
        formData.append('action', 'update_photo');
        formData.append('registration_id', registrationId);
        formData.append('visitor_photo', file);

        $.ajax({
            url: ACTION_URL,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            beforeSend: function () { Swal.showLoading(); },
            success: function (res) {
                if (res.status !== 'success') {
                    Swal.fire('Error', res.message || 'Photo upload failed.', 'error');
                    return;
                }
                $('#viewVisitorPhoto').attr('src', res.photo_url + '?t=' + Date.now());
                Swal.fire({ icon: 'success', title: 'Photo Updated', timer: 1200, showConfirmButton: false });
            },
            error: function () { Swal.fire('Error', 'Photo upload failed.', 'error'); },
            complete: function () { $('#changePhotoInput').val(''); }
        });
    });

    $(document).on('click', '.banBtn', function () {
        $('#banForm')[0].reset();
        $('#ban_visitor_id').val($(this).closest('tr').data('id'));
        banModal.show();
    });

    $(document).on('submit', '#banForm', function (e) {
        e.preventDefault();
        const visitorId = $('#ban_visitor_id').val();
        const formData = new FormData(this);
        formData.append('action', 'ban_visitor');

        $.ajax({
            url: ACTION_URL,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            beforeSend: function () { Swal.showLoading(); },
            success: function (res) {
                if (res.status !== 'success') {
                    Swal.fire('Error', res.message || 'Ban failed.', 'error');
                    return;
                }
                banModal.hide();
                updateRowBanState($(`#visitorManagementTable tbody tr[data-id="${visitorId}"]`), true, $('#effective_until').val());
                Swal.fire({ icon: 'success', title: 'Visitor Banned', timer: 1400, showConfirmButton: false });
            },
            error: function () { Swal.fire('Error', 'Server error occurred.', 'error'); }
        });
    });

    $(document).on('click', '.liftBtn', function () {
        const visitorId = $(this).closest('tr').data('id');

        Swal.fire({
            title: 'Lift Ban',
            input: 'textarea',
            inputLabel: 'Reason for lifting',
            inputPlaceholder: 'Enter reason here...',
            inputValidator: (value) => !value ? 'Reason is required.' : undefined,
            showCancelButton: true,
            confirmButtonText: 'Confirm',
            confirmButtonColor: '#198754'
        }).then((result) => {
            if (!result.isConfirmed) return;

            $.ajax({
                url: ACTION_URL,
                type: 'POST',
                dataType: 'json',
                data: { action: 'lift_ban', visitor_id: visitorId, lift_reason: result.value },
                beforeSend: function () { Swal.showLoading(); },
                success: function (res) {
                    if (res.status !== 'success') {
                        Swal.fire('Error', res.message || 'Failed to lift ban.', 'error');
                        return;
                    }
                    updateRowBanState($(`#visitorManagementTable tbody tr[data-id="${visitorId}"]`), false, '');
                    Swal.fire({ icon: 'success', title: 'Ban Lifted', timer: 1400, showConfirmButton: false });
                },
                error: function () { Swal.fire('Error', 'Server error occurred.', 'error'); }
            });
        });
    });

    $(document).on('click', '.historyBtn', function () {
        const visitorId = $(this).closest('tr').data('id');
        $('#banHistoryBody').html('<tr><td colspan="8" class="text-center text-muted">Loading...</td></tr>');
        banHistoryModal.show();

        $.getJSON(ACTION_URL, { action: 'ban_history', id: visitorId }).done((res) => {
            if (res.status !== 'success') {
                $('#banHistoryBody').html('<tr><td colspan="8" class="text-center text-danger">Failed to load ban history.</td></tr>');
                return;
            }

            const rows = res.data || [];
            if (!rows.length) {
                $('#banHistoryBody').html('<tr><td colspan="8" class="text-center text-muted">No ban history found.</td></tr>');
                return;
            }

            let html = '';
            rows.forEach((row, index) => {
                html += `<tr>
                    <td>${index + 1}</td>
                    <td>${row.violation_type || '-'}</td>
                    <td>${row.effective_from || '-'}</td>
                    <td>${row.effective_until || '-'}</td>
                    <td>${row.banned_by_name || 'SYSTEM'}</td>
                    <td>${row.lifted_by_name || '-'}</td>
                    <td>${row.lifted_on || '-'}</td>
                    <td>${row.lift_reason || '-'}</td>
                </tr>`;
            });
            $('#banHistoryBody').html(html);
        }).fail(() => $('#banHistoryBody').html('<tr><td colspan="8" class="text-center text-danger">Failed to load ban history.</td></tr>'));
    });

    $(document).on('click', '.viewPDLBtn', function () {
        const pdlId = $(this).closest('tr').data('pdl-id');
        if (!pdlId) return;

        $.getJSON(ACTION_URL, { action: 'get_pdl_details', pdl_id: pdlId }).done((res) => {
            if (res.status !== 'success') {
                Swal.fire('Error', res.message || 'Resident details not found.', 'error');
                return;
            }

            const pdl = res.data || {};
            const pdlName = [pdl.pdl_fname, pdl.pdl_mname, pdl.pdl_lname, pdl.pdl_suffix].filter(Boolean).join(' ');

            $('#pdlFullInfo').html(`
                <div class="row g-4">
                    <div class="col-md-4 text-center">
                        <img src="${(pdl.pdl_photo_url || DEFAULT_PHOTO) + '?t=' + Date.now()}" class="img-fluid rounded-4 border shadow-sm" style="max-height:260px; object-fit:cover;" alt="Resident photo">
                    </div>
                    <div class="col-md-8">
                        <div class="row mb-2"><div class="col-sm-4 fw-semibold">Resident ID</div><div class="col-sm-8">${pdl.pdl_id || '-'}</div></div>
                        <div class="row mb-2"><div class="col-sm-4 fw-semibold">Name</div><div class="col-sm-8">${pdlName || '-'}</div></div>
                        <div class="row mb-2"><div class="col-sm-4 fw-semibold">Age</div><div class="col-sm-8">${pdl.pdl_age || '-'}</div></div>
                        <div class="row mb-2"><div class="col-sm-4 fw-semibold">Sex</div><div class="col-sm-8">${pdl.pdl_sex || '-'}</div></div>
                        <div class="row mb-2"><div class="col-sm-4 fw-semibold">Dorm</div><div class="col-sm-8">${pdl.dorm_no || '-'}</div></div>
                        <div class="row mb-2"><div class="col-sm-4 fw-semibold">Case No</div><div class="col-sm-8">${pdl.case_no || '-'}</div></div>
                        <div class="row mb-2"><div class="col-sm-4 fw-semibold">Case Type</div><div class="col-sm-8">${pdl.case_type || '-'}</div></div>
                        <div class="row mb-0"><div class="col-sm-4 fw-semibold">Court</div><div class="col-sm-8">${pdl.court || '-'}</div></div>
                    </div>
                </div>
            `);
            viewPDLModal.show();
        }).fail(() => Swal.fire('Error', 'Failed to load Resident details.', 'error'));
    });

    table.on('draw', updateVisibleCount);
    updateSummaryCards();
    updateVisibleCount();
});
</script>

<?php require_once ROOT_PATH . '/modules/layout/footer.php'; ?>
