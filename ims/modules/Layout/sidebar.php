<?php

require_once ROOT_PATH . '/helpers/functions.php';

$current = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$adminPath = trim(parse_url(BASE_URL . '/admin', PHP_URL_PATH), '/');
$logsPath = trim(parse_url(BASE_URL . '/activity-logs', PHP_URL_PATH), '/');
$accountPath = trim(parse_url(BASE_URL . '/account', PHP_URL_PATH), '/');
$changePasswordPath = trim(parse_url(BASE_URL . '/change-password', PHP_URL_PATH), '/');

$modules = [
    [
        'title' => 'Main',
        'icon' => 'bi-house-door-fill',
        'items' => [
            [
                'slug' => 'dashboard',
                'title' => 'Dashboard',
                'icon' => 'bi-grid-1x2-fill',
                'href' => BASE_URL . '/ictd/dashboard',
                'permission' => 'ictd.dashboard.view'
            ],
        ]
    ],
    // [
    //     'title' => 'Request',
    //     'icon' => 'bi-inboxes-fill',
    //     'items' => [
    //         [
    //             'slug' => 'request_modules',
    //             'title' => 'Request Modules',
    //             'icon' => 'bi-diagram-3-fill',
    //             'href' => BASE_URL . '/ictd/requests/modules',
    //             'permission' => 'ictd.request.view'
    //         ],
    //         [
    //             'slug' => 'request_equipment_group',
    //             'title' => 'Equipment',
    //             'icon' => 'bi-hdd-stack-fill',
    //             'children' => [
    //                 [
    //                     'slug' => 'request_equipment',
    //                     'title' => 'Request Equipment',
    //                     'icon' => 'bi-hdd-stack-fill',
    //                     'href' => BASE_URL . '/ictd/requests/equipment',
    //                     'permission' => 'ictd.request.view'
    //                 ],
    //                 [
    //                     'slug' => 'request_equipment_archive',
    //                     'title' => 'Request Archive',
    //                     'icon' => 'bi-archive-fill',
    //                     'href' => BASE_URL . '/ictd/requests/equipment/archive',
    //                     'permission' => 'ictd.request.view'
    //                 ],
    //                 [
    //                     'slug' => 'request_equipment_status',
    //                     'title' => 'Request Status',
    //                     'icon' => 'bi-list-check',
    //                     'href' => BASE_URL . '/ictd/requests/equipment-status',
    //                     'permission' => 'ictd.request.manage'
    //                 ],
    //             ],
    //         ],
    //         [
    //             'slug' => 'request_service_group',
    //             'title' => 'Service',
    //             'icon' => 'bi-tools',
    //             'children' => [
    //                 [
    //                     'slug' => 'request_service',
    //                     'title' => 'Request Service',
    //                     'icon' => 'bi-tools',
    //                     'href' => BASE_URL . '/ictd/requests/service',
    //                     'permission' => 'ictd.request.view'
    //                 ],
    //                 [
    //                     'slug' => 'request_service_archive',
    //                     'title' => 'Request Archive',
    //                     'icon' => 'bi-archive-fill',
    //                     'href' => BASE_URL . '/ictd/requests/service/archive',
    //                     'permission' => 'ictd.request.view'
    //                 ],
    //                 [
    //                     'slug' => 'request_service_status',
    //                     'title' => 'Request Status',
    //                     'icon' => 'bi-wrench-adjustable-circle',
    //                     'href' => BASE_URL . '/ictd/requests/service-status',
    //                     'permission' => 'ictd.request.manage'
    //                 ],
    //             ],
    //         ],
    //     ]
    // ],
    [
        'title' => 'Equipment',
        'icon' => 'bi-hdd-network-fill',
        'items' => [
            [
                'slug' => 'equipment',
                'title' => 'Manage Equipment',
                'icon' => 'bi-pc-display',
                'href' => BASE_URL . '/ictd/equipment/manage',
                'permission' => 'ictd.equipment.view'
            ],
            [
                'slug' => 'equipment_archive',
                'title' => 'Archive',
                'icon' => 'bi-archive-fill',
                'href' => BASE_URL . '/ictd/equipment/archive',
                'permission' => 'ictd.equipment.archive'
            ],
            [
                'slug' => 'import_equipment',
                'title' => 'Import data',
                'icon' => 'bi-upload',
                'href' => BASE_URL . '/ictd/equipment/import',
                'permission' => 'ictd.equipment.import'
            ],
        ]
    ],
    [
        'title' => 'Supplies',
        'icon' => 'bi-box-seam-fill',
        'items' => [
            [
                'slug' => 'supplies',
                'title' => 'Manage Supplies',
                'icon' => 'bi-box-seam-fill',
                'href' => BASE_URL . '/ictd/supplies',
                'permission' => 'ictd.supplies.view'
            ],
            [
                'slug' => 'supplies_archive',
                'title' => 'Archive',
                'icon' => 'bi-archive-fill',
                'href' => BASE_URL . '/ictd/supplies/archive',
                'permission' => 'ictd.supplies.view'
            ],
            [
                'slug' => 'supplies_import',
                'title' => 'Import data',
                'icon' => 'bi-upload',
                'href' => BASE_URL . '/ictd/supplies/import',
                'permission' => 'ictd.supplies.view'
            ],
        ]
    ],
    [
        'title' => 'Assign',
        'icon' => 'bi-person-workspace',
        'items' => [
            [
                'slug' => 'assign_main',
                'title' => 'Manage Assign',
                'icon' => 'bi-person-workspace',
                'href' => BASE_URL . '/ictd/assign',
                'permission' => 'ictd.assign.view'
            ],
            [
                'slug' => 'assign_archive',
                'title' => 'Archive',
                'icon' => 'bi-archive-fill',
                'href' => BASE_URL . '/ictd/assign/archive',
                'permission' => 'ictd.assign.view'
            ],
            [
                'slug' => 'assign_import',
                'title' => 'Import data',
                'icon' => 'bi-upload',
                'href' => BASE_URL . '/ictd/assign/import',
                'permission' => 'ictd.assign.view'
            ],
        ]
    ],
    [
        'title' => 'Other operations',
        'icon' => 'bi-kanban-fill',
        'items' => [
            [
                'slug' => 'subscription_group',
                'title' => 'Subscription',
                'icon' => 'bi-file-earmark-text-fill',
                'children' => [
                    [
                        'slug' => 'subscription',
                        'title' => 'Manage Subscription',
                        'icon' => 'bi-file-earmark-text-fill',
                        'href' => BASE_URL . '/ictd/subscription',
                        'permission' => 'ictd.subscription.view'
                    ],
                    [
                        'slug' => 'subscription_archive',
                        'title' => 'Archive',
                        'icon' => 'bi-archive-fill',
                        'href' => BASE_URL . '/ictd/subscription/archive',
                        'permission' => 'ictd.subscription.view'
                    ],
                    [
                        'slug' => 'subscription_import',
                        'title' => 'Import data',
                        'icon' => 'bi-upload',
                        'href' => BASE_URL . '/ictd/subscription/import',
                        'permission' => 'ictd.subscription.view'
                    ],
                ],
            ],
            [
                'slug' => 'borrowed_group',
                'title' => 'Borrowed',
                'icon' => 'bi-arrow-left-right',
                'children' => [
                    [
                        'slug' => 'borrowed',
                        'title' => 'Manage Borrowed',
                        'icon' => 'bi-arrow-left-right',
                        'href' => BASE_URL . '/ictd/borrowed',
                        'permission' => 'ictd.borrowed.view'
                    ],
                    [
                        'slug' => 'borrowed_archive',
                        'title' => 'Archive',
                        'icon' => 'bi-archive-fill',
                        'href' => BASE_URL . '/ictd/borrowed/archive',
                        'permission' => 'ictd.borrowed.view'
                    ],
                    [
                        'slug' => 'borrowed_import',
                        'title' => 'Import data',
                        'icon' => 'bi-upload',
                        'href' => BASE_URL . '/ictd/borrowed/import',
                        'permission' => 'ictd.borrowed.view'
                    ],
                ],
            ],
            [
                'slug' => 'bidding_group',
                'title' => 'Bidding',
                'icon' => 'bi-briefcase-fill',
                'children' => [
                    [
                        'slug' => 'bidding',
                        'title' => 'Manage Bidding',
                        'icon' => 'bi-briefcase-fill',
                        'href' => BASE_URL . '/ictd/bidding',
                        'permission' => 'ictd.bidding.view'
                    ],
                    [
                        'slug' => 'bidding_archive',
                        'title' => 'Archive',
                        'icon' => 'bi-archive-fill',
                        'href' => BASE_URL . '/ictd/bidding/archive',
                        'permission' => 'ictd.bidding.view'
                    ],
                    [
                        'slug' => 'bidding_import',
                        'title' => 'Import data',
                        'icon' => 'bi-upload',
                        'href' => BASE_URL . '/ictd/bidding/import',
                        'permission' => 'ictd.bidding.view'
                    ],
                ],
            ],
            [
                'slug' => 'title_group',
                'title' => 'Title',
                'icon' => 'bi-type-bold',
                'children' => [
                    [
                        'slug' => 'title',
                        'title' => 'Manage Title',
                        'icon' => 'bi-type-bold',
                        'href' => BASE_URL . '/ictd/title',
                        'permission' => 'ictd.title.view'
                    ],
                    [
                        'slug' => 'title_archive',
                        'title' => 'Archive',
                        'icon' => 'bi-archive-fill',
                        'href' => BASE_URL . '/ictd/title/archive',
                        'permission' => 'ictd.title.view'
                    ],
                    [
                        'slug' => 'title_import',
                        'title' => 'Import data',
                        'icon' => 'bi-upload',
                        'href' => BASE_URL . '/ictd/title/import',
                        'permission' => 'ictd.title.view'
                    ],
                ],
            ],
        ]
    ],
    [
        'title' => 'Reports',
        'icon' => 'bi-graph-up-arrow',
        'items' => [
            [
                'slug' => 'service_report_main',
                'title' => 'Service Report',
                'icon' => 'bi-clipboard-data-fill',
                'href' => BASE_URL . '/ictd/service-report',
                'permission' => 'ictd.service-report.view'
            ],
            [
                'slug' => 'service_report_archive',
                'title' => 'Service Archive',
                'icon' => 'bi-archive-fill',
                'href' => BASE_URL . '/ictd/service-report/archive',
                'permission' => 'ictd.service-report.view'
            ],
            [
                'slug' => 'maintenance_report',
                'title' => 'Maintenance Report',
                'icon' => 'bi-tools',
                'href' => BASE_URL . '/ictd/maintenance',
                'permission' => 'ictd.maintenance.view'
            ],
            [
                'slug' => 'maintenance_archive',
                'title' => 'Maintenance Archive',
                'icon' => 'bi-archive-fill',
                'href' => BASE_URL . '/ictd/maintenance/archive',
                'permission' => 'ictd.maintenance.view'
            ],
            [
                'slug' => 'transmittal_report',
                'title' => 'Transmittal Report',
                'icon' => 'bi-send-fill',
                'href' => BASE_URL . '/ictd/transmittal',
                'permission' => 'ictd.transmittal.view'
            ],
            [
                'slug' => 'transmittal_archive',
                'title' => 'Transmittal Archive',
                'icon' => 'bi-archive-fill',
                'href' => BASE_URL . '/ictd/transmittal/archive',
                'permission' => 'ictd.transmittal.view'
            ],
            [
                'slug' => 'reports',
                'title' => 'General Report',
                'icon' => 'bi-bar-chart-line',
                'href' => BASE_URL . '/ictd/reports',
                'permission' => 'ictd.reports.view'
            ],
        ]
    ],
    [
        'title' => 'Masterlist',
        'icon' => 'bi-collection-fill',
        'items' => [
            [
                'slug' => 'masterlist_manage',
                'title' => 'Manage Masterlist',
                'icon' => 'bi-ui-checks-grid',
                'href' => BASE_URL . '/ictd/masterlists',
                'permission' => 'ictd.masterlist.view'
            ],
        ]
    ],
];

