<?php
require_once ROOT_PATH . '/helpers/functions.php';
require_login();
require_permission('documentation.view');
require_once ROOT_PATH . '/modules/layout/header.php';

$documentationBundleUrl = dirname(BASE_URL, 2) . '/demo/';

$docs = [
    [
        'title' => 'Documentation Pack',
        'subtitle' => 'Combined process flow, module guide, role guide, and user manual reference.',
        'icon' => 'bi-folder2-open',
        'file' => dirname(BASE_URL, 2) . '/demo/',
        'type' => 'html',
        'accent' => 'danger',
    ],
    [
        'title' => 'Process Flow',
        'subtitle' => 'Step-by-step operational process of the whole system.',
        'icon' => 'bi-diagram-3',
        'file' => dirname(BASE_URL, 2) . '/demo/',
        'type' => 'html',
        'accent' => 'primary',
    ],
    [
        'title' => 'Flowchart',
        'subtitle' => 'Visual system workflow from authentication to reporting.',
        'icon' => 'bi-bezier2',
        'file' => dirname(BASE_URL, 2) . '/demo/',
        'type' => 'svg',
        'accent' => 'success',
    ],
    [
        'title' => 'User Manual',
        'subtitle' => 'Operational guide for staff, admin, and super admin users.',
        'icon' => 'bi-journal-richtext',
        'file' => dirname(BASE_URL, 2) . '/demo/',
        'type' => 'html',
        'accent' => 'warning',
    ],
];
?>

<style>
  .docs-shell { display: grid; gap: 1rem; }
  .docs-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; }
  .docs-eyebrow { color: #667085; font-size: .78rem; font-weight: 800; }
  .docs-title { margin: 0; color: #1f2937; font-size: 1.35rem; font-weight: 850; }
  .docs-subtitle { color: #667085; font-size: .86rem; }
  .docs-head-actions { display: flex; gap: .65rem; flex-wrap: wrap; justify-content: flex-end; }
  .docs-head-actions .badge,
  .docs-head-actions .btn { min-height: 38px; border-radius: 4px; display: inline-flex; align-items: center; }
  .docs-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: .8rem;
  }
  .docs-card, .docs-preview-card {
    border: 0;
    border-radius: 10px;
    background: rgba(255,255,255,.82);
    box-shadow: none;
  }
  .docs-card .card-body { padding: 1rem; }
  .docs-icon {
    width: 42px;
    height: 42px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.05rem;
    background: rgba(20,154,160,.10) !important;
    color: var(--ims-old-teal-dark, #0b5c66) !important;
  }
  .docs-type-badge {
    border: 1px solid #eaecf0;
    background: #fff !important;
    color: #344054 !important;
    font-size: .68rem;
    border-radius: 4px;
    padding: .32rem .48rem;
  }
  .docs-card h5 {
    color: #1f2937;
    font-size: .94rem;
    font-weight: 850;
  }
  .docs-card p {
    min-height: 42px;
    font-size: .78rem;
  }
  .docs-actions {
    display: flex;
    gap: .5rem;
    flex-wrap: wrap;
  }
  .docs-actions .btn { border-radius: 4px; font-size: .76rem; }
  .docs-preview-card .card-header {
    padding: 1rem;
    border-bottom: 1px solid #edf0f5 !important;
    background: rgba(255,255,255,.88) !important;
  }
  .docs-preview-card .card-body { padding: 1rem; }
  .docs-preview-frame {
    width: 100%;
    min-height: 640px;
    border: 1px solid rgba(148, 163, 184, 0.18);
    border-radius: 8px;
    background: #fff;
  }
  @media (max-width: 1399px) {
    .docs-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  }
  @media (max-width: 1199px) {
    .docs-grid { grid-template-columns: 1fr; }
  }
  @media (max-width: 767px) {
    .docs-head { flex-direction: column; }
    .docs-head-actions, .docs-head-actions .btn { width: 100%; }
    .docs-head-actions .btn { justify-content: center; }
  }
</style>

<div class="container-fluid py-3">
    <div class="docs-shell">
        <div class="docs-head">
            <div>
                <div class="docs-eyebrow">Documentation Center</div>
                <h2 class="docs-title"><i class="bi bi-folder2-open me-1"></i>Documentation & Process Flow</h2>
                <div class="docs-subtitle">Open the IMS process flow, operational flowchart, and user manual from one internal documentation workspace.</div>
            </div>
            <div class="docs-head-actions">
                <span class="badge text-bg-light text-dark"><?= count($docs) ?> document(s)</span>
                <span class="badge text-bg-light text-dark">Internal Reference</span>
                <a href="<?= e($documentationBundleUrl) ?>" download class="btn btn-primary btn-sm">
                    <i class="bi bi-file-zip me-1"></i> Download All
                </a>
            </div>
        </div>

        <div class="docs-grid">
            <?php foreach ($docs as $doc): ?>
                <div class="card docs-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                            <div>
                                <div class="docs-icon bg-<?= $doc['accent'] ?>-subtle text-<?= $doc['accent'] ?>">
                                    <i class="bi <?= $doc['icon'] ?>"></i>
                                </div>
                            </div>
                            <span class="badge docs-type-badge"><?= strtoupper($doc['type']) ?></span>
                        </div>
                        <h5 class="fw-bold mb-2"><?= e($doc['title']) ?></h5>
                        <p class="text-muted mb-3"><?= e($doc['subtitle']) ?></p>
                        <div class="docs-actions">
                            <a href="<?= e($doc['file']) ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-box-arrow-up-right me-1"></i> Open
                            </a>
                            <a href="<?= e($doc['file']) ?>" download class="btn btn-success btn-sm">
                                <i class="bi bi-download me-1"></i> Download
                            </a>
                            <?php if (!empty($doc['pdf'])): ?>
                                <a href="<?= e($doc['pdf']) ?>" download class="btn btn-outline-danger btn-sm">
                                    <i class="bi bi-file-earmark-pdf me-1"></i> Download PDF
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="card docs-preview-card">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <h6 class="fw-bold mb-0">Quick Preview</h6>
                <small class="text-muted">Loads the process flow document directly inside the module for quick viewing.</small>
            </div>
            <div class="card-body px-4 pb-4">
                <iframe
                    src="<?= BASE_URL ?>/downloads/ims_process_flow.html"
                    title="IMS Process Flow Preview"
                    class="docs-preview-frame"
                ></iframe>
            </div>
        </div>
    </div>
</div>

<?php require_once ROOT_PATH . '/modules/layout/footer.php'; ?>
