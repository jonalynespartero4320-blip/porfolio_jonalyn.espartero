<?php
$modulePage = $modulePage ?? [];
$page_title = $modulePage['title'] ?? 'Module';

require_once ROOT_PATH . '/modules/layout/header.php';

$title = (string) ($modulePage['title'] ?? 'Module');
$subtitle = (string) ($modulePage['subtitle'] ?? 'This module is ready for migration.');
$icon = (string) ($modulePage['icon'] ?? 'bi-grid');
$badge = (string) ($modulePage['badge'] ?? 'Inventory Management System');
$actions = $modulePage['actions'] ?? [];
?>

<div class="container-fluid py-3">
    <div class="card border-0 shadow-sm overflow-hidden">
        <div class="card-body p-4 p-lg-5">
            <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
                <div class="d-flex align-items-start gap-3">
                    <div class="module-page-icon">
                        <i class="bi <?= e($icon) ?>"></i>
                    </div>
                    <div>
                        <div class="text-uppercase text-muted small fw-bold mb-1"><?= e($badge) ?></div>
                        <h2 class="fw-bold mb-2"><?= e($title) ?></h2>
                        <p class="text-muted mb-0"><?= e($subtitle) ?></p>
                    </div>
                </div>
                <a href="<?= BASE_URL ?>/ictd/dashboard" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-12 col-lg-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-2">Module Workspace</h5>
                    <p class="text-muted mb-4">
                        This page is wired to the new MIS environment with its own route, permission check, layout, and sidebar active state.
                    </p>

                    <?php if (!empty($actions)): ?>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($actions as $action): ?>
                                <a href="<?= e($action['href'] ?? '#') ?>" class="btn <?= e($action['class'] ?? 'btn-primary') ?>">
                                    <?php if (!empty($action['icon'])): ?>
                                        <i class="bi <?= e($action['icon']) ?>"></i>
                                    <?php endif; ?>
                                    <?= e($action['label'] ?? 'Open') ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info mb-0">
                            Old-system logic can now be migrated into this module file without changing the sidebar path again.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3">Migration Status</h6>
                    <div class="d-flex align-items-center gap-2 text-success fw-semibold mb-2">
                        <i class="bi bi-check2-circle"></i>
                        Route connected
                    </div>
                    <div class="d-flex align-items-center gap-2 text-success fw-semibold mb-2">
                        <i class="bi bi-check2-circle"></i>
                        Sidebar path connected
                    </div>
                    <div class="d-flex align-items-center gap-2 text-warning fw-semibold">
                        <i class="bi bi-hourglass-split"></i>
                        Feature implementation pending
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once ROOT_PATH . '/modules/layout/footer.php'; ?>
