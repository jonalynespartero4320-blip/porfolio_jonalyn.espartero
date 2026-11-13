<?php
declare(strict_types=1);

$database = $argv[1] ?? 'ims_clean_work_9';

define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/helpers/functions.php';

$pdo = new PDO("mysql:host=127.0.0.1;dbname={$database};charset=utf8mb4", 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

function q(PDO $pdo, string $sql): void
{
    $pdo->exec($sql);
}

q($pdo, 'SET FOREIGN_KEY_CHECKS=0');

q($pdo, 'DROP TEMPORARY TABLE IF EXISTS assign_user_canonical');
q($pdo, "
    CREATE TEMPORARY TABLE assign_user_canonical AS
    SELECT end_user_id, MIN(assigned_id) AS canonical_assigned_id
    FROM assigned_record
    WHERE end_user_id IS NOT NULL AND end_user_id > 0
    GROUP BY end_user_id
");
q($pdo, 'ALTER TABLE assign_user_canonical ADD PRIMARY KEY (end_user_id)');

q($pdo, 'DROP TEMPORARY TABLE IF EXISTS assign_id_map');
q($pdo, "
    CREATE TEMPORARY TABLE assign_id_map AS
    SELECT ar.assigned_id AS old_assigned_id, auc.canonical_assigned_id
    FROM assigned_record ar
    JOIN assign_user_canonical auc ON auc.end_user_id = ar.end_user_id
");
q($pdo, 'ALTER TABLE assign_id_map ADD PRIMARY KEY (old_assigned_id)');
q($pdo, 'ALTER TABLE assign_id_map ADD INDEX idx_assign_map_canonical (canonical_assigned_id)');

q($pdo, "
    UPDATE assigned_record_items ari
    JOIN assign_id_map map ON map.old_assigned_id = ari.assigned_id
    SET ari.assigned_id = map.canonical_assigned_id
    WHERE ari.assigned_id <> map.canonical_assigned_id
");

q($pdo, 'DROP TEMPORARY TABLE IF EXISTS assign_item_keep');
q($pdo, "
    CREATE TEMPORARY TABLE assign_item_keep AS
    SELECT
        assigned_id,
        item_type,
        COALESCE(source_id, 0) AS source_key,
        COALESCE(NULLIF(TRIM(control_no), ''), '') AS control_key,
        MIN(id) AS keep_id
    FROM assigned_record_items
    GROUP BY assigned_id, item_type, COALESCE(source_id, 0), COALESCE(NULLIF(TRIM(control_no), ''), '')
");
q($pdo, 'ALTER TABLE assign_item_keep ADD INDEX idx_assign_item_keep (keep_id)');
q($pdo, "
    DELETE ari
    FROM assigned_record_items ari
    LEFT JOIN assign_item_keep keep_map ON keep_map.keep_id = ari.id
    WHERE keep_map.keep_id IS NULL
");

q($pdo, "
    UPDATE equipment e
    JOIN assign_id_map map ON map.old_assigned_id = e.assigned_id
    SET e.assigned_id = map.canonical_assigned_id
");
q($pdo, "
    UPDATE other_equipment oe
    JOIN assign_id_map map ON map.old_assigned_id = oe.assigned_id
    SET oe.assigned_id = map.canonical_assigned_id
");

if (db_table_exists($pdo, 'assigned_user_history') && db_column_exists($pdo, 'assigned_user_history', 'assigned_id')) {
    q($pdo, "
        UPDATE assigned_user_history auh
        JOIN assign_id_map map ON map.old_assigned_id = auh.assigned_id
        SET auh.assigned_id = map.canonical_assigned_id
    ");
}

q($pdo, "
    UPDATE assigned_record ar
    JOIN (
        SELECT
            ar.end_user_id,
            MAX(ar.department_id) AS department_id,
            MIN(NULLIF(ar.connection_ip, '')) AS connection_ip,
            MAX(ar.internet) AS internet,
            MAX(ar.wifi) AS wifi,
            MAX(NULLIF(ar.program_installed, '')) AS program_installed,
            MIN(NULLIF(ar.assigned_date, '')) AS assigned_date,
            MAX(NULLIF(ar.months_since_assigned, '')) AS months_since_assigned,
            MAX(NULLIF(ar.equipment_analysis, '')) AS equipment_analysis,
            MIN(ar.created_at) AS created_at,
            MIN(ar.created_by) AS created_by
        FROM assigned_record ar
        WHERE ar.end_user_id IS NOT NULL AND ar.end_user_id > 0
        GROUP BY ar.end_user_id
    ) merged ON merged.end_user_id = ar.end_user_id
    JOIN assign_user_canonical auc ON auc.canonical_assigned_id = ar.assigned_id
    SET ar.department_id = COALESCE(merged.department_id, ar.department_id),
        ar.connection_ip = COALESCE(merged.connection_ip, ar.connection_ip),
        ar.internet = COALESCE(merged.internet, ar.internet),
        ar.wifi = COALESCE(merged.wifi, ar.wifi),
        ar.program_installed = COALESCE(merged.program_installed, ar.program_installed),
        ar.assigned_date = COALESCE(merged.assigned_date, ar.assigned_date),
        ar.months_since_assigned = COALESCE(merged.months_since_assigned, ar.months_since_assigned),
        ar.equipment_analysis = COALESCE(merged.equipment_analysis, ar.equipment_analysis),
        ar.created_at = COALESCE(merged.created_at, ar.created_at),
        ar.created_by = COALESCE(merged.created_by, ar.created_by)
");

q($pdo, 'DROP TEMPORARY TABLE IF EXISTS assign_primary_item');
q($pdo, "
    CREATE TEMPORARY TABLE assign_primary_item AS
    SELECT ari.assigned_id, MIN(ari.id) AS item_id
    FROM assigned_record_items ari
    JOIN (
        SELECT assigned_id, MIN(CASE WHEN item_type = 'equipment' THEN 1 ELSE 2 END) AS type_rank
        FROM assigned_record_items
        GROUP BY assigned_id
    ) ranked ON ranked.assigned_id = ari.assigned_id
       AND ranked.type_rank = CASE WHEN ari.item_type = 'equipment' THEN 1 ELSE 2 END
    GROUP BY ari.assigned_id
");
q($pdo, 'ALTER TABLE assign_primary_item ADD PRIMARY KEY (assigned_id)');
q($pdo, "
    UPDATE assigned_record ar
    LEFT JOIN assign_primary_item primary_map ON primary_map.assigned_id = ar.assigned_id
    LEFT JOIN assigned_record_items ari ON ari.id = primary_map.item_id
    SET ar.equipment_id = CASE WHEN ari.item_type = 'equipment' THEN ari.source_id ELSE NULL END,
        ar.control_no = COALESCE(NULLIF(ari.control_no, ''), ar.control_no)
");

q($pdo, "
    DELETE ar
    FROM assigned_record ar
    JOIN assign_id_map map ON map.old_assigned_id = ar.assigned_id
    WHERE ar.assigned_id <> map.canonical_assigned_id
");

foreach ([
    'ALTER TABLE assigned_record ADD UNIQUE KEY uq_assigned_record_end_user (end_user_id)',
    'ALTER TABLE assigned_record_items ADD UNIQUE KEY uq_assigned_item_source (assigned_id, item_type, source_id)',
] as $sql) {
    try {
        q($pdo, $sql);
    } catch (Throwable $ignored) {
    }
}

q($pdo, 'SET FOREIGN_KEY_CHECKS=1');

$summary = [
    'assigned_record' => (int) $pdo->query('SELECT COUNT(*) FROM assigned_record')->fetchColumn(),
    'assigned_users' => (int) $pdo->query('SELECT COUNT(DISTINCT end_user_id) FROM assigned_record WHERE end_user_id IS NOT NULL AND end_user_id > 0')->fetchColumn(),
    'duplicate_user_headers' => (int) $pdo->query('
        SELECT COUNT(*)
        FROM (
            SELECT end_user_id
            FROM assigned_record
            WHERE end_user_id IS NOT NULL AND end_user_id > 0
            GROUP BY end_user_id
            HAVING COUNT(*) > 1
        ) duplicates
    ')->fetchColumn(),
    'assigned_record_items' => (int) $pdo->query('SELECT COUNT(*) FROM assigned_record_items')->fetchColumn(),
    'broken_item_links' => (int) $pdo->query("
        SELECT COUNT(*)
        FROM assigned_record_items ari
        LEFT JOIN equipment e ON ari.item_type = 'equipment' AND e.id = ari.source_id
        LEFT JOIN other_equipment oe ON ari.item_type = 'supply' AND oe.id = ari.source_id
        WHERE (ari.item_type = 'equipment' AND e.id IS NULL)
           OR (ari.item_type = 'supply' AND oe.id IS NULL)
    ")->fetchColumn(),
];

echo json_encode($summary, JSON_PRETTY_PRINT) . PHP_EOL;
