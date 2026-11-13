<?php
require_once ROOT_PATH . '/modules/layout/header.php';
require_once ROOT_PATH . '/config/database.php';

$mode = strtolower(trim((string) ($_GET['mode'] ?? 'pdl')));
$mode = in_array($mode, ['pdl', 'visitors'], true) ? $mode : 'pdl';

if ($mode === 'visitors') {
    require_permission('pdl.import');
} else {
    require_permission('pdl.create');
}

$selectedPdl = null;
if ($mode === 'visitors') {
    $selectedPdlId = (int) ($_GET['pdl_id'] ?? 0);
    if ($selectedPdlId <= 0) {
        echo '<div class="container-fluid py-3"><div class="alert alert-danger">Invalid Resident selected for visitor import.</div></div>';
        require_once ROOT_PATH . '/modules/layout/footer.php';
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT p.id, p.pdl_id, p.pdl_fname, p.pdl_mname, p.pdl_lname, p.pdl_suffix, p.dorm_no, COUNT(v.id) AS visitor_count
        FROM pdl_info p
        LEFT JOIN pdl_authorized_visitors v ON v.pdl_id = p.id
        WHERE p.id = ?
        GROUP BY p.id, p.pdl_id, p.pdl_fname, p.pdl_mname, p.pdl_lname, p.pdl_suffix, p.dorm_no
        LIMIT 1
    ");
    $stmt->execute([$selectedPdlId]);
    $selectedPdl = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if (!$selectedPdl) {
        echo '<div class="container-fluid py-3"><div class="alert alert-danger">Resident record not found.</div></div>';
        require_once ROOT_PATH . '/modules/layout/footer.php';
        exit;
    }
}
?>

<style>
  .pdl-import-shell {
    display: grid;
    gap: .85rem;
    max-width: 1180px;
    margin: 0 auto;
    padding: 1rem;
    border-radius: 8px;
    background: linear-gradient(180deg, rgba(255,255,255,.9), rgba(248,250,252,.96));
  }
  .pdl-import-hero {
    overflow: hidden;
    border: 0;
    border-radius: 8px;
    background: linear-gradient(135deg, #082844 0%, #14513b 58%, #4f8f3f 100%);
    box-shadow: 0 16px 34px rgba(15, 23, 42, .13);
  }
  .pdl-import-hero .card-body { padding: 1rem 1.15rem; }
  .pdl-import-hero h2 { font-size: clamp(1.35rem, 2vw, 1.9rem); letter-spacing: 0; }
  .pdl-context-chip {
    border-radius: 999px;
    padding: .45rem .75rem;
    background: rgba(255,255,255,.14);
    border: 1px solid rgba(255,255,255,.2);
    font-size: .84rem;
    font-weight: 700;
  }
  .import-step-list {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: .75rem;
  }
  .import-step,
  .pdl-import-panel,
  .pdl-import-side-panel,
  .advanced-outline {
    border: 1px solid rgba(8, 19, 31, .08);
    border-radius: 8px;
    background: rgba(255,255,255,.98);
    box-shadow: 0 12px 26px rgba(15, 23, 42, .07);
  }
  .import-step {
    display: flex;
    gap: .75rem;
    align-items: flex-start;
    padding: .85rem;
  }
  .import-step-number,
  .import-icon-box {
    width: 38px;
    height: 38px;
    flex: 0 0 38px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #e9f7ef;
    color: #157347;
    font-weight: 800;
  }
  .pdl-import-workspace {
    display: grid;
    grid-template-columns: minmax(0, 1.45fr) minmax(300px, .75fr);
    gap: .85rem;
    align-items: start;
  }
  .pdl-import-panel,
  .pdl-import-side-panel {
    padding: 1rem;
  }
  .template-actions {
    display: flex;
    gap: .55rem;
    flex-wrap: wrap;
  }
  .pdl-import-dropzone {
    position: relative;
    display: grid;
    gap: .55rem;
    place-items: center;
    min-height: 190px;
    padding: 1.25rem;
    border: 2px dashed rgba(21, 115, 71, .34);
    border-radius: 8px;
    background: linear-gradient(135deg, rgba(239,246,255,.88), rgba(240,253,244,.95));
    text-align: center;
    cursor: pointer;
    transition: border-color .16s ease, box-shadow .16s ease, transform .16s ease;
  }
  .pdl-import-dropzone:hover,
  .pdl-import-dropzone.is-dragover {
    border-color: rgba(21, 115, 71, .8);
    box-shadow: 0 10px 24px rgba(21, 115, 71, .13);
    transform: translateY(-1px);
  }
  .pdl-import-dropzone input[type="file"] {
    position: absolute;
    width: 1px;
    height: 1px;
    opacity: 0;
    pointer-events: none;
  }
  .dropzone-icon {
    width: 54px;
    height: 54px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #fff;
    color: #157347;
    font-size: 1.45rem;
    box-shadow: 0 8px 18px rgba(15, 23, 42, .08);
  }
  .selected-file {
    display: inline-flex;
    align-items: center;
    gap: .45rem;
    max-width: 100%;
    min-height: 34px;
    padding: .38rem .65rem;
    border-radius: 999px;
    background: #fff;
    border: 1px solid rgba(8,19,31,.08);
    color: #334155;
    font-size: .88rem;
    font-weight: 700;
  }
  .selected-file span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  .pdl-import-code {
    display: block;
    padding: .75rem .85rem;
    border-radius: 8px;
    background: #111827;
    color: #e5e7eb;
    font-size: .84rem;
    white-space: nowrap;
    overflow-x: auto;
  }
  .format-card {
    display: grid;
    gap: .55rem;
    padding: .85rem;
    border-radius: 8px;
    border: 1px solid rgba(8,19,31,.08);
    background: #f8fafc;
  }
  .quick-rule {
    display: flex;
    align-items: flex-start;
    gap: .65rem;
    padding: .7rem 0;
    border-bottom: 1px solid rgba(8,19,31,.07);
  }
  .quick-rule:last-child { border-bottom: 0; }
  .quick-rule i { color: #157347; font-size: 1.05rem; margin-top: .05rem; }
  .advanced-outline {
    padding: .95rem;
  }
  .advanced-outline summary {
    cursor: pointer;
    list-style: none;
  }
  .advanced-outline summary::-webkit-details-marker { display: none; }
  .advanced-outline summary .bi-chevron-down {
    transition: transform .16s ease;
  }
  .advanced-outline[open] summary .bi-chevron-down {
    transform: rotate(180deg);
  }
  .outline-textarea {
    min-height: 190px;
    font-family: Consolas, "Courier New", monospace;
    font-size: .9rem;
    line-height: 1.5;
  }
  @media (max-width: 991px) {
    .pdl-import-workspace,
    .import-step-list { grid-template-columns: 1fr; }
  }
  @media (max-width: 575px) {
    .pdl-import-shell { padding: .75rem; }
    .pdl-import-hero .card-body,
    .pdl-import-panel,
    .pdl-import-side-panel { padding: .85rem; }
    .template-actions,
    .template-actions .btn,
    .pdl-import-panel .btn,
    .advanced-outline .btn { width: 100%; }
  }
</style>

<div class="container-fluid py-3">
    <div class="pdl-import-shell">
        <div class="card pdl-import-hero text-white">
            <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div class="pe-lg-4">
                    <!-- <div class="text-uppercase small fw-semibold opacity-75 mb-2"><?= $mode === 'visitors' ? 'Authorized Visitor Intake' : 'Resident Bulk Intake' ?></div> -->
                    <h2 class="fw-bold mb-1 text-black"><i class="bi bi-cloud-arrow-up me-2"></i><?= $mode === 'visitors' ? 'Import Visitors For Selected Resident' : 'Import Resident Records' ?></h2>
                    <p class="mb-0 opacity-75 text-black"><?= $mode === 'visitors' ? 'Append authorized visitors to one Resident record while keeping the 5-visitor limit visible.' : 'Create Resident records in bulk, then add authorized visitors from each Resident row when needed.' ?></p>
                </div>
                <div class="d-flex gap-2 flex-wrap align-items-center justify-content-lg-end">
                    <?php if ($mode === 'visitors' && $selectedPdl): ?>
                        <span class="pdl-context-chip"><i class="bi bi-person-badge me-1"></i><?= htmlspecialchars(trim(($selectedPdl['pdl_id'] ?? '') . ' - ' . ($selectedPdl['pdl_fname'] ?? '') . ' ' . ($selectedPdl['pdl_lname'] ?? ''))) ?></span>
                        <span class="pdl-context-chip"><i class="bi bi-people me-1"></i><?= (int) ($selectedPdl['visitor_count'] ?? 0) ?>/5 visitors</span>
                    <?php endif; ?>
                    <a href="<?= BASE_URL ?>/pdl-management" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
                </div>
            </div>
        </div>

        <div class="import-step-list">
            <div class="import-step">
                <span class="import-step-number">1</span>
                <div>
                    <div class="fw-bold mb-1">Template</div>
                    <div class="small text-muted">CSV format with the exact column headers.</div>
                </div>
            </div>
            <div class="import-step">
                <span class="import-step-number">2</span>
                <div>
                    <div class="fw-bold mb-1">Records</div>
                    <div class="small text-muted"><?= $mode === 'visitors' ? 'Visitor rows for the selected Resident.' : 'Resident rows without visitor details.' ?></div>
                </div>
            </div>
            <div class="import-step">
                <span class="import-step-number">3</span>
                <div>
                    <div class="fw-bold mb-1">Import</div>
                    <div class="small text-muted">CSV and TXT files are accepted.</div>
                </div>
            </div>
        </div>

        <div class="pdl-import-workspace">
            <div class="pdl-import-panel">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                    <div>
                        <div class="text-uppercase small fw-semibold text-success mb-1">Primary Import</div>
                        <h5 class="fw-bold mb-1"><?= $mode === 'visitors' ? 'Upload Visitor File' : 'Upload Resident File' ?></h5>
                        <div class="text-muted small"><?= $mode === 'visitors' ? 'Imports are attached only to the selected Resident.' : 'Blank Resident IDs can be auto-generated during import.' ?></div>
                    </div>
                    <div class="template-actions">
                        <a href="<?= BASE_URL ?>/pdl-import-action?action=<?= $mode === 'visitors' ? 'download_visitors_template' : 'download_pdl_template' ?>" class="btn btn-outline-success btn-sm"><i class="bi bi-file-earmark-spreadsheet me-1"></i>CSV Template</a>
                        <a href="<?= BASE_URL ?>/pdl-import-action?action=download_guide&mode=<?= $mode ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-file-earmark-text me-1"></i>Guide</a>
                    </div>
                </div>

                <form id="pdlImportForm" enctype="multipart/form-data">
                    <input type="hidden" name="mode" value="<?= htmlspecialchars($mode) ?>">
                    <?php if ($mode === 'visitors' && $selectedPdl): ?>
                        <input type="hidden" name="pdl_id" value="<?= (int) $selectedPdl['id'] ?>">
                    <?php endif; ?>

                    <label class="pdl-import-dropzone mb-3" for="importFile" id="importDropzone">
                        <input type="file" name="import_file" id="importFile" accept=".csv,.txt,text/csv,text/plain" required>
                        <span class="dropzone-icon"><i class="bi bi-file-earmark-arrow-up"></i></span>
                        <span>
                            <span class="d-block fw-bold">Choose or drop a CSV/TXT file</span>
                            <span class="d-block small text-muted">Maximize success by starting from the template.</span>
                        </span>
                        <span class="selected-file" id="selectedFileName"><i class="bi bi-paperclip"></i><span>No file selected</span></span>
                    </label>

                    <div class="format-card mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <span class="import-icon-box"><i class="bi bi-columns-gap"></i></span>
                            <div>
                                <div class="fw-bold">Required columns</div>
                                <div class="small text-muted"><?= $mode === 'visitors' ? 'Visitor import columns' : 'Resident import columns' ?></div>
                            </div>
                        </div>
                        <?php if ($mode === 'visitors'): ?>
                            <code class="pdl-import-code">visitor_fname, visitor_mname, visitor_lname, visitor_age, visitor_sex, visitor_relationship, visitor_address, visitor_contact</code>
                        <?php else: ?>
                            <code class="pdl-import-code">record_key, pdl_id, pdl_fname, pdl_mname, pdl_lname, pdl_suffix, pdl_age, pdl_sex, case_no, court, dorm_no</code>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-success px-4" id="pdlImportSubmit"><i class="bi bi-cloud-upload me-1"></i>Import <?= $mode === 'visitors' ? 'Visitors' : 'Resident' ?></button>
                    </div>
                </form>
            </div>

            <div class="pdl-import-side-panel">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <span class="import-icon-box"><i class="bi bi-shield-check"></i></span>
                    <div>
                        <div class="text-uppercase small fw-semibold text-muted">Checks</div>
                        <h6 class="fw-bold mb-0">Before Import</h6>
                    </div>
                </div>

                <?php if ($mode === 'visitors'): ?>
                    <div class="quick-rule"><i class="bi bi-check-circle-fill"></i><div><div class="fw-semibold">Selected Resident only</div><div class="small text-muted">Rows are not matched to other Resident records.</div></div></div>
                    <div class="quick-rule"><i class="bi bi-check-circle-fill"></i><div><div class="fw-semibold">Visitor limit</div><div class="small text-muted"><?= max(0, 5 - (int) ($selectedPdl['visitor_count'] ?? 0)) ?> slot(s) currently available.</div></div></div>
                    <div class="quick-rule"><i class="bi bi-check-circle-fill"></i><div><div class="fw-semibold">Required names</div><div class="small text-muted">First name and last name are validated.</div></div></div>
                <?php else: ?>
                    <div class="quick-rule"><i class="bi bi-check-circle-fill"></i><div><div class="fw-semibold">Unique Resident ID</div><div class="small text-muted">Duplicate IDs are blocked before records are created.</div></div></div>
                    <div class="quick-rule"><i class="bi bi-check-circle-fill"></i><div><div class="fw-semibold">Auto ID</div><div class="small text-muted">Leave pdl_id blank to generate from sex category.</div></div></div>
                    <div class="quick-rule"><i class="bi bi-check-circle-fill"></i><div><div class="fw-semibold">Required profile fields</div><div class="small text-muted">Name, age, sex, case, court, and dorm are validated.</div></div></div>
                <?php endif; ?>

                <details class="advanced-outline mt-3">
                    <summary class="d-flex justify-content-between align-items-center gap-2 fw-bold">
                        <span><i class="bi bi-list-nested me-1"></i>Outline Import</span>
                        <i class="bi bi-chevron-down"></i>
                    </summary>

                    <form id="pdlOutlineForm" class="mt-3">
                        <input type="hidden" name="mode" value="<?= htmlspecialchars($mode) ?>">
                        <?php if ($mode === 'visitors' && $selectedPdl): ?>
                            <input type="hidden" name="pdl_id" value="<?= (int) $selectedPdl['id'] ?>">
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="outlineText">Outline Text</label>
                            <textarea name="outline_text" id="outlineText" class="form-control outline-textarea" rows="7" placeholder="<?= $mode === 'visitors' ? 'VISITOR|MARIA||ESPARTERO|55|Female|Mother|Demo City|09171234567&#10;VISITOR|PEDRO||ESPARTERO|57|Male|Father|Demo City|09179876543' : 'Resident|PDL-001||JONALYN||ESPARTERO||30|Female|CASE-001|RTC BRANCH 1|Dorm 1&#10;Resident|PDL-002||PEDRO||SANTOS||35|Male|CASE-002|MTC BRANCH 4|Dorm 2' ?>"></textarea>
                        </div>

                        <div class="format-card mb-3">
                            <div class="fw-bold">Line format</div>
                            <?php if ($mode === 'visitors'): ?>
                                <code class="pdl-import-code">VISITOR|visitor_fname|visitor_mname|visitor_lname|visitor_age|visitor_sex|visitor_relationship|visitor_address|visitor_contact</code>
                            <?php else: ?>
                                <code class="pdl-import-code">Resident|record_key|pdl_id|pdl_fname|pdl_mname|pdl_lname|pdl_suffix|pdl_age|pdl_sex|case_no|court|dorm_no</code>
                            <?php endif; ?>
                        </div>

                        <div class="d-flex justify-content-end gap-2 flex-wrap">
                            <a href="<?= BASE_URL ?>/pdl-import-action?action=<?= $mode === 'visitors' ? 'download_visitors_outline_template' : 'download_pdl_outline_template' ?>" class="btn btn-outline-info"><i class="bi bi-download me-1"></i>Outline Template</a>
                            <button type="submit" class="btn btn-info text-white" id="pdlOutlineSubmit"><i class="bi bi-list-nested me-1"></i>Import Outline</button>
                        </div>
                    </form>
                </details>
            </div>
        </div>
    </div>
</div>

<script>
$(function () {
    const importMode = <?= json_encode($mode) ?>;
    const selectedPdlId = <?= json_encode((int) ($selectedPdl['id'] ?? 0)) ?>;
    const $dropzone = $('#importDropzone');
    const $fileInput = $('#importFile');
    const $selectedFileName = $('#selectedFileName span');

    function setSelectedFileName(file) {
        $selectedFileName.text(file ? file.name : 'No file selected');
    }

    $fileInput.on('change', function () {
        setSelectedFileName(this.files && this.files.length ? this.files[0] : null);
    });

    $dropzone.on('dragenter dragover', function (e) {
        e.preventDefault();
        e.stopPropagation();
        $dropzone.addClass('is-dragover');
    });

    $dropzone.on('dragleave dragend drop', function (e) {
        e.preventDefault();
        e.stopPropagation();
        $dropzone.removeClass('is-dragover');
    });

    $dropzone.on('drop', function (e) {
        const droppedFiles = e.originalEvent.dataTransfer && e.originalEvent.dataTransfer.files;
        if (!droppedFiles || !droppedFiles.length) return;

        const file = droppedFiles[0];
        const extension = file.name.split('.').pop().toLowerCase();
        if (!['csv', 'txt'].includes(extension)) {
            Swal.fire('Invalid File', 'Please choose a CSV or TXT file.', 'warning');
            return;
        }

        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);
        document.getElementById('importFile').files = dataTransfer.files;
        setSelectedFileName(file);
    });

    $('#pdlImportForm').on('submit', function (e) {
        e.preventDefault();

        const fileInput = document.getElementById('importFile');
        if (!fileInput || !fileInput.files.length) {
            Swal.fire('No File', 'Please select a CSV or TXT file to import.', 'warning');
            return;
        }

        const formData = new FormData(this);
        formData.append('action', importMode === 'visitors' ? 'import_visitors_file' : 'import_pdl_file');

        const $submit = $('#pdlImportSubmit');
        $submit.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Importing...');

        $.ajax({
            url: APP_BASE_URL + '/pdl-import-action',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function (res) {
                if (res.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Import Completed',
                        html: `
                            <div class="text-start">
                                <div><strong>Detected format:</strong> ${res.summary?.detected_format || 'file'}</div>
                                <div><strong>Source file:</strong> ${res.summary?.source_file || '-'}</div>
                                <div><strong>Resident imported:</strong> ${res.summary?.pdl_imported || 0}</div>
                                <div><strong>Visitors imported:</strong> ${res.summary?.visitors_imported || 0}</div>
                                <div><strong>Rows processed:</strong> ${res.summary?.rows_processed || 0}</div>
                            </div>
                        `,
                        confirmButtonColor: '#198754'
                    }).then(() => {
                        window.location.href = APP_BASE_URL + '/pdl-management';
                    });
                    return;
                }

                let errorHtml = '<div class="text-start"><div class="mb-2">' + (res.message || 'Import failed.') + '</div>';
                if (Array.isArray(res.errors) && res.errors.length) {
                    errorHtml += '<ul class="small text-danger ps-3 mb-0">';
                    res.errors.slice(0, 12).forEach(function (item) {
                        errorHtml += '<li>' + $('<div>').text(item).html() + '</li>';
                    });
                    errorHtml += '</ul>';
                }
                errorHtml += '</div>';

                Swal.fire({ icon: 'error', title: 'Import Failed', html: errorHtml, confirmButtonColor: '#198754', width: 720 });
            },
            error: function () {
                Swal.fire('Server Error', 'An unexpected server error occurred while importing the file.', 'error');
            },
            complete: function () {
                $submit.prop('disabled', false).html('<i class="bi bi-cloud-upload me-1"></i> Import ' + (importMode === 'visitors' ? 'Visitors' : 'Resident'));
            }
        });
    });

    $('#pdlOutlineForm').on('submit', function (e) {
        e.preventDefault();

        const outlineText = $('#outlineText').val().trim();
        if (!outlineText) {
            Swal.fire('No Outline', 'Please paste the outline text first.', 'warning');
            return;
        }

        const $submit = $('#pdlOutlineSubmit');
        $submit.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Importing...');

        $.ajax({
            url: APP_BASE_URL + '/pdl-import-action',
            type: 'POST',
            dataType: 'json',
            data: {
                action: importMode === 'visitors' ? 'import_visitors_outline' : 'import_pdl_outline',
                mode: importMode,
                pdl_id: selectedPdlId,
                outline_text: outlineText
            },
            success: function (res) {
                if (res.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Outline Import Completed',
                        html: `
                            <div class="text-start">
                                <div><strong>Resident imported:</strong> ${res.summary?.pdl_imported || 0}</div>
                                <div><strong>Visitors imported:</strong> ${res.summary?.visitors_imported || 0}</div>
                                <div><strong>Lines processed:</strong> ${res.summary?.rows_processed || 0}</div>
                            </div>
                        `,
                        confirmButtonColor: '#198754'
                    }).then(() => {
                        window.location.href = APP_BASE_URL + '/pdl-management';
                    });
                    return;
                }

                let errorHtml = '<div class="text-start"><div class="mb-2">' + (res.message || 'Outline import failed.') + '</div>';
                if (Array.isArray(res.errors) && res.errors.length) {
                    errorHtml += '<ul class="small text-danger ps-3 mb-0">';
                    res.errors.slice(0, 12).forEach(function (item) {
                        errorHtml += '<li>' + $('<div>').text(item).html() + '</li>';
                    });
                    errorHtml += '</ul>';
                }
                errorHtml += '</div>';

                Swal.fire({ icon: 'error', title: 'Outline Import Failed', html: errorHtml, confirmButtonColor: '#198754', width: 720 });
            },
            error: function () {
                Swal.fire('Server Error', 'An unexpected server error occurred while importing the outline.', 'error');
            },
            complete: function () {
                $submit.prop('disabled', false).html('<i class="bi bi-list-nested me-1"></i> Import Outline');
            }
        });
    });
});
</script>

<?php require_once ROOT_PATH . '/modules/layout/footer.php'; ?>
