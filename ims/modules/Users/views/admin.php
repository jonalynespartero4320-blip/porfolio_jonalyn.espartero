<?php 
require_once ROOT_PATH.'/helpers/functions.php';
require_login();
require_permission('users.view');

include ROOT_PATH.'/modules/layout/header.php'; 

global $pdo;
ensure_permission_catalog($pdo);
ensure_record_scope_schema($pdo);
$adminVisiblePermissionNames = admin_ims_permission_names();
$adminRoleNameExpr = db_column_exists($pdo, 'roles', 'slug')
    ? "COALESCE(NULLIF(r.slug, ''), LOWER(REPLACE(r.name, ' ', '_')))"
    : "LOWER(REPLACE(r.name, ' ', '_'))";
$adminRoleLabelExpr = "COALESCE(NULLIF(r.name, ''), " . $adminRoleNameExpr . ")";
$usersHasEmail = db_column_exists($pdo, 'users', 'email');
$usersHasIsActive = db_column_exists($pdo, 'users', 'is_active');
$usersHasLastActivity = db_column_exists($pdo, 'users', 'last_activity');
$resetDateColumn = db_column_exists($pdo, 'password_reset_requests', 'created_at') ? 'created_at' : 'requested_at';
$resetReasonExpr = db_column_exists($pdo, 'password_reset_requests', 'reason') ? 'pr.reason' : 'pr.notes';
$adminUsersSummary = $pdo->query("
SELECT u.*, {$adminRoleNameExpr} AS role_name, {$adminRoleLabelExpr} AS role_label
FROM users u
LEFT JOIN roles r ON r.id = u.role_id
")->fetchAll(PDO::FETCH_ASSOC);
$adminRolesSummary = $pdo->query("SELECT * FROM roles")->fetchAll(PDO::FETCH_ASSOC);
$adminResetsSummary = $pdo->query("
    SELECT pr.*, {$resetReasonExpr} AS reason, pr.{$resetDateColumn} AS created_at, u.name, COALESCE(u.username, pr.username) AS username
    FROM password_reset_requests pr
    LEFT JOIN users u ON u.id = pr.user_id
    ORDER BY pr.{$resetDateColumn} DESC
")->fetchAll(PDO::FETCH_ASSOC);
$adminPermissionsSummary = admin_fetch_permissions_by_names($pdo, $adminVisiblePermissionNames);
$adminDepartments = db_table_exists($pdo, 'list_department')
    ? ($pdo->query("SELECT department_id, department_name FROM list_department ORDER BY department_name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [])
    : [];
$adminUserDepartmentScopes = [];
if (db_table_exists($pdo, 'user_department_scopes')) {
    $scopeRows = $pdo->query("SELECT user_id, department_id FROM user_department_scopes")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($scopeRows as $scopeRow) {
        $adminUserDepartmentScopes[(int) $scopeRow['user_id']][] = (int) $scopeRow['department_id'];
    }
}
$adminUserCount = count($adminUsersSummary);
$adminRoleCount = count($adminRolesSummary);
$adminPendingResets = count(array_filter($adminResetsSummary, static fn($row) => ($row['status'] ?? '') === 'pending'));
$adminSuperAdmins = count(array_filter($adminUsersSummary, static fn($row) => strtolower((string) ($row['role_name'] ?? '')) === 'super_admin'));
$adminIctdUsers = count(array_filter($adminUsersSummary, static function ($row) {
    $role = strtolower((string) ($row['role_name'] ?? ''));
    return in_array($role, ['admin', 'super_admin'], true);
}));

function admin_role_landing_label(array $userRow): string {
    $role = strtolower((string) ($userRow['role_name'] ?? ''));
    if (in_array($role, ['admin', 'super_admin'], true)) {
        return '/ictd/dashboard';
    }

    return '/dashboard';
}

function admin_permission_module_label(string $module): string {
    $labels = [
        'ictd.dashboard' => 'Dashboard',
        'ictd.masterlist' => 'Masterlist',
        'ictd.equipment' => 'Equipment',
        'ictd.supplies' => 'Supplies',
        'ictd.assign' => 'Assign',
        'ictd.maintenance' => 'Maintenance',
        'ictd.service-report' => 'Service Report',
        'ictd.transmittal' => 'Transmittal',
        'ictd.subscription' => 'Subscription',
        'ictd.borrowed' => 'Borrowed',
        'ictd.bidding' => 'Bidding',
        'ictd.reports' => 'General Report',
        'ictd.request' => 'Requests',
        'ictd.title' => 'Title',
        'ictd.admin-users' => 'Admin Users',
        'users' => 'Users',
    ];

    return $labels[$module] ?? ucwords(str_replace(['-', '_'], ' ', $module));
}

function admin_ims_permission_names(): array {
    return [
        'ictd.dashboard.view',
        'ictd.request.view',
        'ictd.request.manage',
        'ictd.equipment.view',
        'ictd.equipment.create',
        'ictd.equipment.edit',
        'ictd.equipment.delete',
        'ictd.equipment.import',
        'ictd.equipment.archive',
        'ictd.supplies.view',
        'ictd.supplies.create',
        'ictd.supplies.edit',
        'ictd.supplies.delete',
        'ictd.assign.view',
        'ictd.assign.create',
        'ictd.assign.delete',
        'ictd.subscription.view',
        'ictd.subscription.create',
        'ictd.subscription.edit',
        'ictd.subscription.delete',
        'ictd.borrowed.view',
        'ictd.borrowed.create',
        'ictd.borrowed.edit',
        'ictd.borrowed.delete',
        'ictd.bidding.view',
        'ictd.bidding.create',
        'ictd.bidding.edit',
        'ictd.bidding.delete',
        'ictd.title.view',
        'ictd.service-report.view',
        'ictd.maintenance.view',
        'ictd.transmittal.view',
        'ictd.reports.view',
        'ictd.masterlist.view',
        'ictd.admin-users.view',
        'users.view',
        'users.create',
        'users.edit',
        'users.delete',
    ];
}

function admin_fetch_permissions_by_names(PDO $pdo, array $permissionNames): array {
    $permissionNames = array_values(array_unique(array_map('normalize_permission_name', $permissionNames)));
    if (empty($permissionNames)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($permissionNames), '?'));
    $stmt = $pdo->prepare("SELECT * FROM permissions WHERE name IN ($placeholders)");
    $stmt->execute($permissionNames);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $rowsByName = [];

    foreach ($rows as $row) {
        $rowsByName[normalize_permission_name($row['name'] ?? '')] = $row;
    }

    $ordered = [];
    foreach ($permissionNames as $permissionName) {
        if (isset($rowsByName[$permissionName])) {
            $ordered[] = $rowsByName[$permissionName];
        }
    }

    return $ordered;
}
?>

<style>
  .admin-shell { display: grid; gap: 1.05rem; }
  .admin-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; }
  .admin-eyebrow { color: #667085; font-size: .78rem; font-weight: 750; }
  .admin-title { margin: 0; color: #1f2937; font-size: 1.35rem; font-weight: 850; }
  .admin-head-actions { display: flex; gap: .5rem; flex-wrap: wrap; justify-content: flex-end; }
  .admin-head-actions .badge { min-height: 30px; border-radius: 4px; display: inline-flex; align-items: center; padding-inline: .65rem; border: 1px solid #eaecf0; background: #fff !important; color: #344054 !important; }
  .admin-stat-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .8rem; }
  .admin-stat-card { border: 1px solid #eef2f6; border-radius: 8px; background: rgba(255,255,255,.92); box-shadow: none; }
  .admin-stat-card .card-body { padding: .9rem; }
  .admin-stat-label { color: #667085; font-size: .72rem; font-weight: 800; text-transform: uppercase; }
  .admin-stat-value { color: #1f2937; font-size: 1.45rem; font-weight: 850; line-height: 1.15; margin-top: .35rem; }
  .admin-stat-hint { color: #667085; font-size: .75rem; margin-top: .15rem; }
  .admin-toolbar, .admin-panel { border: 0; border-radius: 10px; background: rgba(255,255,255,.78); box-shadow: none; overflow: hidden; }
  .admin-toolbar .card-body { padding: 1rem; }
  .admin-search-wrap { position: relative; }
  .admin-search-wrap i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #98a2b3; font-size: .85rem; }
  .admin-search-wrap input { height: 38px; padding-left: 34px; border-radius: 4px; border-color: #eaecf0; font-size: .78rem; }
  #usersTable, #resetTable { font-size: .8rem; }
  #usersTable thead th, #resetTable thead th, #roleModal table thead th {
    white-space: nowrap;
    background: #f3f4f6 !important;
    border: 0 !important;
    color: #111827 !important;
    font-size: .72rem;
    padding: .72rem .8rem !important;
  }
  #usersTable tbody td, #resetTable tbody td {
    border: 0 !important;
    border-bottom: 1px solid #f2f4f7 !important;
    padding: .72rem .8rem !important;
    white-space: nowrap;
    vertical-align: middle;
  }
  .admin-action-btn { border: 0; background: transparent; padding: 0; line-height: 1; font-size: .92rem; font-weight: 740; display: inline-flex; align-items: center; gap: .25rem; }
  .admin-action-manage { color: #3657f6; }
  .admin-action-approve { color: #16803d; }
  .admin-action-reject { color: #d92d20; }
  .admin-full-access { border-radius: 4px; background: #ecfdf3 !important; color: #16803d !important; padding: .35rem .55rem; font-weight: 750; }
  .admin-stat-icon {
    width: 32px; height: 32px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; font-size: 1rem; background: #eef4ff; color: #3657f6;
  }
  .admin-tabs { gap: .35rem; border-bottom: 1px solid #eaecf0; overflow-x: auto; flex-wrap: nowrap; }
  .admin-tabs .nav-link {
    border: 0;
    border-bottom: 2px solid transparent;
    border-radius: 0;
    padding: .72rem 1rem;
    background: transparent;
    color: #475467;
    font-size: .82rem;
    font-weight: 700;
    white-space: nowrap;
  }
  .admin-tabs .nav-link.active {
    background: transparent;
    color: #3657f6;
    border-bottom-color: #3657f6;
  }
  .admin-chip {
    display: inline-flex;
    align-items: center;
    gap: .45rem;
    padding: .45rem .75rem;
    border-radius: 999px;
    background: rgba(15,71,215,0.08);
    color: #ffffff;
    font-weight: 600;
  }
  .perm-checkbox:checked { accent-color: #3657f6; }
  td.active-cell { background: rgba(54, 87, 246, 0.12) !important; }
  tr.active-row td:first-child { background: rgba(54, 87, 246, 0.18) !important; }
  .modal-content { border-radius: 8px; }
  .modal-body { max-height: 65vh; overflow-y: auto; }
  .modal-header, .modal-footer { position: sticky; z-index: 10; }
  .modal-header { top: 0; }
  .modal-footer { bottom: 0; }
  .table-responsive { max-height: 60vh; }
  .admin-panel .dataTables_paginate .pagination { gap: .35rem; margin: 0; justify-content: flex-end; }
  .admin-panel .page-link { min-width: 34px; height: 34px; border-radius: 4px !important; display: inline-flex; align-items: center; justify-content: center; border-color: #eaecf0; color: #344054; font-size: .78rem; box-shadow: none; }
  .admin-panel .page-item.active .page-link { background: #3657f6; border-color: #3657f6; color: #fff; }
  .permission-role-note {
    border-radius: 8px;
    background: #f8fbff;
    border: 1px solid #e4e7ec;
    padding: .9rem 1rem;
  }
  .admin-rbac-note {
    border-radius: 8px;
    background: #f8fbff;
    border: 1px solid #e4e7ec;
    padding: 1rem 1.1rem;
  }
  .data-scope-panel {
    border: 1px solid #e4e7ec;
    border-radius: 8px;
    background: #fff;
    padding: 1rem;
  }
  .department-scope-select {
    width: 100%;
  }
  #roleModal .select2-container--default .select2-selection--multiple {
    min-height: 42px;
    border-color: #dee2e6;
    border-radius: .375rem;
    padding: .2rem .35rem;
  }
  #roleModal .select2-container--default.select2-container--focus .select2-selection--multiple {
    border-color: #86b7fe;
    box-shadow: 0 0 0 .25rem rgba(13, 110, 253, .25);
  }
  .admin-role-badge {
    border-radius: 4px;
    padding: .35rem .55rem;
    font-weight: 700;
    background: #eef4ff !important;
    color: #3657f6 !important;
  }
  .admin-status-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-block;
  }
  #approveModal .modal-header, #rejectModal .modal-header, #roleModal .modal-header { background: #fff !important; color: #1f2937 !important; border-bottom: 1px solid #eaecf0; }
  #approveModal .btn-close, #rejectModal .btn-close, #roleModal .btn-close { filter: none; }
  .container-fluid.py-3 > h4.fw-bold.mb-3 { display: none; }
  @media (max-width: 1199px) {
    .admin-stat-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  }
  @media (max-width: 767px) {
    .admin-stat-grid { grid-template-columns: 1fr; }
    .admin-head { flex-direction: column; }
    .admin-head-actions { width: 100%; justify-content: flex-start; }
  }
</style>

<div class="container-fluid py-3">

<h4 class="fw-bold mb-3">👥 Admin Panel</h4>

<div class="admin-shell">

<div class="admin-head">
    <div>
        <div class="admin-eyebrow">Administration</div>
        <h2 class="admin-title">Manage Users</h2>
        <div class="text-muted small">Manage roles, permissions, and department record access.</div>
    </div>
    <div class="admin-head-actions">
        <span class="badge text-bg-light text-dark"><?= $adminUserCount ?> users</span>
        <span class="badge text-bg-light text-dark"><?= $adminRoleCount ?> roles</span>
        <span class="badge text-bg-light text-dark"><?= $adminPendingResets ?> pending resets</span>
    </div>
</div>

<div class="admin-stat-grid">
    <div class="card admin-stat-card">
        <div class="card-body d-flex align-items-center justify-content-between gap-2">
            <div>
                <div class="admin-stat-label">Users</div>
                <div class="admin-stat-value"><?= number_format($adminUserCount) ?></div>
                <div class="admin-stat-hint">Registered accounts</div>
            </div>
            <div class="admin-stat-icon"><i class="bi bi-people"></i></div>
        </div>
    </div>
    <div class="card admin-stat-card">
        <div class="card-body d-flex align-items-center justify-content-between gap-2">
            <div>
                <div class="admin-stat-label">Roles</div>
                <div class="admin-stat-value"><?= number_format($adminRoleCount) ?></div>
                <div class="admin-stat-hint">Access groups</div>
            </div>
            <div class="admin-stat-icon"><i class="bi bi-person-badge"></i></div>
        </div>
    </div>
    <div class="card admin-stat-card">
        <div class="card-body d-flex align-items-center justify-content-between gap-2">
            <div>
                <div class="admin-stat-label">ICTD Access</div>
                <div class="admin-stat-value"><?= number_format($adminIctdUsers) ?></div>
                <div class="admin-stat-hint">Admin users</div>
            </div>
            <div class="admin-stat-icon"><i class="bi bi-shield-check"></i></div>
        </div>
    </div>
    <div class="card admin-stat-card">
        <div class="card-body d-flex align-items-center justify-content-between gap-2">
            <div>
                <div class="admin-stat-label">Resets</div>
                <div class="admin-stat-value"><?= number_format($adminPendingResets) ?></div>
                <div class="admin-stat-hint">Pending requests</div>
            </div>
            <div class="admin-stat-icon"><i class="bi bi-key"></i></div>
        </div>
    </div>
</div>

<div class="card admin-toolbar">
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-lg-7">
                <div class="admin-search-wrap">
                    <i class="bi bi-search"></i>
                    <input type="text" id="adminQuickSearch" class="form-control" placeholder="Search users, roles, usernames, or reset requests">
                </div>
            </div>
            <div class="col-lg-5">
                <div class="small text-muted">Permissions Scope</div>
                <div class="fw-semibold fs-5"><?= count($adminPermissionsSummary) ?> system permissions available</div>
            </div>
        </div>
    </div>
</div>

<ul class="nav nav-tabs admin-tabs mb-3" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active"
            data-bs-toggle="tab"
            data-bs-target="#usersTab"
            type="button"
            role="tab">
            Users
        </button>
    </li>

    <li class="nav-item" role="presentation">
        <button class="nav-link"
            data-bs-toggle="tab"
            data-bs-target="#resetTab"
            type="button"
            role="tab">
            Reset Requests
        </button>
    </li>
</ul>

<div class="tab-content">

<!-- ================= USERS TAB ================= -->
<div class="tab-pane fade show active" id="usersTab" role="tabpanel">

<?php
global $pdo;

$users = $pdo->query("
SELECT u.*, {$adminRoleNameExpr} AS role_name, {$adminRoleLabelExpr} AS role_label
FROM users u
LEFT JOIN roles r ON r.id = u.role_id
")->fetchAll(PDO::FETCH_ASSOC);

$roles = $pdo->query(db_column_exists($pdo, 'roles', 'slug')
    ? "SELECT *, COALESCE(NULLIF(slug, ''), LOWER(REPLACE(name, ' ', '_'))) AS role_key, COALESCE(NULLIF(name, ''), slug) AS role_label FROM roles ORDER BY id ASC"
    : "SELECT *, LOWER(REPLACE(name, ' ', '_')) AS role_key, name AS role_label FROM roles ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$rolesByKey = [];
foreach ($roles as $roleRow) {
    $roleKey = strtolower(trim((string) ($roleRow['role_key'] ?? $roleRow['name'] ?? '')));
    if ($roleKey === '') {
        continue;
    }

    if (!isset($rolesByKey[$roleKey])) {
        $rolesByKey[$roleKey] = $roleRow;
        continue;
    }

    $expectedLabel = ucwords(str_replace('_', ' ', $roleKey));
    $existingLabel = (string) ($rolesByKey[$roleKey]['role_label'] ?? $rolesByKey[$roleKey]['name'] ?? '');
    $candidateLabel = (string) ($roleRow['role_label'] ?? $roleRow['name'] ?? '');
    if ($existingLabel !== $expectedLabel && $candidateLabel === $expectedLabel) {
        $rolesByKey[$roleKey] = $roleRow;
    }
}
$roles = array_values($rolesByKey);
$permissions = admin_fetch_permissions_by_names($pdo, $adminVisiblePermissionNames);
$canManageUsers = can('users.edit');
$visiblePermissionIds = array_map(static fn($row) => (int) $row['id'], $permissions);
$roleProfileIds = [
    'staff' => array_values(array_intersect(role_permission_profile_ids($pdo, 'staff'), $visiblePermissionIds)),
    'admin' => array_values(array_intersect(role_permission_profile_ids($pdo, 'admin'), $visiblePermissionIds)),
];

$modules = [];
$actions = [];

foreach ($permissions as $p) {
    if (strpos($p['name'], '.') === false) continue;

    $parts = explode('.', $p['name']);
    $action = array_pop($parts);
    $module = implode('.', $parts);
    $modules[$module][$action] = $p['id'];
    $actions[$action] = true;
}
?>

<div class="card admin-panel mb-3">
    <div class="card-body p-0">

        <div class="table-responsive">
            <table class="table table-hover align-middle text-center text-black mb-0" id="usersTable">

                <thead class="table-secondary">
                    <tr>
                        <th style="width:120px;" class="text-black">Action</th>
                        <th class="text-black">ID</th>
                        <th class="text-black">Name</th>
                        <th class="text-black">Username</th>
                        <?php if ($usersHasEmail): ?><th class="text-black">Email</th><?php endif; ?>
                        <th class="text-black">Role</th>
                        <?php if ($usersHasIsActive): ?><th class="text-black">Session</th><?php endif; ?>
                        <th class="text-black">Landing</th>
                        <?php if ($usersHasLastActivity): ?><th class="text-black">Last Activity</th><?php endif; ?>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td>
                                <?php if (strtolower($u['role_name'] ?? '') !== 'super_admin'): ?>
                                    <?php if ($canManageUsers): ?>
                                        <button
                                            type="button"
                                            class="admin-action-btn admin-action-manage manage-user-btn"
                                            data-user-id="<?= (int) $u['id'] ?>"
                                            data-role-id="<?= (int) ($u['role_id'] ?? 0) ?>"
                                            data-user-name="<?= e($u['name']) ?>"
                                            data-view-all-records="<?= (int) ($u['can_view_all_records'] ?? 0) ?>"
                                            data-department-scopes="<?= e(json_encode($adminUserDepartmentScopes[(int) $u['id']] ?? [])) ?>">
                                            <i class="bi bi-sliders"></i> Manage
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted">View Only</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge admin-full-access">
                                        <i class="bi bi-shield-check"></i> Full Access
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><?= $u['id'] ?></td>
                            <td class="text-start"><?= htmlspecialchars($u['name']) ?></td>
                            <td><?= htmlspecialchars($u['username']) ?></td>
                            <?php if ($usersHasEmail): ?>
                                <td><?= htmlspecialchars((string) ($u['email'] ?? '')) ?></td>
                            <?php endif; ?>
                            <td>
                                <span class="badge bg-info admin-role-badge">
                                    <?= htmlspecialchars($u['role_label'] ?? $u['role_name'] ?? 'No Role') ?>
                                </span>
                            </td>
                            <?php if ($usersHasIsActive): ?>
                                <td>
                                    <?php $isActive = (int) ($u['is_active'] ?? 0) === 1; ?>
                                    <span class="d-inline-flex align-items-center gap-2">
                                        <span class="admin-status-dot <?= $isActive ? 'bg-success' : 'bg-secondary' ?>"></span>
                                        <span><?= $isActive ? 'Online' : 'Offline' ?></span>
                                    </span>
                                </td>
                            <?php endif; ?>
                            <td><code><?= e(admin_role_landing_label($u)) ?></code></td>
                            <?php if ($usersHasLastActivity): ?>
                                <td><?= htmlspecialchars((string) ($u['last_activity'] ?? '')) ?></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>

            </table>
        </div>

    </div>
</div>

</div>

<!-- ================= RESET TAB ================= -->
<div class="tab-pane fade" id="resetTab" role="tabpanel">

<?php
$resets = $pdo->query("
    SELECT pr.*, {$resetReasonExpr} AS reason, pr.{$resetDateColumn} AS created_at, u.name, COALESCE(u.username, pr.username) AS username
    FROM password_reset_requests pr
    LEFT JOIN users u ON u.id = pr.user_id
    ORDER BY pr.{$resetDateColumn} DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="card admin-panel">
    <div class="card-body p-0">

        <div class="table-responsive">
            <table class="table table-hover align-middle text-center mb-0" id="resetTable">

                <thead class="table-light">
                    <tr>
                        <th style="width:180px;">Action</th>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($resets as $r): ?>
                        <tr>
                            <td>
                                <?php if ($r['status'] === 'pending' && can('users.edit')): ?>
                                    <div class="d-flex justify-content-center gap-1">

                                        <button class="admin-action-btn admin-action-approve"
                                            onclick="openApproveModal(<?= $r['id'] ?>)">
                                            <i class="bi bi-check2-circle"></i> Approve
                                        </button>

                                        <button class="admin-action-btn admin-action-reject"
                                            onclick="openRejectModal(<?= $r['id'] ?>)">
                                            <i class="bi bi-x-circle"></i> Reject
                                        </button>

                                    </div>
                                <?php else: ?>

                                    <?php if ($r['status'] === 'rejected' && $r['reason']): ?>
                                        <small class="text-danger">
                                            <?= htmlspecialchars($r['reason']) ?>
                                        </small>
                                    <?php else: ?>
                                        <span class="text-muted">No action</span>
                                    <?php endif; ?>

                                <?php endif; ?>
                            </td>

                            <td><?= $r['id'] ?></td>
                            <td class="text-start"><?= htmlspecialchars($r['name']) ?></td>
                            <td><?= htmlspecialchars($r['username']) ?></td>

                            <td>
                                <?php if ($r['status'] === 'pending'): ?>
                                    <span class="badge bg-warning">Pending</span>
                                <?php elseif ($r['status'] === 'approved'): ?>
                                    <span class="badge bg-success">Approved</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Rejected</span>
                                <?php endif; ?>
                            </td>

                            <td><?= $r['created_at'] ?></td>

                        </tr>
                    <?php endforeach; ?>

                </tbody>

            </table>
        </div>

    </div>
</div>

</div>

</div> <!-- tab-content -->

</div>
</div>

<!-- ================= ROLE MODAL ================= -->
<div class="modal fade" id="roleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">

            <form method="POST" action="<?= BASE_URL ?>/assign-role-permissions">

                <div class="modal-header bg-dark text-white">
                    <h5 class="mb-0">Permission Matrix</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <input type="hidden" name="user_id" id="modal_user_id">

                    <input type="text" id="modal_user_name"
                        class="form-control mb-3" disabled>

                    <select name="role_id" id="modal_role_id" class="form-select mb-3" <?= $canManageUsers ? '' : 'disabled' ?>>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= $r['id'] ?>"
                                data-role="<?= e(strtolower((string) ($r['role_key'] ?? $r['name']))) ?>">
                                <?= e($r['role_label'] ?? $r['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div class="permission-role-note mb-3">
                        <div class="fw-semibold mb-1">Role Guidance</div>
                        <div class="small text-muted" id="roleHelperText">Choose a role, then fine-tune permission overrides as needed. Login destination and access scope will follow the role.</div>
                    </div>

                    <div class="data-scope-panel mb-3">
                        <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
                            <div>
                                <div class="fw-semibold mb-1">Record Visibility Scope</div>
                                <div class="small text-muted">
                                    Users always see records they created. Enable full visibility to let them see every department, or select department scopes to add department-only visibility.
                                </div>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="modal_can_view_all_records" name="can_view_all_records" value="1" <?= $canManageUsers ? '' : 'disabled' ?>>
                                <label class="form-check-label fw-semibold" for="modal_can_view_all_records">Can view all records</label>
                            </div>
                        </div>
                        <label class="form-label fw-semibold mt-3 mb-1" for="modal_department_scope_ids">Allowed Departments</label>
                        <select id="modal_department_scope_ids" name="department_scope_ids[]" class="form-select department-scope-select" multiple <?= $canManageUsers ? '' : 'disabled' ?>>
                            <?php foreach ($adminDepartments as $department): ?>
                                <option value="<?= (int) $department['department_id'] ?>">
                                    <?= e($department['department_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Leave this empty if the user should only see records they personally created.</div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mb-3 align-items-center justify-content-between">
                        <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($actions as $action => $_): ?>
                            <button type="button"
                                class="btn btn-outline-primary btn-sm"
                                onclick="toggleColumn('<?= $action ?>')">
                                <?= ucfirst($action) ?>
                            </button>
                        <?php endforeach; ?>

                        <button type="button" class="btn btn-dark btn-sm" onclick="toggleAll()">
                            All
                        </button>
                        </div>
                        <div class="d-flex gap-2 align-items-center flex-wrap">
                            <input type="text" id="permissionSearch" class="form-control form-control-sm" placeholder="Filter modules" style="max-width:220px;">
                            <span class="badge text-bg-light text-dark" id="permissionSummary">0 permission(s) selected</span>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered text-center align-middle">

                            <thead class="table-light">
                                <tr>
                                    <th class="text-start">Module</th>
                                    <?php foreach ($actions as $action => $_): ?>
                                        <th><?= ucfirst($action) ?></th>
                                    <?php endforeach; ?>
                                    <th>All</th>
                                </tr>
                            </thead>

                            <tbody id="permissionTableBody">
                                <?php foreach ($modules as $module => $perms): ?>
                                    <tr data-module-row="<?= e($module) ?>">

                                        <td class="fw-bold text-start">
                                            <?= e(admin_permission_module_label($module)) ?>
                                        </td>

                                        <?php foreach ($actions as $action => $_): ?>
                                            <td>
                                                <?php if (isset($perms[$action])): ?>
                                                    <input type="checkbox"
                                                        class="perm-checkbox"
                                                        data-action="<?= $action ?>"
                                                        data-module="<?= $module ?>"
                                                        name="permissions[]"
                                                        value="<?= $perms[$action] ?>"
                                                        <?= $canManageUsers ? '' : 'disabled' ?>
                                                        onchange="highlightCell(this)">
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>

                                        <td>
                                            <button type="button"
                                                class="btn btn-sm btn-outline-secondary"
                                                <?= $canManageUsers ? '' : 'disabled' ?>
                                                onclick="toggleRow('<?= $module ?>')">
                                                All
                                            </button>
                                        </td>

                                    </tr>
                                <?php endforeach; ?>
                            </tbody>

                        </table>
                    </div>

                </div>

                <div class="modal-footer">
                    <?php if ($canManageUsers): ?>
                        <button class="btn btn-success">Save</button>
                    <?php else: ?>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <?php endif; ?>
                </div>

            </form>

        </div>
    </div>
</div>


<!-- ================= APPROVE MODAL ================= -->
<div class="modal fade" id="approveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">

            <form method="POST" action="<?= BASE_URL ?>/handle-reset-request">

                <div class="modal-header bg-success text-white">
                    <h5 class="mb-0">Approve Reset</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <input type="hidden" name="request_id" id="approve_id">
                    <input type="hidden" name="action" value="approve">

                    <label class="form-label">New Password</label>
                    <input type="text" name="password" class="form-control" minlength="6" value="123456" required>

                </div>

                <div class="modal-footer">
                    <button class="btn btn-success">Approve</button>
                </div>

            </form>

        </div>
    </div>
</div>


<!-- ================= REJECT MODAL ================= -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">

            <form method="POST" action="<?= BASE_URL ?>/handle-reset-request">

                <div class="modal-header bg-danger text-white">
                    <h5 class="mb-0">Reject Request</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <input type="hidden" name="request_id" id="reject_id">
                    <input type="hidden" name="action" value="reject">

                    <label class="form-label">Reason</label>
                    <textarea name="reason" class="form-control" required></textarea>

                </div>

                <div class="modal-footer">
                    <button class="btn btn-danger">Reject</button>
                </div>

            </form>

        </div>
    </div>
</div>

<?php include ROOT_PATH.'/modules/layout/footer.php'; ?>
<script>
const roleModalElement = document.getElementById('roleModal');
const approveModalElement = document.getElementById('approveModal');
const rejectModalElement = document.getElementById('rejectModal');

const roleModal = roleModalElement ? new bootstrap.Modal(roleModalElement) : null;
const approveModal = approveModalElement ? new bootstrap.Modal(approveModalElement) : null;
const rejectModal = rejectModalElement ? new bootstrap.Modal(rejectModalElement) : null;
const canManageUsers = <?= $canManageUsers ? 'true' : 'false' ?>;
const ROLE_PROFILE_PERMISSION_IDS = <?= json_encode($roleProfileIds) ?>;
const ROLE_PROFILES = {
    super_admin: 'Super Admin has full platform governance, full ICTD access, and always lands on /ictd/dashboard after login.',
    admin: 'Admin receives all page-level access, including ICTD modules, and lands on /ictd/dashboard after login.',
    staff: 'Staff stays focused on operational work and lands on /dashboard unless you explicitly grant extra permissions.'
};
let usersTable = null;
let resetTable = null;

$(document).ready(function () {

    /* ================= DATATABLES ================= */

    usersTable = $('#usersTable').DataTable({
        pageLength: 8,
        lengthMenu: [8, 15, 30, 50],
        dom: 'rt<"row align-items-center mt-3 px-3 pb-3"<"col-md-4"i><"col-md-4 text-center"l><"col-md-4"p>>',
        ordering: true,
        responsive: true,
        order: [],
        language: {
            search: '',
            searchPlaceholder: 'Search users',
            emptyTable: 'No users found.'
        }
    });

    resetTable = $('#resetTable').DataTable({
        pageLength: 8,
        lengthMenu: [8, 15, 30, 50],
        dom: 'rt<"row align-items-center mt-3 px-3 pb-3"<"col-md-4"i><"col-md-4 text-center"l><"col-md-4"p>>',
        ordering: true,
        order: [[5, 'desc']], // sort by date
        responsive: true,
        language: {
            search: '',
            searchPlaceholder: 'Search reset requests',
            emptyTable: 'No reset requests found.'
        }
    });

    $('#adminQuickSearch').on('input', function () {
        const value = this.value;
        if (usersTable) usersTable.search(value).draw();
        if (resetTable) resetTable.search(value).draw();
    });

    if ($.fn.select2) {
        $('#modal_department_scope_ids').select2({
            width: '100%',
            dropdownParent: $('#roleModal'),
            placeholder: 'Select department access',
            closeOnSelect: false,
            allowClear: true
        });
    }

    $(document).on('click', '.manage-user-btn', function () {
        const button = this;
        openRoleModal(
            Number(button.dataset.userId || 0),
            Number(button.dataset.roleId || 0),
            button.dataset.userName || 'User',
            Number(button.dataset.viewAllRecords || 0),
            JSON.parse(button.dataset.departmentScopes || '[]')
        );
    });

    $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function () {
        if (usersTable) usersTable.columns.adjust().responsive.recalc();
        if (resetTable) resetTable.columns.adjust().responsive.recalc();
    });

});

function updatePermissionSummary() {
    const count = document.querySelectorAll('.perm-checkbox:checked').length;
    const summary = document.getElementById('permissionSummary');
    if (summary) {
        summary.textContent = `${count} permission(s) selected`;
    }
}

function updateRoleHelperText() {
    const roleSelectEl = document.getElementById('modal_role_id');
    const helper = document.getElementById('roleHelperText');
    if (!roleSelectEl || !helper) return;

    const role = roleSelectEl.selectedOptions[0]?.dataset.role || '';
    helper.textContent = ROLE_PROFILES[role] || 'Choose a role, then fine-tune permission overrides as needed. Login destination and access scope will follow the role.';
}


/* ================= MODALS ================= */

function openApproveModal(id) {
    document.getElementById('approve_id').value = id;
    if (approveModal) {
        approveModal.show();
    }
}

function openRejectModal(id) {
    document.getElementById('reject_id').value = id;
    if (rejectModal) {
        rejectModal.show();
    }
}


/* ================= PERMISSION UI ================= */

function highlightCell(cb) {
    const td = cb.closest('td');
    const tr = cb.closest('tr');

    td.classList.toggle('active-cell', cb.checked);

    const anyChecked = tr.querySelectorAll('.perm-checkbox:checked').length > 0;
    tr.classList.toggle('active-row', anyChecked);
    updatePermissionSummary();
}


/* ================= LOAD PERMISSIONS ================= */

function openRoleModal(id, role, name, viewAllRecords = 0, departmentScopes = []) {
    if (!roleModal) {
        alert('Role modal failed to initialize.');
        return;
    }

    if (!canManageUsers) {
        alert('You have view-only access for users.');
        return;
    }

    if (!id || !role) {
        alert('Invalid user details.');
        return;
    }

    document.getElementById('modal_user_id').value = id;
    document.getElementById('modal_role_id').value = role;
    document.getElementById('modal_user_name').value = name;
    document.getElementById('modal_can_view_all_records').checked = Number(viewAllRecords) === 1;

    const departmentSelect = document.getElementById('modal_department_scope_ids');
    const selectedDepartments = new Set((departmentScopes || []).map((id) => String(id)));
    if (departmentSelect) {
        Array.from(departmentSelect.options).forEach((option) => {
            option.selected = selectedDepartments.has(String(option.value));
        });
        if (window.jQuery && $.fn.select2) {
            $('#modal_department_scope_ids').trigger('change.select2');
        }
    }

    resetPermissionCheckboxes();
    handleRoleUI();
    roleModal.show();

    fetch("<?= BASE_URL ?>/get-user-permissions", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: new URLSearchParams({ user_id: String(id) }).toString()
    })
    .then(async res => {
        const text = await res.text();

        if (!res.ok) {
            throw new Error(text || 'Failed to load permissions.');
        }

        try {
            return JSON.parse(text);
        } catch (error) {
            throw new Error(text || 'Invalid permissions response.');
        }
    })
    .then(data => {
        const savedPermissions = Array.isArray(data)
            ? data
            : (Array.isArray(data.permissions) ? data.permissions : []);
        const hasOverride = !Array.isArray(data) && Boolean(data.has_override);

        const selectedRole = document.getElementById('modal_role_id').selectedOptions[0]?.dataset.role || '';
        const selectedPermissions = hasOverride
            ? savedPermissions.map((pid) => String(pid))
            : collectRoleDefaultPermissionIds(selectedRole);
        handleRoleUI(selectedPermissions);

    })
    .catch((error) => {
        console.error(error);
        handleRoleUI();
        alert('Failed to load saved permissions for this user, but you can still assign a role manually.');
    });
}

function resetPermissionCheckboxes() {
    document.querySelectorAll('.perm-checkbox').forEach(cb => {
        cb.checked = false;
        cb.disabled = false;
        highlightCell(cb);
    });
}

function collectRoleDefaultPermissionIds(role) {
    if (role === 'super_admin') {
        return Array.from(document.querySelectorAll('.perm-checkbox')).map((cb) => String(cb.value));
    }

    return (ROLE_PROFILE_PERMISSION_IDS[role] || []).map((id) => String(id));
}

function applyPermissionState(selectedIds, role) {
    const selectedSet = new Set((selectedIds || []).map((id) => String(id)));
    const allCheckboxes = document.querySelectorAll('.perm-checkbox');

    allCheckboxes.forEach(cb => {
        const shouldDisable = role === 'super_admin';
        cb.disabled = shouldDisable;
        cb.checked = shouldDisable ? true : selectedSet.has(String(cb.value));
        highlightCell(cb);
    });

    updateRoleHelperText();
    updatePermissionSummary();
}


/* ================= ROLE LOGIC ================= */

function handleRoleUI(customSelection = null) {
    const role = document.getElementById('modal_role_id')
        .selectedOptions[0]
        .dataset.role;

    const selection = customSelection === null
        ? collectRoleDefaultPermissionIds(role)
        : customSelection;

    applyPermissionState(selection, role);
}


/* ================= ROLE CHANGE EVENT ================= */

const roleSelect = document.getElementById('modal_role_id');

if (roleSelect) {
    roleSelect.addEventListener('change', () => {
        Swal.fire({
            title: 'Reset permissions for this role?',
            text: 'Changing the role can reset the current permission matrix selection.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Apply Role Defaults',
            cancelButtonText: 'Keep Current'
        }).then((result) => {
            if (result.isConfirmed) {
                handleRoleUI();
            } else {
                updateRoleHelperText();
                updatePermissionSummary();
            }
        });
    });
}


/* ================= TOGGLE FUNCTIONS ================= */

function toggleColumn(action) {
    if (!canManageUsers) return;

    const checkboxes = document.querySelectorAll(`.perm-checkbox[data-action='${action}']`);
    const allChecked = [...checkboxes].every(cb => cb.checked);

    checkboxes.forEach(cb => {
        if (!cb.disabled) {
            cb.checked = !allChecked;
            highlightCell(cb);
        }
    });
}


function toggleRow(module) {
    if (!canManageUsers) return;

    const checkboxes = document.querySelectorAll(`.perm-checkbox[data-module='${module}']`);
    const allChecked = [...checkboxes].every(cb => cb.checked);

    checkboxes.forEach(cb => {
        if (!cb.disabled) {
            cb.checked = !allChecked;
            highlightCell(cb);
        }
    });
}


function toggleAll() {
    if (!canManageUsers) return;

    const checkboxes = [...document.querySelectorAll('.perm-checkbox')];
    const allChecked = checkboxes.every(cb => cb.checked);

    checkboxes.forEach(cb => {
        if (!cb.disabled) {
            cb.checked = !allChecked;
            highlightCell(cb);
        }
    });
}

$('#permissionSearch').on('input', function () {
    const value = this.value.trim().toLowerCase();
    $('#permissionTableBody tr').each(function () {
        const moduleName = ($(this).data('module-row') || '').toString().toLowerCase();
        $(this).toggle(moduleName.includes(value));
    });
});

updateRoleHelperText();
updatePermissionSummary();

</script>
