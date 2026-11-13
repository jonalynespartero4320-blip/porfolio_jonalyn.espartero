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
  .import-accent-btn {
    background: #d4a93a;
    border-color: #d4a93a;
    color: #fff;
  }
  .import-accent-btn:hover,
  .import-accent-btn:focus {
    background: #bf972f;
    border-color: #bf972f;
    color: #fff;
  }
  .import-accent-outline {
    background: #fff8e3;
    border-color: #d4a93a;
    color: #8b6a12;
  }
  .import-accent-outline:hover,
  .import-accent-outline:focus {
    background: #d4a93a;
    border-color: #d4a93a;
    color: #fff;
  }
  .pdl-import-shell {
    display: block;
    width: 100%;
    max-width: none;
    margin: 0;
    padding: 0;
    border-radius: 0;
    background: transparent;
  }
  .pdl-import-shell .pdl-import-hero,
  .pdl-import-shell .pdl-import-panel,
  .pdl-import-shell .pdl-import-side-panel {
    border-radius: 12px;
  }
  .pdl-import-hero {
    overflow: hidden;
    border: 0;
    border-radius: 8px;
    background: linear-gradient(135deg, #062150 0%, #003E51 42%, #447A61 100%);
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
    display: block;
    gap: .85rem;
    align-items: stretch;
  }
  .pdl-import-panel,
  .pdl-import-side-panel {
    width: 100%;
    padding: 1.25rem;
  }
  .import-preview-actions {
    display: flex;
    flex-wrap: wrap;
    gap: .75rem;
    justify-content: space-between;
    margin-bottom: .85rem;
  }
  .preview-duplicate {
    background: rgba(248, 215, 218, .35) !important;
  }
  .import-preview-alert {
    border-radius: 8px;
    padding: .85rem 1rem;
    margin-bottom: .85rem;
    background: #fff4e5;
    border: 1px solid #f4c15b;
    color: #764a0d;
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
  .import-preview-panel {
    padding: 1rem;
    border-radius: 8px;
    border: 1px solid rgba(8,19,31,.08);
    background: #f8fafc;
    margin-bottom: 1rem;
  }
  .import-preview-meta {
    display: flex;
    flex-wrap: wrap;
    gap: .75rem;
    align-items: center;
    justify-content: space-between;
    margin-bottom: .85rem;
  }
  .import-preview-alert {
    border-radius: 8px;
    padding: .85rem 1rem;
    margin-bottom: .85rem;
    background: #fff4e5;
    border: 1px solid #f4c15b;
    color: #764a0d;
  }
  .import-preview-panel table {
    width: 100% !important;
    min-width: 100%;
  }
  .import-preview-panel td,
  .import-preview-panel th {
    white-space: nowrap;
  }
  .preview-duplicate {
    background: rgba(248, 215, 218, .35) !important;
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
                    <a href="javascript:history.back()" class="btn btn-light btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
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
                        <!-- <div class="text-uppercase small fw-semibold text-success mb-1">Primary Import</div> -->
                        <h5 class="fw-bold mb-1"><?= $mode === 'visitors' ? 'Upload Visitor File' : 'Upload Resident File' ?></h5>
                        <div class="text-muted small"><?= $mode === 'visitors' ? 'Imports are attached only to the selected Resident.' : 'Blank Resident IDs can be auto-generated during import.' ?></div>
                        <!-- <div class="text-muted small mt-1"><?= $mode === 'visitors' ? 'visitor_relationship values may use relationship name or description.' : 'case_no, court, and dorm_no may use masterlist values or IDs. SQL dumps from phpMyAdmin are also supported for Resident plus authorized visitors.' ?></div> -->
                    </div>
                    <div class="template-actions">
                        <a href="<?= BASE_URL ?>/pdl-import-action?action=<?= $mode === 'visitors' ? 'download_visitors_template' : 'download_pdl_template' ?>" class="btn import-accent-outline btn-sm" title="Download a sample CSV file with correct column headers"><i class="bi bi-file-earmark-spreadsheet me-1"></i>CSV Template</a>
                        <a href="<?= BASE_URL ?>/pdl-import-action?action=download_guide&mode=<?= $mode ?>" class="btn btn-outline-secondary btn-sm" title="Download a text guide with detailed instructions"><i class="bi bi-file-earmark-text me-1"></i>Guide</a>
                    </div>
                </div>

                <form id="pdlImportForm" enctype="multipart/form-data">
                    <input type="hidden" name="mode" value="<?= htmlspecialchars($mode) ?>">
                    <?php if ($mode === 'visitors' && $selectedPdl): ?>
                        <input type="hidden" name="pdl_id" value="<?= (int) $selectedPdl['id'] ?>">
                    <?php endif; ?>

                    <label class="pdl-import-dropzone mb-3" for="importFile" id="importDropzone">
                        <input type="file" name="import_file" id="importFile" accept="<?= $mode === 'visitors' ? '.csv,.txt,text/csv,text/plain' : '.csv,.txt,.sql,text/csv,text/plain,application/sql,text/x-sql' ?>" required>
                        <span class="dropzone-icon"><i class="bi bi-file-earmark-arrow-up"></i></span>
                        <span>
                            <span class="d-block fw-bold">Choose or drop a <?= $mode === 'visitors' ? 'CSV/TXT' : 'CSV/TXT/SQL' ?> file</span>
                        <span class="d-block small text-muted"><?= $mode === 'visitors' ? 'Ensure your file matches the required columns. Download the template and guide for accurate sample data.' : 'Use the template for CSV/TXT, or upload a phpMyAdmin SQL dump like bjmp_db (22).sql.' ?></span>
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

                    <div id="importPreviewContainer" class="import-preview-panel d-none">
                        <div class="import-preview-meta">
                            <div>
                                <div class="fw-bold">Preview import file</div>
                                <div class="small text-muted">Review rows before importing to avoid duplicate records.</div>
                            </div>
                            <span id="importPreviewStats" class="badge bg-secondary">No file selected</span>
                        </div>
                        <div id="importPreviewActions" class="import-preview-actions d-none">
                            <button type="button" class="btn btn-outline-danger btn-sm" id="removeDuplicateRowsBtn"><i class="bi bi-trash me-1"></i>Remove duplicate rows</button>
                            <button type="button" class="btn btn-outline-success btn-sm d-none" id="keepDuplicateRowsBtn"><i class="bi bi-check2 me-1"></i>Keep duplicate rows</button>
                            <button type="button" class="btn btn-outline-primary btn-sm d-none" id="editDuplicateRowsBtn"><i class="bi bi-pencil me-1"></i>Edit duplicate rows</button>
                            <button type="button" class="btn btn-outline-info btn-sm d-none" id="saveDuplicateEditsBtn"><i class="bi bi-save me-1"></i>Save edits</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm d-none" id="resetPreviewBtn"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset preview</button>
                        </div>
                        <div id="importPreviewAlert" class="import-preview-alert d-none"></div>
                        <div class="table-responsive">
                            <table id="importPreviewTable" class="table table-sm table-hover">
                                <thead></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn import-accent-btn px-4" id="pdlImportSubmit" title="Upload and process the selected CSV/TXT file to import records"><i class="bi bi-cloud-upload me-1"></i>Import <?= $mode === 'visitors' ? 'Visitors' : 'Resident' ?></button>
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
                    <div class="quick-rule"><i class="bi bi-check-circle-fill"></i><div><div class="fw-semibold">SQL dump support</div><div class="small text-muted">Imports pdl_info and pdl_authorized_visitors only, not the entire database.</div></div></div>
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
                            <a href="<?= BASE_URL ?>/pdl-import-action?action=<?= $mode === 'visitors' ? 'download_visitors_outline_template' : 'download_pdl_outline_template' ?>" class="btn btn-outline-info" title="Download a sample text file for outline import format"><i class="bi bi-download me-1"></i>Outline Template</a>
                            <button type="submit" class="btn import-accent-btn" id="pdlOutlineSubmit" title="Process the pasted outline text to import records"><i class="bi bi-list-nested me-1"></i>Import Outline</button>
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
    const $removeDuplicateRowsBtn = $('#removeDuplicateRowsBtn');
    const $keepDuplicateRowsBtn = $('#keepDuplicateRowsBtn');
    const $editDuplicateRowsBtn = $('#editDuplicateRowsBtn');
    const $saveDuplicateEditsBtn = $('#saveDuplicateEditsBtn');
    const $resetPreviewBtn = $('#resetPreviewBtn');

    let previewTable = null;
    let previewRows = null;
    let originalPreviewRows = null;
    let currentPreviewFile = null;
    let duplicatesRemoved = false;
    let duplicateEditMode = false;

    function setSelectedFileName(file) {
        $selectedFileName.text(file ? file.name : 'No file selected');
    }

    function serializeCsv(rows) {
        return rows.map(row => row.map(value => {
            if (value == null) {
                return '';
            }
            const stringValue = String(value);
            if (/[",\n]/.test(stringValue)) {
                return '"' + stringValue.replace(/"/g, '""') + '"';
            }
            return stringValue;
        }).join(',')).join('\r\n');
    }

    function setFileFromRows(rows, originalFile) {
        if (!originalFile || !rows || !rows.length) {
            return;
        }

        const csvText = serializeCsv(rows);
        const blob = new Blob([csvText], { type: originalFile.type || 'text/csv;charset=UTF-8' });
        const fileName = originalFile.name;
        const newFile = new File([blob], fileName, { type: originalFile.type || 'text/csv' });
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(newFile);
        document.getElementById('importFile').files = dataTransfer.files;
        setSelectedFileName(newFile);
    }

    function removeDuplicateRows(rows) {
        if (!rows || rows.length <= 1) {
            return rows;
        }

        const header = rows[0];
        const data = rows.slice(1);
        const normalizedHeader = header.map(h => h.toLowerCase().trim());
        const keyIndexes = importMode === 'visitors'
            ? [normalizedHeader.indexOf('visitor_fname'), normalizedHeader.indexOf('visitor_lname'), normalizedHeader.indexOf('visitor_contact')]
            : [normalizedHeader.indexOf('record_key'), normalizedHeader.indexOf('pdl_id')];

        const seen = {};
        const filtered = [header];

        data.forEach(row => {
            const uniqueKey = keyIndexes.map(idx => (idx >= 0 ? (row[idx] || '') : '')).join('|').toLowerCase();
            if (!seen[uniqueKey]) {
                seen[uniqueKey] = true;
                filtered.push(row);
            }
        });

        return filtered;
    }

    function setEditableDuplicateRows(enable) {
        const $rows = $('#importPreviewTable tbody tr.preview-duplicate');
        $rows.find('td').attr('contenteditable', enable ? 'true' : 'false');
        $rows.toggleClass('table-warning', enable);
        duplicateEditMode = enable;
        $editDuplicateRowsBtn.toggleClass('d-none', enable);
    }

    function readPreviewRowsFromDom() {
        const header = $('#importPreviewTable thead th').map((_, el) => $(el).text().trim()).get();
        const rows = [header];

        $('#importPreviewTable tbody tr').each(function () {
            const row = $(this).find('td').map((_, td) => $(td).text().trim()).get();
            if (row.length) {
                rows.push(row);
            }
        });

        return rows;
    }

    function saveDuplicateEdits() {
        if (!duplicateEditMode) {
            return;
        }

        previewRows = readPreviewRowsFromDom();
        setFileFromRows(previewRows, currentPreviewFile);
        setEditableDuplicateRows(false);
        renderPreview(previewRows);
        Swal.fire('Duplicates updated', 'Duplicate rows were updated in the preview and import file.', 'success');
    }

    function detectCsvDelimiter(line) {
        const candidates = [',', ';', '\t', '|'];
        let bestDelimiter = ',';
        let bestCount = 0;

        for (const delimiter of candidates) {
            const regex = new RegExp(`(?:"[^"]*(?:""[^"]*)*"|[^${delimiter}]*)${delimiter}`,'g');
            const count = (line.match(new RegExp(delimiter, 'g')) || []).length;
            if (count > bestCount) {
                bestCount = count;
                bestDelimiter = delimiter;
            }
        }

        return bestCount > 0 ? bestDelimiter : ',';
    }

    function parseCsvLine(line, delimiter) {
        const values = [];
        let current = '';
        let inQuotes = false;

        for (let i = 0; i < line.length; i += 1) {
            const char = line[i];
            const nextChar = line[i + 1];

            if (char === '"') {
                if (inQuotes && nextChar === '"') {
                    current += '"';
                    i += 1;
                } else {
                    inQuotes = !inQuotes;
                }
                continue;
            }

            if (char === delimiter && !inQuotes) {
                values.push(current.trim());
                current = '';
                continue;
            }

            current += char;
        }

        values.push(current.trim());
        return values;
    }

    function parseCsvText(text) {
        const normalized = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n');
        const lines = normalized.split('\n').filter(line => line.trim().length > 0);
        if (!lines.length) {
            return [];
        }

        const delimiter = detectCsvDelimiter(lines[0]);
        return lines.map(line => parseCsvLine(line, delimiter));
    }

    function clearPreview() {
        $('#importPreviewContainer').addClass('d-none');
        $('#importPreviewActions').addClass('d-none');
        $removeDuplicateRowsBtn.add($keepDuplicateRowsBtn).add($editDuplicateRowsBtn).add($saveDuplicateEditsBtn).add($resetPreviewBtn).addClass('d-none');
        $('#importPreviewStats').text('No file selected');
        $('#importPreviewAlert').addClass('d-none').text('');
        $('#importPreviewTable thead').empty();
        $('#importPreviewTable tbody').empty();
        if (previewTable) {
            previewTable.destroy();
            previewTable = null;
        }
        previewRows = null;
        originalPreviewRows = null;
        currentPreviewFile = null;
        duplicatesRemoved = false;
        duplicateEditMode = false;
    }

    function getExpectedHeaders() {
        return importMode === 'visitors'
            ? ['visitor_fname', 'visitor_mname', 'visitor_lname', 'visitor_age', 'visitor_sex', 'visitor_relationship', 'visitor_address', 'visitor_contact']
            : ['record_key', 'pdl_id', 'pdl_fname', 'pdl_mname', 'pdl_lname', 'pdl_suffix', 'pdl_age', 'pdl_sex', 'case_no', 'court', 'dorm_no'];
    }

    function renderPreview(rows) {
        const $container = $('#importPreviewContainer');
        const $stats = $('#importPreviewStats');
        const $alert = $('#importPreviewAlert');
        const $thead = $('#importPreviewTable thead');
        const $tbody = $('#importPreviewTable tbody');
        const $actions = $('#importPreviewActions');

        clearPreview();

        if (!rows.length) {
            $stats.text('No rows found');
            return;
        }

        const header = rows[0].map(h => h.trim());
        const dataRows = rows.slice(1);
        const expected = getExpectedHeaders();
        const normalizedHeader = header.map(h => h.toLowerCase().trim());
        const invalidHeaders = expected.filter((value, index) => normalizedHeader[index] !== value);

        const duplicateMap = {};
        const duplicateDetails = [];

        const keyIndexes = importMode === 'visitors'
            ? [normalizedHeader.indexOf('visitor_fname'), normalizedHeader.indexOf('visitor_lname'), normalizedHeader.indexOf('visitor_contact')]
            : [normalizedHeader.indexOf('record_key'), normalizedHeader.indexOf('pdl_id')];

        dataRows.forEach((row, rowIndex) => {
            const keyParts = keyIndexes.map(idx => (idx >= 0 ? (row[idx] || '') : '')).join('|').toLowerCase();
            const uniqueKey = keyParts || row.join('|').toLowerCase();

            if (duplicateMap[uniqueKey]) {
                duplicateMap[uniqueKey].count += 1;
                duplicateMap[uniqueKey].rows.push(rowIndex + 2);
            } else {
                duplicateMap[uniqueKey] = { count: 1, rows: [rowIndex + 2] };
            }
        });

        Object.values(duplicateMap).forEach((item) => {
            if (item.count > 1) {
                duplicateDetails.push(`Duplicate rows detected at ${item.rows.join(', ')}`);
            }
        });

        previewRows = rows;

        if (invalidHeaders.length) {
            $alert.removeClass('d-none').text('Warning: The first row does not match the expected template headers. Confirm the file format before importing.');
        } else if (duplicateDetails.length) {
            $alert.removeClass('d-none').html(`Duplicate rows detected: <strong>${duplicateDetails.length}</strong>. Review the highlighted rows before importing.`);
        }

        const showActions = duplicateDetails.length > 0 || duplicatesRemoved;
        $actions.toggleClass('d-none', !showActions);
        $removeDuplicateRowsBtn.toggleClass('d-none', duplicateDetails.length === 0 || duplicatesRemoved);
        $keepDuplicateRowsBtn.toggleClass('d-none', duplicatesRemoved);
        $editDuplicateRowsBtn.toggleClass('d-none', duplicateDetails.length === 0 || duplicatesRemoved);
        $saveDuplicateEditsBtn.toggleClass('d-none', true);
        $resetPreviewBtn.toggleClass('d-none', !duplicatesRemoved);

        $thead.html('<tr>' + header.map(column => `<th>${$('<div>').text(column).html()}</th>`).join('') + '</tr>');

        dataRows.slice(0, 20).forEach((row, rowIndex) => {
            const rowKeyParts = keyIndexes.map(idx => (idx >= 0 ? (row[idx] || '') : '')).join('|').toLowerCase();
            const uniqueRowKey = rowKeyParts || row.join('|').toLowerCase();
            const isDuplicate = duplicateMap[uniqueRowKey] && duplicateMap[uniqueRowKey].count > 1;
            const rowClass = isDuplicate ? 'preview-duplicate' : '';

            $tbody.append(`
                <tr class="${rowClass}">
                    ${row.map(cell => `<td>${$('<div>').text(cell).html()}</td>`).join('')}
                </tr>
            `);
        });

        if (dataRows.length > 20) {
            $tbody.append(`<tr><td colspan="${header.length}" class="text-center small text-muted">Showing first 20 of ${dataRows.length} rows</td></tr>`);
        }

        $stats.text(`${dataRows.length} row(s) loaded` + (duplicateDetails.length ? ` • ${duplicateDetails.length} duplicate(s)` : ''));
        $container.removeClass('d-none');

        previewTable = $('#importPreviewTable').DataTable({
            paging: true,
            searching: true,
            info: true,
            responsive: true,
            pageLength: 5,
            lengthChange: false,
            columnDefs: [ { targets: '_all', orderable: false } ]
        });
    }

    function previewFile(file) {
        if (!file) {
            clearPreview();
            return;
        }

        const extension = file.name.split('.').pop().toLowerCase();
        const allowedExtensions = importMode === 'visitors' ? ['csv', 'txt'] : ['csv', 'txt', 'sql'];
        if (!allowedExtensions.includes(extension)) {
            Swal.fire('Invalid File', 'Please choose a ' + (importMode === 'visitors' ? 'CSV or TXT' : 'CSV, TXT, or SQL') + ' file.', 'warning');
            clearPreview();
            return;
        }

        if (extension === 'sql') {
            clearPreview();
            $('#importPreviewContainer').removeClass('d-none');
            $('#importPreviewStats').text('SQL dump selected');
            $('#importPreviewAlert')
                .removeClass('d-none')
                .html('SQL dump ready. The importer will extract <strong>pdl_info</strong> and <strong>pdl_authorized_visitors</strong> rows only.');
            currentPreviewFile = file;
            return;
        }

        const reader = new FileReader();
        reader.onload = function (event) {
            const rawText = event.target.result;
            const rows = parseCsvText(rawText);
            if (rows.length === 0) {
                Swal.fire('Empty File', 'The selected file appears to have no rows.', 'warning');
                clearPreview();
                return;
            }
            currentPreviewFile = file;
            originalPreviewRows = rows;
            duplicatesRemoved = false;
            renderPreview(rows);
        };
        reader.onerror = function () {
            Swal.fire('Read Error', 'Unable to read the selected file.', 'error');
            clearPreview();
        };
        reader.readAsText(file, 'UTF-8');
    }

    $fileInput.on('change', function () {
        const file = this.files && this.files.length ? this.files[0] : null;
        setSelectedFileName(file);
        previewFile(file);
    });

    $removeDuplicateRowsBtn.on('click', function () {
        if (!previewRows || !originalPreviewRows || !currentPreviewFile) {
            return;
        }

        const cleanedRows = removeDuplicateRows(previewRows);
        if (cleanedRows.length === previewRows.length) {
            Swal.fire('No duplicates found', 'There are no duplicate rows to remove.', 'info');
            return;
        }

        duplicatesRemoved = true;
        previewRows = cleanedRows;
        setFileFromRows(previewRows, currentPreviewFile);
        renderPreview(previewRows);
        $resetPreviewBtn.removeClass('d-none');
        Swal.fire('Duplicates removed', 'Duplicate rows were removed from the preview and updated in the import file.', 'success');
    });

    $keepDuplicateRowsBtn.on('click', function () {
        if (!originalPreviewRows || !currentPreviewFile) {
            return;
        }

        duplicatesRemoved = false;
        previewRows = originalPreviewRows;
        setFileFromRows(previewRows, currentPreviewFile);
        renderPreview(previewRows);
        Swal.fire('Duplicates kept', 'Duplicate rows remain in the preview and import file.', 'success');
    });

    $editDuplicateRowsBtn.on('click', function () {
        if (!previewRows || !originalPreviewRows) {
            return;
        }

        setEditableDuplicateRows(true);
        $saveDuplicateEditsBtn.removeClass('d-none');
        $editDuplicateRowsBtn.addClass('d-none');
    });

    $saveDuplicateEditsBtn.on('click', function () {
        saveDuplicateEdits();
        $(this).addClass('d-none');
        $editDuplicateRowsBtn.removeClass('d-none');
    });

    $resetPreviewBtn.on('click', function () {
        if (!originalPreviewRows || !currentPreviewFile) {
            return;
        }

        duplicatesRemoved = false;
        previewRows = originalPreviewRows;
        setFileFromRows(previewRows, currentPreviewFile);
        renderPreview(previewRows);
        $resetPreviewBtn.addClass('d-none');
        Swal.fire('Preview restored', 'The preview and import file have been restored to the original selection.', 'success');
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
        const allowedExtensions = importMode === 'visitors' ? ['csv', 'txt'] : ['csv', 'txt', 'sql'];
        if (!allowedExtensions.includes(extension)) {
            Swal.fire('Invalid File', 'Please choose a ' + (importMode === 'visitors' ? 'CSV or TXT' : 'CSV, TXT, or SQL') + ' file.', 'warning');
            clearPreview();
            return;
        }

        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);
        document.getElementById('importFile').files = dataTransfer.files;
        setSelectedFileName(file);
        previewFile(file);
    });

    $('#pdlImportForm').on('submit', function (e) {
        e.preventDefault();

        const fileInput = document.getElementById('importFile');
        if (!fileInput || !fileInput.files.length) {
            Swal.fire('No File', 'Please select a ' + (importMode === 'visitors' ? 'CSV or TXT' : 'CSV, TXT, or SQL') + ' file to import.', 'warning');
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