if (!function_exists('sidebar_item_visible')) {
    function sidebar_item_visible(array $item): bool
    {
        if (!empty($item['children'])) {
            return !empty(sidebar_visible_items($item['children']));
        }

        if (!isset($item['permission'])) {
            return true;
        }

        if ($item['permission'] === 'ictd.request.manage' && is_admin()) {
            return true;
        }

        return can($item['permission']);
    }
}

if (!function_exists('sidebar_visible_items')) {
    function sidebar_visible_items(array $items): array
    {
        $visible = [];

        foreach ($items as $item) {
            if (!empty($item['children'])) {
                $item['children'] = sidebar_visible_items($item['children']);
            }

            if (sidebar_item_visible($item)) {
                $visible[] = $item;
            }
        }

        return $visible;
    }
}

if (!function_exists('sidebar_path_active')) {
    function sidebar_path_active(string $href, string $current): bool
    {
        $itemPath = trim(parse_url($href, PHP_URL_PATH), '/');

        if ($current === $itemPath) {
            return true;
        }

        $prefixActivePaths = [
            'ictd/masterlists',
        ];

        return in_array($itemPath, $prefixActivePaths, true) && str_starts_with($current . '/', $itemPath . '/');
    }
}

if (!function_exists('sidebar_item_active')) {
    function sidebar_item_active(array $item, string $current): bool
    {
        if (!empty($item['children'])) {
            foreach ($item['children'] as $child) {
                if (sidebar_item_active($child, $current)) {
                    return true;
                }
            }

            return false;
        }

        return !empty($item['href']) && sidebar_path_active($item['href'], $current);
    }
}

