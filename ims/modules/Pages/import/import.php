<?php
require_once ROOT_PATH . '/helpers/functions.php';
require_login();
require_once ROOT_PATH . '/modules/layout/header.php';

$importModuleKey = $importModuleKey ?? trim((string) ($_GET['module'] ?? 'equipment'));

$importModules = [
    'equipment' => ['title' => 'Import Equipment', 'records' => BASE_URL . '/ictd/equipment/manage', 'icon' => 'bi-hdd-network-fill'],
    'supplies' => ['title' => 'Import Supplies', 'records' => BASE_URL . '/ictd/supplies', 'icon' => 'bi-box-seam-fill'],
    'assign' => ['title' => 'Import Assignments', 'records' => BASE_URL . '/ictd/assign', 'icon' => 'bi-person-workspace'],
    'subscription' => ['title' => 'Import Subscriptions', 'records' => BASE_URL . '/ictd/subscription', 'icon' => 'bi-file-earmark-text-fill'],
    'borrowed' => ['title' => 'Import Borrowed Items', 'records' => BASE_URL . '/ictd/borrowed', 'icon' => 'bi-arrow-left-right'],
    'bidding' => ['title' => 'Import Bidding', 'records' => BASE_URL . '/ictd/bidding', 'icon' => 'bi-briefcase-fill'],
    'title' => ['title' => 'Import Titles', 'records' => BASE_URL . '/ictd/title', 'icon' => 'bi-type-bold'],
];

if (!isset($importModules[$importModuleKey])) {
    $importModuleKey = 'equipment';
}

$currentImport = $importModules[$importModuleKey];
$actionUrl = BASE_URL . '/ictd/import-action';
$templateUrl = $actionUrl . '?action=template&module=' . rawurlencode($importModuleKey);
?>

<style>
  .ims-import-shell { display: grid; gap: 1rem; }
  .ims-import-hero {
    border: 0;
    border-radius: 8px;
    color: #fff;
    background: linear-gradient(90deg, var(--ims-old-teal, #149aa0) 0%, var(--ims-old-navy, #07346c) 100%);
    box-shadow: 0 12px 26px rgba(15,23,42,.12);
  }
  .ims-import-hero .card-body { padding: 1.25rem; }
  .ims-import-card { border: 0; border-radius: 8px; box-shadow: 0 10px 24px rgba(15,23,42,.08); }
  .ims-import-drop {
    border: 1px dashed #9fb2c6;
    border-radius: 8px;
    padding: 1.25rem;
    background: #f8fbfd;
  }
  .ims-import-result { display: none; }
  .ims-import-result.show { display: block; }
  .ims-import-result pre {
    max-height: 260px;
    overflow: auto;
    margin: 0;
    white-space: pre-wrap;
    font-size: 11px;
  }
  .ims-import-template-table th,
  .ims-import-template-table td { white-space: nowrap; }
</style>

<div class="container-fluid py-3">
  <div class="ims-import-shell">
    <div class="card ims-import-hero">
      <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
          <div class="small fw-bold text-uppercase opacity-75">CSV Import</div>
          <h4 class="fw-bold mb-1"><i class="bi <?= e($currentImport['icon']) ?> me-2"></i><?= e($currentImport['title']) ?></h4>
          <div class="opacity-75">Download the template, fill in the rows, then upload the CSV file for validation and saving.</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <a class="btn btn-light btn-sm" href="<?= e($templateUrl) ?>">
            <i class="bi bi-download me-1"></i> Download Template
          </a>
          <a class="btn btn-outline-light btn-sm" href="<?= e($currentImport['records']) ?>">
            <i class="bi bi-table me-1"></i> Open Records
          </a>
        </div>
      </div>
    </div>

    <div class="row g-3">
      <div class="col-lg-7">
        <div class="card ims-import-card">
          <div class="card-body">
            <h6 class="fw-bold mb-2">Upload CSV</h6>
            <form id="imsImportForm" enctype="multipart/form-data">
              <input type="hidden" name="action" value="import">
              <input type="hidden" name="module" value="<?= e($importModuleKey) ?>">
              <div class="ims-import-drop mb-3">
                <label class="form-label fw-bold">CSV File</label>
                <input type="file" class="form-control" name="import_file" accept=".csv,text/csv" required>
                <div class="text-muted small mt-2">Use UTF-8 CSV format. Keep the first row as column headers.</div>
              </div>
              <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" value="1" id="dryRun" name="dry_run">
                <label class="form-check-label" for="dryRun">Validate only, do not save yet</label>
              </div>
              <button type="submit" class="btn btn-primary" id="imsImportSubmit">
                <i class="bi bi-upload me-1"></i> Validate and Import
              </button>
            </form>
          </div>
        </div>

        <div class="card ims-import-card ims-import-result mt-3" id="imsImportResult">
          <div class="card-body">
            <h6 class="fw-bold mb-2" id="imsImportResultTitle">Import Result</h6>
            <pre id="imsImportResultBody"></pre>
          </div>
        </div>
      </div>

      <div class="col-lg-5">
        <div class="card ims-import-card">
          <div class="card-body">
            <h6 class="fw-bold mb-2">Import Notes</h6>
            <ul class="mb-0">
              <li>Required columns must not be blank.</li>
              <li>Name fields may use existing masterlist names or numeric IDs.</li>
              <li>Unknown masterlist names are created when safe, such as suppliers, brands, descriptions, departments, funds, and users.</li>
              <li>Rows with errors are skipped and listed in the result.</li>
              <li>Use Validate only first when importing a large file.</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.getElementById('imsImportForm').addEventListener('submit', async function (event) {
  event.preventDefault();
  const form = event.currentTarget;
  const button = document.getElementById('imsImportSubmit');
  const result = document.getElementById('imsImportResult');
  const title = document.getElementById('imsImportResultTitle');
  const body = document.getElementById('imsImportResultBody');
  button.disabled = true;
  button.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Processing...';
  result.classList.remove('show');

  try {
    const response = await fetch('<?= e($actionUrl) ?>', {
      method: 'POST',
      body: new FormData(form),
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    const data = await response.json();
    title.textContent = data.success ? 'Import Completed' : 'Import Failed';
    body.textContent = [
      data.message || '',
      '',
      'Inserted: ' + (data.inserted || 0),
      'Skipped: ' + (data.skipped || 0),
      'Rows Read: ' + (data.rows_read || 0),
      '',
      (data.errors && data.errors.length ? 'Errors:\n- ' + data.errors.join('\n- ') : 'No row errors.')
    ].join('\n');
    result.classList.add('show');
  } catch (error) {
    title.textContent = 'Import Failed';
    body.textContent = error.message || 'Unable to process import.';
    result.classList.add('show');
  } finally {
    button.disabled = false;
    button.innerHTML = '<i class="bi bi-upload me-1"></i> Validate and Import';
  }
});
</script>

<?php require_once ROOT_PATH . '/modules/layout/footer.php'; ?>
