<?php

require_once ROOT_PATH . '/helpers/functions.php';

class UserController {

    public static function getUserPermissions() {
        global $pdo;
        ensure_permission_catalog($pdo);

        require_permission('users.view');

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $user_id = (int) ($_POST['user_id'] ?? 0);

        if ($user_id <= 0) {
            http_response_code(422);
            echo json_encode([]);
            exit;
        }

        $stmt = $pdo->prepare("
            SELECT permission_id
            FROM user_permissions
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);

        header('Content-Type: application/json');
        log_activity('USER_PERMISSION_VIEW', "Loaded override permissions for user ID: {$user_id}");
        echo json_encode(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
        exit;
    }

    public static function assignRolePermissions() {

        global $pdo;
        ensure_permission_catalog($pdo);

        if (!is_super_admin()) {
            http_response_code(403);
            die('Only the super admin can approve roles and permissions.');
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $user_id     = (int) ($_POST['user_id'] ?? 0);
        $role_id     = (int) ($_POST['role_id'] ?? 0);
        $permissions = $_POST['permissions'] ?? [];

        // ===============================
        // VALIDATION
        // ===============================
        if (!$user_id || !$role_id) {
            die("Invalid request.");
        }

        // sanitize permission IDs
        $permissions = array_values(array_unique(array_filter(array_map('intval', $permissions))));
        $beforeUser = fetch_table_record($pdo, 'users', $user_id);
        $beforePermissionIds = [];
        if ($user_id > 0) {
            $stmt = $pdo->prepare("SELECT permission_id FROM user_permissions WHERE user_id = ? ORDER BY permission_id ASC");
            $stmt->execute([$user_id]);
            $beforePermissionIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        }

        try {

            // ===============================
            // START TRANSACTION
            // ===============================
            $pdo->beginTransaction();

            // ===============================
            // UPDATE USER ROLE
            // ===============================
            $stmt = $pdo->prepare("
                UPDATE users SET role_id = ? WHERE id = ?
            ");
            $stmt->execute([$role_id, $user_id]);

            // ===============================
            // CLEAR USER PERMISSIONS
            // ===============================
            $pdo->prepare("
                DELETE FROM user_permissions WHERE user_id = ?
            ")->execute([$user_id]);

            // ===============================
            // GET ROLE NAME
            // ===============================
            $stmt = $pdo->prepare("
                SELECT name FROM roles WHERE id = ?
            ");
            $stmt->execute([$role_id]);
            $role_name = strtolower(trim((string) $stmt->fetchColumn()));

            if ($role_name === '') {
                throw new PDOException('Selected role was not found.');
            }

            if ($role_name === 'pending') {
                $permissions = [];
            }

            // ===============================
            // SUPER ADMIN → NO NEED TO STORE
            // ===============================
            if ($role_name === 'super_admin') {

                // skip inserting permissions
                $pdo->commit();

                archive_record_change('users', $user_id, 'updated', [
                    'user' => $beforeUser,
                    'permission_ids' => $beforePermissionIds,
                ], [
                    'user' => fetch_table_record($pdo, 'users', $user_id),
                    'permission_ids' => [],
                ], "Assigned Super Admin role to user ID {$user_id}");

                clear_auth_cache();

                if ((int) ($_SESSION['user']['id'] ?? 0) === $user_id) {
                    $_SESSION['user']['role_id'] = $role_id;
                    $_SESSION['user']['role_name'] = $role_name;
                }

                $_SESSION['notification'] = [
                    'type' => 'success',
                    'message' => 'Role and permissions updated successfully!'
                ];

                log_activity("ROLE_UPDATE", "Super Admin assigned to user ID: $user_id");

                header("Location: " . BASE_URL . "/admin");
                exit;
            }

            // ===============================
            // ADMIN → AUTO ALL PAGE ACCESS
            // ===============================
            if ($role_name === 'admin') {

                $stmt = $pdo->query("
                    SELECT id FROM permissions WHERE name LIKE '%.view'
                ");

                $page_permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);

                $permissions = array_unique(array_merge($permissions, $page_permissions));
            }

            if (!empty($permissions)) {
                $placeholders = implode(',', array_fill(0, count($permissions), '?'));
                $stmt = $pdo->prepare("
                    SELECT id
                    FROM permissions
                    WHERE id IN ($placeholders)
                ");
                $stmt->execute($permissions);
                $permissions = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
            }

            // ===============================
            // INSERT PERMISSIONS (NO DUPLICATES)
            // ===============================
            if (!empty($permissions)) {

                $stmt = $pdo->prepare("
                    INSERT INTO user_permissions (user_id, permission_id)
                    VALUES (?, ?)
                ");

                foreach ($permissions as $perm_id) {

                    // skip invalid IDs
                    if (!$perm_id) continue;

                    $stmt->execute([$user_id, $perm_id]);
                }
            }

            // ===============================
            // COMMIT
            // ===============================
            $pdo->commit();

            // ===============================
            // CLEAR SESSION CACHE (IMPORTANT)
            // ===============================
            clear_auth_cache();

            if ((int) ($_SESSION['user']['id'] ?? 0) === $user_id) {
                $_SESSION['user']['role_id'] = $role_id;
                $_SESSION['user']['role_name'] = $role_name;
            }

            // ===============================
            // LOG
            // ===============================
            $afterUser = fetch_table_record($pdo, 'users', $user_id);
            $stmt = $pdo->prepare("SELECT permission_id FROM user_permissions WHERE user_id = ? ORDER BY permission_id ASC");
            $stmt->execute([$user_id]);
            $afterPermissionIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

            archive_record_change('users', $user_id, 'updated', [
                'user' => $beforeUser,
                'permission_ids' => $beforePermissionIds,
            ], [
                'user' => $afterUser,
                'permission_ids' => $afterPermissionIds,
            ], "Updated user role and permissions for user ID {$user_id}");

            log_activity(
                "ROLE_PERMISSION_UPDATE",
                "Updated role & permissions for user ID: $user_id"
            );

            // ===============================
            // SUCCESS MESSAGE
            // ===============================
            $_SESSION['notification'] = [
                'type' => 'success',
                'message' => 'Role and permissions updated successfully!'
            ];

            header("Location: " . BASE_URL . "/admin");
            exit;

        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $_SESSION['notification'] = [
                'type' => 'error',
                'message' => $e->getMessage()
            ];

            header("Location: " . BASE_URL . "/admin");
            exit;
        }
    }
}