if (!function_exists('sidebar_first_href')) {
    function sidebar_first_href(array $items): string
    {
        foreach ($items as $item) {
            if (!empty($item['href'])) {
                return $item['href'];
            }

            if (!empty($item['children'])) {
                $href = sidebar_first_href($item['children']);
                if ($href !== '#') {
                    return $href;
                }
            }
        }

        return '#';
    }
}

if (!function_exists('sidebar_submenu_id')) {
    function sidebar_submenu_id(string $prefix, array $item, int $index): string
    {
        $slug = (string) ($item['slug'] ?? ('item_' . $index));
        $safeSlug = preg_replace('/[^a-zA-Z0-9_-]/', '_', $slug);

        return $prefix . '_' . $index . '_' . $safeSlug;
    }
}

if (!function_exists('sidebar_render_panel_items')) {
    function sidebar_render_panel_items(array $items, string $current, string $idPrefix): void
    {
        foreach ($items as $index => $item) {
            $hasChildren = !empty($item['children']);
            $isActive = sidebar_item_active($item, $current);

            if ($hasChildren) {
                $submenuId = sidebar_submenu_id($idPrefix, $item, $index);
                ?>
                <div class="sidebar-nested-group <?= $isActive ? 'open' : '' ?>">
                    <button
                        type="button"
                        class="sidebar-link sidebar-nested-toggle <?= $isActive ? 'active' : '' ?>"
                        data-sidebar-submenu="<?= e($submenuId) ?>"
                        aria-expanded="<?= $isActive ? 'true' : 'false' ?>"
                    >
                        <span class="sidebar-link-main">
                            <i class="bi <?= e($item['icon'] ?? 'bi-folder-fill') ?>"></i>
                            <span><?= e($item['title']) ?></span>
                        </span>
                        <i class="bi bi-chevron-down sidebar-submenu-caret"></i>
                    </button>
                    <div class="sidebar-submenu sidebar-nested-submenu <?= $isActive ? 'show' : '' ?>" id="<?= e($submenuId) ?>">
                        <?php sidebar_render_panel_items($item['children'], $current, $submenuId); ?>
                    </div>
                </div>
                <?php
                continue;
            }

            $itemPathActive = !empty($item['href']) && sidebar_path_active($item['href'], $current);
            ?>
            <a
                href="<?= e($item['href']) ?>"
                class="sidebar-link <?= $itemPathActive ? 'active' : '' ?>"
                data-sidebar-link
            >
                <span class="sidebar-link-main">
                    <i class="bi <?= e($item['icon'] ?? 'bi-circle-fill') ?>"></i>
                    <span><?= e($item['title']) ?></span>
                </span>
                <?php if ($itemPathActive): ?>
                    <i class="bi bi-chevron-right sidebar-link-caret"></i>
                <?php endif; ?>
            </a>
            <?php
        }
    }
}

