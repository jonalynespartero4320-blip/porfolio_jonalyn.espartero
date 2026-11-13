<?php
require_once ROOT_PATH . '/config/database.php';

class RolePermissionController {

    public static function getRolePermissions() {
        global $pdo;

        $role_id = $_POST['role_id'];

        $stmt = $pdo->prepare("SELECT permission_id FROM role_permissions WHERE role_id=?");
        $stmt->execute([$role_id]);

        echo json_encode($stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public static function updateRolePermissions() {
        global $pdo;

        session_start();

        $role_id = $_POST['role_id'];
        $permissions = $_POST['permissions'] ?? [];

        $pdo->beginTransaction();

        try {

            // delete old
            $stmt = $pdo->prepare("DELETE FROM role_permissions WHERE role_id=?");
            $stmt->execute([$role_id]);

            // insert new
            $stmt = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");

            foreach ($permissions as $perm) {
                $stmt->execute([$role_id, $perm]);
            }

            $pdo->commit();

            $_SESSION['notification'] = [
                'type' => 'success',
                'message' => 'Permissions updated successfully.'
            ];

        } catch (Exception $e) {
            $pdo->rollBack();

            $_SESSION['notification'] = [
                'type' => 'error',
                'message' => $e->getMessage()
            ];
        }

        header("Location: " . BASE_URL . "/admin-permissions");
        exit;
    }
}