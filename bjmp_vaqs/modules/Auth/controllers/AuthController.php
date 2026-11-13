<?php

class AuthController {

    // ===============================
    // LOGIN
    // ===============================
    public static function login() {
        global $pdo;

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        // ============================
        // VALIDATION
        // ============================
        if (!$username || !$password) {
            self::error('Username and password are required.');
        }

        // ============================
        // 🔎 GET USER
        // ============================
        $stmt = $pdo->prepare("
            SELECT u.*, r.name AS role_name
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE u.username = ?
            LIMIT 1
        ");
        $stmt->execute([$username]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // ============================
        // ❌ INVALID LOGIN
        // ============================
        if (!$user || !password_verify($password, $user['password_hash'])) {
            log_activity("LOGIN_FAILED", "Failed login: $username");
            self::error('Invalid username or password.');
        }

        // ============================
        // ✅ SUCCESS LOGIN
        // ============================
        session_regenerate_id(true);

        clear_auth_cache();
        unset($_SESSION['notification'], $_SESSION['error']);

        $_SESSION['user'] = [
            'id'        => $user['id'],
            'name'      => $user['name'],
            'username'  => $user['username'],
            'role_id'   => $user['role_id'],
            'role_name' => strtolower(trim($user['role_name']))
        ];

        // 🔥 FIX: use role_id (not user id)
        $_SESSION['permissions'] = self::loadPermissions($user['role_id']);

        log_activity("LOGIN_SUCCESS", "User logged in: {$user['username']}");

        session_write_close();

        header("Location: " . BASE_URL . "/dashboard");
        exit;
    }

    // ===============================
    // LOAD PERMISSIONS
    // ===============================
    private static function loadPermissions($role_id) {
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

    // ===============================
    // LOGOUT
    // ===============================
    public static function logout() {

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!empty($_SESSION['user']['username'])) {
            log_activity("LOGOUT", "User logged out: {$_SESSION['user']['username']}");
        }

        $_SESSION = [];
        session_unset();

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();

        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');

        header("Location: " . BASE_URL);
        exit;
    }

    // ===============================
    // REGISTER
    // ===============================
    public static function register() {
        global $pdo;
        ensure_permission_catalog($pdo);

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $name     = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!$name || !$username || !$password) {
            self::error('All fields are required.');
        }

        if (strlen($password) < 6) {
            self::error('Password must be at least 6 characters.');
        }

        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);

        if ($stmt->fetch()) {
            self::error('Username already exists.');
        }

        // 🔥 FIXED ROLE QUERY
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        try {
            $pdo->beginTransaction();

            $role_id = self::ensurePendingRole();

            $stmt = $pdo->prepare("
                INSERT INTO users (name, username, password_hash, role_id, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$name, $username, $password_hash, $role_id]);

            $user_id = (int) $pdo->lastInsertId();
            if ($user_id <= 0) {
                throw new RuntimeException('Unable to create the user account.');
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            self::error('Registration could not be completed. Please try again.');
        }

        log_activity("REGISTER", "New account registered and awaiting super admin role approval: $username");

        $_SESSION['notification'] = [
            'type' => 'success',
            'message' => 'Registration submitted successfully. Please wait for the super admin to approve your role and module access.'
        ];

        header("Location: " . BASE_URL . "/login");
        exit;
    }

    private static function ensurePendingRole(): int {
        global $pdo;

        $stmt = $pdo->prepare("SELECT id FROM roles WHERE LOWER(name) = 'pending' LIMIT 1");
        $stmt->execute();
        $roleId = (int) ($stmt->fetchColumn() ?: 0);

        if ($roleId > 0) {
            return $roleId;
        }

        $stmt = $pdo->prepare("INSERT INTO roles (name) VALUES ('pending')");
        $stmt->execute();

        return (int) $pdo->lastInsertId();
    }

    // ===============================
    // ERROR HANDLER
    // ===============================
    private static function error($message) {

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['notification'] = [
            'type' => 'error',
            'message' => $message
        ];

        header("Location: " . BASE_URL);
        exit;
    }

    // ===============================
    // REQUEST RESET (USER SIDE)
    // ===============================
    public static function resetRequest() {
        global $pdo;

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $username = trim($_POST['username'] ?? '');

        if (!$username) {
            self::redirectUser('error', 'Username is required.');
        }

        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
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

        $stmt = $pdo->prepare("
            INSERT INTO password_reset_requests (user_id, status)
            VALUES (?, 'pending')
        ");
        $stmt->execute([$user_id]);

        log_activity("RESET_REQUEST", "Password reset requested for user ID: $user_id");

        self::redirectUser('success', 'Request submitted. Please wait for admin approval.');
    }

    // ===============================
    // CHANGE PASSWORD
    // ===============================
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

    // ===============================
    // HANDLE RESET (ADMIN SIDE)
    // ===============================
    public static function handleResetRequest() {
        global $pdo;

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        require_permission('users.edit');

        $request_id = $_POST['request_id'] ?? null;
        $action     = $_POST['action'] ?? null;
        $reason     = $_POST['reason'] ?? '';
        $password   = $_POST['password'] ?? '';

        if (!$request_id || !$action) {
            self::redirectAdmin('error', 'Invalid request data.');
        }

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

                $pdo->prepare("
                    UPDATE password_reset_requests
                    SET status = 'approved',
                        approved_by = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ")->execute([$_SESSION['user']['id'], $request_id]);

                log_activity("RESET_APPROVED", "Approved reset for user ID: {$request['user_id']} | request_id: {$request_id}");

                self::redirectAdmin('success', 'Password reset approved.');

            } elseif ($action === 'reject') {

                $pdo->prepare("
                    UPDATE password_reset_requests
                    SET status = 'rejected',
                        reason = ?,
                        approved_by = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ")->execute([$reason, $_SESSION['user']['id'], $request_id]);

                log_activity("RESET_REJECTED", "Rejected reset for user ID: {$request['user_id']} | request_id: {$request_id}");

                self::redirectAdmin('warning', 'Request rejected.');

            } else {
                self::redirectAdmin('error', 'Invalid action.');
            }

        } catch (Exception $e) {
            self::redirectAdmin('error', 'Something went wrong.');
        }
    }

    // ===============================
    // USER REDIRECT
    // ===============================
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

    // ===============================
    // ADMIN REDIRECT
    // ===============================
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