if (!function_exists('sidebar_render_rail_items')) {
    function sidebar_render_rail_items(array $items, string $current): void
    {
        foreach ($items as $item) {
            if (!empty($item['children'])) {
                ?>
                <div class="rail-popout-subtitle">
                    <i class="bi <?= e($item['icon'] ?? 'bi-folder-fill') ?>"></i>
                    <span><?= e($item['title']) ?></span>
                </div>
                <?php sidebar_render_rail_items($item['children'], $current); ?>
                <?php
                continue;
            }

            $railMenuActive = !empty($item['href']) && sidebar_path_active($item['href'], $current);
            ?>
            <a href="<?= e($item['href']) ?>" class="<?= $railMenuActive ? 'active' : '' ?>" data-sidebar-link>
                <i class="bi <?= e($item['icon'] ?? 'bi-circle-fill') ?>"></i>
                <span><?= e($item['title']) ?></span>
            </a>
            <?php
        }
    }
}

$user = current_user() ?: [];
$displayName = trim((string) ($user['name'] ?? $user['username'] ?? 'User'));
$displayRole = trim((string) ($user['role_name'] ?? 'User'));
$initialSource = preg_replace('/[^a-z0-9 ]/i', '', $displayName) ?: 'User';
$initialParts = preg_split('/\s+/', trim($initialSource));
$initials = strtoupper(substr($initialParts[0] ?? 'U', 0, 1) . substr($initialParts[1] ?? '', 0, 1));
$railItems = [];
foreach ($modules as $module) {
    $visibleItems = sidebar_visible_items($module['items']);

    if (!empty($visibleItems)) {
        $moduleActive = false;
        foreach ($visibleItems as $item) {
            if (sidebar_item_active($item, $current)) {
                $moduleActive = true;
                break;
            }
        }

        $railItems[] = [
            'title' => $module['title'],
            'icon' => $module['icon'] ?? 'bi-folder-fill',
            'href' => sidebar_first_href($visibleItems),
            'active' => $moduleActive,
            'items' => $visibleItems,
        ];
    }
}
?>

