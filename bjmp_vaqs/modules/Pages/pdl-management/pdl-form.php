<?php
require_once ROOT_PATH . '/modules/layout/header.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/modules/Auth/controllers/MasterlistController.php';

MasterlistController::ensureRelationshipTable();

$isEdit   = false;
$pdlData  = [];
$visitors = [];
$suffixes = ['Jr.', 'Sr.', 'II', 'III', 'IV', 'V'];

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    require_permission('pdl.edit');

    $id     = (int) $_GET['id'];
    $isEdit = true;

    $stmt = $pdo->prepare("SELECT * FROM pdl_info WHERE id = ?");
    $stmt->execute([$id]);
    $pdlData = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    if (!$pdlData) {
        echo '<div class="alert alert-danger m-3">Resident record not found.</div>';
        require_once ROOT_PATH . '/modules/layout/footer.php';
        exit;
    }

    $pdlData['dorm_no']   = trim((string) ($pdlData['dorm_no'] ?? ''));
    $pdlData['pdl_suffix'] = trim((string) ($pdlData['pdl_suffix'] ?? ''));
    $pdlData['pdl_photo'] = trim((string) ($pdlData['pdl_photo'] ?? ''));

    $stmt = $pdo->prepare("
        SELECT *
        FROM pdl_authorized_visitors
        WHERE pdl_id = ?
        ORDER BY id ASC
        LIMIT 5
    ");
    $stmt->execute([$id]);
    $visitors = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    require_permission('pdl.create');
}

$photoFile = trim((string) ($pdlData['pdl_photo'] ?? ''));
$photoPath = ROOT_PATH . '/public/demo-uploads/pdl/' . $photoFile;
$photoUrl  = (!empty($photoFile) && file_exists($photoPath))
    ? BASE_URL . '/demo-uploads/pdl/' . rawurlencode($photoFile)
    : '';


