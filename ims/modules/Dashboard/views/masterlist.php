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


/* ==================================================
   HANDLE ACTIONS (USING CONTROLLER)
================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';
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

    if ($result) {
        $_SESSION['notification'] = [
            'type' => $result['status'],
            'message' => $result['message']
        ];
    }

    header("Location: " . BASE_URL . "/masterlist");
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

$totalCases = count($cases);
$totalCourts = count($courts);
$totalDorms = count($dorms);
?>

<style>
  .master-shell { display: grid; gap: 1.25rem; }
  .master-hero {
    position: relative;
    overflow: hidden;
    border: 0;
    border-radius: 28px;
    background:
      radial-gradient(circle at top right, rgba(59, 130, 246, 0.22), transparent 26%),
      linear-gradient(135deg, #111827 0%, #1d4ed8 100%);
    box-shadow: 0 24px 60px rgba(15, 23, 42, 0.16);
  }
  .master-hero::after {
    content: "";
    position: absolute;
    right: -90px;
    bottom: -90px;
    width: 240px;
    height: 240px;
    border-radius: 50%;
    background: rgba(255,255,255,0.08);
  }
  .master-hero .card-body { position: relative; z-index: 1; padding: 1.6rem; }
  .master-stat-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1rem; }
  .master-stat-card, .master-toolbar, .master-table-card {
    border: 0;
    border-radius: 24px;
    box-shadow: 0 16px 38px rgba(15, 23, 42, 0.08);
  }
  .master-stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 16px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
  }
  .master-tab-nav { gap: .5rem; border-bottom: 0; }
  .master-tab-nav .nav-link {
    border: 0;
    border-radius: 16px;
    padding: .8rem 1rem;
    background: #f8fafc;
    color: #334155;
    font-weight: 600;
  }
  .master-tab-nav .nav-link.active {
    background: linear-gradient(135deg, #3657f6, #1f6fe5);
    color: #fff;
  }
  .master-table-card .card-header {
    background: linear-gradient(135deg, #3657f6, #1f6fe5);
    border: 0;
    color: #fff;
  }
  .master-toolbar .form-control { border-radius: 14px; }
  .master-action-row { display: flex; gap: .5rem; flex-wrap: wrap; }
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
                    <p class="mb-0 opacity-75">Maintain case references, courts, and dorm records from one organized admin workspace.</p>
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
        </div>

        <div class="card master-toolbar">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-lg-8">
                        <label for="masterQuickSearch" class="form-label fw-semibold mb-1">Quick Search</label>
                        <input type="text" id="masterQuickSearch" class="form-control" placeholder="Search case, court, or dorm records">
                    </div>
                    <div class="col-lg-4">
                        <div class="small text-muted">Module Scope</div>
                        <div class="fw-semibold fs-5"><?= $totalCases + $totalCourts + $totalDorms ?> managed records</div>
                    </div>
                </div>
            </div>
        </div>

        <ul class="nav nav-tabs master-tab-nav">
            <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#case">Case</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#court">Court</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#dorm">Dorm</a></li>
        </ul>

<?php if (!$canCaseCreate && !$canCaseEdit && !$canCaseDelete): ?>
    <div class="alert alert-info mt-3 mb-0">
        You currently have view-only access to the masterlist.
    </div>
<?php endif; ?>

<div class="tab-content">

<!-- ================= CASE ================= -->
<div class="tab-pane fade show active" id="case">
    <div class="card master-table-card">
    <div class="card-header"><strong>Case Masterlist</strong></div>
    <div class="card-body">
    <table class="table align-middle" id="caseTable">
        <thead>
            <tr>
                <?php if ($canCaseEdit || $canCaseDelete): ?>
                    <th>Action</th>
                <?php endif; ?>
                <th>Case No</th>
                <th>Case Type</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($cases as $c): ?>
            <tr>
                <?php if ($canCaseEdit || $canCaseDelete): ?>
                    <td>
                        <?php if ($canCaseEdit): ?>
                            <button class="btn btn-warning btn-sm"
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
                    </td>
                <?php endif; ?>
                <td><?= e($c['case_no']) ?></td>
                <td><?= e($c['case_type']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    </div>
</div>

<!-- ================= COURT ================= -->
<div class="tab-pane fade" id="court">
    <div class="card master-table-card">
    <div class="card-header"><strong>Court Masterlist</strong></div>
    <div class="card-body">
    <table class="table align-middle" id="courtTable">
        <thead>
            <tr>
                <?php if ($canCourtEdit || $canCourtDelete): ?>
                    <th>Action</th>
                <?php endif; ?>
                <th>Court</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($courts as $c): ?>
            <tr>
                <?php if ($canCourtEdit || $canCourtDelete): ?>
                    <td>
                        <?php if ($canCourtEdit): ?>
                            <button class="btn btn-warning btn-sm"
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
                    </td>
                <?php endif; ?>
                <td><?= e($c['court_name']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    </div>
</div>

<!-- ================= DORM ================= -->
<div class="tab-pane fade" id="dorm">
    <div class="card master-table-card">
    <div class="card-header"><strong>Dorm Masterlist</strong></div>
    <div class="card-body">
    <table class="table align-middle" id="dormTable">
        <thead>
            <tr>
                <?php if ($canDormEdit || $canDormDelete): ?>
                    <th>Action</th>
                <?php endif; ?>
                <th>Dorm</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($dorms as $d): ?>
            <tr>
                <?php if ($canDormEdit || $canDormDelete): ?>
                    <td>
                        <?php if ($canDormEdit): ?>
                            <button class="btn btn-warning btn-sm"
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
                    </td>
                <?php endif; ?>
                <td><?= e($d['dorm_no']) ?></td>
                <td><?= e($d['description']) ?></td>
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

<script>
$(document).ready(function(){
    const masterTables = $('#caseTable,#courtTable,#dormTable').DataTable({
        responsive: true,
        pageLength: 10,
        order: [],
        language: {
            search: '',
            searchPlaceholder: 'Search records'
        }
    });

    $('#masterQuickSearch').on('input', function () {
        masterTables.search(this.value).draw();
    });
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

/* ================= DELETE ================= */
function postDelete(action,id){
    let form = document.createElement('form');
    form.method = 'POST';

    form.innerHTML = `
        <input type="hidden" name="action" value="${action}">
        <input type="hidden" name="id" value="${id}">
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
</script>

<?php include ROOT_PATH . '/modules/layout/footer.php'; ?>
