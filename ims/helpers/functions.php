<?php

require_once ROOT_PATH . '/config/database.php';

function normalize_permission_name($permission_name) {
    return strtolower(trim((string) $permission_name));
}

function permission_catalog(): array {
    return [
        'dashboard.view',
        'ictd.dashboard.view',
        'ictd.masterlist.view',
        'ictd.equipment.view',
        'ictd.equipment.create',
        'ictd.equipment.edit',
        'ictd.equipment.delete',
        'ictd.equipment.import',
        'ictd.equipment.archive',
        'ictd.assign.view',
        'ictd.assign.create',
        'ictd.assign.delete',
        'ictd.maintenance.view',
        'ictd.service-report.view',
        'ictd.transmittal.view',
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
        'ictd.supplies.view',
        'ictd.supplies.create',
        'ictd.supplies.edit',
        'ictd.supplies.delete',
        'ictd.reports.view',
        'records.view_all',
        'records.scope_departments',
        'ictd.request.view',
        'ictd.request.manage',
        'ictd.title.view',
        'ictd.admin-users.view',
        'users.view',
        'users.create',
        'users.edit',
        'users.delete',
        'case.view',
        'case.create',
        'case.edit',
        'case.delete',
        'case.import',
        'case.export',
        'case.print',
        'visitors.view',
        'visitors.create',
        'visitors.edit',
        'visitors.delete',
        'visitors.import',
        'visitors.export',
        'visitors.print',
        'visitors.ban',
        'visitors.queue',
        'visitors.issue',
        'queue.view',
        'queue.create',
        'queue.edit',
        'queue.delete',
        'queue.print',
        'reports.view',
        'reports.export',
        'reports.print',
        'logs.delete',
        'documentation.view',
        'id-issuance.view',
        'id-issuance.create',
        'id-issuance.edit',
        'id-issuance.export',
        'id-issuance.print',
        'requests.view',
        'products.view',
        'page3.view',
    ];
}

function role_permission_profile_names(string $roleName): array {
    $roleName = normalize_permission_name($roleName);

    $profiles = [
        'staff' => [
            'dashboard.view',
            'ictd.dashboard.view',
            'visitors.view',
            'visitors.edit',
            'visitors.queue',
            'queue.view',
            'queue.create',
            'id-issuance.view',
            'id-issuance.create',
            'reports.view',
            'ictd.request.view',
        ],
        'admin' => [
            'dashboard.view',
            'ictd.dashboard.view',
            'ictd.masterlist.view',
            'ictd.equipment.view',
            'ictd.equipment.create',
            'ictd.equipment.edit',
            'ictd.equipment.delete',
            'ictd.equipment.import',
            'ictd.equipment.archive',
            'ictd.assign.view',
            'ictd.maintenance.view',
            'ictd.service-report.view',
            'ictd.transmittal.view',
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
            'ictd.supplies.view',
            'ictd.reports.view',
            'ictd.request.view',
            'ictd.request.manage',
            'ictd.title.view',
            'ictd.admin-users.view',
            'case.view',
            'case.create',
            'case.edit',
            'case.import',
            'case.export',
            'case.print',
            'visitors.view',
            'visitors.edit',
            'visitors.ban',
            'visitors.queue',
            'visitors.issue',
            'queue.view',
            'queue.create',
            'queue.edit',
            'queue.print',
            'reports.view',
            'reports.export',
            'reports.print',
            'documentation.view',
            'id-issuance.view',
            'id-issuance.create',
            'id-issuance.edit',
            'id-issuance.export',
            'id-issuance.print',
        ],
        'super_admin' => permission_catalog(),
    ];

    return array_values(array_unique(array_map('normalize_permission_name', $profiles[$roleName] ?? [])));
}

function role_permission_profile_ids(PDO $pdo, string $roleName): array {
    if (!db_table_exists($pdo, 'permissions')) {
        return [];
    }

    ensure_permission_catalog($pdo);

    $names = role_permission_profile_names($roleName);
    if (empty($names)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($names), '?'));
    $stmt = $pdo->prepare("SELECT id FROM permissions WHERE name IN ($placeholders)");
    $stmt->execute($names);

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
}

