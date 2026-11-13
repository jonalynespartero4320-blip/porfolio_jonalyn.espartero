<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once ROOT_PATH . '/helpers/functions.php';
require_login();

$user = current_user();

if (isset($allowed_roles) && !in_array($user['role_name'], $allowed_roles, true)) {
    die('Access Denied');
}

if (isset($required_permission)) {
    require_permission($required_permission);
}

$page_title = $page_title ?? 'Inventory Management System';
$manualDownloadUrl = dirname(BASE_URL, 2) . '/demo/?project=ims';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($page_title) ?></title>
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/images/demo-mark.svg">
    <link rel="shortcut icon" type="image/png" href="<?= BASE_URL ?>/assets/images/demo-mark.svg">
    <link rel="apple-touch-icon" href="<?= BASE_URL ?>/assets/images/demo-mark.svg">

    <script>
        const APP_BASE_URL = <?= json_encode(BASE_URL) ?>;
    </script>

    <?php include ROOT_PATH . '/modules/layout/bootstrap.php'; ?>
</head>
<body>
<?php require_once dirname(__DIR__, 3) . '/demo/home-link.php'; ?>
<script>
try {
    if (window.innerWidth > 991 && localStorage.getItem('ims.sidebar.collapsed') === '1') {
        document.body.classList.add('sidebar-collapsed');
    }
} catch (error) {}
</script>

<div class="layout">
    <?php include ROOT_PATH . '/modules/layout/sidebar.php'; ?>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <main class="content">
        <div class="topbar d-flex justify-content-between align-items-center px-3 py-2 shadow-sm">
            <div class="d-flex align-items-center gap-3">
                <button type="button" class="sidebar-toggle btn btn-outline-secondary btn-sm" id="sidebarToggle" aria-label="Toggle sidebar">
                    <i class="bi bi-list"></i>
                </button>
                <div class="system-title fw-bold">
                    <i class="bi bi-pc-display-horizontal"></i>
                    <span>Inventory Management System</span>
                </div>

                <div class="topbar-clock text-muted">
                    <i class="bi bi-clock"></i>
                    <span id="currentDateTime"></span>
                </div>
            </div>

            <div class="d-flex align-items-center gap-3">
                <?php if (can('reports.view')): ?>
                    <a href="<?= BASE_URL ?>/activity-logs" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-clock-history"></i> Logs
                    </a>
                <?php endif; ?>

                <a href="<?= htmlspecialchars($manualDownloadUrl) ?>" download class="btn btn-outline-success btn-sm">
                    <i class="bi bi-download"></i> Manual
                </a>

                <div class="user-panel d-flex align-items-center gap-2">
                    <i class="bi bi-person-circle fs-4"></i>

                    <div>
                        <div class="fw-semibold"><?= htmlspecialchars($user['username'] ?? 'User') ?></div>
                        <small class="text-muted"><?= htmlspecialchars($user['role_name'] ?? 'User') ?></small>
                    </div>
                </div>

                <a href="<?= BASE_URL ?>/change-password" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-key"></i> Change Password
                </a>

                <a href="<?= BASE_URL ?>/logout" class="btn btn-danger btn-sm">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </div>

        <div class="p-3">
            <?php if (!empty($_SESSION['notification'])): ?>
                <?php
                show_notification(
                    $_SESSION['notification']['type'],
                    $_SESSION['notification']['message']
                );

                unset($_SESSION['notification']);
                ?>
            <?php endif; ?>
