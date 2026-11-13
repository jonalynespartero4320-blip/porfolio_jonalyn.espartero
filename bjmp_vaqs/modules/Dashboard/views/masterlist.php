<?php
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/functions.php';
require_once ROOT_PATH . '/modules/Auth/controllers/MasterlistController.php';

$canCaseCreate = can('case.create');
$canCaseEdit = can('case.edit');
$canCaseDelete = can('case.delete');
$canCourtCreate = can('case.create');
$canCourtEdit = can('case.edit');
$canCourtDelete = can('case.delete');
$canDormCreate = can('case.create');
$canDormEdit = can('case.edit');
$canDormDelete = can('case.delete');
$canRelationshipCreate = can('case.create');
$canRelationshipEdit = can('case.edit');
$canRelationshipDelete = can('case.delete');
$canRequirementCreate = can('case.create');
$canRequirementEdit = can('case.edit');
$canRequirementDelete = can('case.delete');
$canValidIdTypeCreate = can('case.create');
$canValidIdTypeEdit = can('case.edit');
$canValidIdTypeDelete = can('case.delete');

MasterlistController::ensureRelationshipTable();
MasterlistController::ensureRequirementTable();
MasterlistController::ensureValidIdTypeTable();

/* ==================================================
   HANDLE ACTIONS (USING CONTROLLER)
================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';
    $activeTab = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($_POST['active_tab'] ?? 'case'));
    $result = null;

    // CASE
    if ($action === 'add_case') {
        require_permission('case.create');
        $result = MasterlistController::addCase($_POST['case_no'], $_POST['case_type']);
    }

    if ($action === 'edit_case') {
        require_permission('case.edit');
        $result = MasterlistController::editCase($_POST['case_id'], $_POST['case_no'], $_POST['case_type']);
    }

    if ($action === 'delete_case') {
        require_permission('case.delete');
        $result = MasterlistController::deleteCase($_POST['id']);
    }

    // COURT
    if ($action === 'add_court') {
        require_permission('case.create');
        $result = MasterlistController::addCourt($_POST['court_name']);
    }

    if ($action === 'edit_court') {
        require_permission('case.edit');
        $result = MasterlistController::editCourt($_POST['court_id'], $_POST['court_name']);
    }

    if ($action === 'delete_court') {
        require_permission('case.delete');
        $result = MasterlistController::deleteCourt($_POST['id']);
    }

    // DORM
    if ($action === 'add_dorm') {
        require_permission('case.create');
        $result = MasterlistController::addDorm($_POST['dorm_no'], $_POST['description']);
    }

    if ($action === 'edit_dorm') {
        require_permission('case.edit');
        $result = MasterlistController::editDorm($_POST['dorm_id'], $_POST['dorm_no'], $_POST['description']);
    }

    if ($action === 'delete_dorm') {
        require_permission('case.delete');
        $result = MasterlistController::deleteDorm($_POST['id']);
    }

    // RELATIONSHIP
    if ($action === 'add_relationship') {
        require_permission('case.create');
        $result = MasterlistController::addRelationship($_POST['relationship_name'] ?? '', $_POST['description'] ?? '');
    }

    if ($action === 'edit_relationship') {
        require_permission('case.edit');
        $result = MasterlistController::editRelationship($_POST['relationship_id'] ?? 0, $_POST['relationship_name'] ?? '', $_POST['description'] ?? '');
    }

    if ($action === 'delete_relationship') {
        require_permission('case.delete');
        $result = MasterlistController::deleteRelationship($_POST['id']);
    }

    // REQUIREMENT
    if ($action === 'add_requirement') {
        require_permission('case.create');
        $result = MasterlistController::addRequirement($_POST['requirement_name'] ?? '', $_POST['description'] ?? '', $_POST['is_active'] ?? 1);
    }

    if ($action === 'edit_requirement') {
        require_permission('case.edit');
        $result = MasterlistController::editRequirement($_POST['requirement_id'] ?? 0, $_POST['requirement_name'] ?? '', $_POST['description'] ?? '', $_POST['is_active'] ?? 1);
    }

    if ($action === 'delete_requirement') {
        require_permission('case.delete');
        $result = MasterlistController::deleteRequirement($_POST['id']);
    }

    // ACCEPTED VALID ID
    if ($action === 'add_valid_id_type') {
        require_permission('case.create');
        $result = MasterlistController::addValidIdType($_POST['id_type_name'] ?? '', $_POST['description'] ?? '', $_POST['is_active'] ?? 1);
    }

    if ($action === 'edit_valid_id_type') {
        require_permission('case.edit');
        $result = MasterlistController::editValidIdType($_POST['id_type_id'] ?? 0, $_POST['id_type_name'] ?? '', $_POST['description'] ?? '', $_POST['is_active'] ?? 1);
    }

    if ($action === 'delete_valid_id_type') {
        require_permission('case.delete');
        $result = MasterlistController::deleteValidIdType($_POST['id']);
    }

    if ($result) {
        $_SESSION['notification'] = [
            'type' => $result['status'],
            'message' => $result['message']
        ];
    }

    header("Location: " . BASE_URL . "/masterlist?tab=" . rawurlencode($activeTab));
    exit;
}

/* ==================================================
   LOAD UI
================================================== */
include ROOT_PATH . '/modules/layout/header.php';
include ROOT_PATH . '/modules/Dashboard/views/modals/masterlist_modal.php';