function ensure_rbac_schema(PDO $pdo): void {
    static $ensured = false;

    if ($ensured) {
        return;
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS roles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) NOT NULL UNIQUE,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS permissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    if (!db_column_exists($pdo, 'permissions', 'name')) {
        $pdo->exec("ALTER TABLE permissions ADD COLUMN name VARCHAR(100) NULL AFTER id");
        if (db_column_exists($pdo, 'permissions', 'permission')) {
            $pdo->exec("UPDATE permissions SET name = LOWER(TRIM(permission)) WHERE name IS NULL AND permission IS NOT NULL AND TRIM(permission) <> ''");
        }
    }
    if (db_column_exists($pdo, 'permissions', 'code')) {
        $pdo->exec("UPDATE permissions SET name = LOWER(TRIM(code)) WHERE (name IS NULL OR name = '') AND code IS NOT NULL AND TRIM(code) <> ''");
        if (db_column_exists($pdo, 'permissions', 'module') && db_column_exists($pdo, 'permissions', 'action') && db_column_exists($pdo, 'permissions', 'label')) {
            $pdo->exec("
                UPDATE permissions
                SET
                    code = name,
                    module = SUBSTRING_INDEX(name, '.', 1),
                    action = SUBSTRING_INDEX(name, '.', -1),
                    label = CONCAT(UPPER(LEFT(REPLACE(name, '.', ' '), 1)), SUBSTRING(REPLACE(name, '.', ' '), 2))
                WHERE name IS NOT NULL
                  AND name <> ''
                  AND (code IS NULL OR code = '')
            ");
        }
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS role_permissions (
            role_id INT NOT NULL,
            permission_id INT NOT NULL,
            PRIMARY KEY (role_id, permission_id),
            INDEX idx_role_permissions_permission (permission_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_permissions (
            user_id INT NOT NULL,
            permission_id INT NOT NULL,
            PRIMARY KEY (user_id, permission_id),
            INDEX idx_user_permissions_permission (permission_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_permission_overrides (
            user_id INT NOT NULL PRIMARY KEY,
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            updated_at DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    if (db_column_exists($pdo, 'roles', 'slug')) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO roles (slug, name) VALUES (?, ?)");
        foreach (['staff' => 'Staff', 'admin' => 'Admin', 'super_admin' => 'Super Admin'] as $roleName => $roleLabel) {
            $stmt->execute([$roleName, $roleLabel]);
        }
        $pdo->exec("
            UPDATE roles r
            SET r.slug = LOWER(REPLACE(r.name, ' ', '_'))
            WHERE (r.slug IS NULL OR r.slug = '')
              AND r.name IS NOT NULL
              AND r.name <> ''
              AND NOT EXISTS (
                  SELECT 1
                  FROM (SELECT slug FROM roles WHERE slug IS NOT NULL AND slug <> '') existing_roles
                  WHERE existing_roles.slug = LOWER(REPLACE(r.name, ' ', '_'))
              )
        ");
    } else {
        $stmt = $pdo->prepare("INSERT IGNORE INTO roles (name) VALUES (?)");
        foreach (['staff', 'admin', 'super_admin'] as $roleName) {
            $stmt->execute([$roleName]);
        }
    }

    if (db_table_exists($pdo, 'users') && !db_column_exists($pdo, 'users', 'role_id')) {
        $pdo->exec("ALTER TABLE users ADD COLUMN role_id INT NULL");
    }

    if (db_table_exists($pdo, 'users') && db_column_exists($pdo, 'users', 'role_id')) {
        $roleSql = db_column_exists($pdo, 'roles', 'slug')
            ? "SELECT id, COALESCE(NULLIF(slug, ''), LOWER(REPLACE(name, ' ', '_'))) AS role_key FROM roles"
            : "SELECT id, LOWER(REPLACE(name, ' ', '_')) AS role_key FROM roles";
        $roleIds = [];
        foreach ($pdo->query($roleSql)->fetchAll(PDO::FETCH_ASSOC) ?: [] as $roleRow) {
            $roleIds[strtolower((string) $roleRow['role_key'])] = (int) $roleRow['id'];
        }
        $defaultRoleId = (int) ($roleIds['staff'] ?? $roleIds['user'] ?? reset($roleIds) ?: 0);

        if (db_column_exists($pdo, 'users', 'role')) {
            $update = $pdo->prepare("UPDATE users SET role_id = ? WHERE (role_id IS NULL OR role_id = 0) AND LOWER(REPLACE(role, ' ', '_')) = ?");
            foreach (['staff', 'admin', 'super_admin', 'user'] as $legacyRole) {
                $mappedRole = $legacyRole === 'user' ? 'staff' : $legacyRole;
                if (!empty($roleIds[$mappedRole])) {
                    $update->execute([(int) $roleIds[$mappedRole], $legacyRole]);
                }
            }
        }

        if ($defaultRoleId > 0) {
            $pdo->prepare("UPDATE users SET role_id = ? WHERE role_id IS NULL OR role_id = 0")->execute([$defaultRoleId]);
        }
    }

    $ensured = true;
}

function ensure_permission_catalog(PDO $pdo): void {
    static $ensured = false;

    if ($ensured) {
        return;
    }

    ensure_rbac_schema($pdo);

    $catalog = array_values(array_unique(array_map('normalize_permission_name', permission_catalog())));
    if (empty($catalog)) {
        $ensured = true;
        return;
    }

    $existing = $pdo->query("SELECT name FROM permissions")->fetchAll(PDO::FETCH_COLUMN) ?: [];
    $existing = array_map('normalize_permission_name', $existing);
    $missing = array_values(array_diff($catalog, $existing));

    if (!empty($missing)) {
        $hasPermissionCodeShape = db_column_exists($pdo, 'permissions', 'module')
            && db_column_exists($pdo, 'permissions', 'action')
            && db_column_exists($pdo, 'permissions', 'code')
            && db_column_exists($pdo, 'permissions', 'label');

        $stmt = $hasPermissionCodeShape
            ? $pdo->prepare("INSERT INTO permissions (name, module, action, code, label) VALUES (?, ?, ?, ?, ?)")
            : $pdo->prepare("INSERT INTO permissions (name) VALUES (?)");

        foreach ($missing as $permissionName) {
            if ($hasPermissionCodeShape) {
                [$module, $action] = array_pad(explode('.', $permissionName, 2), 2, 'view');
                $label = ucwords(str_replace(['.', '-', '_'], ' ', $permissionName));
                $stmt->execute([$permissionName, $module, $action, $permissionName, $label]);
            } else {
                $stmt->execute([$permissionName]);
            }
        }
    }

    foreach (['staff', 'admin', 'super_admin'] as $roleName) {
        $stmt = $pdo->prepare(db_column_exists($pdo, 'roles', 'slug')
            ? "SELECT id FROM roles WHERE slug = ? OR LOWER(REPLACE(name, ' ', '_')) = ? LIMIT 1"
            : "SELECT id FROM roles WHERE LOWER(REPLACE(name, ' ', '_')) = ? LIMIT 1");
        $stmt->execute(db_column_exists($pdo, 'roles', 'slug') ? [$roleName, $roleName] : [$roleName]);
        $roleId = (int) ($stmt->fetchColumn() ?: 0);
        if ($roleId <= 0) {
            continue;
        }

        $profilePermissions = role_permission_profile_names($roleName);
        if (empty($profilePermissions)) {
            continue;
        }

        $placeholders = implode(',', array_fill(0, count($profilePermissions), '?'));
        $stmt = $pdo->prepare("SELECT id FROM permissions WHERE name IN ($placeholders)");
        $stmt->execute($profilePermissions);
        $permissionIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);

        if (!empty($permissionIds)) {
            $insert = $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
            foreach ($permissionIds as $permissionId) {
                $insert->execute([$roleId, $permissionId]);
            }
        }
    }

    $ensured = true;
}

function resolve_permission_aliases($permission_name) {
    $permission_name = normalize_permission_name($permission_name);

    $aliases = [
        'view_users' => ['users.view'],
        'assign_role' => ['users.edit'],
        'manage_permissions' => ['users.view', 'users.edit'],
        'admin' => ['users.view'],
        'logs.view' => ['reports.view'],
        'requests.view' => ['ictd.request.view', 'requests.view'],
        'logs.delete' => ['logs.delete'],
        'queue.view' => ['queue.view', 'visitors.view'],
        'queue.create' => ['queue.create'],
        'queue.edit' => ['queue.edit'],
        'queue.delete' => ['queue.delete'],
        'queue.print' => ['queue.print'],
        'id-issuance.view' => ['id-issuance.view', 'visitors.view'],
        'id-issuance.create' => ['id-issuance.create'],
        'id-issuance.edit' => ['id-issuance.edit'],
        'id-issuance.export' => ['id-issuance.export'],
        'id-issuance.print' => ['id-issuance.print'],
        'reports.export' => ['reports.export'],
        'reports.print' => ['reports.print'],
        'visitors.ban' => ['visitors.ban', 'visitors.edit'],
        'visitors.queue' => ['visitors.queue', 'queue.create'],
        'visitors.issue' => ['visitors.issue', 'id-issuance.create'],
    ];

    return $aliases[$permission_name] ?? [$permission_name];
}

function clear_auth_cache() {
    unset($_SESSION['permissions'], $_SESSION['user_permissions'], $_SESSION['user_fresh']);
}

function rbac_tables_available(PDO $pdo): bool {
    static $available = null;

    if ($available !== null) {
        return $available;
    }

    ensure_rbac_schema($pdo);

    $requiredTables = ['roles', 'permissions', 'role_permissions', 'user_permissions'];
    foreach ($requiredTables as $table) {
        if (!db_table_exists($pdo, $table)) {
            return $available = false;
        }
    }

    return $available = true;
}

function legacy_fallback_permissions(): array {
    return permission_catalog();
}

function log_route_activity($route, $label = 'ROUTE_ACCESS', array $context = []) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['user'])) {
        return;
    }

    $route = '/' . ltrim(trim((string) $route), '/');
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

    $details = array_merge([
        'method' => $method,
        'route' => $route,
    ], $context);

    $parts = [];
    foreach ($details as $key => $value) {
        if ($value === null || $value === '') {
            continue;
        }

        if (is_array($value)) {
            $value = implode(', ', $value);
        }

        $parts[] = $key . ': ' . $value;
    }

    log_activity($label, implode(' | ', $parts));
}

function current_user() {
    global $pdo;
    ensure_rbac_schema($pdo);

    if (!isset($_SESSION['user'])) {
        return null;
    }

    // Return cached fresh user
    if (isset($_SESSION['user_fresh'])) {
        return $_SESSION['user_fresh'];
    }

    $user_id = $_SESSION['user']['id'];

    if (db_table_exists($pdo, 'roles')) {
        $roleNameExpr = db_column_exists($pdo, 'roles', 'slug')
            ? "COALESCE(NULLIF(r.slug, ''), LOWER(REPLACE(r.name, ' ', '_')))"
            : "LOWER(REPLACE(r.name, ' ', '_'))";
        $stmt = $pdo->prepare("
            SELECT u.*, {$roleNameExpr} AS role_name
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE u.id = ?
        ");
        $stmt->execute([$user_id]);
    } else {
        $stmt = $pdo->prepare("
            SELECT u.*
            FROM users u
            WHERE u.id = ?
        ");
        $stmt->execute([$user_id]);
    }

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        if (empty($user['role_name'])) {
            $user['role_name'] = $_SESSION['user']['role_name'] ?? (rbac_tables_available($pdo) ? 'staff' : 'admin');
        }
        $_SESSION['user_fresh'] = $user;
        return $user;
    }

    return null;
}

/*auth */
function require_login() {
    if (!isset($_SESSION['user'])) {
        header("Location: " . BASE_URL . "/login");
        exit;
    }
}

/*role check*/
function is_super_admin() {
    $user = current_user();
    return $user && normalize_permission_name($user['role_name'] ?? '') === 'super_admin';
}

function is_admin() {
    $user = current_user();
    return $user && in_array(normalize_permission_name($user['role_name'] ?? ''), ['admin', 'super_admin'], true);
}

/*load permission (cache) */
function load_permissions($role_id) {
    global $pdo;

    if (!rbac_tables_available($pdo)) {
        return legacy_fallback_permissions();
    }

    $stmt = $pdo->prepare("
        SELECT p.name
        FROM role_permissions rp
        JOIN permissions p ON rp.permission_id = p.id
        WHERE rp.role_id = ?
    ");
    $stmt->execute([$role_id]);

    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function load_user_permissions($user_id) {
    global $pdo;

    if (!rbac_tables_available($pdo)) {
        return [];
    }

    $stmt = $pdo->prepare("
        SELECT p.name
        FROM user_permissions up
        JOIN permissions p ON p.id = up.permission_id
        WHERE up.user_id = ?
    ");
    $stmt->execute([$user_id]);

    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function user_permission_override_enabled($user_id): bool {
    global $pdo;

    if (!rbac_tables_available($pdo)) {
        return false;
    }

    ensure_permission_catalog($pdo);

    if (db_table_exists($pdo, 'user_permission_overrides')) {
        $stmt = $pdo->prepare("SELECT enabled FROM user_permission_overrides WHERE user_id = ? LIMIT 1");
        $stmt->execute([(int) $user_id]);
        $enabled = $stmt->fetchColumn();
        if ($enabled !== false) {
            return (int) $enabled === 1;
        }
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_permissions WHERE user_id = ?");
    $stmt->execute([(int) $user_id]);

    return (int) $stmt->fetchColumn() > 0;
}

/*rbac permission check (core) */
function can($permission_name) {
    global $pdo;

    $user = current_user();
    if (!$user) return false;

    $requested_permissions = resolve_permission_aliases($permission_name);

    if (!rbac_tables_available($pdo)) {
        return true;
    }

    // super admin
    ensure_permission_catalog($pdo);

    if (is_super_admin()) return true;

    // load permission //
    if (!isset($_SESSION['permissions'])) {
        $_SESSION['permissions'] = load_permissions($user['role_id']);
    }

    $role_permissions = $_SESSION['permissions'];

    //load user override permission//
    if (!isset($_SESSION['user_permissions'])) {
        $_SESSION['user_permissions'] = load_user_permissions($user['id']);
    }

    $user_permissions = $_SESSION['user_permissions'];

    $has_user_override = user_permission_override_enabled($user['id']);
    $all_permissions = array_map(
        'normalize_permission_name',
        array_unique($has_user_override ? $user_permissions : array_merge($role_permissions, $user_permissions))
    );

    foreach ($requested_permissions as $permission) {
        if (in_array($permission, $all_permissions, true)) {
            return true;
        }
    }

    $_SESSION['permissions'] = load_permissions($user['role_id']);
    $_SESSION['user_permissions'] = load_user_permissions($user['id']);
    $has_user_override = user_permission_override_enabled($user['id']);
    $all_permissions = array_map(
        'normalize_permission_name',
        array_unique($has_user_override ? $_SESSION['user_permissions'] : array_merge($_SESSION['permissions'], $_SESSION['user_permissions']))
    );

    foreach ($requested_permissions as $permission) {
        if (in_array($permission, $all_permissions, true)) {
            return true;
        }
    }

    return false;
}

    /*require permission (req protection) */
    function require_permission($permission_name) {

        if (is_super_admin()) return;


        if (!can($permission_name)) {
            http_response_code(403);
            die("
                <div style='text-align:center;margin-top:50px;font-family:sans-serif'>
                    <h2>🚫 Access Denied</h2>
                    <p>You do not have permission to access this page.</p>
                </div>
            ");
        }
    }

function require_any_permission($permissions = []) {
    foreach ($permissions as $perm) {
        if (can($perm)) return true;
    }

    http_response_code(403);
    die("❌ Access denied.");
}


function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

function db_table_exists(PDO $pdo, string $tableName): bool {
    static $cache = [];

    $tableName = trim($tableName);
    if ($tableName === '') {
        return false;
    }

    if (array_key_exists($tableName, $cache)) {
        return $cache[$tableName];
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_name = ?
    ");
    $stmt->execute([$tableName]);

    return $cache[$tableName] = ((int) $stmt->fetchColumn() > 0);
}

function db_column_exists(PDO $pdo, string $tableName, string $columnName): bool {
    static $cache = [];

    $tableName = trim($tableName);
    $columnName = trim($columnName);
    $key = strtolower($tableName . '.' . $columnName);

    if ($tableName === '' || $columnName === '') {
        return false;
    }

    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = ?
          AND column_name = ?
    ");
    $stmt->execute([$tableName, $columnName]);

    return $cache[$key] = ((int) $stmt->fetchColumn() > 0);
}

function ensure_record_scope_schema(PDO $pdo): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    if (db_table_exists($pdo, 'users') && !db_column_exists($pdo, 'users', 'can_view_all_records')) {
        $pdo->exec("ALTER TABLE users ADD COLUMN can_view_all_records TINYINT(1) NOT NULL DEFAULT 0");
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_department_scopes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            department_id INT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_user_department_scope (user_id, department_id),
            INDEX idx_user_department_scopes_user (user_id),
            INDEX idx_user_department_scopes_department (department_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    foreach ([
        'equipment' => 'INT NULL',
        'other_equipment' => 'INT NULL',
        'maintenance_reports' => 'INT NULL',
        'service_report' => 'INT NULL',
        'transmittal' => 'INT NULL',
        'assigned_record' => 'INT NULL',
        'archive_equipment' => 'INT NULL',
        'archive_other_equipment' => 'INT NULL',
    ] as $table => $definition) {
        if (db_table_exists($pdo, $table) && !db_column_exists($pdo, $table, 'created_by')) {
            $pdo->exec("ALTER TABLE {$table} ADD COLUMN created_by {$definition}");
        }
    }
}

function ensure_password_reset_schema(PDO $pdo): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS password_reset_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            username VARCHAR(100) NULL,
            email VARCHAR(150) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            reason TEXT NULL,
            notes TEXT NULL,
            approved_by INT NULL,
            resolved_by INT NULL,
            requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            resolved_at DATETIME NULL,
            INDEX idx_password_reset_user (user_id),
            INDEX idx_password_reset_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $columnExists = static function (string $column) use ($pdo): bool {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'password_reset_requests'
              AND column_name = ?
        ");
        $stmt->execute([$column]);
        return (int) $stmt->fetchColumn() > 0;
    };

    $columns = [
        'user_id' => "ALTER TABLE password_reset_requests ADD COLUMN user_id INT NOT NULL AFTER id",
        'username' => "ALTER TABLE password_reset_requests ADD COLUMN username VARCHAR(100) NULL AFTER user_id",
        'email' => "ALTER TABLE password_reset_requests ADD COLUMN email VARCHAR(150) NULL AFTER username",
        'status' => "ALTER TABLE password_reset_requests ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'pending' AFTER email",
        'reason' => "ALTER TABLE password_reset_requests ADD COLUMN reason TEXT NULL AFTER status",
        'notes' => "ALTER TABLE password_reset_requests ADD COLUMN notes TEXT NULL AFTER reason",
        'approved_by' => "ALTER TABLE password_reset_requests ADD COLUMN approved_by INT NULL AFTER notes",
        'resolved_by' => "ALTER TABLE password_reset_requests ADD COLUMN resolved_by INT NULL AFTER approved_by",
        'requested_at' => "ALTER TABLE password_reset_requests ADD COLUMN requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER resolved_by",
        'created_at' => "ALTER TABLE password_reset_requests ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER requested_at",
        'updated_at' => "ALTER TABLE password_reset_requests ADD COLUMN updated_at DATETIME NULL AFTER created_at",
        'resolved_at' => "ALTER TABLE password_reset_requests ADD COLUMN resolved_at DATETIME NULL AFTER updated_at",
    ];

    foreach ($columns as $column => $sql) {
        if (!$columnExists($column)) {
            $pdo->exec($sql);
        }
    }

    $idMetaStmt = $pdo->query("
        SELECT COLUMN_KEY, EXTRA
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'password_reset_requests'
          AND column_name = 'id'
        LIMIT 1
    ");
    $idMeta = $idMetaStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $idIsPrimary = strtoupper((string) ($idMeta['COLUMN_KEY'] ?? '')) === 'PRI';
    $idIsAutoIncrement = stripos((string) ($idMeta['EXTRA'] ?? ''), 'auto_increment') !== false;

    if (!$idIsPrimary || !$idIsAutoIncrement) {
        $duplicateStmt = $pdo->query("
            SELECT COUNT(*)
            FROM (
                SELECT id
                FROM password_reset_requests
                GROUP BY id
                HAVING COUNT(*) > 1
            ) duplicate_ids
        ");
        $hasDuplicateIds = (int) $duplicateStmt->fetchColumn() > 0;

        if ($hasDuplicateIds && !$idIsPrimary) {
            if (!$columnExists('__ims_reset_pk')) {
                $pdo->exec("ALTER TABLE password_reset_requests ADD COLUMN __ims_reset_pk INT NOT NULL AUTO_INCREMENT PRIMARY KEY");
            }

            $pdo->exec("UPDATE password_reset_requests SET id = __ims_reset_pk");
            $pdo->exec("ALTER TABLE password_reset_requests DROP PRIMARY KEY");
            $pdo->exec("ALTER TABLE password_reset_requests DROP COLUMN __ims_reset_pk");
        }

        if ($idIsPrimary) {
            $pdo->exec("ALTER TABLE password_reset_requests MODIFY id INT NOT NULL AUTO_INCREMENT");
        } else {
            $pdo->exec("ALTER TABLE password_reset_requests MODIFY id INT NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (id)");
        }
    }
}

function record_scope_current_user_id(?array $user = null): int {
    $user = $user ?: current_user();
    return (int) ($user['id'] ?? 0);
}

function user_can_view_all_records(?array $user = null): bool {
    global $pdo;

    $user = $user ?: current_user();
    if (!$user) {
        return false;
    }

    if (is_super_admin() || can('records.view_all')) {
        return true;
    }

    ensure_record_scope_schema($pdo);

    if ((int) ($user['can_view_all_records'] ?? 0) === 1) {
        return true;
    }

    $stmt = $pdo->prepare('SELECT can_view_all_records FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([record_scope_current_user_id($user)]);

    return (int) ($stmt->fetchColumn() ?: 0) === 1;
}

function user_department_scope_ids(?array $user = null): array {
    global $pdo;

    $user = $user ?: current_user();
    $userId = record_scope_current_user_id($user);
    if ($userId <= 0) {
        return [];
    }

    ensure_record_scope_schema($pdo);

    $stmt = $pdo->prepare('SELECT department_id FROM user_department_scopes WHERE user_id = ? ORDER BY department_id ASC');
    $stmt->execute([$userId]);

    return array_values(array_unique(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [])));
}

function record_scope_column(PDO $pdo, string $table, string $column, string $alias = ''): ?string {
    if (!db_table_exists($pdo, $table) || !db_column_exists($pdo, $table, $column)) {
        return null;
    }

    return ($alias !== '' ? $alias . '.' : '') . $column;
}

function apply_record_scope(PDO $pdo, array &$whereClauses, array &$params, ?string $createdByExpression, ?string $departmentExpression = null, string $paramPrefix = 'scope'): void {
    ensure_record_scope_schema($pdo);

    if (user_can_view_all_records()) {
        return;
    }

    $userId = record_scope_current_user_id();
    $scopeParts = [];

    if ($createdByExpression && $userId > 0) {
        $paramName = $paramPrefix . '_user_id';
        $scopeParts[] = "{$createdByExpression} = :{$paramName}";
        $params[$paramName] = $userId;
    }

    $departmentIds = user_department_scope_ids();
    if ($departmentExpression && !empty($departmentIds)) {
        $departmentParams = [];
        foreach ($departmentIds as $index => $departmentId) {
            $paramName = $paramPrefix . '_department_' . $index;
            $departmentParams[] = ':' . $paramName;
            $params[$paramName] = $departmentId;
        }
        $scopeParts[] = "{$departmentExpression} IN (" . implode(',', $departmentParams) . ")";
    }

    if (empty($scopeParts)) {
        $whereClauses[] = '1 = 0';
        return;
    }

    $whereClauses[] = '(' . implode(' OR ', $scopeParts) . ')';
}

function request_mine_only(): bool {
    return (int) ($_REQUEST['mine_only'] ?? 0) === 1;
}

function apply_mine_only_scope(array &$whereClauses, array &$params, ?string $createdByExpression, string $paramPrefix = 'mine'): void {
    if (!request_mine_only() || !$createdByExpression) {
        return;
    }

    $paramName = $paramPrefix . '_created_by';
    $whereClauses[] = "{$createdByExpression} = :{$paramName}";
    $params[$paramName] = record_scope_current_user_id();
}

/* activity log*/
function ensure_activity_logs_schema(PDO $pdo): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS activity_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            username VARCHAR(150) NOT NULL DEFAULT 'Guest',
            action VARCHAR(100) NOT NULL,
            description TEXT NULL,
            ip_address VARCHAR(45) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_activity_logs_user (user_id),
            INDEX idx_activity_logs_username (username),
            INDEX idx_activity_logs_action (action),
            INDEX idx_activity_logs_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    foreach ([
        'user_id' => "INT NULL",
        'username' => "VARCHAR(150) NOT NULL DEFAULT 'Guest'",
        'action' => "VARCHAR(100) NOT NULL",
        'description' => "TEXT NULL",
        'ip_address' => "VARCHAR(45) NULL",
        'created_at' => "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
    ] as $column => $definition) {
        if (!db_column_exists($pdo, 'activity_logs', $column)) {
            $pdo->exec("ALTER TABLE activity_logs ADD COLUMN {$column} {$definition}");
        }
    }
}

function log_activity($action, $description = '') {
    global $pdo;

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $user = $_SESSION['user'] ?? null;

    $user_id  = $user['id'] ?? null;
    $username = $user['username'] ?? 'Guest';
    $ip       = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';

    try {
        ensure_activity_logs_schema($pdo);

        $stmt = $pdo->prepare("
            INSERT INTO activity_logs 
            (user_id, username, action, description, ip_address)
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $user_id,
            $username,
            $action,
            $description,
            $ip
        ]);

    } catch (PDOException $e) {
        // silent fail
    }
}

/* global notif (sweet alert) */
function show_notification($type, $message, $title = '') {

    $types = ['success', 'error', 'warning', 'info'];
    if (!in_array($type, $types)) return;

    if ($title == '') {
        $title = ucfirst($type) . '!';
    }

    echo "
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            icon: '$type',
            title: '$title',
            text: '$message',
            timer: 4000,
            showConfirmButton: false
        });
    });
    </script>
    ";
}
