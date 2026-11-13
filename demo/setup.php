<?php
// CLI only: never expose database setup or reset through a public HTTP endpoint.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/config.php';
$server = demo_pdo();
foreach (['ims', 'bjmp', 'pdad', 'osca', 'rtc'] as $project) {
    $name = demo_database_name($project);
    if (in_array('--reset', $argv, true)) {
        // Names come only from the fixed demo allowlist in config.php.
        $server->exec("DROP DATABASE IF EXISTS `$name`");
    }
    if ($server->query("SHOW DATABASES LIKE '$name'")->fetchColumn()) {
        echo "$name already exists; keeping demo changes.\n";
        continue;
    }
    $server->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    $db = demo_pdo($project);
    if (in_array($project, ['ims', 'bjmp'], true)) {
        $file = $project === 'ims' ? '/ims/ims-cictd-cleaned-connected-rbac.sql' : '/bjmp_vaqs/bjmp_db (22).sql';
        $sql = file_get_contents(dirname(__DIR__) . $file);
        // Import structures only. No existing people, credentials, logs or records.
        preg_match_all('/^CREATE TABLE `[^`]+`\s*\(.*?;\s*$/ms', $sql, $creates);
        $db->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach ($creates[0] as $create) $db->exec($create);
        if ($project === 'bjmp') {
            preg_match_all('/^ALTER TABLE `[^`]+`\s+.*?;/ms', $sql, $alters);
            foreach ($alters[0] as $alter) $db->exec($alter);
        }
        $db->exec('SET FOREIGN_KEY_CHECKS=1');
        // Seed only the shared authentication structures here; catalogs initialize in-app.
        $db->exec("INSERT INTO roles (id, name) VALUES (1, 'super_admin')");
        $columns = $project === 'ims' ? ', email, role' : '';
        $values = $project === 'ims' ? ", 'demo@example.test', 'super_admin'" : '';
        $stmt = $db->prepare("INSERT INTO users (id, name, username, password_hash, role_id$columns) VALUES (1, 'Portfolio Demo', 'demo', ?, 1$values)");
        $stmt->execute([password_hash('Demo2026!', PASSWORD_DEFAULT)]);
    } elseif ($project === 'pdad' || $project === 'osca') {
        $db->exec('CREATE TABLE verification_info (id INT AUTO_INCREMENT PRIMARY KEY, unique_id VARCHAR(7), brgy_code VARCHAR(3), pwd_id VARCHAR(40), senior_citizen_id VARCHAR(15), firstname VARCHAR(100), middlename VARCHAR(100), lastname VARCHAR(100), birthdate DATE, date_issued DATE, sex VARCHAR(10), acc_status VARCHAR(30))');
        $db->exec("INSERT INTO verification_info (unique_id, brgy_code, pwd_id, senior_citizen_id, firstname, middlename, lastname, birthdate, date_issued, sex, acc_status) VALUES ('0000001', '001', 'DEMO-001-0000001', '000001', 'Sample', 'Demo', 'Citizen', '1950-01-15', CURRENT_DATE(), 'F', 'ACTIVE')");
        if ($project === 'pdad') {
            $db->exec('CREATE TABLE brgyList (brgy_code VARCHAR(3) PRIMARY KEY, brgy_name VARCHAR(100))');
            $db->exec("INSERT INTO brgyList VALUES ('001', 'Demo Barangay'), ('002', 'Sample Barangay')");
        }
    } else {
        $db->exec('CREATE TABLE hospital_credentials (id INT PRIMARY KEY, hospital_name VARCHAR(150), password_one VARCHAR(255), password_two VARCHAR(255))');
        $db->exec("INSERT INTO hospital_credentials VALUES (1, 'Demo Hospital', 'Demo2026!', '')");
    }
    echo "$name created with fictional demo data.\n";
}
require __DIR__ . '/seed.php';
