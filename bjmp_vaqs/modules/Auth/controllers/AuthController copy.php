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

        if (!$username || !$password) {
            self::error('Username and password are required.');
        }

        // 🔎 GET USER WITH ROLE
        $stmt = $pdo->prepare("
            SELECT u.*, r.name AS role_name
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE u.username = ?
            LIMIT 1
        ");
        $stmt->execute([$username]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            log_activity("LOGIN_FAILED", "Failed login: $username");
            self::error('Invalid username or password.');
        }

        // ✅ SUCCESS LOGIN
        session_regenerate_id(true);

        // 🔥 CLEAR OLD CACHE
        unset($_SESSION['role_permissions']);
        unset($_SESSION['user_fresh']);

        // ✅ STORE FULL USER
        $_SESSION['user'] = [
            'id' => $user['id'],
            'name' => $user['name'],
            'username' => $user['username'],
            'role_id' => $user['role_id'],
            'role_name' => strtolower(trim($user['role_name'])) // normalize
        ];

        log_activity("LOGIN_SUCCESS", "User logged in: {$user['username']}");

        session_write_close();

        header("Location: " . BASE_URL . "/dashboard");
        exit;
    }


    // ===============================
    // LOGOUT
    // ===============================
    public static function logout() {

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // 🔥 CLEAR EVERYTHING
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

    public static function handleResetRequest() {
        global $pdo;

        require_permission('users.edit');

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $request_id = $_POST['request_id'] ?? null;
        $action     = $_POST['action'] ?? null;
        $reason     = $_POST['reason'] ?? '';
        $password   = $_POST['password'] ?? '';

        if (!$request_id || !$action) {
            die('Invalid request.');
        }

        // 🔎 GET REQUEST
        $stmt = $pdo->prepare("
            SELECT * FROM password_reset_requests WHERE id = ?
        ");
        $stmt->execute([$request_id]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$request) {
            die('Request not found.');
        }

        if ($action === 'approve') {

            if (!$password) {
                die('Password is required.');
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);

            // ✅ UPDATE USER PASSWORD
            $pdo->prepare("
                UPDATE users SET password_hash = ? WHERE id = ?
            ")->execute([$hash, $request['user_id']]);

            // ✅ UPDATE REQUEST
            $pdo->prepare("
                UPDATE password_reset_requests
                SET status='approved', approved_by=?, updated_at=NOW()
                WHERE id=?
            ")->execute([$_SESSION['user']['id'], $request_id]);

            log_activity("RESET_APPROVED", "Approved reset for user ID: ".$request['user_id']);

        } else {

            if (!$reason) {
                die('Rejection reason is required.');
            }

            // ❌ REJECT
            $pdo->prepare("
                UPDATE password_reset_requests
                SET status='rejected', reason=?, approved_by=?, updated_at=NOW()
                WHERE id=?
            ")->execute([$reason, $_SESSION['user']['id'], $request_id]);

            log_activity("RESET_REJECTED", "Rejected reset request ID: ".$request_id);
        }

        header("Location: " . BASE_URL . "/admin");
        exit;
    }
}