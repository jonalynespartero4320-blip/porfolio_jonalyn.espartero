<?php 
require_once ROOT_PATH.'/helpers/functions.php';
require_login();
require_permission('users.view');

include ROOT_PATH.'/modules/layout/header.php'; 

global $pdo;
ensure_permission_catalog($pdo);
$adminUsersSummary = $pdo->query("
SELECT u.*, r.name AS role_name 
FROM users u
LEFT JOIN roles r ON r.id = u.role_id
")->fetchAll(PDO::FETCH_ASSOC);
$adminRolesSummary = $pdo->query("SELECT * FROM roles")->fetchAll(PDO::FETCH_ASSOC);
$adminResetsSummary = $pdo->query("
    SELECT pr.*, u.name, u.username
    FROM password_reset_requests pr
    LEFT JOIN users u ON u.id = pr.user_id
    ORDER BY pr.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);
$adminPermissionsSummary = $pdo->query("SELECT * FROM permissions")->fetchAll(PDO::FETCH_ASSOC);
$adminUserCount = count($adminUsersSummary);
$adminRoleCount = count($adminRolesSummary);
$adminPendingResets = count(array_filter($adminResetsSummary, static fn($row) => ($row['status'] ?? '') === 'pending'));
$adminSuperAdmins = count(array_filter($adminUsersSummary, static fn($row) => strtolower((string) ($row['role_name'] ?? '')) === 'super_admin'));
$adminStaffCount = count(array_filter($adminUsersSummary, static fn($row) => strtolower((string) ($row['role_name'] ?? '')) === 'staff'));
$adminAdminCount = count(array_filter($adminUsersSummary, static fn($row) => strtolower((string) ($row['role_name'] ?? '')) === 'admin'));
$adminPendingApprovalCount = count(array_filter($adminUsersSummary, static fn($row) => strtolower((string) ($row['role_name'] ?? '')) === 'pending'));
?>

<style>
  .admin-shell { display: grid; gap: .85rem; 
    /* background:
      linear-gradient(180deg, rgba(255, 255, 255, 0.86) 0%, rgba(248, 250, 252, 0.96) 100%); */
    border-radius: 28px;
    padding: 24px;}
  .admin-hero {
    position: relative;
    overflow: hidden;
    border: 0;
    border-radius: 22px;
    background:
      linear-gradient(135deg, #062150, #447A61);
    color: #fff;
    box-shadow: 0 18px 42px rgba(8, 19, 31, 0.14);
  }
  .admin-hero::after {
    content: "";
    position: absolute;
    right: -70px;
    bottom: -75px;
    width: 185px;
    height: 185px;
    border-radius: 50%;
    background: rgba(255,255,255,0.1);
  }
  .admin-hero .card-body { position: relative; z-index: 1; padding: 1rem 1.15rem; }
  .admin-hero h2 { font-size: clamp(1.45rem, 2vw, 2rem); }
  .admin-hero p { font-size: .92rem; }
  .admin-stat-grid { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: .75rem; }
  .admin-stat-card, .admin-toolbar { border: 0; border-radius: 24px; box-shadow: 0 16px 38px rgba(15, 23, 42, 0.08); }
  .admin-stat-card { border-radius: 20px; margin-bottom: 0; }
  .admin-stat-card .card-body { padding: .85rem 1rem; }
  .admin-stat-card .display-6 { font-size: 2rem; line-height: 1; }
  .admin-toolbar { border-radius: 20px; margin-bottom: 0; }
  .admin-toolbar .card-body { padding: .85rem 1rem; }
  .admin-stat-icon {
    width: 42px; height: 42px; border-radius: 14px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.1rem;
  }
  .admin-workspace-card {
    border: 0;
    border-radius: 22px;
    background: rgba(255,255,255,0.96);
    box-shadow: 0 14px 34px rgba(15,23,42,0.09);
    overflow: hidden;
  }
  .admin-workspace-card > .card-header {
    padding: .8rem .9rem 0;
    background:
      linear-gradient(135deg, rgba(255,255,255,0.98), rgba(241,247,244,0.98));
    border-bottom: 1px solid rgba(8,19,31,0.08);
  }
  .admin-workspace-card > .card-body {
    padding: .85rem .9rem .9rem;
  }
  .admin-tabs { gap: .45rem; border-bottom: 0; margin-top: .55rem; }
  .admin-tabs .nav-item { margin-bottom: -1px; }
  .admin-tabs .nav-link {
    position: relative;
    border: 1px solid rgba(8,19,31,0.08);
    border-bottom: 0;
    border-radius: 14px 14px 0 0;
    padding: .58rem .85rem .72rem;
    background: linear-gradient(180deg, #f8fafc, #edf3f1);
    color: #334155;
    font-weight: 800;
    min-width: 145px;
    box-shadow: inset 0 -8px 14px rgba(15,23,42,0.04);
  }
  .admin-tabs .nav-link::after {
    content: "";
    position: absolute;
    left: 16px;
    right: 16px;
    bottom: 5px;
    height: 3px;
    border-radius: 999px;
    background: transparent;
  }
  .admin-tabs .nav-link.active {
    background: #fff;
    color: #447A61;
    border-color: rgba(15,91,52,0.18);
    box-shadow: 0 -8px 22px rgba(15,23,42,0.08);
  }
  .admin-tabs .nav-link.active::after {
    background: linear-gradient(90deg, #062150, #447A61);
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
  .admin-panel {
    /* border-radius: 18px; */
    overflow: hidden;
    margin-bottom: 0;
    background: #fff;
    /* border: 1px solid rgba(8,19,31,0.08); */
  }
  .admin-panel .card-header {
    padding: .75rem .9rem;
    background: linear-gradient(135deg, rgba(15,71,215,0.04), rgba(15,143,45,0.04));
    border-bottom: 1px solid rgba(8,19,31,0.08);
  }
  .admin-panel .card-body {
    padding: .65rem;
  }
  .perm-checkbox:checked { accent-color: #062150; }
  td.active-cell { background: rgba(15, 143, 45, 0.12) !important; }
  tr.active-row td:first-child { background: rgba(15, 143, 45, 0.18) !important; }
  .modal-body { max-height: 65vh; overflow-y: auto; }
  .modal-header, .modal-footer { position: sticky; z-index: 10; }
  .modal-header { top: 0; }
  .modal-footer { bottom: 0; }
  .table-responsive { max-height: 60vh; }
.permission-role-note {
    border-radius: 16px;
    background: linear-gradient(135deg, rgba(15,71,215,0.05), rgba(15,143,45,0.05));
    border: 1px solid rgba(8,19,31,0.08);
    padding: .9rem 1rem;
  }
  .container-fluid.py-3 > h4.fw-bold.mb-3 { display: none; }
  @media (max-width: 1199px) {
    .admin-stat-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  }
  @media (max-width: 767px) {
    .admin-stat-grid { grid-template-columns: 1fr; }
  }
</style>

<div class="container-fluid py-3">

<h4 class="fw-bold mb-3">👥 Admin Panel</h4>

<div class="admin-shell">

<div class="card admin-hero">
    <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <div class="text-uppercase small fw-semibold opacity-75 mb-2">Administration</div>
            <h2 class="fw-bold mb-1">Account Administration</h2>
            <p class="mb-0 opacity-75">Manage user governance, role assignment, permission overrides, and password reset approvals from one control center.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <span class="admin-chip"><i class="bi bi-people"></i><?= $adminUserCount ?> users</span>
            <span class="admin-chip"><i class="bi bi-key"></i><?= $adminPendingResets ?> pending resets</span>
        </div>
    </div>
</div>

<div class="admin-stat-grid">
    <div class="card admin-stat-card"><div class="card-body"><div class="d-flex justify-content-between align-items-start"><div><div class="text-muted small">Users</div><div class="display-6 fw-bold mb-0"><?= $adminUserCount ?></div></div><div class="admin-stat-icon bg-primary-subtle text-primary"><i class="bi bi-people-fill"></i></div></div></div></div>
    <div class="card admin-stat-card"><div class="card-body"><div class="d-flex justify-content-between align-items-start"><div><div class="text-muted small">Staff</div><div class="display-6 fw-bold mb-0"><?= $adminStaffCount ?></div></div><div class="admin-stat-icon bg-success-subtle text-success"><i class="bi bi-person-vcard"></i></div></div></div></div>
    <div class="card admin-stat-card"><div class="card-body"><div class="d-flex justify-content-between align-items-start"><div><div class="text-muted small">Admins</div><div class="display-6 fw-bold mb-0"><?= $adminAdminCount ?></div></div><div class="admin-stat-icon bg-info-subtle text-info"><i class="bi bi-person-gear"></i></div></div></div></div>
    <div class="card admin-stat-card"><div class="card-body"><div class="d-flex justify-content-between align-items-start"><div><div class="text-muted small">Pending Approval</div><div class="display-6 fw-bold mb-0"><?= $adminPendingApprovalCount ?></div></div><div class="admin-stat-icon bg-warning-subtle text-warning"><i class="bi bi-hourglass-split"></i></div></div></div></div>
    <div class="card admin-stat-card"><div class="card-body"><div class="d-flex justify-content-between align-items-start"><div><div class="text-muted small">Pending Resets</div><div class="display-6 fw-bold mb-0"><?= $adminPendingResets ?></div></div><div class="admin-stat-icon bg-warning-subtle text-warning"><i class="bi bi-clock-history"></i></div></div></div></div>
    <div class="card admin-stat-card"><div class="card-body"><div class="d-flex justify-content-between align-items-start"><div><div class="text-muted small">Super Admins</div><div class="display-6 fw-bold mb-0"><?= $adminSuperAdmins ?></div></div><div class="admin-stat-icon bg-info-subtle text-info"><i class="bi bi-shield-lock"></i></div></div></div></div>
</div>

<!-- <div class="card admin-toolbar">
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-lg-7">
                <label class="form-label fw-semibold mb-1" for="adminQuickSearch">Quick Search</label>
                <input type="text" id="adminQuickSearch" class="form-control" placeholder="Search users, roles, usernames, or reset requests">
            </div>
            <div class="col-lg-5">
                <div class="small text-muted">Governance Scope</div>
                <div class="fw-semibold fs-5"><?= count($adminPermissionsSummary) ?> system permissions available</div>
            </div>
        </div>
    </div>
</div> -->

<div class="card admin-workspace-card">
     
    <!-- <div class="card-header">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div>
                <div class="text-uppercase small fw-semibold text-muted mb-1">User Governance</div>
                <h4 class="fw-bold mb-1">Account Administration</h4>
                <p class="text-muted mb-0">Manage user roles, permission access, and password reset requests.</p>
            </div>
        </div>
    </div> -->

<div class="card-body">
    <div class="col-lg-7">
                <label class="form-label fw-semibold mb-1" for="adminQuickSearch">Quick Search</label>
                <input type="text" id="adminQuickSearch" class="form-control" placeholder="Search users, roles, usernames, or reset requests">
            </div><br>
<ul class="nav nav-tabs admin-tabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active d-inline-flex align-items-center justify-content-center gap"
            data-bs-toggle="tab"
            data-bs-target="#usersTab"
            type="button"
            role="tab">
             Users
        </button>
    </li>

    <li class="nav-item" role="presentation">
        <button class="nav-link d-inline-flex align-items-center justify-content-center gap-2"
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
SELECT u.*, r.name AS role_name 
FROM users u
LEFT JOIN roles r ON r.id = u.role_id
")->fetchAll(PDO::FETCH_ASSOC);

$roles = $pdo->query("SELECT * FROM roles")->fetchAll(PDO::FETCH_ASSOC);
$permissions = $pdo->query("SELECT * FROM permissions")->fetchAll(PDO::FETCH_ASSOC);
$canManageUsers = is_super_admin();
$roleProfileIds = [
    'staff' => role_permission_profile_ids($pdo, 'staff'),
    'admin' => role_permission_profile_ids($pdo, 'admin'),
];

$modules = [];
$actions = [];

foreach ($permissions as $p) {
    if (strpos($p['name'], '.') === false) continue;

    list($module, $action) = explode('.', $p['name'], 2);
    $modules[$module][$action] = $p['id'];
    $actions[$action] = true;
}
?>

<div class="card-1 admin-panel">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h5 class="fw-bold mb-1"><i class="bi bi-people-fill me-2 text-primary"></i>System Users</h5>
                        <!-- <span class="badge text-bg-light text-dark"><?= $adminUserCount ?> account(s)</span> -->

            <small class="text-muted">Review accounts and manage role-based access.</small>
        </div>
        <span class="badge bg-primary rounded-pill"><?= count($users) ?> user(s)</span>
    </div>
    <div class="card-body">

        <div class="table-responsive">
                    <!-- <span class="badge bg-primary rounded-pill"><?= count($users) ?> user(s)</span> -->

            <table class="table table-hover align-middle text-center mb-0" id="usersTable">

                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th style="width:120px;">Action</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?= $u['id'] ?></td>
                            <td class="text-start"><?= htmlspecialchars($u['name']) ?></td>
                            <td><?= htmlspecialchars($u['username']) ?></td>
                            <td>
                                <span class="badge bg-info">
                                    <?= htmlspecialchars($u['role_name'] ?? 'No Role') ?>
                                </span>
                            </td>
                            <td>
                                <?php if (strtolower($u['role_name'] ?? '') !== 'super_admin'): ?>
                                    <?php if ($canManageUsers): ?>
                                        <button
                                            type="button"
                                            class="btn btn-primary btn-sm manage-user-btn"
                                            data-user-id="<?= (int) $u['id'] ?>"
                                            data-role-id="<?= (int) ($u['role_id'] ?? 0) ?>"
                                            data-user-name="<?= e($u['name']) ?>">
                                            Manage
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted">View Only</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge bg-success px-3 py-2">
                                        <i class="bi bi-shield-check"></i> Full Access
                                    </span>
                                <?php endif; ?>
                            </td>
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
    SELECT pr.*, u.name, u.username
    FROM password_reset_requests pr
    LEFT JOIN users u ON u.id = pr.user_id
    ORDER BY pr.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="admin-panel-table">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h5 class="fw-bold mb-1"><i class="bi bi-key-fill me-2 text-warning"></i>Password Reset Requests</h5>
            <small class="text-muted">Approve or reject pending password recovery requests.</small>
        </div>
        <span class="badge bg-warning text-dark rounded-pill"><?= count($resets) ?> request(s)</span>
    </div>
    <div class="card-body">

        <div class="table-responsive">
            <table class="table table-hover align-middle text-center mb-0" id="resetTable">

                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th style="width:180px;">Action</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($resets as $r): ?>
                        <tr>

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

                            <td>
                                <?php if ($r['status'] === 'pending' && can('users.edit')): ?>
                                    <div class="d-flex justify-content-center gap-1">

                                        <button class="btn btn-success btn-sm"
                                            onclick="openApproveModal(<?= $r['id'] ?>)">
                                            Approve
                                        </button>

                                        <button class="btn btn-danger btn-sm"
                                            onclick="openRejectModal(<?= $r['id'] ?>)">
                                            Reject
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

</div>
</div>

<!-- ================= ROLE MODAL ================= -->
<div class="modal fade" id="roleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">

            <form method="POST" action="<?= BASE_URL ?>/assign-role-permissions">

                <div class="modal-header bg-dark text-white">
                    <h5 class="mb-0 text-black">Permission Matrix</h5>
                    <button type="button" class="btn-close btn-close-black" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <input type="hidden" name="user_id" id="modal_user_id">

                    <input type="text" id="modal_user_name"
                        class="form-control mb-3" disabled>

                    <select name="role_id" id="modal_role_id" class="form-select mb-3" <?= $canManageUsers ? '' : 'disabled' ?>>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= $r['id'] ?>"
                                data-role="<?= strtolower($r['name']) ?>">
                                <?= e($r['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div class="permission-role-note mb-3">
                        <div class="fw-semibold mb-1">Role Guidance</div>
                        <div class="small text-muted" id="roleHelperText">Choose a role, then fine-tune permission overrides as needed.</div>
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
                                            <?= ucfirst($module) ?>
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
    super_admin: 'Super Admin has full platform governance, including user management, permissions, security actions, and all operational modules.',
    admin: 'Admin is focused on jail operations: Resident records, visitor processing, queue supervision, reporting, and ID issuance, without platform-governance powers by default.',
    staff: 'Staff is limited to day-to-day frontline work such as visitor review, queue intake, basic ID issuance, and dashboard access.',
    pending: 'Pending users have no module access yet. Assign Staff, Admin, or Super Admin only after super admin approval.'
};
let usersTable = null;
let resetTable = null;

$(document).ready(function () {

    /* ================= DATATABLES ================= */

    usersTable = $('#usersTable').DataTable({
        pageLength: 8,
        lengthMenu: [8, 15, 30, 50],
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
        ordering: true,
        order: [[4, 'desc']], // sort by date
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

    $(document).on('click', '.manage-user-btn', function () {
        const button = this;
        openRoleModal(
            Number(button.dataset.userId || 0),
            Number(button.dataset.roleId || 0),
            button.dataset.userName || 'User'
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
    helper.textContent = ROLE_PROFILES[role] || 'Choose a role, then fine-tune permission overrides as needed.';
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

function openRoleModal(id, role, name) {
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
        if (!Array.isArray(data)) {
            throw new Error('Permissions response is not an array.');
        }

        resetPermissionCheckboxes();
        const selectedRole = document.getElementById('modal_role_id').selectedOptions[0]?.dataset.role || '';
        const mergedSelection = Array.from(new Set([
            ...collectRoleDefaultPermissionIds(selectedRole),
            ...data.map((pid) => String(pid))
        ]));
        handleRoleUI(mergedSelection);

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
