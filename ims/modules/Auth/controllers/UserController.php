<?php

require_once ROOT_PATH . '/helpers/functions.php';

class UserController {

    public static function getUserPermissions() {
        global $pdo;
        ensure_permission_catalog($pdo);
        ensure_record_scope_schema($pdo);

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
        $permissions = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        $hasOverride = user_permission_override_enabled($user_id);

        header('Content-Type: application/json');
        log_activity('USER_PERMISSION_VIEW', "Loaded override permissions for user ID: {$user_id}");
        echo json_encode([
            'permissions' => $permissions,
            'has_override' => $hasOverride,
        ]);
        exit;
    }

    public static function assignRolePermissions() {

        global $pdo;
        ensure_permission_catalog($pdo);
        ensure_record_scope_schema($pdo);

        require_permission('users.edit');

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $user_id     = (int) ($_POST['user_id'] ?? 0);
        $role_id     = (int) ($_POST['role_id'] ?? 0);
        $permissions = $_POST['permissions'] ?? [];
        $can_view_all_records = (int) ($_POST['can_view_all_records'] ?? 0) === 1 ? 1 : 0;
        $department_scope_ids = $_POST['department_scope_ids'] ?? [];

        // validation
        if (!$user_id || !$role_id) {
            die("Invalid request.");
        }

        // sanitize permission IDs
        $permissions = array_values(array_unique(array_filter(array_map('intval', $permissions))));
        $department_scope_ids = array_values(array_unique(array_filter(array_map('intval', (array) $department_scope_ids))));

        try {

            // start transaction
            $pdo->beginTransaction();

           
            $stmt = $pdo->prepare("
                UPDATE users SET role_id = ? WHERE id = ?
            ");
            $stmt->execute([$role_id, $user_id]);

            $stmt = $pdo->prepare("UPDATE users SET can_view_all_records = ? WHERE id = ?");
            $stmt->execute([$can_view_all_records, $user_id]);

            $pdo->prepare("DELETE FROM user_department_scopes WHERE user_id = ?")->execute([$user_id]);
            if (!empty($department_scope_ids)) {
                $validDepartmentIds = [];
                $placeholders = implode(',', array_fill(0, count($department_scope_ids), '?'));
                $stmt = $pdo->prepare("SELECT department_id FROM list_department WHERE department_id IN ($placeholders)");
                $stmt->execute($department_scope_ids);
                $validDepartmentIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);

                if (!empty($validDepartmentIds)) {
                    $stmt = $pdo->prepare("INSERT INTO user_department_scopes (user_id, department_id) VALUES (?, ?)");
                    foreach ($validDepartmentIds as $departmentId) {
                        $stmt->execute([$user_id, $departmentId]);
                    }
                }
            }

         
            $pdo->prepare("
                DELETE FROM user_permissions WHERE user_id = ?
            ")->execute([$user_id]);

            $roleNameExpr = db_column_exists($pdo, 'roles', 'slug')
                ? "COALESCE(NULLIF(slug, ''), LOWER(REPLACE(name, ' ', '_')))"
                : "LOWER(REPLACE(name, ' ', '_'))";
            $stmt = $pdo->prepare("
                SELECT {$roleNameExpr} FROM roles WHERE id = ?
            ");
            $stmt->execute([$role_id]);
            $role_name = strtolower(trim((string) $stmt->fetchColumn()));

            if ($role_name === '') {
                throw new PDOException('Selected role was not found.');
            }

           
            if ($role_name === 'super_admin') {

                // skip inserting permissions
                if (db_table_exists($pdo, 'user_permission_overrides')) {
                    $pdo->prepare("DELETE FROM user_permission_overrides WHERE user_id = ?")->execute([$user_id]);
                }
                $pdo->commit();

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

            // admin - auto access all
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

            if (db_table_exists($pdo, 'user_permission_overrides')) {
                $stmt = $pdo->prepare("
                    INSERT INTO user_permission_overrides (user_id, enabled, updated_at)
                    VALUES (?, 1, NOW())
                    ON DUPLICATE KEY UPDATE enabled = VALUES(enabled), updated_at = VALUES(updated_at)
                ");
                $stmt->execute([$user_id]);
            }

           
            $pdo->commit();

           
            clear_auth_cache();

            if ((int) ($_SESSION['user']['id'] ?? 0) === $user_id) {
                $_SESSION['user']['role_id'] = $role_id;
                $_SESSION['user']['role_name'] = $role_name;
            }

            
            log_activity(
                "ROLE_PERMISSION_UPDATE",
                "Updated role, permissions, and record scope for user ID: $user_id"
            );

          
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

