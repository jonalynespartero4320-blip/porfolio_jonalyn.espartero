<?php
require_once 'config/database.php';

try {
    $pdo->beginTransaction();

    // ==============================
    // 1. CREATE OR GET ROLE
    // ==============================
    $roleName = 'super_admin';

    $stmt = $pdo->prepare("SELECT id FROM roles WHERE name = ?");
    $stmt->execute([$roleName]);
    $role = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$role) {
        $pdo->prepare("INSERT INTO roles (name) VALUES (?)")->execute([$roleName]);

        // safer way (no assumption of id column name)
        $stmt = $pdo->prepare("SELECT id FROM roles WHERE name = ?");
        $stmt->execute([$roleName]);
        $role = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    $role_id = $role['id'];

    // ==============================
    // 2. ASSIGN ALL PERMISSIONS
    // ==============================
    $permissions = $pdo->query("SELECT id FROM permissions")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($permissions as $perm) {
        $permission_id = $perm['id'];

        $check = $pdo->prepare("
            SELECT 1 FROM role_permissions 
            WHERE role_id = ? AND permission_id = ?
        ");
        $check->execute([$role_id, $permission_id]);

        if (!$check->fetch()) {
            $insert = $pdo->prepare("
                INSERT INTO role_permissions (role_id, permission_id)
                VALUES (?, ?)
            ");
            $insert->execute([$role_id, $permission_id]);
        }
    }

    // ==============================
    // 3. CREATE SUPER ADMIN USER
    // ==============================
    $username = 'superadmin';
    $password = password_hash('admin123', PASSWORD_BCRYPT);

    $checkUser = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $checkUser->execute([$username]);

    if (!$checkUser->fetch()) {
        $stmt = $pdo->prepare("
            INSERT INTO users (username, password_hash, role_id)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$username, $password, $role_id]);

        echo "✅ Super Admin created successfully!<br>";
        echo "Username: superadmin<br>Password: admin123";
    } else {
        echo "⚠️ Super Admin already exists.";
    }

    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();
    die("❌ Error: " . $e->getMessage());
}