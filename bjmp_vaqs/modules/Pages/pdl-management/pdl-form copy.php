<?php
require_once ROOT_PATH.'/modules/layout/header.php';
require_once ROOT_PATH.'/config/database.php';

require_permission('pdl.create');

$isEdit   = false;
$pdlData  = [];
$visitors = [];
$suffixes = ['Jr.','Sr.','II','III','IV'];

if (isset($_GET['id']) && is_numeric($_GET['id'])) {

    require_permission('pdl.edit');

    $id = intval($_GET['id']);
    $isEdit = true;

    $stmt = $pdo->prepare("SELECT * FROM pdl_info WHERE id=?");
    $stmt->execute([$id]);
    $pdlData = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT * FROM pdl_authorized_visitors
        WHERE pdl_id=?
        ORDER BY id ASC
        LIMIT 5
    ");
    $stmt->execute([$pdlData['id'] ?? 0]);
    $visitors = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<div class="card shadow-sm border-0 rounded-4">
    
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold">
            <?= $isEdit ? 'EDIT Resident INFORMATION' : 'ADD NEW Resident INFORMATION'; ?>
        </h5>
        <a href="<?= BASE_URL ?>/pdl-record" class="btn btn-light btn-sm">← Back</a>
    </div>

    <div class="card-body">

        <form method="POST" action="<?= BASE_URL ?>/pdl-action" enctype="multipart/form-data">

            <!-- Hidden DB ID -->
            <input type="hidden" name="id" value="<?= $pdlData['id'] ?? '' ?>">

            <h6 class="text-success fw-bold mb-3">Resident INFORMATION</h6>

            <div class="row g-3">

                <div class="col-md-3 text-center">

                    <input type="file" name="pdl_photo" class="form-control mb-2" id="photoInput" accept="image/*">

                    <div class="mt-2 position-relative mx-auto"
                        style="width:140px;height:140px;border:1px solid #ddd;overflow:hidden;border-radius:10px;">

                        <img id="photoPreview"
                            src="<?= !empty($pdlData['pdl_photo']) ? BASE_URL.'/demo-uploads/pdl/'.$pdlData['pdl_photo'] : '' ?>"
                            style="width:100%;height:100%;object-fit:cover;<?= empty($pdlData['pdl_photo']) ? 'display:none;' : '' ?>">

                        <div id="photoPlaceholder"
                            class="d-flex align-items-center justify-content-center h-100 <?= !empty($pdlData['pdl_photo']) ? 'd-none' : '' ?>">
                            <i class="bi bi-person fs-1"></i>
                        </div>

                    </div>
                </div>

                <!-- DETAILS -->
                <div class="col-md-9">
                    <div class="row g-3">

                        <!-- AUTO-GENERATED Resident ID (readonly if edit) -->
                        <div class="col-md-4">
                            <input type="text" name="pdl_id" class="form-control"
                                   placeholder="Resident ID"
                                   value="<?= $pdlData['pdl_id'] ?? '' ?>"
                                   <?= $isEdit ? 'readonly' : 'readonly' ?>>
                        </div>

                        <div class="col-md-4">
                            <input type="text" name="pdl_fname" class="form-control"
                                   placeholder="First Name"
                                   value="<?= $pdlData['pdl_fname'] ?? '' ?>" required>
                        </div>

                        <div class="col-md-4">
                            <input type="text" name="pdl_mname" class="form-control"
                                   placeholder="Middle Name"
                                   value="<?= $pdlData['pdl_mname'] ?? '' ?>">
                        </div>

                        <div class="col-md-4">
                            <input type="text" name="pdl_lname" class="form-control"
                                   placeholder="Last Name"
                                   value="<?= $pdlData['pdl_lname'] ?? '' ?>" required>
                        </div>

                        <div class="col-md-2">
                            <select name="pdl_suffix" class="form-select select2">
                                <option value=""></option>
                                <?php foreach($suffixes as $s): ?>
                                    <option value="<?= $s ?>"
                                        <?= ($pdlData['pdl_suffix'] ?? '') == $s ? 'selected' : '' ?>>
                                        <?= $s ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <input type="number" name="pdl_age" class="form-control"
                                   placeholder="Age"
                                   value="<?= $pdlData['pdl_age'] ?? '' ?>" required>
                        </div>

                        <div class="col-md-2">
                            <select name="pdl_sex" class="form-select select2" required>
                                <option value=""></option>
                                <option value="Male" <?= ($pdlData['pdl_sex'] ?? '')=='Male'?'selected':'' ?>>Male</option>
                                <option value="Female" <?= ($pdlData['pdl_sex'] ?? '')=='Female'?'selected':'' ?>>Female</option>
                            </select>
                        </div>

                        <!-- YOUR ORIGINAL SELECTS KEPT -->
                        <div class="col-md-6">
                            <select name="case_no" class="form-select select2" required>
                                <option value="">SELECT CASE</option>
                                <?php
                                $cases = $pdo->query("SELECT case_no, case_type FROM case_masterlist")->fetchAll();
                                foreach ($cases as $c):
                                ?>
                                <option value="<?= $c['case_no'] ?>" <?= ($pdlData['case_no'] ?? '')==$c['case_no']?'selected':'' ?>>
                                    <?= $c['case_no'].' - '.$c['case_type'] ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                           <select name="court" class="form-select select2" required>
                                <option value="">SELECT COURT</option>
                                <?php
                                $courts = $pdo->query("SELECT court_name FROM court_masterlist")->fetchAll();
                                foreach ($courts as $ct):
                                ?>
                                <option value="<?= $ct['court_name'] ?>" <?= ($pdlData['court'] ?? '')==$ct['court_name']?'selected':'' ?>>
                                    <?= $ct['court_name'] ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                           <select name="dorm_no" class="form-select select2" required>
                            <option value="">SELECT DORM</option>
                            <?php
                            $dorms = $pdo->query("SELECT dorm_no, description FROM dorm_masterlist")->fetchAll();
                            foreach ($dorms as $d):
                            ?>
                            <option value="<?= $d['dorm_no'] ?>" <?= ($pdlData['dorm_no'] ?? '')==$d['dorm_no']?'selected':'' ?>>
                                <?= $d['dorm_no'].' - '.$d['description'] ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        </div>

                    </div>
                </div>

            </div>

            <hr>

            <h6 class="text-success fw-bold">AUTHORIZED VISITORS (MAX 5)</h6>

            <?php for ($i = 0; $i < 5; $i++): 
                $v = $visitors[$i] ?? []; 
            ?>

            <div class="row g-2 mb-2 border p-2 rounded">

                <input type="hidden" name="visitors[<?= $i ?>][id]" value="<?= $v['id'] ?? '' ?>">

                <div class="col-md-2">
                    <input type="text" name="visitors[<?= $i ?>][fname]" class="form-control form-control-sm"
                           value="<?= $v['visitor_fname'] ?? '' ?>">
                </div>

                <div class="col-md-2">
                    <input type="text" name="visitors[<?= $i ?>][mname]" class="form-control form-control-sm"
                           value="<?= $v['visitor_mname'] ?? '' ?>">
                </div>

                <div class="col-md-2">
                    <input type="text" name="visitors[<?= $i ?>][lname]" class="form-control form-control-sm"
                           value="<?= $v['visitor_lname'] ?? '' ?>">
                </div>

                <div class="col-md-1">
                    <input type="number" name="visitors[<?= $i ?>][age]" class="form-control form-control-sm"
                           value="<?= $v['visitor_age'] ?? '' ?>">
                </div>

                <div class="col-md-1">
                    <select name="visitors[<?= $i ?>][sex]" class="form-select form-select-sm select2">
                        <option value=""></option>
                        <option value="Male" <?= ($v['visitor_sex'] ?? '')=='Male'?'selected':'' ?>>M</option>
                        <option value="Female" <?= ($v['visitor_sex'] ?? '')=='Female'?'selected':'' ?>>F</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <input type="text" name="visitors[<?= $i ?>][relationship]" class="form-control form-control-sm"
                           value="<?= $v['visitor_relationship'] ?? '' ?>">
                </div>

                <div class="col-md-2">
                    <input type="text" name="visitors[<?= $i ?>][contact]" class="form-control form-control-sm"
                           value="<?= $v['visitor_contact'] ?? '' ?>">
                </div>

            </div>

            <?php endfor; ?>

            <div class="text-end mt-3">
                <a href="<?= BASE_URL ?>/pdl-record" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-success">
                    <?= $isEdit ? 'Update' : 'Save' ?>
                </button>
            </div>

        </form>

    </div>
</div>
<script>
$(document).ready(function() {
    $('.select2').select2({
        width: '100%',
        placeholder: "Select",
        allowClear: true
    });
});

document.getElementById('photoInput').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(event) {
            const img = document.getElementById('photoPreview');
            const placeholder = document.getElementById('photoPlaceholder');

            img.src = event.target.result;
            img.style.display = 'block';

            if (placeholder) placeholder.classList.add('d-none');
        };
        reader.readAsDataURL(file);
    }
});
</script>

<?php require_once ROOT_PATH.'/modules/layout/footer.php'; ?>