/* ==================================================
   FETCH DATA
================================================== */
$cases  = $pdo->query("SELECT * FROM case_masterlist")->fetchAll(PDO::FETCH_ASSOC);
$courts = $pdo->query("SELECT * FROM court_masterlist")->fetchAll(PDO::FETCH_ASSOC);
$dorms  = $pdo->query("SELECT * FROM dorm_masterlist")->fetchAll(PDO::FETCH_ASSOC);
$relationships = $pdo->query("SELECT * FROM relationship_masterlist ORDER BY relationship_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$requirements = $pdo->query("SELECT * FROM requirement_masterlist ORDER BY sort_order ASC, requirement_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$validIdTypes = $pdo->query("SELECT * FROM valid_id_type_masterlist ORDER BY sort_order ASC, id_type_name ASC")->fetchAll(PDO::FETCH_ASSOC);

$totalCases = count($cases);
$totalCourts = count($courts);
$totalDorms = count($dorms);
$totalRelationships = count($relationships);
$totalRequirements = count($requirements);
$totalValidIdTypes = count($validIdTypes);
$allowedMasterTabs = ['case', 'court', 'dorm', 'relationship', 'requirement', 'valid-id-type'];
$activeMasterTab = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($_GET['tab'] ?? 'case'));
if (!in_array($activeMasterTab, $allowedMasterTabs, true)) {
    $activeMasterTab = 'case';
}
?>

