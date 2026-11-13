<?php

// 🔥 AUTO FIND ROOT (NO PATH ERRORS)
$path = __DIR__;
while (!file_exists($path . '/config/database.php')) {
    $path = dirname($path);
}
require_once $path . '/config/database.php';

// 🔒 OPTIONAL: ONLY ALLOW LOCAL ACCESS
// if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1') {
//     die('❌ Access denied');
// }

try {

    // 🔐 START TRANSACTION
    $pdo->beginTransaction();

    /* ==================================================
       1️⃣ CREATE SUPER ADMIN ROLE
    ================================================== */
    $stmt = $pdo->prepare("SELECT id FROM roles WHERE name = 'super_admin' LIMIT 1");
    $stmt->execute();
    $role = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$role) {
        $pdo->prepare("
            INSERT INTO roles (name, description) 
            VALUES ('super_admin', 'Full system access')
        ")->execute();

        $role_id = $pdo->lastInsertId();
    } else {
        $role_id = $role['id'];
    }

    /* ==================================================
       2️⃣ ASSIGN ALL PERMISSIONS
    ================================================== */
    $permissions = $pdo->query("SELECT id FROM permissions")
                       ->fetchAll(PDO::FETCH_COLUMN);

    $stmt = $pdo->prepare("
        INSERT IGNORE INTO role_permissions (role_id, permission_id)
        VALUES (?, ?)
    ");

    foreach ($permissions as $perm_id) {
        $stmt->execute([$role_id, $perm_id]);
    }

    /* ==================================================
       3️⃣ CREATE ADMIN USER (IF NOT EXISTS)
    ================================================== */
    $check = $pdo->prepare("SELECT id FROM users WHERE username = 'admin'");
    $check->execute();

    if (!$check->fetch()) {

        $password = password_hash('admin123', PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("
            INSERT INTO users (name, username, password_hash, role_id)
            VALUES (?, ?, ?, ?)
        ");

        $stmt->execute([
            'System Administrator',
            'admin',
            $password,
            $role_id
        ]);

        echo "✅ Super Admin created successfully!";
    } else {
        echo "⚠️ Admin already exists!";
    }

    // 🔐 COMMIT
    $pdo->commit();

} catch (Exception $e) {

    $pdo->rollBack();
    echo "❌ Error: " . $e->getMessage();
}