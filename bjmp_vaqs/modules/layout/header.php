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

$page_title = $page_title ?? 'ID & Queuing System';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($page_title) ?></title>

    <script>
        const APP_BASE_URL = <?= json_encode(BASE_URL) ?>;
    </script>

    <?php include ROOT_PATH . '/modules/layout/bootstrap.php'; ?>
</head>
<body>
<?php require_once dirname(__DIR__, 3) . '/demo/home-link.php'; ?>

<div class="layout">
    <?php include ROOT_PATH . '/modules/layout/sidebar.php'; ?>

    <main class="content">
        <div class="topbar d-flex justify-content-between align-items-center px-3 py-2 shadow-sm">
            <div class="d-flex align-items-center gap-3">
                <div class="system-title fw-bold">
                    <i class="bi bi-shield-lock"></i>
                    <span>ID & Queuing System</span>
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

                <a href="<?= BASE_URL ?>/logout" class="btn btn-danger btn-sm" data-logout-clear-state="1">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </div>

        <div class="px-3 pb-3 pt-1">
            <?php if (!empty($_SESSION['notification'])): ?>
                <?php
                show_notification(
                    $_SESSION['notification']['type'],
                    $_SESSION['notification']['message']
                );

                unset($_SESSION['notification']);
                ?>
            <?php endif; ?>
