<?php
require_once ROOT_PATH . '/helpers/functions.php';
require_login();
require_permission('documentation.view');
require_once ROOT_PATH . '/modules/layout/header.php';

$documentationBundleUrl = dirname(BASE_URL, 2) . '/demo/';

$docs = [
    [
        'title' => 'Flowchart',
        'subtitle' => 'Visual map of the main workflow for quick orientation.',
        'icon' => 'bi-bezier2',
        // 'file' => dirname(BASE_URL, 2) . '/demo/',
        // 'pdf' => dirname(BASE_URL, 2) . '/demo/',
        'type' => 'svg',
        'accent' => 'success',
    ],
    [
        'title' => 'User Manual',
        'subtitle' => 'Updated daily-use instructions for staff, admin, and super admin users.',
        'icon' => 'bi-journal-richtext',
        'file' => dirname(BASE_URL, 2) . '/demo/',
        'pdf' => dirname(BASE_URL, 2) . '/demo/',
        'type' => 'pdf',
        'accent' => 'warning',
    ],
    [
        'title' => 'Technical Terms & User Guide',
        'subtitle' => 'Reference document for system terms, user roles, permissions, and user guidance.',
        'icon' => 'bi-braces-asterisk',
        'file' => dirname(BASE_URL, 2) . '/demo/',
        'pdf' => dirname(BASE_URL, 2) . '/demo/',
        'type' => 'pdf',
        'accent' => 'info',
    ],
    [
        'title' => 'Buttons to Remember',
        'subtitle' => 'Quick reference guide explaining the common buttons users usually encounter in the system.',
        'icon' => 'bi-hand-index-thumb',
        'file' => dirname(BASE_URL, 2) . '/demo/',
        'pdf' => dirname(BASE_URL, 2) . '/demo/',
        'type' => 'pdf',
        'accent' => 'primary',
    ],
];
?>

<style>
  .docs-shell { display: grid; gap: 1.25rem; }
  .docs-hero {
    position: relative;
    overflow: hidden;
    border: 0;
    border-radius: 28px;
    background:
      linear-gradient(135deg, #062150, #447A61);
    color: #fff;
    box-shadow: 0 24px 60px rgba(8, 19, 31, 0.14);
  }
  .docs-hero::after {
    content: "";
    position: absolute;
    right: -80px;
    bottom: -80px;
    width: 220px;
    height: 220px;
    border-radius: 50%;
    background: rgba(255,255,255,0.1);
  }
  .docs-hero .card-body { position: relative; z-index: 1; padding: 1.6rem; }
  .docs-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem;
  }
  .docs-card {
    border: 0;
    border-radius: 24px;
    box-shadow: 0 16px 38px rgba(15, 23, 42, 0.08);
  }
  .docs-preview-card {
    border: 0;
    border-radius: 24px;
    box-shadow: 0 16px 38px rgba(15, 23, 42, 0.08);
    overflow: hidden;
  }
  .docs-card .card-body { padding: 1.25rem; }
  .docs-icon {
    width: 56px;
    height: 56px;
    border-radius: 18px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
  }
  .docs-actions {
    display: flex;
    gap: .75rem;
    flex-wrap: wrap;
  }
  .docs-note {
    border: 1px solid rgba(148, 163, 184, 0.22);
    border-radius: 20px;
    background: rgba(255, 255, 255, 0.86);
    padding: 1rem 1.15rem;
  }
  .docs-preview-frame {
    width: 100%;
    min-height: 720px;
    border: 1px solid rgba(148, 163, 184, 0.18);
    border-radius: 18px;
    background: #fff;
  }
  @media (max-width: 1199px) {
    .docs-grid { grid-template-columns: 1fr; }
  }
</style>

<div class="container-fluid py-3">
    <div class="docs-shell">
        <div class="card docs-hero">
            <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <div class="text-uppercase small fw-semibold opacity-75 mb-2">Document Library</div>
                    <h2 class="fw-bold mb-1"><i class="bi bi-folder2-open me-2"></i>Documentation</h2>
                    <p class="mb-0 opacity-75">Download the user guides, quick references, technical reference, flowchart, and complete documentation bundle.</p>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <span class="badge bg-light text-dark fs-6"><?= count($docs) ?> document(s)</span>
                    <a href="<?= e($documentationBundleUrl) ?>" download class="btn btn-light btn-sm">
                        <i class="bi bi-file-zip me-1"></i> Download All
                    </a>
                </div>
            </div>
        </div>

        <!-- <div class="docs-note">
            <h6 class="fw-bold mb-1"><i class="bi bi-info-circle me-1 text-primary"></i>Simple documentation view</h6>
            <p class="text-muted small mb-0">Detailed step-by-step instructions are hidden from this page and are available only inside the downloaded PDF documents.</p>
        </div> -->

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
                            <span class="badge text-bg-light text-dark"><?= strtoupper((string) ($doc['type'] ?? 'file')) ?></span>
                        </div>
                        <h5 class="fw-bold mb-2"><?= e($doc['title']) ?></h5>
                        <p class="text-muted mb-3"><?= e($doc['subtitle']) ?></p>
                        <div class="docs-actions">
                            <?php if (!empty($doc['pdf'])): ?>
                                <a href="<?= e($doc['pdf']) ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-box-arrow-up-right me-1"></i> Open PDF
                                </a>
                                <a href="<?= e($doc['pdf']) ?>" download class="btn btn-outline-danger btn-sm">
                                    <i class="bi bi-file-earmark-pdf me-1"></i> Download PDF
                                </a>
                            <?php elseif (!empty($doc['file'])): ?>
                                <a href="<?= e($doc['file']) ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-box-arrow-up-right me-1"></i> Open File
                                </a>
                                <a href="<?= e($doc['file']) ?>" download class="btn btn-outline-success btn-sm">
                                    <i class="bi bi-download me-1"></i> Download File
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="card docs-preview-card">
            <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h6 class="fw-bold mb-0">Document Preview</h6>
                    <small class="text-muted">Preview of the updated ID & Queuing System User Manual.</small>
                </div>
                <a href="<?= BASE_URL ?>/downloads/bjmp_vaqs_user_manual.pdf" target="_blank" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-box-arrow-up-right me-1"></i> Open Preview PDF
                </a>
            </div>
            <div class="card-body px-4 pb-4">
                <iframe
                    src="<?= BASE_URL ?>/downloads/bjmp_vaqs_user_manual.pdf"
                    title="ID & Queuing System User Manual PDF Preview"
                    class="docs-preview-frame"
                ></iframe>
            </div>
        </div>

    </div>
</div>

<?php require_once ROOT_PATH . '/modules/layout/footer.php'; ?>