<aside class="sidebar app-sidebar" id="appSidebar">
    <div class="sidebar-rail">
        <button type="button" class="rail-avatar" id="sidebarRailCollapse" title="Collapse sidebar" aria-label="Collapse sidebar" aria-expanded="true">
            <i class="bi bi-pc-display-horizontal"></i>
            <span class="rail-label"></span>
        </button>

        <div class="rail-nav">
            <?php foreach ($railItems as $index => $railItem): ?>
                <?php $railMenuId = 'railGroupMenu' . $index; ?>
                <button
                    type="button"
                    class="rail-link rail-group-toggle <?= !empty($railItem['active']) ? 'active' : '' ?>"
                    title="<?= e($railItem['title']) ?>"
                    aria-label="<?= e($railItem['title']) ?>"
                    aria-expanded="false"
                    data-rail-menu-toggle="<?= e($railMenuId) ?>"
                >
                    <i class="bi <?= e($railItem['icon']) ?>"></i>
                    <span class="rail-label"><?= e($railItem['title']) ?></span>
                </button>
                <div class="rail-popout-menu" id="<?= e($railMenuId) ?>" aria-hidden="true">
                    <div class="rail-popout-title"><?= e($railItem['title']) ?></div>
                    <?php sidebar_render_rail_items($railItem['items'], $current); ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="rail-bottom">
            <button type="button" class="rail-link rail-settings <?= in_array($current, [$accountPath, $changePasswordPath, $logsPath, $adminPath], true) ? 'active' : '' ?>" id="accountMenuToggle" title="Account menu" aria-label="Account menu" aria-expanded="false">
                <i class="bi bi-gear"></i>
                <span class="rail-label">Account</span>
            </button>
            <div class="rail-popout-menu rail-account-menu" id="accountRailMenu" aria-hidden="true">
                <div class="rail-popout-title">Account</div>
                <a href="<?= BASE_URL ?>/account" class="<?= $current === $accountPath ? 'active' : '' ?>" data-sidebar-link>
                    <i class="bi bi-person-lines-fill"></i>
                    <span>Account Details</span>
                </a>
                <a href="<?= BASE_URL ?>/change-password" class="<?= $current === $changePasswordPath ? 'active' : '' ?>" data-sidebar-link>
                    <i class="bi bi-key"></i>
                    <span>Change Password</span>
                </a>
                <?php if (can('reports.view')): ?>
                    <a href="<?= BASE_URL ?>/activity-logs" class="<?= $current === $logsPath ? 'active' : '' ?>" data-sidebar-link>
                        <i class="bi bi-clock-history"></i>
                        <span>Logs</span>
                    </a>
                <?php endif; ?>
                <?php if (can('users.view')): ?>
                    <a href="<?= BASE_URL ?>/admin" class="<?= $current === $adminPath ? 'active' : '' ?>" data-sidebar-link>
                        <i class="bi bi-person-gear"></i>
                        <span>User Admin</span>
                    </a>
                <?php endif; ?>
            </div>
            <a href="<?= BASE_URL ?>/logout" class="rail-link rail-logout" title="Logout">
                <i class="bi bi-power"></i>
                <span class="rail-label">Logout</span>
            </a>
        </div>
    </div>

    <div class="sidebar-panel">
        <div class="sidebar-profile">
            <div class="sidebar-module-search">
                <i class="bi bi-search"></i>
                <input type="search" id="sidebarModuleSearch" placeholder="Search modules..." autocomplete="off">
                <button type="button" id="sidebarModuleSearchClear" aria-label="Clear module search">
                    <i class="bi bi-x-lg"></i>
                    <span>Clear</span>
                </button>
            </div>
            <button type="button" class="profile-collapse" id="sidebarCollapseToggle" title="Collapse sidebar" aria-label="Collapse sidebar" aria-expanded="true">
                <i class="bi bi-layout-sidebar-inset"></i>
                <span></span>
            </button>
        </div>
        <div class="sidebar-search-empty" id="SearchEmpty">No matching modules found.</div>

        <nav class="sidebar-menu">
            <?php foreach ($modules as $moduleIndex => $module): ?>
                <?php
                $visibleItems = sidebar_visible_items($module['items']);

                if (empty($visibleItems)) continue;

                $submenuId = 'sidebarSubmenu' . $moduleIndex;
                $moduleActive = false;
                foreach ($visibleItems as $activeItem) {
                    if (sidebar_item_active($activeItem, $current)) {
                        $moduleActive = true;
                        break;
                    }
                }
                ?>

                <section class="sidebar-section sidebar-module <?= $moduleActive ? 'open' : '' ?>">
                    <button
                        type="button"
                        class="sidebar-section-title sidebar-submenu-toggle <?= $moduleActive ? 'active' : '' ?>"
                        data-sidebar-submenu="<?= e($submenuId) ?>"
                        aria-expanded="<?= $moduleActive ? 'true' : 'false' ?>"
                    >
                        <span class="sidebar-submenu-label">
                            <i class="bi <?= e($module['icon'] ?? 'bi-folder-fill') ?>"></i>
                            <span><?= e($module['title']) ?></span>
                        </span>
                        <i class="bi bi-chevron-down sidebar-submenu-caret"></i>
                    </button>

                    <div class="sidebar-submenu <?= $moduleActive ? 'show' : '' ?>" id="<?= e($submenuId) ?>">
                        <?php sidebar_render_panel_items($visibleItems, $current, $submenuId); ?>
                    </div>
                </section>
            <?php endforeach; ?>

            <?php
            $accountActive = in_array($current, [$accountPath, $changePasswordPath, $logsPath], true);
            ?>
            <section class="sidebar-section sidebar-module <?= $accountActive ? 'open' : '' ?>">
                <button
                    type="button"
                    class="sidebar-section-title sidebar-submenu-toggle <?= $accountActive ? 'active' : '' ?>"
                    data-sidebar-submenu="sidebarSubmenuAccount"
                    aria-expanded="<?= $accountActive ? 'true' : 'false' ?>"
                >
                    <span class="sidebar-submenu-label">
                        <i class="bi bi-person-circle"></i>
                        <span>Account</span>
                    </span>
                    <i class="bi bi-chevron-down sidebar-submenu-caret"></i>
                </button>

                <div class="sidebar-submenu <?= $accountActive ? 'show' : '' ?>" id="sidebarSubmenuAccount">
                    <a href="<?= BASE_URL ?>/account" class="sidebar-link <?= $current === $accountPath ? 'active' : '' ?>" data-sidebar-link>
                        <span class="sidebar-link-main">
                            <i class="bi bi-person-lines-fill"></i>
                            <span>Account Details</span>
                        </span>
                    </a>

                    <a href="<?= BASE_URL ?>/change-password" class="sidebar-link <?= $current === $changePasswordPath ? 'active' : '' ?>" data-sidebar-link>
                        <span class="sidebar-link-main">
                            <i class="bi bi-key"></i>
                            <span>Change Password</span>
                        </span>
                    </a>

                    <?php if (can('reports.view')): ?>
                        <a href="<?= BASE_URL ?>/activity-logs" class="sidebar-link <?= $current === $logsPath ? 'active' : '' ?>" data-sidebar-link>
                            <span class="sidebar-link-main">
                                <i class="bi bi-clock-history"></i>
                                <span>Logs</span>
                            </span>
                        </a>
                    <?php endif; ?>
                </div>
            </section>

            <?php if (is_admin()): ?>
                <?php $adminActive = $current === $adminPath; ?>
                <section class="sidebar-section sidebar-module <?= $adminActive ? 'open' : '' ?>">
                    <button
                        type="button"
                        class="sidebar-section-title sidebar-submenu-toggle <?= $adminActive ? 'active' : '' ?>"
                        data-sidebar-submenu="sidebarSubmenuAdmin"
                        aria-expanded="<?= $adminActive ? 'true' : 'false' ?>"
                    >
                        <span class="sidebar-submenu-label">
                            <i class="bi bi-shield-lock-fill"></i>
                            <span>Admin</span>
                        </span>
                        <i class="bi bi-chevron-down sidebar-submenu-caret"></i>
                    </button>

                    <div class="sidebar-submenu <?= $adminActive ? 'show' : '' ?>" id="sidebarSubmenuAdmin">
                        <?php if (can('users.view')): ?>
                            <a href="<?= BASE_URL ?>/admin" class="sidebar-link <?= $current === $adminPath ? 'active' : '' ?>" data-sidebar-link>
                                <span class="sidebar-link-main">
                                    <i class="bi bi-person-gear"></i>
                                    <span>Manage Users</span>
                                </span>
                            </a>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>
        </nav>

        <div class="sidebar-footer">
            Developed by Demo Development Team
        </div>
    </div>

</aside>
