<?php

require_once ROOT_PATH . '/config/database.php';

function normalize_permission_name($permission_name) {
    return strtolower(trim((string) $permission_name));
}

function permission_catalog(): array {
    return [
        'dashboard.view',
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
        'pdl.view',
        'pdl.create',
        'pdl.edit',
        'pdl.delete',
        'pdl.import',
        'pdl.export',
        'pdl.print',
        'pdl-management.view',
        'pdl-management.create',
        'pdl-management.edit',
        'pdl-management.delete',
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
        'pending' => [],
        'staff' => [
            'dashboard.view',
            'visitors.view',
            'visitors.edit',
            'visitors.queue',
            'queue.view',
            'queue.create',
            'id-issuance.view',
            'id-issuance.create',
            'reports.view',
        ],
        'admin' => [
            'dashboard.view',
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
            'pdl.view',
            'pdl.create',
            'pdl.edit',
            'pdl.import',
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

function ensure_permission_catalog(PDO $pdo): void {
    static $ensured = false;

    if ($ensured) {
        return;
    }

    $catalog = array_values(array_unique(array_map('normalize_permission_name', permission_catalog())));
    if (empty($catalog)) {
        $ensured = true;
        return;
    }

    $existing = $pdo->query("SELECT name FROM permissions")->fetchAll(PDO::FETCH_COLUMN) ?: [];
    $existing = array_map('normalize_permission_name', $existing);
    $missing = array_values(array_diff($catalog, $existing));

    if (!empty($missing)) {
        $stmt = $pdo->prepare("INSERT INTO permissions (name) VALUES (?)");
        foreach ($missing as $permissionName) {
            $stmt->execute([$permissionName]);
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
        'pdl.view' => ['pdl.view', 'pdl-management.view'],
        'pdl.create' => ['pdl.create', 'pdl-management.create'],
        'pdl.edit' => ['pdl.edit', 'pdl-management.edit'],
        'pdl.delete' => ['pdl.delete', 'pdl-management.delete'],
        'pdl.import' => ['pdl.import', 'pdl.edit', 'pdl-management.edit'],
        'pdl.export' => ['pdl.export'],
        'pdl.print' => ['pdl.print'],
        'pdl-management.view' => ['pdl-management.view', 'pdl.view'],
        'pdl-management.create' => ['pdl-management.create', 'pdl.create'],
        'pdl-management.edit' => ['pdl-management.edit', 'pdl.edit'],
        'pdl-management.delete' => ['pdl-management.delete', 'pdl.delete'],
    ];

    return $aliases[$permission_name] ?? [$permission_name];
}

function clear_auth_cache() {
    unset($_SESSION['permissions'], $_SESSION['user_permissions'], $_SESSION['user_fresh']);
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

/* ==================================================
   CURRENT USER (WITH ROLE JOIN + CACHE)
================================================== */
function current_user() {
    global $pdo;

    if (!isset($_SESSION['user'])) {
        return null;
    }

    // ✅ Return cached fresh user
    if (isset($_SESSION['user_fresh'])) {
        return $_SESSION['user_fresh'];
    }

    $user_id = $_SESSION['user']['id'];

    $stmt = $pdo->prepare("
        SELECT u.*, r.name AS role_name
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        WHERE u.id = ?
    ");
    $stmt->execute([$user_id]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $_SESSION['user_fresh'] = $user;
        return $user;
    }

    return null;
}

/* ==================================================
   AUTH
================================================== */
function require_login() {
    if (!isset($_SESSION['user'])) {
        header("Location: " . BASE_URL . "/login");
        exit;
    }

    if (!headers_sent()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }
}

/* ==================================================
   ROLE CHECKS
================================================== */
function is_super_admin() {
    $user = current_user();
    return $user && normalize_permission_name($user['role_name'] ?? '') === 'super_admin';
}

function is_admin() {
    $user = current_user();
    return $user && in_array(normalize_permission_name($user['role_name'] ?? ''), ['admin', 'super_admin'], true);
}

function require_super_admin() {
    if (is_super_admin()) {
        return;
    }

    http_response_code(403);
    die("
        <div style='text-align:center;margin-top:50px;font-family:sans-serif'>
            <h2>Access Denied</h2>
            <p>Only the super admin can access this page.</p>
        </div>
    ");
}

/* ==================================================
   LOAD PERMISSIONS (CACHE)
================================================== */
function load_permissions($role_id) {
    global $pdo;

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

    $stmt = $pdo->prepare("
        SELECT p.name
        FROM user_permissions up
        JOIN permissions p ON p.id = up.permission_id
        WHERE up.user_id = ?
    ");
    $stmt->execute([$user_id]);

    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/* ==================================================
   RBAC PERMISSION CHECK (CORE)
================================================== */
function can($permission_name) {

    $user = current_user();
    if (!$user) return false;

    $requested_permissions = resolve_permission_aliases($permission_name);

    // ✅ SUPER ADMIN
    if (is_super_admin()) return true;

    // ===============================
    // LOAD ROLE PERMISSIONS
    // ===============================
    if (!isset($_SESSION['permissions'])) {
        $_SESSION['permissions'] = load_permissions($user['role_id']);
    }

    $role_permissions = $_SESSION['permissions'];

    // ===============================
    // LOAD USER OVERRIDE PERMISSIONS
    // ===============================
    if (!isset($_SESSION['user_permissions'])) {
        $_SESSION['user_permissions'] = load_user_permissions($user['id']);
    }

    $user_permissions = $_SESSION['user_permissions'];

    // ===============================
    // MERGE PERMISSIONS
    // ===============================
    $all_permissions = array_map(
        'normalize_permission_name',
        array_unique(array_merge($role_permissions, $user_permissions))
    );

    foreach ($requested_permissions as $permission) {
        if (in_array($permission, $all_permissions, true)) {
            return true;
        }
    }

    return false;
}

    /* ==================================================
    REQUIRE PERMISSION (PAGE PROTECTION)
    ================================================== */
    function require_permission($permission_name) {

        // ✅ Super admin = full access
        if (is_super_admin()) return;

        // ✅ Admin = can access ALL pages

        // ❌ Staff = strict permission check
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
/* ==================================================
   OPTIONAL: REQUIRE ANY PERMISSION
================================================== */
function require_any_permission($permissions = []) {
    foreach ($permissions as $perm) {
        if (can($perm)) return true;
    }

    http_response_code(403);
    die("❌ Access denied.");
}

/* ==================================================
   ESCAPE OUTPUT
================================================== */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/* ==================================================
   ACTIVITY LOG
================================================== */
function log_activity_label($action) {
    $labels = [
        'LOGIN_FAILED' => 'Failed login attempt',
        'LOGIN_SUCCESS' => 'Successful login',
        'LOGOUT' => 'User logout',
        'REGISTER' => 'User registration',
        'RESET_REQUEST' => 'Password reset request',
        'RESET_APPROVED' => 'Password reset approved',
        'RESET_REJECTED' => 'Password reset rejected',
        'PASSWORD_CHANGED' => 'Password changed',
        'PDL_CREATE' => 'Resident record created',
        'PDL_UPDATE' => 'Resident record updated',
        'PDL_DELETE' => 'Resident record deleted',
        'PDL_IMPORT' => 'Resident import completed',
        'PDL_VISITOR_IMPORT' => 'Resident visitor import completed',
        'VISITOR_REGISTRATION_CREATE' => 'Visitor registration created',
        'VISITOR_REGISTRATION_UPDATE' => 'Visitor registration updated',
        'VISITOR_REGISTRATION_SUBMIT' => 'Visitor registration submitted',
        'VISITOR_DOCUMENT_UPLOAD' => 'Visitor document uploaded',
        'VISITOR_DOCUMENT_REMOVE' => 'Visitor document removed',
        'VISITOR_PHOTO_UPDATE' => 'Visitor photo updated',
        'VISITOR_REQUIREMENT_UPDATE' => 'Visitor requirement updated',
        'VISITOR_EMERGENCY_CONTACT_UPDATE' => 'Visitor emergency contact updated',
        'VISITOR_BAN' => 'Visitor banned',
        'VISITOR_BAN_LIFT' => 'Visitor ban lifted',
        'VISITOR_ID_ISSUE' => 'Visitor ID issued',
        'VISITOR_ID_REISSUE' => 'Visitor ID re-issued',
        'QUEUE_VISITOR_LOAD' => 'Queue visitor profile loaded',
        'QUEUE_QR_SCAN' => 'Visitor QR validated',
        'QUEUE_ADD' => 'Visitor added to queue',
        'QUEUE_ACCEPT' => 'Queue request accepted',
        'QUEUE_REJECT' => 'Queue request rejected',
        'QUEUE_TIMEOUT' => 'Visitor timed out',
        'ROLE_UPDATE' => 'User role updated',
        'ROLE_PERMISSION_UPDATE' => 'Role permissions updated',
        'USER_PERMISSION_VIEW' => 'User permissions viewed',
        'LOGS_DELETE' => 'Activity logs deleted',
        'PAGE_VIEW' => 'Page opened',
    ];

    return $labels[$action] ?? ucwords(strtolower(str_replace(['_', '-'], ' ', (string) $action)));
}

function log_activity_route() {
    $uri = trim((string) ($_SERVER['REQUEST_URI'] ?? ''));
    if ($uri === '') {
        return '';
    }

    $path = parse_url($uri, PHP_URL_PATH);
    return is_string($path) ? $path : $uri;
}

function log_activity_description($action, $description, $username, $roleName, $ip) {
    $parts = [
        'Activity: ' . log_activity_label($action),
    ];

    $description = trim((string) $description);
    if ($description !== '') {
        $parts[] = 'Details: ' . preg_replace('/\s+/', ' ', $description);
    }

    $actor = trim((string) $username);
    if ($actor !== '') {
        $roleName = trim((string) $roleName);
        $parts[] = 'Performed by: ' . $actor . ($roleName !== '' ? ' (' . $roleName . ')' : '');
    }

    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? ''));
    $route = log_activity_route();
    if ($method !== '' || $route !== '') {
        $parts[] = 'Request: ' . trim($method . ' ' . $route);
    }

    if (!empty($_SERVER['HTTP_USER_AGENT'])) {
        $userAgent = substr(preg_replace('/\s+/', ' ', (string) $_SERVER['HTTP_USER_AGENT']), 0, 120);
        $parts[] = 'Device: ' . $userAgent;
    }

    if ($ip !== '') {
        $parts[] = 'IP: ' . $ip;
    }

    return implode(' | ', $parts);
}

function log_activity($action, $description = '') {
    global $pdo;

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $user = $_SESSION['user'] ?? null;

    $user_id  = $user['id'] ?? null;
    $username = $user['username'] ?? 'Guest';
    $roleName = $user['role_name'] ?? '';
    $ip       = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $detailedDescription = log_activity_description($action, $description, $username, $roleName, $ip);

    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs 
            (user_id, username, action, description, ip_address)
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $user_id,
            $username,
            $action,
            $detailedDescription,
            $ip
        ]);

    } catch (PDOException $e) {
        // silent fail
    }
}

/* ==================================================
   GLOBAL NOTIFICATION (SWEETALERT)
================================================== */
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

/* ==================================================
   RECORD HISTORY / ARCHIVE
================================================== */
function ensure_record_history_table(PDO $pdo): void {
    static $ensured = false;

    if ($ensured) {
        return;
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS record_history (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            entity_type VARCHAR(100) NOT NULL,
            entity_id VARCHAR(100) NOT NULL,
            action_type ENUM('created','updated','deleted','restored') NOT NULL,
            summary VARCHAR(255) DEFAULT NULL,
            before_data LONGTEXT DEFAULT NULL,
            after_data LONGTEXT DEFAULT NULL,
            changed_by INT(11) DEFAULT NULL,
            changed_by_username VARCHAR(150) DEFAULT NULL,
            ip_address VARCHAR(64) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_entity (entity_type, entity_id),
            INDEX idx_action_type (action_type),
            INDEX idx_created_at (created_at),
            INDEX idx_changed_by (changed_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $ensured = true;
}

function archive_record_change(string $entityType, $entityId, string $actionType, $beforeData = null, $afterData = null, string $summary = ''): void {
    global $pdo;

    $actionType = strtolower(trim($actionType));
    if (!in_array($actionType, ['created', 'updated', 'deleted', 'restored'], true)) {
        $actionType = 'updated';
    }

    try {
        ensure_record_history_table($pdo);

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $user = $_SESSION['user'] ?? [];
        $encode = static function ($value): ?string {
            if ($value === null || $value === '') {
                return null;
            }

            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        };

        $stmt = $pdo->prepare("
            INSERT INTO record_history (
                entity_type,
                entity_id,
                action_type,
                summary,
                before_data,
                after_data,
                changed_by,
                changed_by_username,
                ip_address
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            trim($entityType),
            (string) $entityId,
            $actionType,
            trim($summary),
            $encode($beforeData),
            $encode($afterData),
            $user['id'] ?? null,
            $user['username'] ?? 'Guest',
            $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN',
        ]);
    } catch (Throwable $e) {
        // Archive failures must not block operational transactions.
    }
}

function fetch_table_record(PDO $pdo, string $table, $id): ?array {
    static $allowedTables = [
        'case_masterlist',
        'court_masterlist',
        'dorm_masterlist',
        'relationship_masterlist',
        'pdl_info',
        'visitors_registration',
        'users',
    ];

    if (!in_array($table, $allowedTables, true)) {
        return null;
    }

    $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE id = ? LIMIT 1");
    $stmt->execute([(int) $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}