$dorms = $pdo->query("
    SELECT id, dorm_no, description
    FROM dorm_masterlist
    ORDER BY dorm_no ASC
")->fetchAll(PDO::FETCH_ASSOC);

$relationships = $pdo->query("
    SELECT relationship_name
    FROM relationship_masterlist
    ORDER BY relationship_name ASC
")->fetchAll(PDO::FETCH_COLUMN);

$selectedDorm = trim((string) ($pdlData['dorm_no'] ?? ''));
$selectedSuffix = trim((string) ($pdlData['pdl_suffix'] ?? ''));
if ($selectedSuffix !== '' && !in_array($selectedSuffix, $suffixes, true)) {
    $suffixes[] = $selectedSuffix;
}
?>

<div class="card shadow-sm border-0 rounded-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h5 class="mb-0 fw-bold text-dark">
            <i class="bi bi-person-badge me-2 text-success"></i>
            <?= $isEdit ? 'EDIT Resident INFORMATION' : 'ADD NEW Resident INFORMATION'; ?>
        </h5>
        <a href="<?= BASE_URL ?>/pdl-management" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
    </div>

    <div class="card-body p-4">
        <form id="pdlForm" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?= htmlspecialchars($pdlData['id'] ?? '') ?>">
            <input type="hidden" name="existing_photo" value="<?= htmlspecialchars($pdlData['pdl_photo'] ?? '') ?>">
            <input type="hidden" name="action" value="<?= $isEdit ? 'update' : 'create' ?>">

            <div class="d-flex align-items-center mb-3">
                <span class="bg-success rounded me-2" style="width:4px;height:20px;display:inline-block;"></span>
                <h6 class="text-success fw-bold mb-0">Resident INFORMATION</h6>
            </div>

            <div class="row g-3">
                <div class="col-md-3 text-center">
                    <label class="form-label fw-semibold text-muted small">Resident PHOTO</label>

                    <div
                        class="mx-auto mb-2 position-relative"
                        style="width:150px;height:150px;border:2px dashed #ccc;overflow:hidden;border-radius:12px;background:#f8f9fa;cursor:pointer;"
                        onclick="document.getElementById('photoInput').click()"
                    >
                        <img
                            id="photoPreview"
                            src="<?= htmlspecialchars($photoUrl) ?>"
                            style="width:100%;height:100%;object-fit:cover;<?= empty($photoUrl) ? 'display:none;' : '' ?>"
                            alt="Resident Photo Preview"
                        >

                        <div
                            id="photoPlaceholder"
                            class="d-flex flex-column align-items-center justify-content-center h-100 text-muted <?= !empty($photoUrl) ? 'd-none' : '' ?>"
                        >
                            <i class="bi bi-camera fs-2"></i>
                            <small class="mt-1" style="font-size:11px;">Click to upload</small>
                        </div>
                    </div>

                    <input type="file" name="pdl_photo" id="photoInput" accept="image/*" class="d-none">
                    <small class="text-muted">JPG, PNG (max 2MB)</small>
                </div>

                <div class="col-md-9">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Resident ID</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-hash"></i></span>
                                <input
                                    type="text"
                                    name="pdl_id"
                                    id="pdl_id"
                                    class="form-control bg-light"
                                    placeholder="Auto-generated"
                                    value="<?= htmlspecialchars($pdlData['pdl_id'] ?? '') ?>"
                                    readonly
                                >
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">First Name <span class="text-danger">*</span></label>
                            <input
                                type="text"
                                name="pdl_fname"
                                class="form-control"
                                placeholder="First Name"
                                value="<?= htmlspecialchars($pdlData['pdl_fname'] ?? '') ?>"
                                required
                            >
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Middle Name</label>
                            <input
                                type="text"
                                name="pdl_mname"
                                class="form-control"
                                placeholder="Middle Name"
                                value="<?= htmlspecialchars($pdlData['pdl_mname'] ?? '') ?>"
                            >
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Last Name <span class="text-danger">*</span></label>
                            <input
                                type="text"
                                name="pdl_lname"
                                class="form-control"
                                placeholder="Last Name"
                                value="<?= htmlspecialchars($pdlData['pdl_lname'] ?? '') ?>"
                                required
                            >
                        </div>

                        <div class="col-md-2">
                            <label class="form-label small fw-semibold text-muted">Suffix</label>
                            <select name="pdl_suffix" class="form-select select2" data-placeholder="None">
                                <option value="">None</option>
                                <?php foreach ($suffixes as $s): ?>
                                    <option value="<?= htmlspecialchars($s) ?>" <?= $selectedSuffix === $s ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($s) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label small fw-semibold text-muted">Age <span class="text-danger">*</span></label>
                            <input
                                type="number"
                                name="pdl_age"
                                class="form-control"
                                placeholder="Age"
                                min="1"
                                max="120"
                                value="<?= htmlspecialchars($pdlData['pdl_age'] ?? '') ?>"
                                required
                            >
                        </div>

                        <div class="col-md-2">
                            <label class="form-label small fw-semibold text-muted">Sex <span class="text-danger">*</span></label>
                            <select name="pdl_sex" id="pdl_sex" class="form-select select2" data-placeholder="Select Sex" required>
                                <option value="">Select</option>
                                <option value="Male" <?= trim((string) ($pdlData['pdl_sex'] ?? '')) === 'Male' ? 'selected' : '' ?>>Male</option>
                                <option value="Female" <?= trim((string) ($pdlData['pdl_sex'] ?? '')) === 'Female' ? 'selected' : '' ?>>Female</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Case <span class="text-danger">*</span></label>
                            <select name="case_no" class="form-select select2" data-placeholder="Select Case" required>
                                <option value="">Select Case</option>
                                <?php
                                $cases = $pdo->query("SELECT case_no, case_type FROM case_masterlist ORDER BY case_no ASC")->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($cases as $c):
                                ?>
                                    <option value="<?= htmlspecialchars($c['case_no']) ?>" <?= trim((string) ($pdlData['case_no'] ?? '')) === trim((string) $c['case_no']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['case_no'] . ' - ' . $c['case_type']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Court <span class="text-danger">*</span></label>
                            <select name="court" class="form-select select2" data-placeholder="Select Court" required>
                                <option value="">Select Court</option>
                                <?php
                                $courts = $pdo->query("SELECT court_name FROM court_masterlist ORDER BY court_name ASC")->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($courts as $ct):
                                ?>
                                    <option value="<?= htmlspecialchars($ct['court_name']) ?>" <?= trim((string) ($pdlData['court'] ?? '')) === trim((string) $ct['court_name']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($ct['court_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Dorm <span class="text-danger">*</span></label>
                            <select name="dorm_no" class="form-select select2" data-placeholder="Select Dorm" required>
                                <option value="">Select Dorm</option>
                                <?php foreach ($dorms as $d): ?>
                                    <?php
                                        $optionDormId = trim((string) ($d['id'] ?? ''));
                                        $optionDormNo = trim((string) ($d['dorm_no'] ?? ''));
                                        $optionDescription = trim((string) ($d['description'] ?? ''));
                                        $isDormSelected = $selectedDorm !== '' && (
                                            $selectedDorm === $optionDormId ||
                                            $selectedDorm === $optionDormNo ||
                                            strcasecmp($selectedDorm, $optionDescription) === 0
                                        );
                                    ?>
                                    <option
                                        value="<?= htmlspecialchars($optionDormId) ?>"
                                        <?= $isDormSelected ? 'selected' : '' ?>
                                    >
                                        <?= htmlspecialchars($optionDormNo . ' - ' . ($d['description'] ?? '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <hr class="my-4">

            <div class="d-flex align-items-center mb-3">
                <span class="bg-success rounded me-2" style="width:4px;height:20px;display:inline-block;"></span>
                <h6 class="text-success fw-bold mb-0">AUTHORIZED VISITORS <span class="text-muted fw-normal">(MAX 5)</span></h6>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle">
                    <thead class="table-light text-center">
                        <tr>
                            <th style="width:30px">#</th>
                            <th>First Name</th>
                            <th>Middle Name</th>
                            <th>Last Name</th>
                            <th style="width:95px">Suffix</th>
                            <th style="width:70px">Age</th>
                            <th style="width:90px">Sex</th>
                            <th>Relationship</th>
                            <th>Address</th>
                            <th>Contact No.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for ($i = 0; $i < 5; $i++): ?>
                            <?php $v = $visitors[$i] ?? []; ?>
                            <tr>
                                <td class="text-center text-muted fw-bold"><?= $i + 1 ?></td>
                                <input type="hidden" name="visitors[<?= $i ?>][id]" value="<?= htmlspecialchars($v['id'] ?? '') ?>">
                                <td><input type="text" name="visitors[<?= $i ?>][fname]" class="form-control form-control-sm" value="<?= htmlspecialchars($v['visitor_fname'] ?? '') ?>" placeholder="First Name"></td>
                                <td><input type="text" name="visitors[<?= $i ?>][mname]" class="form-control form-control-sm" value="<?= htmlspecialchars($v['visitor_mname'] ?? '') ?>" placeholder="Middle Name"></td>
                                <td><input type="text" name="visitors[<?= $i ?>][lname]" class="form-control form-control-sm" value="<?= htmlspecialchars($v['visitor_lname'] ?? '') ?>" placeholder="Last Name"></td>
                                <td>
                                    <?php
                                        $visitorSuffix = trim((string) ($v['visitor_suffix'] ?? ''));
                                        $visitorSuffixOptions = $suffixes;
                                        if ($visitorSuffix !== '' && !in_array($visitorSuffix, $visitorSuffixOptions, true)) {
                                            $visitorSuffixOptions[] = $visitorSuffix;
                                        }
                                    ?>
                                    <select name="visitors[<?= $i ?>][suffix]" class="form-select form-select-sm visitor-suffix-select">
                                        <option value="">None</option>
                                        <?php foreach ($visitorSuffixOptions as $suffixOption): ?>
                                            <option value="<?= htmlspecialchars($suffixOption) ?>" <?= $visitorSuffix === $suffixOption ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($suffixOption) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><input type="number" name="visitors[<?= $i ?>][age]" class="form-control form-control-sm" value="<?= htmlspecialchars($v['visitor_age'] ?? '') ?>" placeholder="Age" min="1" max="120"></td>
                                <td>
                                    <select name="visitors[<?= $i ?>][sex]" class="form-select form-select-sm">
                                        <option value="">-</option>
                                        <option value="Male" <?= trim((string) ($v['visitor_sex'] ?? '')) === 'Male' ? 'selected' : '' ?>>Male</option>
                                        <option value="Female" <?= trim((string) ($v['visitor_sex'] ?? '')) === 'Female' ? 'selected' : '' ?>>Female</option>
                                    </select>
                                </td>
                                <td>
                                    <?php
                                        $visitorRelationship = trim((string) ($v['visitor_relationship'] ?? ''));
                                        $visitorRelationshipOptions = $relationships;
                                        if ($visitorRelationship !== '' && !in_array($visitorRelationship, $visitorRelationshipOptions, true)) {
                                            $visitorRelationshipOptions[] = $visitorRelationship;
                                            sort($visitorRelationshipOptions, SORT_NATURAL | SORT_FLAG_CASE);
                                        }
                                    ?>
                                    <select name="visitors[<?= $i ?>][relationship]" class="form-select form-select-sm visitor-relationship-select">
                                        <option value="">Select</option>
                                        <?php foreach ($visitorRelationshipOptions as $relationshipOption): ?>
                                            <option value="<?= htmlspecialchars($relationshipOption) ?>" <?= $visitorRelationship === $relationshipOption ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($relationshipOption) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><input type="text" name="visitors[<?= $i ?>][address]" class="form-control form-control-sm" value="<?= htmlspecialchars($v['visitor_address'] ?? '') ?>" placeholder="Complete Address"></td>
                                <td><input type="text" name="visitors[<?= $i ?>][contact]" class="form-control form-control-sm" value="<?= htmlspecialchars($v['visitor_contact'] ?? '') ?>" placeholder="Contact Number"></td>
                            </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="<?= BASE_URL ?>/pdl-management" class="btn btn-secondary px-4">
                    <i class="bi bi-x-circle me-1"></i> Cancel
                </a>
                <button type="submit" class="btn btn-success px-4" id="submitBtn">
                    <i class="bi bi-<?= $isEdit ? 'pencil-square' : 'save' ?> me-1"></i>
                    <?= $isEdit ? 'Update' : 'Save' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
$(document).ready(function () {
    $('.select2').each(function () {
        const isSuffixField = $(this).attr('name') === 'pdl_suffix';
        $(this).select2({
            width: '100%',
            placeholder: $(this).data('placeholder') || 'Select an option',
            allowClear: false,
            tags: isSuffixField
        });
    });

    $('.visitor-suffix-select').select2({
        width: '100%',
        placeholder: 'None',
        allowClear: false,
        tags: true
    });

    $('.visitor-relationship-select').select2({
        width: '100%',
        placeholder: 'Select Relationship',
        allowClear: true
    });

    $('#photoInput').on('change', function (e) {
        const file = e.target.files[0];
        if (!file) return;

        if (file.size > 2 * 1024 * 1024) {
            Swal.fire({
                icon: 'warning',
                title: 'File Too Large',
                text: 'Photo must not exceed 2MB.',
                confirmButtonColor: '#198754'
            });
            this.value = '';
            return;
        }

        const reader = new FileReader();
        reader.onload = function (event) {
            $('#photoPreview').attr('src', event.target.result).show();
            $('#photoPlaceholder').addClass('d-none');
        };
        reader.readAsDataURL(file);
    });

    $('#pdlForm').on('submit', function (e) {
        e.preventDefault();

        const isEdit = <?= $isEdit ? 'true' : 'false' ?>;
        const btnLabel = isEdit ? 'Updating...' : 'Saving...';

        Swal.fire({
            title: isEdit ? 'Update Resident Record?' : 'Save Resident Record?',
            text: isEdit ? 'Are you sure you want to update this record?' : 'Are you sure you want to save this new Resident record?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#198754',
            cancelButtonColor: '#6c757d',
            confirmButtonText: isEdit ? 'Yes, Update' : 'Yes, Save',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (!result.isConfirmed) return;

            const $btn = $('#submitBtn');
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>' + btnLabel);

            const formData = new FormData(document.getElementById('pdlForm'));

            $.ajax({
                url: APP_BASE_URL + '/pdl-action',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function (res) {
                    if (res.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: res.message || 'Record saved successfully.',
                            confirmButtonColor: '#198754',
                            timer: 2000,
                            timerProgressBar: true
                        }).then(() => {
                            window.location.href = APP_BASE_URL + '/pdl-management';
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Failed!',
                            text: res.message || 'Something went wrong.',
                            confirmButtonColor: '#198754'
                        });

                        $btn.prop('disabled', false).html(
                            '<i class="bi bi-' + (isEdit ? 'pencil-square' : 'save') + ' me-1"></i>' +
                            (isEdit ? 'Update' : 'Save')
                        );
                    }
                },
                error: function () {
                    Swal.fire({
                        icon: 'error',
                        title: 'Server Error',
                        text: 'An unexpected server error occurred. Please try again.',
                        confirmButtonColor: '#198754'
                    });

                    $btn.prop('disabled', false).html(
                        '<i class="bi bi-' + (isEdit ? 'pencil-square' : 'save') + ' me-1"></i>' +
                        (isEdit ? 'Update' : 'Save')
                    );
                }
            });
        });
    });
});
</script>

<?php require_once ROOT_PATH . '/modules/layout/footer.php'; ?>
