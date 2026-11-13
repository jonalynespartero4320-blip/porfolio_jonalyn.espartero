<?php

require_once ROOT_PATH . '/helpers/functions.php';

$current = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

$modules = [

    [
        "title" => "MAIN",
        "items" => [
            [
                'slug' => 'dashboard',
                'title' => 'Dashboard',
                'icon' => 'bi-speedometer2',
                'href' => BASE_URL . '/dashboard',
                'permission' => 'dashboard.view'
            ],
            [
                'slug' => 'queue',
                'title' => 'Queue',
                'icon' => 'bi-upc-scan',
                'href' => BASE_URL . '/queue',
                'permission' => 'queue.view'
            ],
            [
                'slug' => 'special-cases',
                'title' => 'Special Cases',
                'icon' => 'bi-stars',
                'href' => BASE_URL . '/special-cases',
                'super_admin_only' => true
            ],
        ]
    ],

    [
        "title" => "MODULES",
        "items" => [
            [
                'slug' => 'pdl-management',
                'title' => 'Resident Management',
                'icon' => 'bi-file',
                'href' => BASE_URL . '/pdl-management',
                'permission' => 'pdl-management.view'
            ],
            [
                'slug' => 'visitor-registration',
                'title' => 'Visitor Registration',
                'icon' => 'bi-person-vcard',
                'href' => BASE_URL . '/visitor-registration',
                'permission' => 'visitors.view'
            ],
            [
                'slug' => 'visitor-management',
                'title' => 'Visitor Management',
                'icon' => 'bi-people-fill',
                'href' => BASE_URL . '/visitor-management',
                'permission' => 'visitors.view'
            ],
            [
                'slug' => 'id-issuance',
                'title' => 'ID Issuance',
                'icon' => 'bi-person-vcard-fill',
                'href' => BASE_URL . '/id-issuance',
                'permission' => 'id-issuance.view'
            ]
        ]
    ],

    [
        "title" => "REPORTING",
        "items" => [
            [
                'slug' => 'reports',
                'title' => 'Reports',
                'icon' => 'bi-bar-chart-line',
                'href' => BASE_URL . '/reports',
                'permission' => 'reports.view'
            ],
        ]
    ],

];

?>

<aside class="sidebar">

    <!-- BRAND -->
    <div class="sidebar-brand text-center py-3">
        <img
            src="<?= BASE_URL ?>/assets/images/demo-mark.svg"
            alt="Demo ID symbol"
            class="sidebar-brand-logo"
        >
        <div class="fw-bold">ID & Queuing System</div>
    </div>

    <!-- MENU -->
    <nav class="sidebar-menu px-2">

        <?php foreach($modules as $module): ?>

            <?php
            // 🔥 Filter visible items
            $visibleItems = array_filter($module['items'], function($item) {
                if (!empty($item['super_admin_only']) && !is_super_admin()) {
                    return false;
                }

                return !isset($item['permission']) || can($item['permission']);
            });

            // Skip empty modules
            if (empty($visibleItems)) continue;
            ?>

            <div class="sidebar-module mb-3">

                <div class="sidebar-divider text-uppercase small text-light px-2 mb-2">
                    <?= $module['title'] ?>
                </div>

                <?php foreach($visibleItems as $item): ?>

                    <?php
                    $isActive = str_contains(
                        $current,
                        trim(parse_url($item['href'], PHP_URL_PATH), '/')
                    );
                    ?>

                    <a 
                        href="<?= $item['href'] ?>"
                        class="sidebar-link d-flex align-items-center gap-2 px-2 py-2 mb-1 rounded <?= $isActive ? 'active' : '' ?>"
                    >
                        <i class="bi <?= $item['icon'] ?>"></i>
                        <span><?= $item['title'] ?></span>
                    </a>

                <?php endforeach; ?>

            </div>

        <?php endforeach; ?>


        <!-- 🔥 ADMIN SECTION -->
        <?php if (is_admin()): ?>

            <div class="sidebar-module">

                <div class="sidebar-divider text-uppercase small text-warning px-2 mb-2">
                    ADMIN
                </div>

                <?php if (can('case.view')): ?>
                <a href="<?= BASE_URL ?>/masterlist" class="sidebar-link d-flex align-items-center gap-2 px-2 py-2">
                    <i class="bi bi-bookmark"></i>
                    <span>Masterlist</span>
                </a>
                <?php endif; ?>

                <?php if (can('users.view')): ?>
                <a href="<?= BASE_URL ?>/admin" class="sidebar-link d-flex align-items-center gap-2 px-2 py-2">
                    <i class="bi bi-person-gear"></i>
                    <span>Manage Users</span>
                </a>
                <?php endif; ?>

                <?php if (can('documentation.view')): ?>
                <a href="<?= BASE_URL ?>/documentation-process-flow" class="sidebar-link d-flex align-items-center gap-2 px-2 py-2">
                    <i class="bi bi-journal-text"></i>
                    <span>Documentation & Process Flow</span>
                </a>
                <?php endif; ?>

            </div>

        <?php endif; ?>

    </nav>

</aside>
