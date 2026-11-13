<?php
require_once ROOT_PATH . '/modules/layout/header.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/modules/Auth/controllers/MasterlistController.php';

require_login();
MasterlistController::ensureRequirementTable();
MasterlistController::ensureValidIdTypeTable();

function loadPdlsBySex(PDO $pdo, array $sexes): array
{
    $placeholders = implode(',', array_fill(0, count($sexes), '?'));
    $stmt = $pdo->prepare("
        SELECT id, pdl_fname, pdl_mname, pdl_lname, pdl_suffix, dorm_no
        FROM pdl_info
        WHERE LOWER(TRIM(pdl_sex)) IN ($placeholders)
        ORDER BY pdl_fname ASC, pdl_lname ASC
    ");
    $stmt->execute($sexes);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$malePdls = loadPdlsBySex($pdo, ['m', 'male']);
$femalePdls = loadPdlsBySex($pdo, ['f', 'female']);
$supportedDocumentMap = [
    'valid_id' => ['column' => 'valid_id', 'cert_field' => 'valid_id_no', 'icon' => 'info'],
    'birth_certificate' => ['column' => 'birth_certificate_id', 'cert_field' => 'birth_certificate_no', 'icon' => 'primary'],
    'marriage_contract' => ['column' => 'marriage_contract_id', 'cert_field' => 'marriage_contract_no', 'icon' => 'success'],
    'cohabitation_certificate' => ['column' => 'cohabitation_certificate_id', 'cert_field' => 'cohabitation_certificate_no', 'icon' => 'warning'],
];
$requirementRows = $pdo->query("
    SELECT requirement_key, requirement_name
    FROM requirement_masterlist
    WHERE is_active = 1
    ORDER BY sort_order ASC, requirement_name ASC
")->fetchAll(PDO::FETCH_ASSOC);
$validIdTypes = $pdo->query("
    SELECT id_type_name
    FROM valid_id_type_masterlist
    WHERE is_active = 1
    ORDER BY sort_order ASC, id_type_name ASC
")->fetchAll(PDO::FETCH_COLUMN);
$documents = [];
foreach ($requirementRows as $row) {
    $key = trim((string) ($row['requirement_key'] ?? ''));
    if (isset($supportedDocumentMap[$key])) {
        $documents[] = array_merge($supportedDocumentMap[$key], [
            'key' => $key,
            'label' => trim((string) ($row['requirement_name'] ?? '')),
        ]);
    }
}
if (!$documents) {
    $documents = [
        ['key' => 'valid_id', 'label' => 'Valid ID', 'column' => 'valid_id', 'cert_field' => 'valid_id_no', 'icon' => 'info'],
        ['key' => 'birth_certificate', 'label' => 'Birth Certificate', 'column' => 'birth_certificate_id', 'cert_field' => 'birth_certificate_no', 'icon' => 'primary'],
        ['key' => 'marriage_contract', 'label' => 'Marriage Certificate', 'column' => 'marriage_contract_id', 'cert_field' => 'marriage_contract_no', 'icon' => 'success'],
        ['key' => 'cohabitation_certificate', 'label' => 'Cohabitation Certificate', 'column' => 'cohabitation_certificate_id', 'cert_field' => 'cohabitation_certificate_no', 'icon' => 'warning'],
    ];
}
$documentCountMax = count($documents);
?>

<style>
  .vr-shell { display: grid; gap: 1.25rem; 
    /* background:
      linear-gradient(180deg, rgba(255, 255, 255, 0.86) 0%, rgba(248, 250, 252, 0.96) 100%); */
    border-radius: 28px;
    padding: 24px;}
  .vr-hero {
    position: relative;
    overflow: hidden;
    border: 0;
    border-radius: 28px;
    background:
      linear-gradient(135deg, #062150, #447A61);
    box-shadow: 0 24px 60px rgba(15, 23, 42, 0.16);
  }
  .vr-hero::after {
    content: "";
    position: absolute;
    right: -90px;
    bottom: -90px;
    width: 240px;
    height: 240px;
    border-radius: 50%;
    background: rgba(255,255,255,0.08);
  }
  .vr-hero .card-body { position: relative; z-index: 1; padding: 1.6rem; }
  .vr-stat-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1rem; }
  .vr-stat-card, .vr-main-card { border: 0; border-radius: 24px; box-shadow: 0 16px 38px rgba(15, 23, 42, 0.08); }
  .vr-stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 16px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
  }
  .vr-tabs { gap: .5rem; border-bottom: 0; }
  .vr-tabs .nav-link {
    border: 0;
    border-radius: 16px;
    padding: .85rem 1rem;
    background: #f8fafc;
    color: #334155;
    font-weight: 600;
  }
  .vr-tabs .nav-link.active {
    background: linear-gradient(135deg, #062150, #80A497);
    color: #fff;
    box-shadow: 0 14px 28px rgba(15, 23, 42, 0.12);
  }
  .vr-progress {
    height: 16px;
    border-radius: 999px;
    overflow: hidden;
    background: #e2e8f0;
  }
  .vr-stage {
    border: 1px solid #e2e8f0;
    border-radius: 24px;
    padding: 1.5rem;
    background: #fff;
  }
  .vr-filter-bar {
    display: flex;
    gap: .75rem;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
  }
  .vr-filter-tools {
    display: flex;
    gap: .75rem;
    flex-wrap: wrap;
    align-items: center;
  }
  .photo-wrapper { width: 220px; height: 220px; position: relative; }
  #cameraVideo { width: 220px; height: 220px; object-fit: cover; }
  .vr-photo-frame {
    border: 2px dashed #cbd5e1;
    border-radius: 24px;
    background: linear-gradient(180deg, #f8fafc, #eef2ff);
    padding: 1rem;
  }
  .vr-photo-meta {
    display: flex;
    gap: .5rem;
    flex-wrap: wrap;
    justify-content: center;
  }
  .vr-chip {
    padding: .45rem .8rem;
    border-radius: 999px;
    background: #f1f5f9;
    font-size: .82rem;
    color: #334155;
  }
  .vr-document-card {
    border: 1px solid #e2e8f0;
    border-radius: 22px;
    box-shadow: 0 12px 28px rgba(15, 23, 42, 0.06);
  }
  .vr-document-card.complete {
    border-color: rgba(34, 197, 94, 0.35);
    background: linear-gradient(180deg, #f0fdf4, #ffffff);
  }
  .document-safety-note {
    border-radius: 16px;
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.12), rgba(59, 130, 246, 0.08));
    border: 1px solid rgba(245, 158, 11, 0.18);
    padding: .8rem .9rem;
  }
  .vr-summary-banner {
    border-radius: 18px;
    background: linear-gradient(135deg, #eff6ff, #f0fdf4);
    padding: 1rem 1.1rem;
    border: 1px solid #dbeafe;
  }
  .vr-table tbody tr { transition: transform .15s ease; }
  .vr-table tbody tr:hover { transform: translateY(-1px); }
  @media (max-width: 1199px) {
    .vr-stat-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  }
  @media (max-width: 767px) {
    .vr-stat-grid { grid-template-columns: 1fr; }
    .photo-wrapper, #cameraVideo { width: 180px; height: 180px; }
  }
</style>

<div class="container-fluid py-3">
    <div class="vr-shell">
        <div class="card vr-hero text-white">
            <div class="card-body">
                <div class="row g-4 align-items-center">
                    <div class="col-xl-7">
                        <div class="text-uppercase small fw-semibold opacity-75 mb-2">Registration Workflow</div>
                        <h2 class="fw-bold mb-2">Visitors Registration </h2>
                        <p class="mb-0 opacity-75">This upgraded workspace keeps the registration team focused with clearer steps, live progress, document tracking, and faster continuation of unfinished submissions.</p>
                    </div>
                    <div class="col-xl-5">
                        <div class="d-flex gap-2 flex-wrap justify-content-xl-end">
                            <span class="vr-chip bg-white text-dark"><i class="bi bi-shield-check me-1 text-success"></i>Verification-ready flow</span>
                            <span class="vr-chip bg-white text-dark"><i class="bi bi-camera me-1 text-primary"></i>Photo capture enabled</span>
                            <span class="vr-chip bg-white text-dark"><i class="bi bi-file-earmark-check me-1 text-info"></i>3-step document review</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="vr-stat-grid">
            <div class="card vr-stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small">Male Resident Records</div>
                            <div class="display-6 fw-bold mb-0" id="malePdlCount"><?= count($malePdls) ?></div>
                        </div>
                        <div class="vr-stat-icon bg-primary-subtle text-primary"><i class="bi bi-gender-male"></i></div>
                    </div>
                </div>
            </div>
            <div class="card vr-stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small">Female Resident Records</div>
                            <div class="display-6 fw-bold mb-0" id="femalePdlCount"><?= count($femalePdls) ?></div>
                        </div>
                        <div class="vr-stat-icon bg-danger-subtle text-danger"><i class="bi bi-gender-female"></i></div>
                    </div>
                </div>
            </div>
            <div class="card vr-stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small">Authorized Visitors Loaded</div>
                            <div class="display-6 fw-bold mb-0" id="authorizedVisitorCount">0</div>
                        </div>
                        <div class="vr-stat-icon bg-success-subtle text-success"><i class="bi bi-people-fill"></i></div>
                    </div>
                </div>
            </div>
            <div class="card vr-stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small">Selected Registration Progress</div>
                            <div class="display-6 fw-bold mb-0" id="registrationProgressStat">0%</div>
                        </div>
                        <div class="vr-stat-icon bg-warning-subtle text-warning"><i class="bi bi-hourglass-split"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white vr-main-card p-4">
        <ul class="nav nav-tabs vr-tabs" id="regTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="tab1-tab" data-bs-toggle="tab" data-bs-target="#tab1" type="button">1. Resident & Visitor</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab2-tab" data-bs-toggle="tab" data-bs-target="#tab2" type="button" disabled>2. Visitor Details</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab3-tab" data-bs-toggle="tab" data-bs-target="#tab3" type="button" disabled>3. Upload Documents</button>
            </li>
        </ul>

        <div class="progress vr-progress my-3">
            <div id="progressBar" class="progress-bar bg-success" style="width:33%;">Step 1 of 3</div>
        </div>

        <div class="tab-content vr-stage">
            <div class="tab-pane fade show active" id="tab1" role="tabpanel">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-body">
                        <div class="vr-filter-bar mb-3">
                            <div id="genderFilter" class="nav nav-pills">
                            <button class="nav-link active" type="button" data-sex="Male"><i class="bi bi-gender-male me-1"></i> Male</button>
                            <button class="nav-link" type="button" data-sex="Female"><i class="bi bi-gender-female me-1"></i> Female</button>
                            </div>
                            <div class="vr-filter-tools">
                                <span class="vr-chip"><i class="bi bi-building me-1 text-primary"></i><span id="selectedDormLabel">No dorm selected</span></span>
                                <span class="vr-chip"><i class="bi bi-list-check me-1 text-success"></i><span id="visitorLoadState">Waiting for Resident selection</span></span>
                            </div>
                        </div>

                        <div class="row g-3 align-items-end">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Select Resident</label>
                                <select id="pdlSelect" class="form-select select2" data-placeholder="Search Resident">
                                    <option value=""></option>
                                    <?php foreach ($malePdls as $pdl): ?>
                                        <?php $fullName = preg_replace('/\s+/', ' ', trim(($pdl['pdl_fname'] ?? '') . ' ' . ($pdl['pdl_mname'] ?? '') . ' ' . ($pdl['pdl_lname'] ?? '') . ' ' . ($pdl['pdl_suffix'] ?? ''))); ?>
                                        <option value="<?= (int) $pdl['id'] ?>" data-sex="Male" data-dorm="<?= e($pdl['dorm_no'] ?? '') ?>">
                                            <?= e($fullName) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Dorm No.</label>
                                <input id="dormNo" class="form-control" readonly>
                            </div>

                            <div class="col-md-3">
                                <button type="button" id="btnSelectPDL" class="btn btn-primary w-100">
                                    <i class="bi bi-search me-1"></i> Load Visitors
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="fw-bold mb-0"><i class="bi bi-people-fill me-1"></i> Authorized Visitors</h6>
                    <div class="d-flex gap-2 flex-wrap">
                        <span class="vr-chip"><i class="bi bi-shield-check me-1 text-success"></i><span id="verifiedCount">0 verified</span></span>
                        <span class="vr-chip"><i class="bi bi-pencil-square me-1 text-warning"></i><span id="inProgressCount">0 in progress</span></span>
                    </div>
                </div>

                <div class="card-1 border-0">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table id="visitorTable" class="table vr-table table-hover align-middle mb-0 w-100">
                                <thead class="table-light">
                                    <tr>
                                        <th>Visitor Name</th>
                                        <th>Sex</th>
                                        <th>Age</th>
                                        <th>Relationship</th>
                                        <th>Address</th>
                                        <th>Contact</th>
                                        <th>Status</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="tab-pane fade" id="tab2" role="tabpanel">
                <div class="vr-summary-banner mb-4">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <div class="fw-bold">Visitor Detail Review</div>
                            <div class="text-muted small">Validate the identity record, capture a usable photo, and confirm emergency contact details before moving to document intake.</div>
                        </div>
                        <span class="vr-chip"><i class="bi bi-person-badge me-1 text-primary"></i><span id="selectedVisitorName">No visitor selected</span></span>
                    </div>
                </div>

                <h6 class="mb-3">Visitor Photo</h6>

                <div class="row align-items-start">
                    <div class="col-md-4 text-center">
                        <div class="vr-photo-frame">
                            <div class="photo-wrapper mx-auto">
                                <img id="photoPreview" src="<?= BASE_URL ?>/assets/images/demo-scene.svg" class="img-fluid rounded border" style="width:220px;height:220px;object-fit:cover;" alt="Visitor Photo Preview">
                                <video id="cameraVideo" class="rounded border d-none" autoplay playsinline></video>
                            </div>
                            <canvas id="cameraCanvas" class="d-none"></canvas>
                            <div class="vr-photo-meta mt-3">
                                <span class="vr-chip"><i class="bi bi-image me-1 text-info"></i><span id="photoStateLabel">No photo confirmed</span></span>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-8">
                        <label class="form-label">Upload Photo</label>
                        <input type="file" class="form-control mb-3" id="visitorPhotoInput" name="visitor_photo" accept="image/jpeg,image/png">

                        <div class="mb-3">
                            <button type="button" class="btn btn-sm btn-primary" id="btnStartCamera"><i class="bi bi-camera-video me-1"></i>Start Camera</button>
                            <button type="button" class="btn btn-sm btn-success d-none" id="btnCapturePhoto"><i class="bi bi-camera-fill me-1"></i>Capture</button>
                            <button type="button" class="btn btn-sm btn-secondary d-none" id="btnStopCamera"><i class="bi bi-stop-circle me-1"></i>Stop</button>
                        </div>

                        <div class="d-none" id="photoActionArea">
                            <button type="button" class="btn btn-sm btn-success me-2" id="btnConfirmPhoto"><i class="bi bi-check2-circle me-1"></i>Confirm</button>
                            <button type="button" class="btn btn-sm btn-danger" id="btnRemovePhoto"><i class="bi bi-trash me-1"></i>Remove</button>
                        </div>
                    </div>
                </div>

                <h6 class="mb-3 mt-4">Visitor Details</h6>
                <form id="visitorDetailsForm">
                    <input type="hidden" id="tab2_pdl_id" name="pdl_id">
                    <input type="hidden" id="tab2_v_id" name="visitor_source_id">
                    <input type="hidden" id="registration_id" name="registration_id">

                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label">First Name</label><input type="text" class="form-control" id="tab2_firstname" name="firstname" readonly></div>
                        <div class="col-md-4"><label class="form-label">Middle Name</label><input type="text" class="form-control" id="tab2_middlename" name="middlename" readonly></div>
                        <div class="col-md-4"><label class="form-label">Last Name</label><input type="text" class="form-control" id="tab2_lastname" name="lastname" readonly></div>
                        <div class="col-md-2"><label class="form-label">Suffix</label><input type="text" class="form-control" id="tab2_suffix" name="suffix" readonly></div>
                        <div class="col-md-2"><label class="form-label">Age</label><input type="number" class="form-control" id="tab2_age" name="age" readonly></div>
                        <div class="col-md-2"><label class="form-label">Sex</label><input type="text" class="form-control" id="tab2_sex" name="sex" readonly></div>
                        <div class="col-md-4"><label class="form-label">Relationship</label><input type="text" class="form-control" id="tab2_relationship" readonly></div>
                        <div class="col-md-6"><label class="form-label">Address</label><input type="text" class="form-control" id="tab2_loc_add" name="loc_add" readonly></div>
                        <div class="col-md-4"><label class="form-label">Contact No.</label><input type="text" class="form-control" id="tab2_contact_no" name="contact_no" readonly></div>
                    </div>

                    <hr class="my-4">
                    <h6 class="mb-3">Emergency Contact Information</h6>

                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label">Emergency Contact Name</label><input type="text" class="form-control" name="ec_name"></div>
                        <div class="col-md-4"><label class="form-label">Emergency Contact No.</label><input type="text" class="form-control" name="ec_contactno"></div>
                        <div class="col-md-4"><label class="form-label">Emergency Address</label><input type="text" class="form-control" name="ec_address"></div>
                    </div>

                    <button type="submit" id="btnSaveVisitor" class="btn btn-success mt-4 w-100"><i class="bi bi-arrow-right-circle me-1"></i>Confirm & Continue</button>
                </form>
            </div>

            <div class="tab-pane fade" id="tab3" role="tabpanel">
                <div class="vr-summary-banner mb-4">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <div class="fw-bold">Document Completion Stage</div>
                            <div class="text-muted small">Upload the required files one by one, verify each preview, then submit the full registration for final validation.</div>
                        </div>
                        <span class="vr-chip"><i class="bi bi-files me-1 text-success"></i><span id="documentProgressLabel">0 of <?= (int) $documentCountMax ?> documents uploaded</span></span>
                    </div>
                </div>

                <form id="documentUploadForm" enctype="multipart/form-data">
                    <input type="hidden" name="registration_id" id="doc_registration_id">

                    <h6 class="fw-bold mb-3">Upload Supporting Documents</h6>
                    <p class="text-muted small">Please review each file carefully before confirming upload.</p>

                    <div class="alert alert-info border-0 rounded-4 small">
                        Valid ID, Birth Certificate, and Cohabitation Certificate are optional supporting documents. Wife, Husband, or Spouse needs Marriage Certificate.
                    </div>

                    <?php foreach ($documents as $doc): ?>
                        <div class="card mb-3 document-card vr-document-card" data-doc="<?= e($doc['key']) ?>">
                            <div class="card-body">
                                <h6 class="fw-bold">
                                    <i class="bi bi-file-earmark-text text-<?= e($doc['icon']) ?> me-2"></i>
                                    <?= e($doc['label']) ?>
                                    <span class="badge text-bg-light requirement-rule-label ms-2">Optional</span>
                                </h6>

                                <div class="row g-2 align-items-end mt-2">
                                    <?php if ($doc['key'] === 'valid_id'): ?>
                                        <div class="col-md-12">
                                            <label class="form-label small fw-semibold text-muted">Accepted ID Type</label>
                                            <select class="form-select valid-id-type-select" name="valid_id_type" data-placeholder="Select accepted ID type">
                                                <option value=""></option>
                                                <?php foreach ($validIdTypes as $idType): ?>
                                                    <option value="<?= e($idType) ?>"><?= e($idType) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    <?php endif; ?>
                                    <div class="col-md-6">
                                        <input type="file" class="form-control doc-file" name="<?= e($doc['column']) ?>" accept=".pdf,.jpg,.jpeg,.png">
                                    </div>
                                    <div class="col-md-6">
                                        <input type="text" class="form-control cert-number" name="<?= e($doc['cert_field']) ?>" placeholder="Reference No. (optional)">
                                    </div>
                                </div>

                                <div class="mt-3 confirm-area d-none">
                                    <div class="document-safety-note mb-2">
                                        <div class="fw-semibold"><i class="bi bi-shield-exclamation me-1 text-warning"></i>Review recommended before upload</div>
                                        <div class="small text-muted">Open the preview first and confirm this is the correct document. This helps prevent wrong or harmful document injection.</div>
                                        <div class="small fw-semibold mt-1 review-state-label text-warning">Awaiting document review or skip confirmation.</div>
                                    </div>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <button type="button" class="btn btn-info btn-sm btn-review-selected-file">
                                            <i class="bi bi-eye me-1"></i>Review Document
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm btn-skip-review">
                                            <i class="bi bi-forward me-1"></i>Skip Review
                                        </button>
                                        <button type="button" class="btn btn-success btn-sm btn-confirm-upload" disabled>
                                            <i class="bi bi-cloud-upload me-1"></i>Confirm Upload
                                        </button>
                                    </div>
                                </div>

                                <div class="progress mt-2 d-none upload-progress">
                                    <div class="progress-bar progress-bar-striped progress-bar-animated" style="width:0%"></div>
                                </div>

                                <div class="action-area mt-3 d-none"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <button type="button" id="btnSubmitRegistration" class="btn btn-success w-100 mt-3"><i class="bi bi-check2-square me-1"></i>Submit Registration</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
const VISITOR_REGISTRATION_BOOTSTRAP = {
    actionUrl: <?= json_encode(BASE_URL . '/visitor-registration-action') ?>,
    defaultPhoto: <?= json_encode(BASE_URL . '/assets/images/demo-scene.svg') ?>,
    documentCountMax: <?= (int) $documentCountMax ?>,
    validIdTypes: <?= json_encode(array_values($validIdTypes), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
    allPdl: {
        Male: <?= json_encode(array_map(static function ($pdl) {
            $fullName = preg_replace('/\s+/', ' ', trim(($pdl['pdl_fname'] ?? '') . ' ' . ($pdl['pdl_mname'] ?? '') . ' ' . ($pdl['pdl_lname'] ?? '') . ' ' . ($pdl['pdl_suffix'] ?? '')));
            return ['id' => (int) $pdl['id'], 'text' => $fullName, 'sex' => 'Male', 'dorm' => trim((string) ($pdl['dorm_no'] ?? ''))];
        }, $malePdls), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
        Female: <?= json_encode(array_map(static function ($pdl) {
            $fullName = preg_replace('/\s+/', ' ', trim(($pdl['pdl_fname'] ?? '') . ' ' . ($pdl['pdl_mname'] ?? '') . ' ' . ($pdl['pdl_lname'] ?? '') . ' ' . ($pdl['pdl_suffix'] ?? '')));
            return ['id' => (int) $pdl['id'], 'text' => $fullName, 'sex' => 'Female', 'dorm' => trim((string) ($pdl['dorm_no'] ?? ''))];
        }, $femalePdls), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
    }
};
</script>

<script>
$(function () {
    const ACTION_URL = VISITOR_REGISTRATION_BOOTSTRAP.actionUrl;
    const DEFAULT_PHOTO = VISITOR_REGISTRATION_BOOTSTRAP.defaultPhoto;
    const DOCUMENT_COUNT_MAX = Math.max(1, parseInt(VISITOR_REGISTRATION_BOOTSTRAP.documentCountMax || 4, 10));
    const VALID_ID_TYPES = VISITOR_REGISTRATION_BOOTSTRAP.validIdTypes || [];
    const allPdl = VISITOR_REGISTRATION_BOOTSTRAP.allPdl;
    let registrationId = null;
    let cameraStream = null;
    let capturedBlob = null;
    let photoConfirmed = false;
    let photoDirty = false;
    let activeVisitors = [];
    let selectedVisitor = null;
    let visitorTable = null;
    let visitorLoadRequestId = 0;
    const tabButtons = {
        0: document.querySelector('#regTabs button[data-bs-target="#tab1"]'),
        1: document.querySelector('#regTabs button[data-bs-target="#tab2"]'),
        2: document.querySelector('#regTabs button[data-bs-target="#tab3"]')
    };

    function showTab(index) {
        Object.values(tabButtons).forEach((btn) => btn.disabled = true);
        if (!tabButtons[index]) return;
        tabButtons[index].disabled = false;
        new bootstrap.Tab(tabButtons[index]).show();
        const percent = ((index + 1) / 3) * 100;
        $('#progressBar').css('width', percent + '%').text(`Step ${index + 1} of 3`);
    }

    function enableTab(index) {
        if (tabButtons[index]) tabButtons[index].disabled = false;
    }

    function setDormLabel(text) {
        $('#selectedDormLabel').text(text || 'No dorm selected');
    }

    function setLoadState(text) {
        $('#visitorLoadState').text(text || 'Waiting for Resident selection');
    }

    function updateRegistrationProgress(percent) {
        const safe = Math.max(0, Math.min(100, parseInt(percent || 0, 10)));
        $('#registrationProgressStat').text(safe + '%');
    }

    function updatePhotoState(text) {
        $('#photoStateLabel').text(text || 'No photo confirmed');
    }

    function updateDocumentProgress() {
        $('.document-card').each(function () {
            const hasFile = $(this).find('.action-area').children().length > 0;
            $(this).toggleClass('complete', hasFile);
        });

        const visibleCards = $('.document-card:not(.d-none)');
        const visibleMax = visibleCards.length || DOCUMENT_COUNT_MAX;
        const visibleComplete = visibleCards.filter('.complete').length;
        $('#documentProgressLabel').text(`${visibleComplete} of ${visibleMax} shown documents uploaded`);
    }

    function updateDocumentCompletionPercent() {
        const visibleCards = $('.document-card:not(.d-none)');
        const visibleMax = visibleCards.length || DOCUMENT_COUNT_MAX;
        const visibleComplete = visibleCards.filter('.complete').length;
        updateRegistrationProgress(Math.round((visibleComplete / visibleMax) * 100));
    }

    function selectedFilePreviewHtml(file, objectUrl) {
        const fileName = $('<div>').text(file.name || 'Selected document').html();
        const isPdf = (file.type || '').toLowerCase().includes('pdf') || (file.name || '').toLowerCase().endsWith('.pdf');
        const preview = isPdf
            ? `<iframe src="${objectUrl}" width="100%" height="620px" style="border:1px solid #e2e8f0;border-radius:14px;"></iframe>`
            : `<img src="${objectUrl}" class="img-fluid rounded border" alt="Document Preview">`;

        return `
            <div class="text-start mb-3">
                <div class="alert alert-warning border-0 rounded-4">
                    <div class="fw-bold mb-1"><i class="bi bi-shield-exclamation me-1"></i>Document Safety Review</div>
                    <div class="small">Confirm this preview is the correct document before upload. If the document looks wrong, suspicious, unreadable, or unrelated, cancel and choose another file.</div>
                </div>
                <div class="small text-muted mb-2">Selected file: <strong>${fileName}</strong></div>
            </div>
            ${preview}
        `;
    }

    function setDocumentReviewState(card, status) {
        const state = status === 'reviewed' ? 'reviewed' : (status === 'skipped' ? 'skipped' : '');
        card.attr('data-review-status', state);
        card.find('.btn-confirm-upload').prop('disabled', state === '');

        const label = card.find('.review-state-label');
        if (state === 'reviewed') {
            label.removeClass('text-warning text-secondary').addClass('text-success').text('Reviewed and confirmed safe by uploader.');
        } else if (state === 'skipped') {
            label.removeClass('text-warning text-success').addClass('text-secondary').text('Uploader skipped preview review and accepted responsibility.');
        } else {
            label.removeClass('text-success text-secondary').addClass('text-warning').text('Awaiting document review or skip confirmation.');
        }
    }

    function resetDocumentReviewState(card) {
        card.removeAttr('data-review-status');
        card.find('.btn-confirm-upload').prop('disabled', true);
        setDocumentReviewState(card, '');
    }

    function reviewSelectedDocument(card) {
        const fileInput = card.find('.doc-file')[0];
        if (!fileInput || !fileInput.files.length) {
            Swal.fire('No File Selected', 'Please choose a document first.', 'warning');
            return;
        }

        const file = fileInput.files[0];
        const objectUrl = URL.createObjectURL(file);
        Swal.fire({
            title: 'Review Document Before Upload',
            html: selectedFilePreviewHtml(file, objectUrl),
            width: 980,
            showCancelButton: true,
            confirmButtonText: 'Confirm Safe',
            cancelButtonText: 'Cancel',
            showCloseButton: true,
            willClose: () => URL.revokeObjectURL(objectUrl)
        }).then((result) => {
            if (result.isConfirmed) {
                setDocumentReviewState(card, 'reviewed');
                Swal.fire({ icon: 'success', title: 'Document Reviewed', text: 'You may now confirm the upload.', timer: 1300, showConfirmButton: false });
            }
        });
    }

    function updateVisitorTableStats(rows) {
        const total = rows.length;
        const verified = rows.filter((row) => (row.registration_status || '').toLowerCase() === 'verified').length;
        const inProgress = rows.filter((row) => row.has_registration && (row.registration_status || '').toLowerCase() !== 'verified').length;
        $('#authorizedVisitorCount').text(total);
        $('#verifiedCount').text(`${verified} verified`);
        $('#inProgressCount').text(`${inProgress} in progress`);
    }

    function getVisitorActionButton(visitor) {
        if ((visitor.registration_status || '').toLowerCase() === 'verified') {
            return '<button class="btn-sm btn-success" disabled><i class="bi bi-check-circle me-1"></i>Verified</button>';
        }

        if (visitor.has_registration) {
            return `<button class="btn btn-sm btn-warning continueRegistration" data-id="${visitor.id || ''}" data-reg="${visitor.registration_id}"><i class="bi bi-arrow-repeat me-1"></i>Continue Registration</button>`;
        }

        return `<button class="btn btn-sm btn-primary selectVisitor" data-id="${visitor.id || ''}"><i class="bi bi-person-plus me-1"></i>Start Registration</button>`;
    }

    function buildVisitorTableRows(rows) {
        return rows.map((visitor) => {
            const fullName = [visitor.visitor_fname, visitor.visitor_mname, visitor.visitor_lname, visitor.visitor_suffix]
                .filter(Boolean)
                .join(' ');
            const status = (visitor.registration_status || 'Not Submitted');
            const badgeClass = status.toLowerCase() === 'verified'
                ? 'success'
                : (visitor.has_registration ? 'warning' : 'secondary');
            const actionBtn = getVisitorActionButton(visitor);

            return [
                `
                    <div class="fw-semibold">${fullName}</div>
                    <div class="small text-muted">Authorized visitor ID #${visitor.id}</div>
                `,
                visitor.visitor_sex || '',
                visitor.visitor_age || '',
                visitor.visitor_relationship || '',
                visitor.visitor_address || '',
                visitor.visitor_contact || '',
                `
                    <span class="badge text-bg-${badgeClass}">${status}</span>
                    <div class="small text-muted mt-1">${visitor.document_count || 0}/${DOCUMENT_COUNT_MAX} docs${visitor.has_photo ? ' | photo ready' : ''}</div>
                `,
                actionBtn,
            ];
        });
    }

    function syncVisitorRowData(rows) {
        $('#visitorTable tbody tr').each(function (index) {
            const visitor = rows[index];
            $(this).attr('data-id', visitor ? visitor.id : '');
        });
    }

    function resetVisitorTable(message) {
        activeVisitors = [];
        updateVisitorTableStats([]);

        if (visitorTable) {
            visitorTable.clear();
            visitorTable.rows.add([[
                '',
                '',
                '',
                '',
                '',
                '',
                `<div class="text-center text-muted py-3">${message}</div>`,
                '',
            ]]).draw(false);
            $('#visitorTable tbody tr').removeAttr('data-id');
        } else {
            const $tbody = $('#visitorTable tbody');
            $tbody.html(`<tr><td colspan="8" class="text-center text-muted py-4">${message}</td></tr>`);
        }
    }

    function stopCamera() {
        if (cameraStream) {
            cameraStream.getTracks().forEach((track) => track.stop());
            cameraStream = null;
        }
        $('#cameraVideo').addClass('d-none');
        $('#btnCapturePhoto,#btnStopCamera').addClass('d-none');
        $('#btnStartCamera').removeClass('d-none');
    }

    function resetPhotoUi() {
        stopCamera();
        capturedBlob = null;
        photoConfirmed = false;
        photoDirty = false;
        $('#visitorPhotoInput').val('');
        $('#photoPreview').attr('src', DEFAULT_PHOTO).removeClass('d-none');
        $('#photoActionArea').addClass('d-none');
        updatePhotoState('No photo confirmed');
    }

    function resetDocumentsUi() {
        $('.document-card').each(function () {
            $(this).find('.doc-file').val('');
            $(this).find('.cert-number').val('');
            $(this).find('.confirm-area,.upload-progress,.action-area').addClass('d-none');
            $(this).find('.action-area').empty();
            $(this).find('.progress-bar').css('width', '0%');
            $(this).removeClass('complete');
        });
        $('.valid-id-type-select').val(null).trigger('change');
        updateRequirementGuidance();
        updateDocumentProgress();
    }

    function buildPdlOptions(sex) {
        const list = allPdl[sex] || [];
        const $select = $('#pdlSelect');
        $select.empty().append('<option value=""></option>');
        list.forEach((pdl) => {
            const option = new Option(pdl.text, pdl.id, false, false);
            $(option).attr('data-dorm', pdl.dorm).attr('data-sex', pdl.sex);
            $select.append(option);
        });
        $select.val(null).trigger('change');
        setLoadState(`Showing ${list.length} ${sex.toLowerCase()} Resident records`);
    }

    function renderVisitors(rows) {
        activeVisitors = rows || [];
        updateVisitorTableStats(activeVisitors);
        if (!visitorTable) {
            visitorTable = $('#visitorTable').DataTable({
                responsive: true,
                pageLength: 10,
                columnDefs: [{ orderable: false, targets: 7 }]
            });
        }

        visitorTable.clear();

        if (!rows.length) {
            visitorTable.rows.add([[
                '',
                '',
                '',
                '',
                '',
                '',
                '<div class="text-center text-muted py-3">No authorized visitors found.</div>',
                '',
            ]]);
            visitorTable.draw(false);
            $('#visitorTable tbody tr').removeAttr('data-id');
        } else {
            visitorTable.rows.add(buildVisitorTableRows(rows)).draw(false);
            syncVisitorRowData(rows);
        }

        setLoadState(rows.length ? `Loaded ${rows.length} authorized visitors` : 'No authorized visitors found for selected Resident');
    }

    function populateVisitorDetails(data) {
        selectedVisitor = data || {};
        $('#tab2_pdl_id').val(data.pdl_id || '');
        $('#tab2_v_id').val(data.id || '');
        $('#tab2_firstname').val(data.visitor_fname || data.firstname || '');
        $('#tab2_middlename').val(data.visitor_mname || data.middlename || '');
        $('#tab2_lastname').val(data.visitor_lname || data.lastname || '');
        $('#tab2_suffix').val(data.visitor_suffix || data.suffix || '');
        $('#tab2_age').val(data.visitor_age || data.age || '');
        $('#tab2_sex').val(data.visitor_sex || data.sex || '');
        $('#tab2_relationship').val(data.visitor_relationship || data.relationship || '');
        $('#tab2_loc_add').val(data.visitor_address || data.loc_add || '');
        $('#tab2_contact_no').val(data.visitor_contact || data.contact_no || '');
        $('input[name="ec_name"]').val(data.ec_name || '');
        $('input[name="ec_contactno"]').val(data.ec_contactno || '');
        $('input[name="ec_address"]').val(data.ec_address || '');
        const selectedName = [data.visitor_fname || data.firstname || '', data.visitor_mname || data.middlename || '', data.visitor_lname || data.lastname || '', data.visitor_suffix || data.suffix || '']
            .filter(Boolean).join(' ');
        $('#selectedVisitorName').text(selectedName || 'Selected visitor');

        if (data.visitor_photo_url) {
            $('#photoPreview').attr('src', data.visitor_photo_url).removeClass('d-none');
            $('#photoActionArea').removeClass('d-none');
            photoConfirmed = true;
            photoDirty = false;
            updatePhotoState('Photo on file');
        } else {
            resetPhotoUi();
        }
        updateRequirementGuidance();
    }

    function updateRequirementGuidance() {
        const age = parseInt((selectedVisitor || {}).visitor_age || (selectedVisitor || {}).age || 0, 10);
        const relationship = String((selectedVisitor || {}).visitor_relationship || '').toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim();
        const isMinor = age > 0 && age < 18;
        const isSpouse = ['wife', 'husband', 'spouse'].includes(relationship);
        const isLiveInPartner = ['live in partner', 'livein partner', 'live in', 'livein'].includes(relationship);
        const isChildRelation = ['daughter', 'son', 'child'].includes(relationship);
        const labels = {
            valid_id: 'Optional',
            birth_certificate: 'Optional',
            marriage_contract: 'Optional',
            cohabitation_certificate: 'Optional'
        };
        const visibleKeys = new Set(['valid_id']);

        if (isMinor || isChildRelation) {
            visibleKeys.add('birth_certificate');
        }

        if (isSpouse) {
            visibleKeys.add('marriage_contract');
            labels.marriage_contract = 'Required';
        } else if (isLiveInPartner) {
            visibleKeys.add('cohabitation_certificate');
        }

        $('.document-card').each(function () {
            const key = String($(this).data('doc') || '');
            $(this).toggleClass('d-none', !visibleKeys.has(key));
            const label = labels[key] || 'Optional';
            const badgeClass = label === 'Required' ? 'text-bg-danger' : (label === 'Alternative' ? 'text-bg-warning' : 'text-bg-light');
            $(this).find('.requirement-rule-label')
                .removeClass('text-bg-danger text-bg-warning text-bg-light')
                .addClass(badgeClass)
                .text(label);
        });

        updateDocumentProgress();
    }

    function renderDocumentAction($card, doc) {
        if (!doc.file_name) {
            $card.find('.action-area').addClass('d-none').empty();
            return;
        }

        const icon = doc.file_name.toLowerCase().endsWith('.pdf')
            ? '<i class="bi bi-file-earmark-pdf text-danger me-2"></i>'
            : '<i class="bi bi-file-earmark-image text-info me-2"></i>';
        const idType = doc.id_type
            ? `<div class="small text-muted mb-2">Accepted ID type: <strong>${$('<div>').text(doc.id_type).html()}</strong></div>`
            : '';

        $card.find('.action-area').removeClass('d-none').html(`
            <div class="fw-semibold text-primary mb-2">${icon}${doc.file_name}</div>
            ${idType}
            <button type="button" class="btn btn-sm btn-info btn-view-file me-1" data-path="${doc.file_url}"><i class="bi bi-eye me-1"></i>View</button>
            <a href="${doc.file_url}" class="btn btn-sm btn-secondary me-1" download><i class="bi bi-download me-1"></i>Download</a>
            <button type="button" class="btn btn-sm btn-danger btn-remove-file" data-column="${doc.column}"><i class="bi bi-trash me-1"></i>Remove</button>
        `);
    }

    function restoreDocuments(documents) {
        let uploadedCount = 0;
        $('.document-card').each(function () {
            const $card = $(this);
            const key = $card.data('doc');
            const doc = documents[key] || {};
            $card.find('.cert-number').val(doc.cert_no || '');
            if (key === 'valid_id') {
                const idType = doc.id_type || '';
                const $idTypeSelect = $card.find('.valid-id-type-select');
                if (idType && !$idTypeSelect.find('option').filter(function () { return this.value === idType; }).length) {
                    $idTypeSelect.append(new Option(idType, idType, true, true));
                }
                $idTypeSelect.val(idType || null).trigger('change');
            }
            $card.find('.confirm-area,.upload-progress').addClass('d-none');
            renderDocumentAction($card, doc);
            const hasFile = !!doc.file_name;
            $card.toggleClass('complete', hasFile);
            if (hasFile) {
                uploadedCount += 1;
            }
        });
        updateRequirementGuidance();
        updateDocumentProgress();
    }

    function visitorDetailsComplete(visitor) {
        return !!(
            visitor &&
            visitor.visitor_photo_url &&
            String(visitor.ec_name || '').trim() &&
            String(visitor.ec_contactno || '').trim() &&
            String(visitor.ec_address || '').trim()
        );
    }

    function documentsComplete(documents) {
        const hasDoc = (key) => {
            const doc = documents[key] || {};
            return !!String(doc.file_name || doc.file_path || '').trim();
        };
        const age = parseInt((selectedVisitor || {}).visitor_age || (selectedVisitor || {}).age || 0, 10);
        const relationship = String((selectedVisitor || {}).visitor_relationship || '').toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim();

        if (['wife', 'husband', 'spouse'].includes(relationship)) {
            return hasDoc('marriage_contract');
        }

        return true;
    }

    function firstIncompleteRegistrationTab(visitor, documents) {
        if (!visitorDetailsComplete(visitor)) {
            return 1;
        }

        if (!documentsComplete(documents || {})) {
            return 2;
        }

        return 2;
    }

    buildPdlOptions('Male');
    $('#pdlSelect').select2({ placeholder: 'Search Resident', allowClear: true, width: '100%' });
    visitorTable = $('#visitorTable').DataTable({
        responsive: true,
        pageLength: 10,
        columnDefs: [{ orderable: false, targets: 7 }]
    });

    $('#genderFilter button').on('click', function () {
        $('#genderFilter button').removeClass('active');
        $(this).addClass('active');
        buildPdlOptions($(this).data('sex'));
        $('#dormNo').val('');
        setDormLabel('No dorm selected');
        resetVisitorTable('Select a Resident to load visitors.');
        showTab(0);
    });

    $('#pdlSelect').on('change', function () {
        const dorm = $(this).find(':selected').data('dorm') || '';
        $('#dormNo').val(dorm);
        setDormLabel(dorm || 'No dorm selected');
        resetVisitorTable('Click "Load Visitors" to view the selected Resident visitors.');
        registrationId = null;
        selectedVisitor = null;
        $('#registration_id,#doc_registration_id').val('');
        resetPhotoUi();
        resetDocumentsUi();
        updateRegistrationProgress(0);
        $('#selectedVisitorName').text('No visitor selected');
        showTab(0);
    });

    $('#btnSelectPDL').on('click', function () {
        const pdlId = $('#pdlSelect').val();
        if (!pdlId) {
            Swal.fire('Warning', 'Please select a Resident.', 'warning');
            return;
        }

        const requestId = ++visitorLoadRequestId;
        setLoadState('Loading authorized visitors...');

        $.getJSON(ACTION_URL, { action: 'get_authorized_visitors', pdl_id: pdlId, _: Date.now() })
            .done((res) => {
                if (requestId !== visitorLoadRequestId) {
                    return;
                }
                if (res.status !== 'success') {
                    Swal.fire('Error', res.message || 'Unable to load visitors.', 'error');
                    return;
                }
                renderVisitors(res.data || []);
            })
            .fail(() => {
                if (requestId !== visitorLoadRequestId) {
                    return;
                }
                Swal.fire('Error', 'Failed to load authorized visitors.', 'error');
            });
    });

    $('#visitorTable tbody').on('click', '.selectVisitor', function () {
        const visitorId = $(this).data('id');
        const row = $(this).closest('tr');
        row.addClass('table-active').siblings().removeClass('table-active');
        resetPhotoUi();
        resetDocumentsUi();
        registrationId = null;
        selectedVisitor = null;
        $('#registration_id,#doc_registration_id').val('');

        if (!visitorId) {
            Swal.fire('Error', 'Unable to identify the selected visitor. Please reload the visitor list and try again.', 'error');
            return;
        }

        $.getJSON(ACTION_URL, { action: 'get_visitor_details', visitor_id: visitorId })
            .done((res) => {
                if (res.status !== 'success') {
                    Swal.fire('Error', res.message || 'Unable to load visitor details.', 'error');
                    return;
                }
                populateVisitorDetails(res.visitor || {});
                updateRegistrationProgress(0);
                enableTab(1);
                showTab(1);
            })
            .fail(() => Swal.fire('Error', 'Failed to load visitor details.', 'error'));
    });

    $('#visitorTable tbody').on('click', '.continueRegistration', function () {
        registrationId = $(this).data('reg');
        $('#registration_id,#doc_registration_id').val(registrationId);

        $.getJSON(ACTION_URL, { action: 'get_registration_data', registration_id: registrationId })
            .done((res) => {
                if (res.status !== 'success') {
                    Swal.fire('Error', res.message || 'No saved registration found.', 'error');
                    return;
                }
                const visitor = res.visitor || {};
                const documents = res.documents || {};
                populateVisitorDetails(visitor);
                restoreDocuments(documents);
                updateRegistrationProgress((res.progress || {}).completion_rate || 0);
                enableTab(1);
                enableTab(2);
                showTab(firstIncompleteRegistrationTab(visitor, documents));
            })
            .fail(() => Swal.fire('Error', 'Failed to load saved registration.', 'error'));
    });

    $('#visitorPhotoInput').on('change', function () {
        const file = this.files[0];
        if (!file) return;
        if (!['image/jpeg', 'image/png'].includes(file.type)) {
            Swal.fire('Error', 'Only JPG or PNG files are allowed.', 'error');
            this.value = '';
            return;
        }
        if (file.size > 2 * 1024 * 1024) {
            Swal.fire('Error', 'Photo must not exceed 2MB.', 'error');
            this.value = '';
            return;
        }
        stopCamera();
        capturedBlob = null;
        photoConfirmed = false;
        photoDirty = true;
        updatePhotoState('Photo selected, awaiting confirmation');
        const reader = new FileReader();
        reader.onload = (e) => {
            $('#photoPreview').attr('src', e.target.result).removeClass('d-none');
            $('#photoActionArea').removeClass('d-none');
        };
        reader.readAsDataURL(file);
    });

    $('#btnConfirmPhoto').on('click', function () {
        if (!capturedBlob && !$('#visitorPhotoInput')[0].files.length && !photoDirty) {
            Swal.fire('Warning', 'Select or capture a photo first.', 'warning');
            return;
        }
        photoConfirmed = true;
        updatePhotoState('Photo confirmed');
        Swal.fire({ icon: 'success', title: 'Photo Confirmed', timer: 1000, showConfirmButton: false });
    });

    $('#btnRemovePhoto').on('click', function () { resetPhotoUi(); });

    $('#btnStartCamera').on('click', async function () {
        try {
            cameraStream = await navigator.mediaDevices.getUserMedia({ video: { width: 500, height: 500 } });
            $('#cameraVideo').removeClass('d-none').get(0).srcObject = cameraStream;
            $('#photoPreview').addClass('d-none');
            $('#btnCapturePhoto,#btnStopCamera').removeClass('d-none');
            $('#btnStartCamera').addClass('d-none');
        } catch (error) {
            Swal.fire('Error', 'Camera access denied.', 'error');
        }
    });

    $('#btnCapturePhoto').on('click', function () {
        const video = document.getElementById('cameraVideo');
        const canvas = document.getElementById('cameraCanvas');
        const ctx = canvas.getContext('2d');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        ctx.drawImage(video, 0, 0);
        canvas.toBlob((blob) => {
            capturedBlob = blob;
            photoConfirmed = false;
            photoDirty = true;
            updatePhotoState('Captured photo, awaiting confirmation');
            const imageUrl = URL.createObjectURL(blob);
            $('#photoPreview').attr('src', imageUrl).removeClass('d-none');
            $('#cameraVideo').addClass('d-none');
            $('#photoActionArea').removeClass('d-none');
        }, 'image/jpeg', 0.9);
    });

    $('#btnStopCamera').on('click', function () {
        stopCamera();
        $('#photoPreview').removeClass('d-none');
    });

    $('#visitorDetailsForm').on('submit', function (e) {
        e.preventDefault();
        if ((capturedBlob || $('#visitorPhotoInput')[0].files.length || photoDirty) && !photoConfirmed) {
            Swal.fire('Warning', 'Please confirm the photo before continuing.', 'warning');
            return;
        }

        const formData = new FormData(this);
        formData.append('action', 'save_registration');
        if (capturedBlob) {
            formData.append('visitor_photo_camera', capturedBlob, 'camera.jpg');
        } else if ($('#visitorPhotoInput')[0].files.length) {
            formData.append('visitor_photo', $('#visitorPhotoInput')[0].files[0]);
        }

        $.ajax({
            url: ACTION_URL,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            beforeSend: function () {
                $('#btnSaveVisitor').prop('disabled', true).text('Saving...');
            },
            success: function (res) {
                $('#btnSaveVisitor').prop('disabled', false).text('Confirm & Continue');
                if (res.status !== 'success') {
                    Swal.fire('Error', res.message || 'Save failed.', 'error');
                    return;
                }
                registrationId = parseInt(res.registration_id, 10);
                $('#registration_id,#doc_registration_id').val(registrationId);
                photoDirty = false;
                photoConfirmed = true;
                updateRegistrationProgress((res.progress || {}).completion_rate || 0);
                Swal.fire({ icon: 'success', title: 'Visitor Saved', timer: 1200, showConfirmButton: false });
                enableTab(2);
                showTab(2);
            },
            error: function () {
                $('#btnSaveVisitor').prop('disabled', false).text('Confirm & Continue');
                Swal.fire('Error', 'Server error occurred while saving visitor.', 'error');
            }
        });
    });

    $('#documentUploadForm').on('change', '.doc-file', function () {
        const card = $(this).closest('.document-card');
        card.find('.action-area').addClass('d-none').empty();
        card.find('.upload-progress').addClass('d-none');
        card.find('.progress-bar').css('width', '0%');
        card.find('.confirm-area').toggleClass('d-none', !this.files.length);
        resetDocumentReviewState(card);

        if (this.files.length) {
            const file = this.files[0];
            Swal.fire({
                icon: 'info',
                title: 'Review Document First',
                html: 'Please preview <b>' + $('<div>').text(file.name).html() + '</b> before upload to make sure it is the correct and safe document.<br><br>You may also skip review, but the action will be recorded under your account.',
                showCancelButton: true,
                confirmButtonText: 'Review Now',
                cancelButtonText: 'Skip for Now'
            }).then((result) => {
                if (result.isConfirmed) {
                    reviewSelectedDocument(card);
                }
            });
        }
    });

    $('#documentUploadForm').on('click', '.btn-review-selected-file', function (e) {
        e.preventDefault();
        reviewSelectedDocument($(this).closest('.document-card'));
    });

    $('#documentUploadForm').on('click', '.btn-skip-review', function (e) {
        e.preventDefault();
        const card = $(this).closest('.document-card');
        Swal.fire({
            title: 'Skip Document Review?',
            text: 'You can proceed, but this skip action will be recorded under your account.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Skip Review and Continue',
            cancelButtonText: 'Review Instead'
        }).then((result) => {
            if (result.isConfirmed) {
                setDocumentReviewState(card, 'skipped');
            } else {
                reviewSelectedDocument(card);
            }
        });
    });

    $('#documentUploadForm').on('click', '.btn-confirm-upload', function (e) {
        e.preventDefault();
        const card = $(this).closest('.document-card');
        const fileInput = card.find('.doc-file')[0];
        const certInput = card.find('.cert-number');
        const regId = $('#doc_registration_id').val();
        if (!regId) {
            Swal.fire('Error', 'Save visitor details first.', 'error');
            return;
        }
        if (!fileInput.files.length) {
            Swal.fire('Warning', 'No file selected.', 'warning');
            return;
        }
        const reviewStatus = card.attr('data-review-status') || '';
        if (!['reviewed', 'skipped'].includes(reviewStatus)) {
            Swal.fire('Review Required', 'Please review the document or explicitly skip review before uploading.', 'warning');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'upload_document');
        formData.append('registration_id', regId);
        formData.append('document_column', fileInput.name);
        formData.append('certificate_number_field', certInput.attr('name'));
        formData.append('certificate_number_value', certInput.val());
        formData.append('document_review_status', reviewStatus);
        formData.append(fileInput.name, fileInput.files[0]);
        if (fileInput.name === 'valid_id') {
            const validIdType = String(card.find('.valid-id-type-select').val() || '').trim();
            if (!validIdType) {
                Swal.fire('Valid ID Type Required', 'Please select which accepted ID was presented.', 'warning');
                return;
            }
            formData.append('valid_id_type', validIdType);
        }

        const progressWrapper = card.find('.upload-progress');
        const progressBar = progressWrapper.find('.progress-bar');
        progressWrapper.removeClass('d-none');
        progressBar.css('width', '0%');

        $.ajax({
            url: ACTION_URL,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            xhr: function () {
                const xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function (evt) {
                    if (evt.lengthComputable) {
                        progressBar.css('width', Math.round((evt.loaded / evt.total) * 100) + '%');
                    }
                });
                return xhr;
            },
            success: function (res) {
                progressWrapper.addClass('d-none');
                card.find('.confirm-area').addClass('d-none');
                if (res.status !== 'success') {
                    Swal.fire('Error', res.message || 'Upload failed.', 'error');
                    return;
                }
                renderDocumentAction(card, res.document || {});
                card.addClass('complete');
                updateDocumentProgress();
                updateDocumentCompletionPercent();
                Swal.fire({ icon: 'success', title: 'Document Uploaded Successfully', timer: 1200, showConfirmButton: false });
            },
            error: function () {
                progressWrapper.addClass('d-none');
                Swal.fire('Error', 'Upload failed.', 'error');
            }
        });
    });

    $('#documentUploadForm').on('click', '.btn-view-file', function (e) {
        e.preventDefault();
        const path = $(this).data('path');
        const isPdf = (path || '').toLowerCase().endsWith('.pdf');
        const previewHtml = isPdf ? `<iframe src="${path}" width="100%" height="600px" style="border:none;"></iframe>` : `<img src="${path}" class="img-fluid" alt="Document Preview">`;
        Swal.fire({ title: 'Document Preview', html: previewHtml, width: 900, showCloseButton: true, showConfirmButton: false });
    });

    $('#documentUploadForm').on('click', '.btn-remove-file', function () {
        const regId = $('#doc_registration_id').val();
        const column = $(this).data('column');
        const card = $(this).closest('.document-card');
        if (!regId || !column) return;
        Swal.fire({
            title: 'Remove Document?',
            text: 'This uploaded file will be removed from the registration.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, Remove',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (!result.isConfirmed) return;
            $.post(ACTION_URL, { action: 'remove_document', registration_id: regId, document_column: column }, function (res) {
                if (res.status !== 'success') {
                    Swal.fire('Error', res.message || 'Unable to remove document.', 'error');
                    return;
                }
                card.find('.doc-file').val('');
                if (column === 'valid_id') {
                    card.find('.valid-id-type-select').val(null).trigger('change');
                }
                card.find('.confirm-area').addClass('d-none');
                card.removeClass('complete');
                card.find('.action-area').addClass('d-none').empty();
                updateDocumentProgress();
                updateDocumentCompletionPercent();
                Swal.fire({ icon: 'success', title: 'Document Removed', timer: 1000, showConfirmButton: false });
            }, 'json').fail(() => Swal.fire('Error', 'Unable to remove document.', 'error'));
        });
    });

    $('#btnSubmitRegistration').on('click', function (e) {
        e.preventDefault();
        const regId = $('#doc_registration_id').val();
        if (!regId) {
            Swal.fire('Error', 'No registration selected.', 'error');
            return;
        }

        Swal.fire({
            title: 'Submit Registration?',
            text: 'This will verify the visitor if requirements are complete.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, Submit',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (!result.isConfirmed) return;
            $.ajax({
                url: ACTION_URL,
                type: 'POST',
                dataType: 'json',
                data: { action: 'final_submit', registration_id: regId },
                beforeSend: function () {
                    $('#btnSubmitRegistration').prop('disabled', true).text('Submitting...');
                },
                success: function (res) {
                    $('#btnSubmitRegistration').prop('disabled', false).text('Submit Registration');
                    if (res.status !== 'success') {
                        Swal.fire('Error', res.message || 'Submission failed.', 'error');
                        return;
                    }
                    updateRegistrationProgress((res.progress || {}).completion_rate || 0);
                    const verified = (res.visitor_status || '').toLowerCase() === 'verified';
                    const missing = Array.isArray(res.missing_requirements) && res.missing_requirements.length
                        ? '<div class="text-start small mt-3"><strong>Missing:</strong> ' + res.missing_requirements.map((item) => $('<div>').text(item).html()).join(', ') + '</div>'
                        : '';
                    Swal.fire({
                        icon: verified ? 'success' : 'warning',
                        title: verified ? 'Verified!' : 'Incomplete Registration',
                        html: (verified ? 'Visitor successfully verified.' : (res.message || 'This registration will be saved but it will not proceed for ID issuance since you have incomplete registration.')) + missing
                    })
                        .then(() => window.location.reload());
                },
                error: function () {
                    $('#btnSubmitRegistration').prop('disabled', false).text('Submit Registration');
                    Swal.fire('Error', 'Server error occurred.', 'error');
                }
            });
        });
    });

    resetVisitorTable('Select a Resident to load visitors.');
    $('.valid-id-type-select').select2({
        width: '100%',
        placeholder: 'Select accepted ID type',
        allowClear: true,
        tags: true
    });
    updateRegistrationProgress(0);
    updatePhotoState('No photo confirmed');
    updateRequirementGuidance();
    updateDocumentProgress();
    setDormLabel('No dorm selected');
});
</script>

<?php require_once ROOT_PATH . '/modules/layout/footer.php'; ?>