<style>
  .master-shell { display: grid; gap: .8rem; 
    /* background:
      linear-gradient(180deg, rgba(255, 255, 255, 0.86) 0%, rgba(248, 250, 252, 0.96) 100%); */
    border-radius: 28px;
    padding: 24px;}
  .master-hero {
    position: relative;
    overflow: hidden;
    border: 0;
    border-radius: 22px;
    background:
      linear-gradient(135deg, #062150, #447A61);
    box-shadow: 0 18px 42px rgba(15, 23, 42, 0.14);
  }
  .master-hero::after {
    content: "";
    position: absolute;
    right: -70px;
    bottom: -75px;
    width: 185px;
    height: 185px;
    border-radius: 50%;
    background: rgba(255,255,255,0.08);
  }
  .master-hero .card-body { position: relative; z-index: 1; padding: 1rem 1.15rem; }
  .master-hero h2 { font-size: clamp(1.45rem, 2vw, 2rem); }
  .master-hero p { font-size: .92rem; }
  .master-stat-grid { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: .75rem; }
  .master-stat-card {
    border: 0;
    border-radius: 20px;
    box-shadow: 0 16px 38px rgba(15, 23, 42, 0.08);
  }
  .master-stat-card { margin-bottom: 0; }
  .master-stat-card .card-body { padding: .85rem 1rem; }
  .master-stat-card .display-6 { font-size: 2rem; line-height: 1; }
  .master-stat-icon {
    width: 42px;
    height: 42px;
    border-radius: 14px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
  }
  .master-workspace-card {
    border: 0;
    border-radius: 22px;
    background: rgba(255,255,255,0.96);
    box-shadow: 0 14px 34px rgba(15,23,42,0.09);
    overflow: hidden;
  }
  .master-workspace-card > .card-header {
    padding: .75rem .9rem 0;
    background:
      linear-gradient(135deg, rgba(255,255,255,0.98), rgba(241,247,244,0.98));
    border-bottom: 1px solid rgba(8,19,31,0.08);
  }
  .master-workspace-card > .card-body {
    padding: .85rem .9rem .9rem;
  }
  .master-workspace-card .master-toolbar {
    border-bottom: 1px solid rgba(8,19,31,0.08);
    background: rgba(248,250,252,0.72);
    padding: .75rem .9rem;
  }
  .master-workspace-card h4 {
    font-size: 1.05rem;
  }
  .master-tab-nav { gap: .45rem; border-bottom: 0; margin-top: .55rem; }
  .master-tab-nav .nav-item { margin-bottom: -1px; }
  .master-tab-nav .nav-link {
    position: relative;
    border: 1px solid rgba(8,19,31,0.08);
    border-bottom: 0;
    border-radius: 14px 14px 0 0;
    padding: .58rem .85rem .72rem;
    background: linear-gradient(180deg, #f8fafc, #edf3f1);
    color: #334155;
    font-weight: 800;
    min-width: 110px;
    box-shadow: inset 0 -8px 14px rgba(15,23,42,0.04);
  }
  .master-tab-nav .nav-link::after {
    content: "";
    position: absolute;
    left: 16px;
    right: 16px;
    bottom: 5px;
    height: 3px;
    border-radius: 999px;
    background: transparent;
  }
  .master-tab-nav .nav-link.active {
    background: #fff;
    color: #447A61;
    border-color: rgba(15,91,52,0.18);
    box-shadow: 0 -8px 22px rgba(15,23,42,0.08);
  }
  .master-tab-nav .nav-link.active::after {
    background: linear-gradient(90deg, #062150, #447A61);
  }
  .master-table-card {
    background: transparent;
    margin-bottom: 0;
  }
  .master-table-heading {
    padding: .35rem .15rem .65rem;
  }
  .master-table-heading h5 {
    font-size: 1.05rem;
  }
  .master-table-area {
    /* border: 1px solid rgba(8,19,31,0.08); */
    border-radius: 16px;
    background: #fff;
    padding: .35rem;
  }
  .master-table-card .table {
    margin-bottom: 0;
  }
  .master-table-card .dataTables_wrapper .top,
  .master-table-card .dataTables_wrapper .bottom {
    margin-bottom: .4rem !important;
    margin-top: .55rem !important;
  }
  .master-toolbar .form-control { border-radius: 14px; }
  .master-action-row { display: flex; gap: .5rem; flex-wrap: wrap; }
  .master-row-actions { display: flex; gap: .35rem; flex-wrap: wrap; }
  @media (max-width: 991px) {
    .master-stat-grid { grid-template-columns: 1fr; }
  }
</style>

<div class="container-fluid py-3">
    <div class="master-shell">
        <div class="card master-hero text-white">
            <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <div class="text-uppercase small fw-semibold opacity-75 mb-2">Reference Data</div>
                    <h2 class="fw-bold mb-1"><i class="bi bi-journal-text me-2"></i>Masterlist Management</h2>
                    <!-- <p class="mb-0 opacity-75">Maintain case references, courts, and dorm records from one organized admin workspace.</p> -->
                </div>
                <div class="master-action-row">
                    <?php if ($canCaseCreate): ?>
                        <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#caseModal">
                            <i class="bi bi-plus-circle me-1"></i> Add Case
                        </button>
                    <?php endif; ?>
                    <?php if ($canCourtCreate): ?>
                        <button class="btn btn-outline-light btn-sm" data-bs-toggle="modal" data-bs-target="#courtModal">
                            <i class="bi bi-bank me-1"></i> Add Court
                        </button>
                    <?php endif; ?>
                    <?php if ($canDormCreate): ?>
                        <button class="btn btn-outline-light btn-sm" data-bs-toggle="modal" data-bs-target="#dormModal">
                            <i class="bi bi-building me-1"></i> Add Dorm
                        </button>
                    <?php endif; ?>
                    <?php if ($canRelationshipCreate): ?>
                        <button class="btn btn-outline-light btn-sm" data-bs-toggle="modal" data-bs-target="#relationshipModal">
                            <i class="bi bi-people me-1"></i> Add Relationship
                        </button>
                    <?php endif; ?>
                    <?php if ($canRequirementCreate): ?>
                        <button class="btn btn-outline-light btn-sm" data-bs-toggle="modal" data-bs-target="#requirementModal">
                            <i class="bi bi-file-earmark-check me-1"></i> Add Requirement
                        </button>
                    <?php endif; ?>
                    <?php if ($canValidIdTypeCreate): ?>
                        <button class="btn btn-outline-light btn-sm" data-bs-toggle="modal" data-bs-target="#validIdTypeModal">
                            <i class="bi bi-card-checklist me-1"></i> Add Accepted ID
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="master-stat-grid">
            <div class="card master-stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small">Case Records</div>
                            <div class="display-6 fw-bold mb-0" id="statCases"><?= $totalCases ?></div>
                        </div>
                        <div class="master-stat-icon bg-primary-subtle text-primary"><i class="bi bi-folder2-open"></i></div>
                    </div>
                </div>
            </div>
            <div class="card master-stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small">Court Records</div>
                            <div class="display-6 fw-bold mb-0" id="statCourts"><?= $totalCourts ?></div>
                        </div>
                        <div class="master-stat-icon bg-success-subtle text-success"><i class="bi bi-bank2"></i></div>
                    </div>
                </div>
            </div>
            <div class="card master-stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small">Dorm Records</div>
                            <div class="display-6 fw-bold mb-0" id="statDorms"><?= $totalDorms ?></div>
                        </div>
                        <div class="master-stat-icon bg-warning-subtle text-warning"><i class="bi bi-buildings"></i></div>
                    </div>
                </div>
            </div>
            <div class="card master-stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small">Relationship Records</div>
                            <div class="display-6 fw-bold mb-0" id="statRelationships"><?= $totalRelationships ?></div>
                        </div>
                        <div class="master-stat-icon bg-info-subtle text-info"><i class="bi bi-people"></i></div>
                    </div>
                </div>
            </div>
            <div class="card master-stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small">Requirement Records</div>
                            <div class="display-6 fw-bold mb-0" id="statRequirements"><?= $totalRequirements ?></div>
                        </div>
                        <div class="master-stat-icon bg-danger-subtle text-danger"><i class="bi bi-file-earmark-check"></i></div>
                    </div>
                </div>
            </div>
            <div class="card master-stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small">Accepted ID Records</div>
                            <div class="display-6 fw-bold mb-0" id="statValidIds"><?= $totalValidIdTypes ?></div>
                        </div>
                        <div class="master-stat-icon bg-secondary-subtle text-secondary"><i class="bi bi-card-checklist"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card master-workspace-card">
            <!-- <div class="card-header">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                    <div>
                        <div class="text-uppercase small fw-semibold text-muted mb-1">Reference Directory</div>
                        <h4 class="fw-bold mb-1">Masterlist Records</h4>
                        <p class="text-muted mb-0">Browse and maintain case, court, and dorm reference data.</p>
                    </div>
                    <span class="badge text-bg-light text-dark"><?= $totalCases + $totalCourts + $totalDorms ?> managed record(s)</span>
                </div>
            </div> -->

            <div class="master-toolbar">
                <div class="row g-3 align-items-end">
                    <div class="col-lg-8">
                        <label for="masterQuickSearch" class="form-label fw-semibold mb-1">Quick Search</label>
                    <input type="text" id="masterQuickSearch" class="form-control" placeholder="Search case, court, dorm, relationship, requirement, or accepted ID records">
                    </div>
                    <!-- <div class="col-lg-4">
                        <div class="small text-muted">Module Scope</div>
                        <div class="fw-semibold fs-5"><?= $totalCases + $totalCourts + $totalDorms ?> managed records</div>
                    </div> -->
                </div>
            </div>

<?php if (!$canCaseCreate && !$canCaseEdit && !$canCaseDelete): ?>
    <div class="alert alert-info mt-3 mb-0">
        You currently have view-only access to the masterlist.
    </div>
<?php endif; ?>

<div class="card-body">
<ul class="nav nav-tabs master-tab-nav">
    <li class="nav-item"><a class="nav-link <?= $activeMasterTab === 'case' ? 'active' : '' ?> d-inline-flex align-items-center justify-content-center gap-2" data-bs-toggle="tab" href="#case"> Case</a></li>
    <li class="nav-item"><a class="nav-link <?= $activeMasterTab === 'court' ? 'active' : '' ?> d-inline-flex align-items-center justify-content-center gap-2" data-bs-toggle="tab" href="#court"> Court</a></li>
    <li class="nav-item"><a class="nav-link <?= $activeMasterTab === 'dorm' ? 'active' : '' ?> d-inline-flex align-items-center justify-content-center gap-2" data-bs-toggle="tab" href="#dorm"> Dorm</a></li>
    <li class="nav-item"><a class="nav-link <?= $activeMasterTab === 'relationship' ? 'active' : '' ?> d-inline-flex align-items-center justify-content-center gap-2" data-bs-toggle="tab" href="#relationship"> Relationship</a></li>
    <li class="nav-item"><a class="nav-link <?= $activeMasterTab === 'requirement' ? 'active' : '' ?> d-inline-flex align-items-center justify-content-center gap-2" data-bs-toggle="tab" href="#requirement"> Requirement</a></li>
    <li class="nav-item"><a class="nav-link <?= $activeMasterTab === 'valid-id-type' ? 'active' : '' ?> d-inline-flex align-items-center justify-content-center gap-2" data-bs-toggle="tab" href="#valid-id-type"> Accepted ID</a></li>
</ul>
<div class="tab-content">

<!-- ================= CASE ================= -->
<div class="tab-pane fade <?= $activeMasterTab === 'case' ? 'show active' : '' ?>" id="case">
    <div class="master-table-card">
    <div class="master-table-heading d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h5 class="fw-bold mb-1"><i class="bi bi-folder2-open me-2 text-primary"></i>Case Masterlist</h5>
            <small class="text-muted">Official case numbers and case type references.</small>
        </div>
        <span class="badge bg-primary rounded-pill"><?= $totalCases ?> record(s)</span>
    </div>
    <div class="master-table-area">
    <table class="table align-middle" id="caseTable">
        <thead>
            <tr>
                <th>Case No</th>
                <th>Case Type</th>
                <?php if ($canCaseEdit || $canCaseDelete): ?>
                    <th>Action</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($cases as $c): ?>
            <tr>
                <td><?= e($c['case_no']) ?></td>
                <td><?= e($c['case_type']) ?></td>
                <?php if ($canCaseEdit || $canCaseDelete): ?>
                    <td>
                        <div class="master-row-actions">
                        <?php if ($canCaseEdit): ?>
                            <button class="btn btn-success btn-sm"
                                data-bs-toggle="modal"
                                data-bs-target="#editCaseModal"
                                onclick="openEditCase(<?= $c['id'] ?>,'<?= e($c['case_no']) ?>','<?= e($c['case_type']) ?>')">
                                Edit
                            </button>
                        <?php endif; ?>

                        <?php if ($canCaseDelete): ?>
                            <button class="btn btn-danger btn-sm"
                                onclick="deleteCase(<?= $c['id'] ?>)">
                                Delete
                            </button>
                        <?php endif; ?>
                        </div>
                    </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    </div>
</div>

<!-- ================= COURT ================= -->
<div class="tab-pane fade <?= $activeMasterTab === 'court' ? 'show active' : '' ?>" id="court">
    <div class="master-table-card">
    <div class="master-table-heading d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h5 class="fw-bold mb-1"><i class="bi bi-bank2 me-2 text-success"></i>Court Masterlist</h5>
            <small class="text-muted">Court reference names used across Resident records.</small>
        </div>
        <span class="badge bg-success rounded-pill"><?= $totalCourts ?> record(s)</span>
    </div>
    <div class="master-table-area">
    <table class="table align-middle" id="courtTable">
        <thead>
            <tr>
                <th>Court</th>
                <?php if ($canCourtEdit || $canCourtDelete): ?>
                    <th>Action</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($courts as $c): ?>
            <tr>
                <td><?= e($c['court_name']) ?></td>
                <?php if ($canCourtEdit || $canCourtDelete): ?>
                    <td>
                        <div class="master-row-actions">
                        <?php if ($canCourtEdit): ?>
                            <button class="btn btn-success btn-sm"
                                data-bs-toggle="modal"
                                data-bs-target="#editCourtModal"
                                onclick="openEditCourt(<?= $c['id'] ?>,'<?= e($c['court_name']) ?>')">
                                Edit
                            </button>
                        <?php endif; ?>

                        <?php if ($canCourtDelete): ?>
                            <button class="btn btn-danger btn-sm"
                                onclick="deleteCourt(<?= $c['id'] ?>)">
                                Delete
                            </button>
                        <?php endif; ?>
                        </div>
                    </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    </div>
</div>

<!-- ================= DORM ================= -->
<div class="tab-pane fade <?= $activeMasterTab === 'dorm' ? 'show active' : '' ?>" id="dorm">
    <div class="master-table-card">
    <div class="master-table-heading d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h5 class="fw-bold mb-1"><i class="bi bi-buildings me-2 text-warning"></i>Dorm Masterlist</h5>
            <small class="text-muted">Dorm numbers and descriptions used for custodial assignment.</small>
        </div>
        <span class="badge bg-warning text-dark rounded-pill"><?= $totalDorms ?> record(s)</span>
    </div>
    <div class="master-table-area">
    <table class="table align-middle" id="dormTable">
        <thead>
            <tr>
                <th>Dorm</th>
                <th>Description</th>
                <?php if ($canDormEdit || $canDormDelete): ?>
                    <th>Action</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($dorms as $d): ?>
            <tr>
                <td><?= e($d['dorm_no']) ?></td>
                <td><?= e($d['description']) ?></td>
                <?php if ($canDormEdit || $canDormDelete): ?>
                    <td>
                        <div class="master-row-actions">
                        <?php if ($canDormEdit): ?>
                            <button class="btn btn-success btn-sm"
                                data-bs-toggle="modal"
                                data-bs-target="#editDormModal"
                                onclick="openEditDorm(<?= $d['id'] ?>,'<?= e($d['dorm_no']) ?>','<?= e($d['description']) ?>')">
                                Edit
                            </button>
                        <?php endif; ?>

                        <?php if ($canDormDelete): ?>
                            <button class="btn btn-danger btn-sm"
                                onclick="deleteDorm(<?= $d['id'] ?>)">
                                Delete
                            </button>
                        <?php endif; ?>
                        </div>
                    </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    </div>
</div>

<!-- ================= RELATIONSHIP ================= -->
<div class="tab-pane fade <?= $activeMasterTab === 'relationship' ? 'show active' : '' ?>" id="relationship">
    <div class="master-table-card">
    <div class="master-table-heading d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h5 class="fw-bold mb-1"><i class="bi bi-people me-2 text-info"></i>Relationship Masterlist</h5>
            <small class="text-muted">Relationship options used for Resident authorized visitors.</small>
        </div>
        <span class="badge bg-info text-dark rounded-pill"><?= $totalRelationships ?> record(s)</span>
    </div>
    <div class="master-table-area">
    <table class="table align-middle" id="relationshipTable">
        <thead>
            <tr>
                <th>Relationship</th>
                <th>Description</th>
                <?php if ($canRelationshipEdit || $canRelationshipDelete): ?>
                    <th>Action</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($relationships as $r): ?>
            <tr>
                <td><?= e($r['relationship_name']) ?></td>
                <td><?= e($r['description'] ?? '') ?></td>
                <?php if ($canRelationshipEdit || $canRelationshipDelete): ?>
                    <td>
                        <div class="master-row-actions">
                        <?php if ($canRelationshipEdit): ?>
                            <button class="btn btn-success btn-sm"
                                data-bs-toggle="modal"
                                data-bs-target="#editRelationshipModal"
                                data-relationship-id="<?= (int) $r['id'] ?>"
                                data-relationship-name="<?= e($r['relationship_name']) ?>"
                                data-relationship-description="<?= e($r['description'] ?? '') ?>">
                                Edit
                            </button>
                        <?php endif; ?>

                        <?php if ($canRelationshipDelete): ?>
                            <button class="btn btn-danger btn-sm"
                                onclick="deleteRelationship(<?= (int) $r['id'] ?>)">
                                Delete
                            </button>
                        <?php endif; ?>
                        </div>
                    </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    </div>
</div>

<!-- ================= REQUIREMENT ================= -->
<div class="tab-pane fade <?= $activeMasterTab === 'requirement' ? 'show active' : '' ?>" id="requirement">
    <div class="master-table-card">
    <div class="master-table-heading d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h5 class="fw-bold mb-1"><i class="bi bi-file-earmark-check me-2 text-danger"></i>Requirement Masterlist</h5>
            <small class="text-muted">Document options used in visitor registration Select2 lists.</small>
        </div>
        <span class="badge bg-danger rounded-pill"><?= $totalRequirements ?> record(s)</span>
    </div>
    <div class="master-table-area">
    <table class="table align-middle" id="requirementTable">
        <thead>
            <tr>
                <th>Requirement</th>
                <th>Description</th>
                <th>Status</th>
                <?php if ($canRequirementEdit || $canRequirementDelete): ?>
                    <th>Action</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($requirements as $r): ?>
            <tr>
                <td><?= e($r['requirement_name']) ?></td>
                <td><?= e($r['description'] ?? '') ?></td>
                <td><span class="badge text-bg-<?= ((int) ($r['is_active'] ?? 0) === 1) ? 'success' : 'secondary' ?>"><?= ((int) ($r['is_active'] ?? 0) === 1) ? 'Active' : 'Inactive' ?></span></td>
                <?php if ($canRequirementEdit || $canRequirementDelete): ?>
                    <td>
                        <div class="master-row-actions">
                        <?php if ($canRequirementEdit): ?>
                            <button class="btn btn-success btn-sm"
                                data-bs-toggle="modal"
                                data-bs-target="#editRequirementModal"
                                data-requirement-id="<?= (int) $r['id'] ?>"
                                data-requirement-name="<?= e($r['requirement_name']) ?>"
                                data-requirement-description="<?= e($r['description'] ?? '') ?>"
                                data-requirement-active="<?= (int) ($r['is_active'] ?? 0) ?>">
                                Edit
                            </button>
                        <?php endif; ?>

                        <?php if ($canRequirementDelete): ?>
                            <button class="btn btn-danger btn-sm"
                                onclick="deleteRequirement(<?= (int) $r['id'] ?>)">
                                Delete
                            </button>
                        <?php endif; ?>
                        </div>
                    </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    </div>
</div>

<!-- ================= ACCEPTED VALID ID ================= -->
<div class="tab-pane fade <?= $activeMasterTab === 'valid-id-type' ? 'show active' : '' ?>" id="valid-id-type">
    <div class="master-table-card">
    <div class="master-table-heading d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h5 class="fw-bold mb-1"><i class="bi bi-card-checklist me-2 text-secondary"></i>Accepted ID Masterlist</h5>
            <small class="text-muted">Valid ID type options used when uploading visitor Valid ID requirements.</small>
        </div>
        <span class="badge bg-secondary rounded-pill"><?= $totalValidIdTypes ?> record(s)</span>
    </div>
    <div class="master-table-area">
    <table class="table align-middle" id="validIdTypeTable">
        <thead>
            <tr>
                <th>Accepted ID</th>
                <th>Description</th>
                <th>Status</th>
                <?php if ($canValidIdTypeEdit || $canValidIdTypeDelete): ?>
                    <th>Action</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($validIdTypes as $idType): ?>
            <tr>
                <td><?= e($idType['id_type_name']) ?></td>
                <td><?= e($idType['description'] ?? '') ?></td>
                <td><span class="badge text-bg-<?= ((int) ($idType['is_active'] ?? 0) === 1) ? 'success' : 'secondary' ?>"><?= ((int) ($idType['is_active'] ?? 0) === 1) ? 'Active' : 'Inactive' ?></span></td>
                <?php if ($canValidIdTypeEdit || $canValidIdTypeDelete): ?>
                    <td>
                        <div class="master-row-actions">
                        <?php if ($canValidIdTypeEdit): ?>
                            <button class="btn btn-success btn-sm"
                                data-bs-toggle="modal"
                                data-bs-target="#editValidIdTypeModal"
                                data-id-type-id="<?= (int) $idType['id'] ?>"
                                data-id-type-name="<?= e($idType['id_type_name']) ?>"
                                data-id-type-description="<?= e($idType['description'] ?? '') ?>"
                                data-id-type-active="<?= (int) ($idType['is_active'] ?? 0) ?>">
                                Edit
                            </button>
                        <?php endif; ?>

                        <?php if ($canValidIdTypeDelete): ?>
                            <button class="btn btn-danger btn-sm"
                                onclick="deleteValidIdType(<?= (int) $idType['id'] ?>)">
                                Delete
                            </button>
                        <?php endif; ?>
                        </div>
                    </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    </div>
</div>

</div>
</div>
</div>
</div>
</div>

<script>
$(document).ready(function(){
    const masterTables = $('#caseTable,#courtTable,#dormTable,#relationshipTable,#requirementTable,#validIdTypeTable').DataTable({
        responsive: true,
        pageLength: 10,
        order: [],
        language: {
            search: '',
            searchPlaceholder: 'Search records',
            emptyTable: 'No records found.'
        }
    });

    $('#masterQuickSearch').on('input', function () {
        masterTables.search(this.value).draw();
    });

    $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function () {
        const tabId = String($(this).attr('href') || '').replace('#', '');
        $('input[name="active_tab"]').val(tabId || 'case');
        const url = new URL(window.location.href);
        url.searchParams.set('tab', tabId || 'case');
        window.history.replaceState({}, '', url.toString());
        masterTables.columns.adjust().responsive.recalc();
    });

    $('input[name="active_tab"]').val(<?= json_encode($activeMasterTab) ?>);
});

/* ================= EDIT ================= */
function openEditCase(id,no,type){
    $('#edit_case_id').val(id);
    $('#edit_case_no').val(no);
    $('#edit_case_type').val(type);
}
function openEditCourt(id,name){
    $('#edit_court_id').val(id);
    $('#edit_court_name').val(name);
}
function openEditDorm(id,no,desc){
    $('#edit_dorm_id').val(id);
    $('#edit_dorm_no').val(no);
    $('#edit_description').val(desc);
}
function openEditRelationship(id,name,desc){
    $('#edit_relationship_id').val(id);
    $('#edit_relationship_name').val(name);
    $('#edit_relationship_description').val(desc);
}
function openEditRequirement(id,name,desc,isActive){
    $('#edit_requirement_id').val(id);
    $('#edit_requirement_name').val(name);
    $('#edit_requirement_description').val(desc);
    $('#edit_requirement_active').val(String(isActive));
}
function openEditValidIdType(id,name,desc,isActive){
    $('#edit_id_type_id').val(id);
    $('#edit_id_type_name').val(name);
    $('#edit_id_type_description').val(desc);
    $('#edit_id_type_active').val(String(isActive));
}

$(document).on('click', '[data-relationship-id]', function () {
    openEditRelationship(
        this.dataset.relationshipId || '',
        this.dataset.relationshipName || '',
        this.dataset.relationshipDescription || ''
    );
});

$(document).on('click', '[data-requirement-id]', function () {
    openEditRequirement(
        this.dataset.requirementId || '',
        this.dataset.requirementName || '',
        this.dataset.requirementDescription || '',
        this.dataset.requirementActive || '1'
    );
});

$(document).on('click', '[data-id-type-id]', function () {
    openEditValidIdType(
        this.dataset.idTypeId || '',
        this.dataset.idTypeName || '',
        this.dataset.idTypeDescription || '',
        this.dataset.idTypeActive || '1'
    );
});

/* ================= DELETE ================= */
function postDelete(action,id){
    const activeTab = $('input[name="active_tab"]').first().val() || $('.master-tab-nav .nav-link.active').attr('href')?.replace('#', '') || 'case';
    let form = document.createElement('form');
    form.method = 'POST';

    form.innerHTML = `
        <input type="hidden" name="action" value="${action}">
        <input type="hidden" name="id" value="${id}">
        <input type="hidden" name="active_tab" value="${activeTab}">
    `;

    document.body.appendChild(form);
    form.submit();
}

function deleteCase(id){
    Swal.fire({
        title: 'Delete this case?',
        text: 'This will permanently remove the selected case record.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Delete',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if(result.isConfirmed){
            postDelete('delete_case',id);
        }
    });
}
function deleteCourt(id){
    Swal.fire({
        title: 'Delete this court?',
        text: 'This will permanently remove the selected court record.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Delete',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if(result.isConfirmed){
            postDelete('delete_court',id);
        }
    });
}
function deleteDorm(id){
    Swal.fire({
        title: 'Delete this dorm?',
        text: 'This will permanently remove the selected dorm record.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Delete',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if(result.isConfirmed){
            postDelete('delete_dorm',id);
        }
    });
}
function deleteRelationship(id){
    Swal.fire({
        title: 'Delete this relationship?',
        text: 'This will permanently remove the selected relationship record.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Delete',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if(result.isConfirmed){
            postDelete('delete_relationship',id);
        }
    });
}
function deleteRequirement(id){
    Swal.fire({
        title: 'Delete this requirement?',
        text: 'This will permanently remove the selected requirement record.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Delete',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if(result.isConfirmed){
            postDelete('delete_requirement',id);
        }
    });
}
function deleteValidIdType(id){
    Swal.fire({
        title: 'Delete this accepted ID?',
        text: 'This will permanently remove the selected accepted ID record.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Delete',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if(result.isConfirmed){
            postDelete('delete_valid_id_type',id);
        }
    });
}
</script>

<?php include ROOT_PATH . '/modules/layout/footer.php'; ?>
