<?php
declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));

require_once ROOT_PATH . '/helpers/functions.php';

$host = '127.0.0.1';
$user = 'root';
$pass = '';
$cleanDb = 'ims_clean_work_9';
$rbacDb = 'ims_clean_work_29';

$pdo = new PDO("mysql:host={$host};dbname={$cleanDb};charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

function run_sql(PDO $pdo, string $sql): void
{
    $sql = trim($sql);
    if ($sql !== '') {
        $pdo->exec($sql);
    }
}

function column_exists_local(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = ?
          AND column_name = ?
    ");
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function add_column_if_missing(PDO $pdo, string $table, string $column, string $definition): void
{
    if (!column_exists_local($pdo, $table, $column)) {
        run_sql($pdo, "ALTER TABLE `{$table}` ADD COLUMN {$definition}");
    }
}

function scalar(PDO $pdo, string $sql): int
{
    return (int) $pdo->query($sql)->fetchColumn();
}

$before = [
    'users' => scalar($pdo, 'SELECT COUNT(*) FROM users'),
    'blank_supply_codes' => scalar($pdo, "SELECT COUNT(*) FROM other_equipment WHERE TRIM(COALESCE(code, '')) = ''"),
    'maintenance_unmatched' => scalar($pdo, "
        SELECT COUNT(*)
        FROM maintenance_reports mr
        LEFT JOIN equipment e ON e.control_no = mr.control_no OR e.serialno = mr.control_no
        LEFT JOIN other_equipment oe ON oe.code = mr.control_no OR oe.control_no = mr.control_no OR oe.serialno = mr.control_no
        WHERE e.id IS NULL AND oe.id IS NULL
    "),
];

run_sql($pdo, 'SET FOREIGN_KEY_CHECKS=0');

// Bring SQL 9 users up to the SQL 29 login/RBAC-compatible shape.
run_sql($pdo, 'DROP TABLE IF EXISTS users');
run_sql($pdo, "CREATE TABLE users LIKE `{$rbacDb}`.users");
run_sql($pdo, "INSERT INTO users SELECT * FROM `{$rbacDb}`.users");

// Seed the current app RBAC tables/catalog from helpers while preserving SQL 29 user accounts.
ensure_permission_catalog($pdo);
run_sql($pdo, "
    UPDATE users u
    JOIN roles r ON r.name = CASE WHEN LOWER(u.role) = 'user' THEN 'staff' ELSE LOWER(u.role) END
    SET u.role_id = r.id
");

// Bring core inventory tables up to columns used by the current modules.
add_column_if_missing($pdo, 'equipment', 'created_by', '`created_by` INT NULL AFTER `department_id`');
add_column_if_missing($pdo, 'equipment', 'updated_at', '`updated_at` DATETIME NULL AFTER `created_by`');
add_column_if_missing($pdo, 'equipment', 'equipment_status_id', '`equipment_status_id` INT NULL AFTER `department_id`');
add_column_if_missing($pdo, 'equipment', 'is_archived', '`is_archived` TINYINT(1) NOT NULL DEFAULT 0 AFTER `equipment_status_id`');

add_column_if_missing($pdo, 'other_equipment', 'assigned_id', '`assigned_id` INT NULL AFTER `department_id`');
add_column_if_missing($pdo, 'other_equipment', 'created_by', '`created_by` INT NULL AFTER `assigned_id`');
add_column_if_missing($pdo, 'other_equipment', 'updated_at', '`updated_at` DATETIME NULL AFTER `created_by`');
add_column_if_missing($pdo, 'other_equipment', 'is_archived', '`is_archived` TINYINT(1) NOT NULL DEFAULT 0 AFTER `assigned_id`');

run_sql($pdo, "
    CREATE TABLE IF NOT EXISTS list_equipment_status (
        id INT AUTO_INCREMENT PRIMARY KEY,
        equipment_status VARCHAR(255) NOT NULL UNIQUE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
foreach (['Working', 'For Repair', 'Condemned', 'Disposed'] as $status) {
    $stmt = $pdo->prepare('INSERT IGNORE INTO list_equipment_status (equipment_status) VALUES (?)');
    $stmt->execute([$status]);
}

// Re-sort SQL 9 inventory using SQL 29's organization style while keeping SQL 9 as
// the only source for the actual equipment/supply records.
$equipmentDescriptions = [
    'ALL IN ONE',
    'DESKTOP COMPUTER',
    'DESKTOP COMPUTER (MINI)',
    'CPU',
    'CPU - ASSEMBLED',
    'HP PAVILION',
    'LAPTOP',
    'MINI CPU',
    'SERVER',
    'SYSTEM UNIT',
    'TABLET',
];
$equipmentList = implode(',', array_map(static fn (string $name): string => $pdo->quote($name), $equipmentDescriptions));

run_sql($pdo, 'DROP TEMPORARY TABLE IF EXISTS move_equipment_to_supplies');
run_sql($pdo, "
    CREATE TEMPORARY TABLE move_equipment_to_supplies AS
    SELECT e.id
    FROM equipment e
    JOIN list_description d ON d.id = e.description_id
    WHERE UPPER(TRIM(d.description_name)) NOT IN ({$equipmentList})
");
run_sql($pdo, "
    INSERT INTO other_equipment
        (code, control_no, old_control_no, description_id, brand_id, serialno, remarks, status, delivered_date, warranty_date,
         delivery_status, end_user_id, department_id, assigned_id, is_archived, created_by, updated_at, created_at)
    SELECT
        '',
        e.control_no,
        e.old_control_no,
        e.description_id,
        e.brand_id,
        e.serialno,
        NULLIF(e.computer_name, ''),
        COALESCE(es.equipment_status, 'Working'),
        e.date_delivered,
        e.warranty_date,
        e.delivery_stat,
        NULL,
        e.department_id,
        e.assigned_id,
        COALESCE(e.is_archived, 0),
        e.created_by,
        e.updated_at,
        e.created_at
    FROM equipment e
    JOIN move_equipment_to_supplies move ON move.id = e.id
    LEFT JOIN list_equipment_status es ON es.id = e.equipment_status_id
");
run_sql($pdo, "
    DELETE e
    FROM equipment e
    JOIN move_equipment_to_supplies move ON move.id = e.id
");

run_sql($pdo, 'DROP TEMPORARY TABLE IF EXISTS move_supplies_to_equipment');
run_sql($pdo, "
    CREATE TEMPORARY TABLE move_supplies_to_equipment AS
    SELECT
        oe.id,
        CASE
            WHEN TRIM(COALESCE(oe.control_no, '')) <> '' THEN TRIM(oe.control_no)
            WHEN TRIM(COALESCE(oe.code, '')) <> '' THEN TRIM(oe.code)
            WHEN TRIM(COALESCE(oe.serialno, '')) <> '' THEN TRIM(oe.serialno)
            ELSE CONCAT('EQ-', LPAD(oe.id, 6, '0'))
        END AS target_control_no
    FROM other_equipment oe
    JOIN list_description d ON d.id = oe.description_id
    WHERE UPPER(TRIM(d.description_name)) IN ({$equipmentList})
");
run_sql($pdo, "
    INSERT INTO equipment
        (control_no, computer_name, description_id, brand_id, serialno, warranty_date, delivery_stat, old_control_no,
         date_delivered, assigned_id, created_at, department_id, equipment_status_id, is_archived, created_by, updated_at)
    SELECT
        ms.target_control_no,
        NULL,
        oe.description_id,
        oe.brand_id,
        oe.serialno,
        oe.warranty_date,
        oe.delivery_status,
        oe.old_control_no,
        oe.delivered_date,
        oe.assigned_id,
        oe.created_at,
        oe.department_id,
        COALESCE(es.id, (SELECT id FROM list_equipment_status WHERE equipment_status = 'Working' LIMIT 1)),
        COALESCE(oe.is_archived, 0),
        oe.created_by,
        oe.updated_at
    FROM other_equipment oe
    JOIN move_supplies_to_equipment ms ON ms.id = oe.id
    LEFT JOIN list_equipment_status es ON UPPER(TRIM(es.equipment_status)) = UPPER(TRIM(oe.status))
    LEFT JOIN equipment existing ON existing.control_no = ms.target_control_no
    WHERE existing.id IS NULL
");
run_sql($pdo, "
    DELETE oe
    FROM other_equipment oe
    JOIN move_supplies_to_equipment ms ON ms.id = oe.id
    JOIN equipment e ON e.control_no = ms.target_control_no
");

// Generate missing supply codes so supplies have stable unique IDs for assignment/report linking.
run_sql($pdo, "
    SET @supply_seq := (
        SELECT COALESCE(MAX(CAST(SUBSTRING(code, 8) AS UNSIGNED)), 0)
        FROM other_equipment
        WHERE code REGEXP '^SUP-[0-9]{2}-[0-9]+$'
    )
");
run_sql($pdo, "
    UPDATE other_equipment oe
    JOIN (
        SELECT id, (@supply_seq := @supply_seq + 1) AS seq
        FROM other_equipment
        WHERE TRIM(COALESCE(code, '')) = ''
        ORDER BY id
    ) x ON x.id = oe.id
    SET oe.code = CONCAT('SUP-25-', LPAD(x.seq, 4, '0'))
");
run_sql($pdo, "
    SET @fix_seq := 0
");
run_sql($pdo, "
    UPDATE other_equipment oe
    JOIN (
        SELECT id, (@fix_seq := @fix_seq + 1) AS seq
        FROM other_equipment
        ORDER BY id
    ) x ON x.id = oe.id
    SET oe.code = CONCAT('SUP-25-', LPAD(x.seq, 4, '0'))
    WHERE oe.code REGEXP '^SUP-25-[0-9]+(\\\\.[0-9]+)?$'
       OR oe.code REGEXP '^SUP-25-[0-9]+\\\\.$'
");

// Repair old-control references in reports to the newest matching active equipment/supply record.
run_sql($pdo, "
    UPDATE maintenance_reports mr
    JOIN (
        SELECT old_control_no, MAX(id) AS equipment_id
        FROM equipment
        WHERE TRIM(COALESCE(old_control_no, '')) <> ''
        GROUP BY old_control_no
    ) map ON map.old_control_no = mr.control_no
    JOIN equipment e ON e.id = map.equipment_id
    SET mr.control_no = e.control_no
");
run_sql($pdo, "
    UPDATE maintenance_reports mr
    JOIN (
        SELECT old_control_no, MAX(id) AS supply_id
        FROM other_equipment
        WHERE TRIM(COALESCE(old_control_no, '')) <> ''
        GROUP BY old_control_no
    ) map ON map.old_control_no = mr.control_no
    JOIN other_equipment oe ON oe.id = map.supply_id
    SET mr.control_no = COALESCE(NULLIF(oe.code, ''), oe.control_no, oe.serialno)
    WHERE NOT EXISTS (
        SELECT 1 FROM equipment e WHERE e.control_no = mr.control_no OR e.serialno = mr.control_no
    )
");
run_sql($pdo, "
    UPDATE service_report sr
    JOIN (
        SELECT old_control_no, MAX(id) AS equipment_id
        FROM equipment
        WHERE TRIM(COALESCE(old_control_no, '')) <> ''
        GROUP BY old_control_no
    ) map ON map.old_control_no = sr.control_no
    JOIN equipment e ON e.id = map.equipment_id
    SET sr.control_no = e.control_no
");
run_sql($pdo, "
    UPDATE service_report_items sri
    JOIN (
        SELECT old_control_no, MAX(id) AS equipment_id
        FROM equipment
        WHERE TRIM(COALESCE(old_control_no, '')) <> ''
        GROUP BY old_control_no
    ) map ON map.old_control_no = sri.control_no
    JOIN equipment e ON e.id = map.equipment_id
    SET sri.control_no = e.control_no
");

// Add explicit report source IDs without removing legacy control_no fields.
add_column_if_missing($pdo, 'maintenance_reports', 'equipment_id', '`equipment_id` INT NULL AFTER `control_no`');
add_column_if_missing($pdo, 'maintenance_reports', 'other_equipment_id', '`other_equipment_id` INT NULL AFTER `equipment_id`');
add_column_if_missing($pdo, 'service_report', 'equipment_id', '`equipment_id` INT NULL AFTER `control_no`');
add_column_if_missing($pdo, 'service_report', 'other_equipment_id', '`other_equipment_id` INT NULL AFTER `equipment_id`');
add_column_if_missing($pdo, 'service_report_items', 'equipment_id', '`equipment_id` INT NULL AFTER `control_no`');
add_column_if_missing($pdo, 'service_report_items', 'other_equipment_id', '`other_equipment_id` INT NULL AFTER `equipment_id`');

run_sql($pdo, 'DROP TEMPORARY TABLE IF EXISTS inventory_source_lookup_raw');
run_sql($pdo, "
    CREATE TEMPORARY TABLE inventory_source_lookup_raw (
        lookup_key VARCHAR(100) NOT NULL,
        item_type VARCHAR(30) NOT NULL,
        source_id INT NOT NULL,
        description_id INT NULL,
        brand_id INT NULL,
        serialno VARCHAR(100) NULL,
        priority INT NOT NULL,
        INDEX idx_lookup_raw_key (lookup_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
foreach ([
    "INSERT INTO inventory_source_lookup_raw
        SELECT TRIM(control_no), 'equipment', id, description_id, brand_id, serialno, 1
        FROM equipment WHERE TRIM(COALESCE(control_no, '')) <> ''",
    "INSERT INTO inventory_source_lookup_raw
        SELECT TRIM(serialno), 'equipment', id, description_id, brand_id, serialno, 1
        FROM equipment WHERE TRIM(COALESCE(serialno, '')) <> ''",
    "INSERT INTO inventory_source_lookup_raw
        SELECT TRIM(old_control_no), 'equipment', id, description_id, brand_id, serialno, 1
        FROM equipment WHERE TRIM(COALESCE(old_control_no, '')) <> ''",
    "INSERT INTO inventory_source_lookup_raw
        SELECT TRIM(code), 'supply', id, description_id, brand_id, serialno, 2
        FROM other_equipment WHERE TRIM(COALESCE(code, '')) <> ''",
    "INSERT INTO inventory_source_lookup_raw
        SELECT TRIM(control_no), 'supply', id, description_id, brand_id, serialno, 2
        FROM other_equipment WHERE TRIM(COALESCE(control_no, '')) <> ''",
    "INSERT INTO inventory_source_lookup_raw
        SELECT TRIM(serialno), 'supply', id, description_id, brand_id, serialno, 2
        FROM other_equipment WHERE TRIM(COALESCE(serialno, '')) <> ''",
    "INSERT INTO inventory_source_lookup_raw
        SELECT TRIM(old_control_no), 'supply', id, description_id, brand_id, serialno, 2
        FROM other_equipment WHERE TRIM(COALESCE(old_control_no, '')) <> ''",
] as $lookupInsertSql) {
    run_sql($pdo, $lookupInsertSql);
}
run_sql($pdo, 'DROP TEMPORARY TABLE IF EXISTS inventory_source_lookup_ranked');
run_sql($pdo, "
    CREATE TEMPORARY TABLE inventory_source_lookup_ranked AS
    SELECT lookup_key, MIN((priority * 10000000) - source_id) AS selected_rank
    FROM inventory_source_lookup_raw
    GROUP BY lookup_key
");
run_sql($pdo, 'DROP TEMPORARY TABLE IF EXISTS inventory_source_lookup');
run_sql($pdo, "
    CREATE TEMPORARY TABLE inventory_source_lookup AS
    SELECT raw.*
    FROM inventory_source_lookup_raw raw
    JOIN inventory_source_lookup_ranked ranked
      ON ranked.lookup_key = raw.lookup_key
     AND ranked.selected_rank = (raw.priority * 10000000) - raw.source_id
");
run_sql($pdo, 'ALTER TABLE inventory_source_lookup ADD INDEX idx_lookup_key (lookup_key)');

run_sql($pdo, "
    UPDATE maintenance_reports mr
    LEFT JOIN inventory_source_lookup src ON src.lookup_key = TRIM(mr.control_no)
    SET mr.equipment_id = CASE WHEN src.item_type = 'equipment' THEN src.source_id ELSE NULL END,
        mr.other_equipment_id = CASE WHEN src.item_type = 'supply' THEN src.source_id ELSE NULL END
");
run_sql($pdo, "
    UPDATE service_report sr
    LEFT JOIN inventory_source_lookup src ON src.lookup_key = COALESCE(NULLIF(TRIM(sr.control_no), ''), NULLIF(TRIM(sr.serialno), ''))
    SET sr.equipment_id = CASE WHEN src.item_type = 'equipment' THEN src.source_id ELSE NULL END,
        sr.other_equipment_id = CASE WHEN src.item_type = 'supply' THEN src.source_id ELSE NULL END,
        sr.serialno = COALESCE(NULLIF(sr.serialno, ''), src.serialno),
        sr.description_id = COALESCE(sr.description_id, src.description_id),
        sr.brand_id = COALESCE(sr.brand_id, src.brand_id)
");
run_sql($pdo, "
    UPDATE service_report_items sri
    LEFT JOIN inventory_source_lookup src ON src.lookup_key = COALESCE(NULLIF(TRIM(sri.control_no), ''), NULLIF(TRIM(sri.serialno), ''))
    SET sri.equipment_id = CASE WHEN src.item_type = 'equipment' THEN src.source_id ELSE NULL END,
        sri.other_equipment_id = CASE WHEN src.item_type = 'supply' THEN src.source_id ELSE NULL END,
        sri.serialno = COALESCE(NULLIF(sri.serialno, ''), src.serialno),
        sri.description_id = COALESCE(sri.description_id, src.description_id),
        sri.brand_id = COALESCE(sri.brand_id, src.brand_id)
");
run_sql($pdo, "
    UPDATE service_report sr
    JOIN (
        SELECT service_report_id,
               MAX(equipment_id) AS equipment_id,
               MAX(other_equipment_id) AS other_equipment_id,
               MAX(NULLIF(control_no, '')) AS control_no,
               MAX(NULLIF(serialno, '')) AS serialno,
               MAX(description_id) AS description_id,
               MAX(brand_id) AS brand_id
        FROM service_report_items
        WHERE equipment_id IS NOT NULL OR other_equipment_id IS NOT NULL
        GROUP BY service_report_id
    ) linked ON linked.service_report_id = sr.id
    SET sr.equipment_id = COALESCE(sr.equipment_id, linked.equipment_id),
        sr.other_equipment_id = CASE WHEN sr.equipment_id IS NULL THEN COALESCE(sr.other_equipment_id, linked.other_equipment_id) ELSE sr.other_equipment_id END,
        sr.control_no = COALESCE(NULLIF(sr.control_no, ''), linked.control_no),
        sr.serialno = COALESCE(NULLIF(sr.serialno, ''), linked.serialno),
        sr.description_id = COALESCE(sr.description_id, linked.description_id),
        sr.brand_id = COALESCE(sr.brand_id, linked.brand_id)
    WHERE sr.equipment_id IS NULL AND sr.other_equipment_id IS NULL
");

// Convert legacy assigned_equipment into the assignment tables used by the current Assign module.
run_sql($pdo, "
    UPDATE assigned_equipment ar
    JOIN equipment e ON e.control_no = ar.control_no OR e.serialno = ar.control_no
    SET ar.equipment_id = e.id
    WHERE ar.equipment_id IS NULL
       OR ar.equipment_id = 0
       OR NOT EXISTS (SELECT 1 FROM equipment ex WHERE ex.id = ar.equipment_id)
");
run_sql($pdo, 'DROP TABLE IF EXISTS assigned_record_items');
run_sql($pdo, 'DROP TABLE IF EXISTS assigned_record');
run_sql($pdo, 'CREATE TABLE assigned_record LIKE assigned_equipment');
add_column_if_missing($pdo, 'assigned_record', 'attachment_pdf', '`attachment_pdf` VARCHAR(255) NULL AFTER `created_by`');
run_sql($pdo, "
    INSERT INTO assigned_record
        (assigned_id, end_user_id, department_id, equipment_id, control_no, connection_ip, internet, wifi, program_installed, assigned_date, months_since_assigned, equipment_analysis, created_at, created_by)
    SELECT assigned_id, end_user_id, department_id, equipment_id, control_no, connection_ip, internet, wifi, program_installed, assigned_date, months_since_assigned, equipment_analysis, created_at, created_by
    FROM assigned_equipment
");
run_sql($pdo, "
    CREATE TABLE assigned_record_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        assigned_id INT NOT NULL,
        item_type VARCHAR(30) NOT NULL,
        source_id INT NULL,
        control_no VARCHAR(100) NULL,
        serialno VARCHAR(100) NULL,
        item_status VARCHAR(100) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ari_assigned (assigned_id),
        INDEX idx_ari_source (item_type, source_id),
        INDEX idx_ari_control (control_no)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
run_sql($pdo, "
    INSERT INTO assigned_record_items (assigned_id, item_type, source_id, control_no, serialno, item_status)
    SELECT
        ar.assigned_id,
        CASE WHEN e.id IS NOT NULL THEN 'equipment' ELSE 'supply' END AS item_type,
        COALESCE(e.id, oe.id) AS source_id,
        COALESCE(e.control_no, oe.code, oe.control_no, ar.control_no) AS control_no,
        COALESCE(e.serialno, oe.serialno) AS serialno,
        COALESCE(es.equipment_status, oe.status) AS item_status
    FROM assigned_record ar
    LEFT JOIN equipment e ON e.id = COALESCE(
        (SELECT ex.id FROM equipment ex WHERE ex.id = ar.equipment_id LIMIT 1),
        (SELECT em.id FROM equipment em WHERE em.control_no = ar.control_no OR em.serialno = ar.control_no ORDER BY em.id DESC LIMIT 1)
    )
    LEFT JOIN list_equipment_status es ON es.id = e.equipment_status_id
    LEFT JOIN other_equipment oe ON e.id IS NULL AND oe.id = (
        SELECT ox.id
        FROM other_equipment ox
        WHERE ox.code = ar.control_no OR ox.control_no = ar.control_no OR ox.serialno = ar.control_no
        ORDER BY ox.id DESC
        LIMIT 1
    )
    WHERE e.id IS NOT NULL OR oe.id IS NOT NULL
");
run_sql($pdo, "
    UPDATE equipment e
    JOIN assigned_record_items ari ON ari.item_type = 'equipment' AND ari.source_id = e.id
    JOIN assigned_record ar ON ar.assigned_id = ari.assigned_id
    SET e.assigned_id = ar.assigned_id,
        e.department_id = ar.department_id
");
run_sql($pdo, "
    UPDATE other_equipment oe
    JOIN assigned_record_items ari ON ari.item_type = 'supply' AND ari.source_id = oe.id
    JOIN assigned_record ar ON ar.assigned_id = ari.assigned_id
    SET oe.assigned_id = ar.assigned_id,
        oe.end_user_id = ar.end_user_id,
        oe.department_id = ar.department_id
");

// Keep transmittal links visible from equipment records.
run_sql($pdo, "
    UPDATE equipment e
    JOIN transmittal_equipment te ON te.equipment_id = e.id
    SET e.transmittal_id = te.transmittal_id
");

// Helpful indexes for lookup-heavy joins. Ignore duplicates on old dumps with partial indexes.
foreach ([
    'ALTER TABLE equipment ADD INDEX idx_equipment_control (control_no)',
    'ALTER TABLE equipment ADD INDEX idx_equipment_serial (serialno)',
    'ALTER TABLE equipment ADD INDEX idx_equipment_old_control (old_control_no)',
    'ALTER TABLE other_equipment ADD INDEX idx_other_code (code)',
    'ALTER TABLE other_equipment ADD INDEX idx_other_control (control_no)',
    'ALTER TABLE other_equipment ADD INDEX idx_other_serial (serialno)',
    'ALTER TABLE maintenance_reports ADD INDEX idx_maintenance_source (equipment_id, other_equipment_id)',
    'ALTER TABLE service_report ADD INDEX idx_service_source (equipment_id, other_equipment_id)',
] as $indexSql) {
    try {
        run_sql($pdo, $indexSql);
    } catch (Throwable $ignored) {
        // Existing dumps may already have some keys.
    }
}

run_sql($pdo, 'SET FOREIGN_KEY_CHECKS=1');

$after = [
    'users' => scalar($pdo, 'SELECT COUNT(*) FROM users'),
    'permissions' => scalar($pdo, 'SELECT COUNT(*) FROM permissions'),
    'role_permissions' => scalar($pdo, 'SELECT COUNT(*) FROM role_permissions'),
    'blank_supply_codes' => scalar($pdo, "SELECT COUNT(*) FROM other_equipment WHERE TRIM(COALESCE(code, '')) = ''"),
    'assigned_record' => scalar($pdo, 'SELECT COUNT(*) FROM assigned_record'),
    'assigned_record_items' => scalar($pdo, 'SELECT COUNT(*) FROM assigned_record_items'),
    'maintenance_unmatched' => scalar($pdo, "
        SELECT COUNT(*)
        FROM maintenance_reports mr
        LEFT JOIN equipment e ON e.id = mr.equipment_id
        LEFT JOIN other_equipment oe ON oe.id = mr.other_equipment_id
        WHERE e.id IS NULL AND oe.id IS NULL
    "),
    'service_unmatched' => scalar($pdo, "
        SELECT COUNT(*)
        FROM service_report sr
        LEFT JOIN equipment e ON e.id = sr.equipment_id
        LEFT JOIN other_equipment oe ON oe.id = sr.other_equipment_id
        WHERE e.id IS NULL AND oe.id IS NULL
    "),
];

echo json_encode(['before' => $before, 'after' => $after], JSON_PRETTY_PRINT) . PHP_EOL;
