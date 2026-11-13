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
        // 🔎 GET USER (USERNAME ONLY)
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

        // 🔥 CLEAR OLD CACHE
        unset($_SESSION['permissions']);
        unset($_SESSION['user_fresh']);

        // ✅ STORE USER SESSION
        $_SESSION['user'] = [
            'id'        => $user['id'],
            'name'      => $user['name'],
            'username'  => $user['username'],
            'role_id'   => $user['role_id'],
            'role_name' => strtolower(trim($user['role_name']))
        ];

        // 🔥 LOAD RBAC PERMISSIONS
        $_SESSION['permissions'] = self::loadPermissions($user['id']);

        // ============================
        // LOG ACTIVITY
        // ============================
        log_activity("LOGIN_SUCCESS", "User logged in: {$user['username']}");

        session_write_close();

        // ============================
        // REDIRECT BASED ON ROLE (OPTIONAL 🔥)
        // ============================
        if ($_SESSION['user']['role_name'] === 'super_admin') {
            header("Location: " . BASE_URL . "/dashboard");
        } else {
            header("Location: " . BASE_URL . "/dashboard");
        }

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

        $_SESSION = [];
        session_unset();
        session_destroy();

        header("Location: " . BASE_URL);
        exit;
    }

    // ===============================
    // REGISTER
    // ===============================
    public static function register() {
        global $pdo;

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $name     = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        // ============================
        // VALIDATION
        // ============================
        if (!$name || !$username || !$password) {
            self::error('All fields are required.');
        }

        if (strlen($password) < 6) {
            self::error('Password must be at least 6 characters.');
        }

        // ============================
        // CHECK DUPLICATE USERNAME
        // ============================
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);

        if ($stmt->fetch()) {
            self::error('Username already exists.');
        }

        // ============================
        // GET DEFAULT ROLE (STAFF)
        // ============================
        $stmt = $pdo->prepare("SELECT role_id FROM roles WHERE name = 'staff' LIMIT 1");
        $stmt->execute();
        $role = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$role) {
            self::error('Default role (staff) not found. Please contact admin.');
        }

        $role_id = $role['role_id'];

        // ============================
        // HASH PASSWORD
        // ============================
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        // ============================
        // INSERT USER
        // ============================
        $stmt = $pdo->prepare("
            INSERT INTO users (name, username, password_hash, role_id, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$name, $username, $password_hash, $role_id]);

        log_activity("REGISTER", "User registered: $username");

        // ============================
        // OPTIONAL: AUTO LOGIN USER 🔥
        // ============================
        $user_id = $pdo->lastInsertId();

        $stmt = $pdo->prepare("
            SELECT u.*, r.name AS role_name
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE u.id = ?
        ");
        $stmt->execute([$user_id]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // STORE SESSION
        $_SESSION['user'] = [
            'id' => $user['id'],
            'name' => $user['name'],
            'username' => $user['username'],
            'role_id' => $user['role_id'],
            'role_name' => strtolower($user['role_name'])
        ];

        // 🔥 LOAD PERMISSIONS
        $_SESSION['permissions'] = self::loadPermissions($user['role_id']);

        $_SESSION['notification'] = [
            'type' => 'success',
            'message' => 'Registration successful. Welcome!'
        ];

        header("Location: " . BASE_URL . "/dashboard");
        exit;
    }
    // ===============================
    // ERROR HANDLER
    // ===============================
    private static function error($message) {

        $_SESSION['notification'] = [
            'type' => 'error',
            'message' => $message
        ];

        header("Location: " . BASE_URL);
        exit;
    }

     // ===============================
    // REQUEST RESET
    // ===============================
   public static function resetRequest() {
        global $pdo;

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $username = trim($_POST['username'] ?? '');

        if (!$username) {
            self::redirectWithMessage('error', 'Username is required.');
        }

        // 🔎 CHECK USER
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            self::redirectWithMessage('error', 'User not found.');
        }

        $user_id = $user['id'];

        // 🚫 CHECK DUPLICATE REQUEST
        $stmt = $pdo->prepare("
            SELECT id FROM password_reset_requests 
            WHERE user_id = ? AND status = 'pending'
        ");
        $stmt->execute([$user_id]);

        if ($stmt->fetch()) {
            self::redirectWithMessage('warning', 'You already have a pending request.');
        }

        // ✅ INSERT REQUEST
        $stmt = $pdo->prepare("
            INSERT INTO password_reset_requests (user_id, status)
            VALUES (?, 'pending')
        ");
        $stmt->execute([$user_id]);

        log_activity("RESET_REQUEST", "Password reset requested for user ID: $user_id");

        // ✅ SUCCESS REDIRECT
        self::redirectWithMessage('success', 'Request submitted. Please wait for admin approval.');
    }

    public static function handleResetRequest() {
        global $pdo;

        require_permission('users.edit'); // 🔥 RBAC protection

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // 🔒 Validate inputs
        $request_id = $_POST['request_id'] ?? null;
        $action     = $_POST['action'] ?? null;
        $reason     = $_POST['reason'] ?? '';

        if (!$request_id || !$action) {
            self::redirectWithMessage('error', 'Invalid request data.');
        }

        // 🔍 GET REQUEST
        $stmt = $pdo->prepare("
            SELECT * FROM password_reset_requests WHERE id = ?
        ");
        $stmt->execute([$request_id]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$request) {
            self::redirectWithMessage('error', 'Request not found.');
        }

        try {

            if ($action === 'approve') {

                // 🔐 DEFAULT PASSWORD (you can randomize later)
                $defaultPassword = '123456';
                $hash = password_hash($defaultPassword, PASSWORD_DEFAULT);

                // UPDATE USER PASSWORD
                $pdo->prepare("
                    UPDATE users 
                    SET password_hash = ? 
                    WHERE id = ?
                ")->execute([$hash, $request['user_id']]);

                // UPDATE REQUEST STATUS
                $pdo->prepare("
                    UPDATE password_reset_requests
                    SET status = 'approved',
                        approved_by = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ")->execute([$_SESSION['user']['id'], $request_id]);

                // ✅ SUCCESS NOTIFICATION
                self::redirectWithMessage(
                    'success',
                    'Password reset approved. Default password is 123456.'
                );

            } elseif ($action === 'reject') {

                // UPDATE REQUEST STATUS
                $pdo->prepare("
                    UPDATE password_reset_requests
                    SET status = 'rejected',
                        reason = ?,
                        approved_by = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ")->execute([
                    $reason,
                    $_SESSION['user']['id'],
                    $request_id
                ]);

                // ⚠️ REJECT NOTIFICATION
                self::redirectWithMessage(
                    'warning',
                    'Password reset request has been rejected.'
                );

            } else {
                self::redirectWithMessage('error', 'Invalid action.');
            }

        } catch (Exception $e) {

            // ❌ ERROR HANDLING
            self::redirectWithMessage(
                'error',
                'Something went wrong. Please try again.'
            );
        }
    }

        private static function redirectWithMessage($type, $message) {

            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            // 🔔 SweetAlert notification (admin side)
            $_SESSION['notification'] = [
                'type' => $type,
                'message' => $message
            ];

            // 🔥 tell admin page to open reset tab
            $_SESSION['open_tab'] = 'reset';

            // ✅ redirect ONLY to admin
            header("Location: " . BASE_URL . "/admin");
            exit;
        }
}



