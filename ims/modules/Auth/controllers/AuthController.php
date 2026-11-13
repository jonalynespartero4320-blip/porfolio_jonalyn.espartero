<?php

class AuthController {

    // login
    public static function login() {
        global $pdo;

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $identifier = trim((string) ($_POST['identifier'] ?? $_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        // validation
        if ($identifier === '' || $password === '') {
            self::error('Enter your email or username and password.');
        }

        // get user
        $hasEmailColumn = db_column_exists($pdo, 'users', 'email');
        $hasIsActiveColumn = db_column_exists($pdo, 'users', 'is_active');
        $hasLastActivityColumn = db_column_exists($pdo, 'users', 'last_activity');

        $whereClause = $hasEmailColumn
            ? '(u.email = :identifier OR u.username = :identifier)'
            : 'u.username = :identifier';

        $rbacReady = rbac_tables_available($pdo);
        $roleNameExpr = db_column_exists($pdo, 'roles', 'slug')
            ? "COALESCE(NULLIF(r.slug, ''), LOWER(REPLACE(r.name, ' ', '_')))"
            : "LOWER(REPLACE(r.name, ' ', '_'))";
        $stmt = $pdo->prepare($rbacReady ? "
            SELECT u.*, {$roleNameExpr} AS role_name
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE {$whereClause}
            LIMIT 1
        " : "
            SELECT u.*
            FROM users u
            WHERE {$whereClause}
            LIMIT 1
        ");
        $stmt->execute(['identifier' => $identifier]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // invalid login
        if (!$user || !password_verify($password, $user['password_hash'])) {
            log_activity('LOGIN_FAILED', "Failed login: {$identifier}");
            self::error('Invalid email/username or password.');
        }

        if ($rbacReady && self::isAccountPendingApproval((int) $user['id'])) {
            log_activity('LOGIN_PENDING_APPROVAL', "Pending account login attempt: {$user['username']}");
            self::error('Your account is pending. Please wait for the admin approval.');
        }

        if ($hasIsActiveColumn || $hasLastActivityColumn) {
            $updates = [];

            if ($hasIsActiveColumn) {
                $updates[] = 'is_active = 1';
            }

            if ($hasLastActivityColumn) {
                $updates[] = 'last_activity = NOW()';
            }

            if (!empty($updates)) {
                $updateStmt = $pdo->prepare('UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?');
                $updateStmt->execute([(int) $user['id']]);
            }
        }

        // success login
        session_regenerate_id(true);

        clear_auth_cache();

        $_SESSION['user'] = [
            'id'        => $user['id'],
            'name'      => $user['name'] ?? $user['username'] ?? 'User',
            'username'  => $user['username'],
            'role_id'   => $user['role_id'] ?? null,
            'role_name' => strtolower(trim((string) ($user['role_name'] ?? ($rbacReady ? 'staff' : 'admin'))))
        ];

        $_SESSION['permissions'] = $rbacReady
            ? self::loadPermissions($user['role_id'] ?? 0)
            : legacy_fallback_permissions();

        log_activity("LOGIN_SUCCESS", "User logged in: {$user['username']}");

        session_write_close();

        header('Location: ' . self::resolveHomePath($user));
        exit;
    }

    // load permission
    private static function loadPermissions($role_id) {
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

    private static function isAccountPendingApproval(int $userId): bool {
        global $pdo;

        if ($userId <= 0 || !db_table_exists($pdo, 'user_permission_overrides')) {
            return false;
        }

        $stmt = $pdo->prepare("
            SELECT enabled
            FROM user_permission_overrides
            WHERE user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $enabled = $stmt->fetchColumn();

        return $enabled !== false && (int) $enabled === 0;
    }

    // logout
    public static function logout() {

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!empty($_SESSION['user']['username'])) {
            log_activity("LOGOUT", "User logged out: {$_SESSION['user']['username']}");
        }

        $_SESSION = [];
        session_unset();
        session_destroy();

        header("Location: " . BASE_URL);
        exit;
    }

    // register
    public static function register() {
        global $pdo;
        ensure_permission_catalog($pdo);

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $name     = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email    = '';
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $hasEmailColumn = db_column_exists($pdo, 'users', 'email');

        if (!$name || !$username || !$password) {
            self::error('All fields are required.', '/register');
        }

        if (strlen($password) < 6) {
            self::error('Password must be at least 6 characters.', '/register');
        }

        if ($password !== $confirmPassword) {
            self::error('Password confirmation does not match.', '/register');
        }

        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);

        if ($stmt->fetch()) {
            self::error('Username already exists.', '/register');
        }

        if ($hasEmailColumn) {
            $emailName = strtolower(trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '.', $username), '.-_'));
            if ($emailName === '') {
                $emailName = 'user';
            }

            $email = $emailName . '@ims.local';
            $emailSuffix = 2;
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");

            while (true) {
                $stmt->execute([$email]);
                if (!$stmt->fetch()) {
                    break;
                }

                $email = $emailName . '.' . $emailSuffix . '@ims.local';
                $emailSuffix++;
            }
        }

        // FIXED ROLE QUERY
        $stmt = $pdo->prepare(db_column_exists($pdo, 'roles', 'slug')
            ? "SELECT id FROM roles WHERE slug = ? OR LOWER(REPLACE(name, ' ', '_')) = ? LIMIT 1"
            : "SELECT id FROM roles WHERE LOWER(REPLACE(name, ' ', '_')) = ? LIMIT 1");
        $stmt->execute(db_column_exists($pdo, 'roles', 'slug') ? ['staff', 'staff'] : ['staff']);
        $role = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$role) {
            self::error('Default role (staff) not found.', '/register');
        }

        $role_id = $role['id'];

        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        try {
            $pdo->beginTransaction();

            $insertColumns = ['name', 'username'];
            $insertValues = [$name, $username];
            $placeholders = ['?', '?'];

            if ($hasEmailColumn) {
                $insertColumns[] = 'email';
                $insertValues[] = $email;
                $placeholders[] = '?';
            }

            $insertColumns[] = 'password_hash';
            $insertValues[] = $password_hash;
            $placeholders[] = '?';

            if (db_column_exists($pdo, 'users', 'role')) {
                $insertColumns[] = 'role';
                $insertValues[] = 'user';
                $placeholders[] = '?';
            }

            $insertColumns[] = 'role_id';
            $insertValues[] = $role_id;
            $placeholders[] = '?';

            if (db_column_exists($pdo, 'users', 'created_at')) {
                $insertColumns[] = 'created_at';
                $placeholders[] = 'NOW()';
            }

            $stmt = $pdo->prepare("
                INSERT INTO users (`" . implode('`, `', $insertColumns) . "`)
                VALUES (" . implode(', ', $placeholders) . ")
            ");
            $stmt->execute($insertValues);

            $user_id = (int) $pdo->lastInsertId();
            if ($user_id <= 0) {
                throw new RuntimeException('Unable to create the user account.');
            }

            if (db_table_exists($pdo, 'user_permission_overrides')) {
                $stmt = $pdo->prepare("
                    INSERT INTO user_permission_overrides (user_id, enabled, updated_at)
                    VALUES (?, 0, NOW())
                    ON DUPLICATE KEY UPDATE enabled = VALUES(enabled), updated_at = VALUES(updated_at)
                ");
                $stmt->execute([$user_id]);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            self::error('Registration could not be completed. Please try again.', '/register');
        }

        log_activity("REGISTER", "User registered: $username");

        clear_auth_cache();
        unset($_SESSION['user']);

        $_SESSION['notification'] = [
            'type' => 'success',
            'message' => 'Registration saved. Please wait for the administrator to approve your account and assign your permissions or contact the administrator for faster approval.'
        ];

        header("Location: " . BASE_URL . "/login");
        exit;
    }

    // error handler
    private static function error($message, string $path = '') {

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['notification'] = [
            'type' => 'error',
            'message' => $message
        ];

        header("Location: " . BASE_URL . $path);
        exit;
    }

    private static function resolveHomePath(array $user): string {
        global $pdo;

        if (!rbac_tables_available($pdo)) {
            return BASE_URL . '/ictd/dashboard';
        }

        $roleName = strtolower(trim((string) ($user['role_name'] ?? '')));

        if (in_array($roleName, ['admin', 'super_admin'], true)) {
            return BASE_URL . '/ictd/dashboard';
        }

        if (in_array('ictd.dashboard.view', $_SESSION['permissions'] ?? [], true)) {
            return BASE_URL . '/ictd/dashboard';
        }

        return BASE_URL . '/dashboard';
    }

    // request reset
    public static function resetRequest() {
        global $pdo;

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        ensure_password_reset_schema($pdo);

        $username = trim($_POST['username'] ?? '');

        if (!$username) {
            self::redirectUser('error', 'Username is required.');
        }

        $selectColumns = ['id', 'username'];
        if (db_column_exists($pdo, 'users', 'email')) {
            $selectColumns[] = 'email';
        }
        $stmt = $pdo->prepare("SELECT " . implode(', ', $selectColumns) . " FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            self::redirectUser('error', 'User not found.');
        }

        $user_id = $user['id'];

        $stmt = $pdo->prepare("
            SELECT id FROM password_reset_requests 
            WHERE user_id = ? AND status = 'pending'
        ");
        $stmt->execute([$user_id]);

        if ($stmt->fetch()) {
            self::redirectUser('warning', 'You already have a pending request.');
        }

        $insertColumns = ['user_id', 'status'];
        $insertValues = [$user_id, 'pending'];
        if (db_column_exists($pdo, 'password_reset_requests', 'username')) {
            $insertColumns[] = 'username';
            $insertValues[] = $user['username'] ?? $username;
        }
        if (db_column_exists($pdo, 'password_reset_requests', 'email')) {
            $insertColumns[] = 'email';
            $insertValues[] = $user['email'] ?? '';
        }
        $placeholders = implode(',', array_fill(0, count($insertColumns), '?'));
        $stmt = $pdo->prepare("
            INSERT INTO password_reset_requests (`" . implode('`,`', $insertColumns) . "`)
            VALUES ({$placeholders})
        ");
        $stmt->execute($insertValues);

        log_activity("RESET_REQUEST", "Password reset requested for user ID: $user_id");

        self::redirectUser('success', 'Request submitted. Please wait for admin approval.');
    }

    // change password
    public static function changePassword() {
        global $pdo;

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        require_login();

        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            self::redirectChangePassword('error', 'All password fields are required.');
        }

        if ($newPassword !== $confirmPassword) {
            self::redirectChangePassword('error', 'New password and confirmation do not match.');
        }

        if (strlen($newPassword) < 6) {
            self::redirectChangePassword('error', 'New password must be at least 6 characters.');
        }

        $userId = (int) ($_SESSION['user']['id'] ?? 0);
        if ($userId <= 0) {
            self::redirectChangePassword('error', 'Unable to identify the logged-in user.');
        }

        $stmt = $pdo->prepare("SELECT id, username, password_hash FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
            self::redirectChangePassword('error', 'Current password is incorrect.');
        }

        if (password_verify($newPassword, $user['password_hash'])) {
            self::redirectChangePassword('warning', 'Your new password must be different from your current password.');
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$newHash, $userId]);

        clear_auth_cache();
        $_SESSION['user'] = array_merge($_SESSION['user'] ?? [], [
            'id' => $userId,
            'username' => $user['username'],
        ]);

        log_activity('PASSWORD_CHANGED', "User changed password: {$user['username']}");

        self::redirectChangePassword('success', 'Password changed successfully.');
    }

    public static function updateAccount() {
        global $pdo;

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        require_login();

        $userId = (int) ($_SESSION['user']['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $username = trim((string) ($_POST['username'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));

        if ($userId <= 0) {
            self::redirectAccount('error', 'Unable to identify the logged-in user.');
        }

        if ($username === '') {
            self::redirectAccount('error', 'Username is required.');
        }

        if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)) {
            self::redirectAccount('error', 'Username must be 3-50 characters and may only use letters, numbers, dots, dashes, or underscores.');
        }

        $hasName = db_column_exists($pdo, 'users', 'name');
        $hasEmail = db_column_exists($pdo, 'users', 'email');

        if ($hasName && $name === '') {
            self::redirectAccount('error', 'Full name is required.');
        }

        if ($hasEmail && $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            self::redirectAccount('error', 'Enter a valid email address.');
        }

        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1');
        $stmt->execute([$username, $userId]);
        if ($stmt->fetch()) {
            self::redirectAccount('error', 'Username is already used by another account.');
        }

        if ($hasEmail && $email !== '') {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
            $stmt->execute([$email, $userId]);
            if ($stmt->fetch()) {
                self::redirectAccount('error', 'Email is already used by another account.');
            }
        }

        $updates = ['username = ?'];
        $values = [$username];

        if ($hasName) {
            $updates[] = 'name = ?';
            $values[] = $name;
        }

        if ($hasEmail) {
            $updates[] = 'email = ?';
            $values[] = $email;
        }

        $values[] = $userId;
        $stmt = $pdo->prepare('UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?');
        $stmt->execute($values);

        $_SESSION['user'] = array_merge($_SESSION['user'] ?? [], [
            'id' => $userId,
            'name' => $hasName ? $name : $username,
            'username' => $username,
        ]);

        log_activity('ACCOUNT_UPDATED', "User updated account details: {$username}");

        self::redirectAccount('success', 'Account details updated successfully.');
    }

    // admin side (reset function)
    public static function handleResetRequest() {
        global $pdo;

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        require_permission('users.edit');
        ensure_password_reset_schema($pdo);

        $requestIdRaw = trim((string) ($_POST['request_id'] ?? ''));
        $action       = trim((string) ($_POST['action'] ?? ''));
        $reason     = $_POST['reason'] ?? '';
        $password   = $_POST['password'] ?? '';

        if ($requestIdRaw === '' || !preg_match('/^\d+$/', $requestIdRaw) || $action === '') {
            self::redirectAdmin('error', 'Invalid request data.');
        }

        $request_id = (int) $requestIdRaw;

        $stmt = $pdo->prepare("
            SELECT * FROM password_reset_requests WHERE id = ?
        ");
        $stmt->execute([$request_id]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$request) {
            self::redirectAdmin('error', 'Request not found.');
        }

        try {

            if ($action === 'approve') {

                $newPassword = trim((string) $password);
                if ($newPassword === '') {
                    $newPassword = '123456';
                }

                if (strlen($newPassword) < 6) {
                    self::redirectAdmin('error', 'Password must be at least 6 characters.');
                }

                $hash = password_hash($newPassword, PASSWORD_DEFAULT);

                $pdo->prepare("
                    UPDATE users SET password_hash = ? WHERE id = ?
                ")->execute([$hash, $request['user_id']]);

                $updates = ['status = ?'];
                $values = ['approved'];
                if (db_column_exists($pdo, 'password_reset_requests', 'approved_by')) {
                    $updates[] = 'approved_by = ?';
                    $values[] = $_SESSION['user']['id'] ?? null;
                }
                if (db_column_exists($pdo, 'password_reset_requests', 'resolved_by')) {
                    $updates[] = 'resolved_by = ?';
                    $values[] = $_SESSION['user']['id'] ?? null;
                }
                if (db_column_exists($pdo, 'password_reset_requests', 'updated_at')) {
                    $updates[] = 'updated_at = NOW()';
                }
                if (db_column_exists($pdo, 'password_reset_requests', 'resolved_at')) {
                    $updates[] = 'resolved_at = NOW()';
                }
                $values[] = $request_id;

                $pdo->prepare("
                    UPDATE password_reset_requests
                    SET " . implode(', ', $updates) . "
                    WHERE id = ?
                ")->execute($values);

                log_activity("RESET_APPROVED", "Approved reset for user ID: {$request['user_id']} | request_id: {$request_id}");

                self::redirectAdmin('success', 'Password reset approved.');

            } elseif ($action === 'reject') {

                $updates = ['status = ?'];
                $values = ['rejected'];
                if (db_column_exists($pdo, 'password_reset_requests', 'reason')) {
                    $updates[] = 'reason = ?';
                    $values[] = $reason;
                }
                if (db_column_exists($pdo, 'password_reset_requests', 'notes')) {
                    $updates[] = 'notes = ?';
                    $values[] = $reason;
                }
                if (db_column_exists($pdo, 'password_reset_requests', 'approved_by')) {
                    $updates[] = 'approved_by = ?';
                    $values[] = $_SESSION['user']['id'] ?? null;
                }
                if (db_column_exists($pdo, 'password_reset_requests', 'resolved_by')) {
                    $updates[] = 'resolved_by = ?';
                    $values[] = $_SESSION['user']['id'] ?? null;
                }
                if (db_column_exists($pdo, 'password_reset_requests', 'updated_at')) {
                    $updates[] = 'updated_at = NOW()';
                }
                if (db_column_exists($pdo, 'password_reset_requests', 'resolved_at')) {
                    $updates[] = 'resolved_at = NOW()';
                }
                $values[] = $request_id;

                $pdo->prepare("
                    UPDATE password_reset_requests
                    SET " . implode(', ', $updates) . "
                    WHERE id = ?
                ")->execute($values);

                log_activity("RESET_REJECTED", "Rejected reset for user ID: {$request['user_id']} | request_id: {$request_id}");

                self::redirectAdmin('warning', 'Request rejected.');

            } else {
                self::redirectAdmin('error', 'Invalid action.');
            }

        } catch (Throwable $e) {
            self::redirectAdmin('error', 'Something went wrong.');
        }
    }

    // user redirect
    private static function redirectUser($type, $message) {

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['notification'] = [
            'type' => $type,
            'message' => $message
        ];

        header("Location: " . BASE_URL . "/reset-request");
        exit;
    }

    private static function redirectChangePassword($type, $message) {

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['notification'] = [
            'type' => $type,
            'message' => $message
        ];

        header("Location: " . BASE_URL . "/change-password");
        exit;
    }

    private static function redirectAccount($type, $message) {

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['notification'] = [
            'type' => $type,
            'message' => $message
        ];

        header("Location: " . BASE_URL . "/account");
        exit;
    }

    // admin redirect
    private static function redirectAdmin($type, $message) {

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['notification'] = [
            'type' => $type,
            'message' => $message
        ];

        $_SESSION['open_tab'] = 'reset';

        header("Location: " . BASE_URL . "/admin");
        exit;
    }
}
