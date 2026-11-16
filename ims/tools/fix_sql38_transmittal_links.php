<?php
declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/helpers/functions.php';

$pdo = new PDO('mysql:host=127.0.0.1;dbname=ims_clean_work_9;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

function exec_sql(PDO $pdo, string $sql): void
{
    $pdo->exec($sql);
}

function add_col(PDO $pdo, string $table, string $column, string $definition): void
{
    if (!db_column_exists($pdo, $table, $column)) {
        exec_sql($pdo, "ALTER TABLE `{$table}` ADD COLUMN {$definition}");
    }
}

exec_sql($pdo, 'SET FOREIGN_KEY_CHECKS=0');

add_col($pdo, 'transmittal_items', 'transmittal_pk', '`transmittal_pk` INT NULL AFTER `transmittal_id`');
add_col($pdo, 'transmittal_items', 'item_type', "`item_type` VARCHAR(30) NOT NULL DEFAULT 'equipment' AFTER `transmittal_pk`");
add_col($pdo, 'transmittal_items', 'source_id', '`source_id` INT NULL AFTER `item_type`');
add_col($pdo, 'transmittal_items', 'control_no', '`control_no` VARCHAR(100) NULL AFTER `source_id`');
add_col($pdo, 'transmittal_items', 'old_control_no', '`old_control_no` VARCHAR(100) NULL AFTER `control_no`');
add_col($pdo, 'transmittal_items', 'description_id', '`description_id` INT NULL AFTER `old_control_no`');
add_col($pdo, 'transmittal_items', 'brand_id', '`brand_id` INT NULL AFTER `description_id`');
add_col($pdo, 'transmittal_items', 'remarks', '`remarks` TEXT NULL AFTER `serialno`');
add_col($pdo, 'other_equipment', 'transmittal_id', '`transmittal_id` VARCHAR(100) NULL AFTER `assigned_id`');

exec_sql($pdo, "
    UPDATE transmittal_items ti
    JOIN transmittal t ON t.transmittal_id = ti.transmittal_id
    SET ti.transmittal_pk = t.id
    WHERE ti.transmittal_pk IS NULL
");

exec_sql($pdo, "
    UPDATE transmittal_items ti
    JOIN equipment e ON e.id = ti.equipment_id
    LEFT JOIN list_description d ON d.description_name = ti.description
    LEFT JOIN list_brand b ON b.brand_name = ti.brand
    SET ti.item_type = 'equipment',
        ti.source_id = e.id,
        ti.control_no = COALESCE(NULLIF(ti.control_no, ''), e.control_no),
        ti.old_control_no = COALESCE(NULLIF(ti.old_control_no, ''), e.old_control_no),
        ti.description_id = COALESCE(ti.description_id, e.description_id, d.id),
        ti.brand_id = COALESCE(ti.brand_id, e.brand_id, b.id),
        ti.serialno = COALESCE(NULLIF(ti.serialno, ''), e.serialno)
    WHERE ti.source_id IS NULL OR ti.item_type = 'equipment'
");

exec_sql($pdo, "
    UPDATE transmittal_items ti
    JOIN equipment e ON ti.source_id IS NULL
        AND TRIM(COALESCE(ti.serialno, '')) <> ''
        AND ti.serialno NOT LIKE '%,%'
        AND e.serialno = TRIM(ti.serialno)
    LEFT JOIN list_description d ON d.description_name = ti.description
    LEFT JOIN list_brand b ON b.brand_name = ti.brand
    SET ti.item_type = 'equipment',
        ti.source_id = e.id,
        ti.control_no = COALESCE(NULLIF(ti.control_no, ''), e.control_no),
        ti.old_control_no = COALESCE(NULLIF(ti.old_control_no, ''), e.old_control_no),
        ti.description_id = COALESCE(ti.description_id, e.description_id, d.id),
        ti.brand_id = COALESCE(ti.brand_id, e.brand_id, b.id),
        ti.serialno = COALESCE(NULLIF(ti.serialno, ''), e.serialno)
");

exec_sql($pdo, "
    UPDATE transmittal_items ti
    JOIN other_equipment oe ON ti.source_id IS NULL
        AND TRIM(COALESCE(ti.serialno, '')) <> ''
        AND oe.serialno = ti.serialno
    LEFT JOIN list_description d ON d.description_name = ti.description
    LEFT JOIN list_brand b ON b.brand_name = ti.brand
    SET ti.item_type = 'supply',
        ti.source_id = oe.id,
        ti.control_no = COALESCE(NULLIF(ti.control_no, ''), NULLIF(oe.code, ''), oe.control_no),
        ti.old_control_no = COALESCE(NULLIF(ti.old_control_no, ''), oe.old_control_no),
        ti.description_id = COALESCE(ti.description_id, oe.description_id, d.id),
        ti.brand_id = COALESCE(ti.brand_id, oe.brand_id, b.id)
");

exec_sql($pdo, "
    UPDATE transmittal_items ti
    JOIN other_equipment oe ON ti.source_id IS NULL
        AND TRIM(COALESCE(ti.description, '')) <> ''
        AND TRIM(COALESCE(ti.serialno, '')) = ''
        AND oe.id = (
            SELECT ox.id
            FROM other_equipment ox
            LEFT JOIN list_description dx ON dx.id = ox.description_id
            LEFT JOIN list_brand bx ON bx.id = ox.brand_id
            WHERE UPPER(TRIM(COALESCE(dx.description_name, ''))) = UPPER(TRIM(ti.description))
              AND (
                  TRIM(COALESCE(ti.brand, '')) = ''
                  OR UPPER(TRIM(COALESCE(bx.brand_name, ''))) = UPPER(TRIM(ti.brand))
              )
            ORDER BY ox.id DESC
            LIMIT 1
        )
    LEFT JOIN list_description d ON d.description_name = ti.description
    LEFT JOIN list_brand b ON b.brand_name = ti.brand
    SET ti.item_type = 'supply',
        ti.source_id = oe.id,
        ti.control_no = COALESCE(NULLIF(ti.control_no, ''), NULLIF(oe.code, ''), oe.control_no),
        ti.old_control_no = COALESCE(NULLIF(ti.old_control_no, ''), oe.old_control_no),
        ti.description_id = COALESCE(ti.description_id, oe.description_id, d.id),
        ti.brand_id = COALESCE(ti.brand_id, oe.brand_id, b.id)
");

exec_sql($pdo, "
    INSERT IGNORE INTO transmittal_equipment (transmittal_id, equipment_id)
    SELECT ti.transmittal_id, ti.source_id
    FROM transmittal_items ti
    WHERE ti.item_type = 'equipment' AND ti.source_id IS NOT NULL
");

exec_sql($pdo, "
    UPDATE transmittal_items ti
    SET ti.item_type = 'supply'
    WHERE ti.source_id IS NULL
      AND (
          UPPER(TRIM(COALESCE(ti.description, ''))) REGEXP 'SCANNER|ID[[:space:]-]*CARD|PRINTER|RIBBON|MOUSE|MONITOR|UPS|PROJECTOR|TELEVISION|CAMERA|KEYBOARD|EXTERNAL[[:space:]-]*DRIVE|POWER[[:space:]-]*CABLE|ADAPTOR'
          OR UPPER(TRIM(COALESCE(ti.brand, ''))) REGEXP 'PVC|RIBBON|UPS'
      )
");

exec_sql($pdo, "
    UPDATE equipment e
    JOIN transmittal_items ti ON ti.item_type = 'equipment' AND ti.source_id = e.id
    SET e.transmittal_id = ti.transmittal_id
");

exec_sql($pdo, "
    UPDATE other_equipment oe
    JOIN transmittal_items ti ON ti.item_type = 'supply' AND ti.source_id = oe.id
    SET oe.transmittal_id = ti.transmittal_id
");

foreach ([
    'ALTER TABLE transmittal_items ADD INDEX idx_transmittal_items_report (transmittal_pk)',
    'ALTER TABLE transmittal_items ADD INDEX idx_transmittal_items_source (item_type, source_id)',
    'ALTER TABLE other_equipment ADD INDEX idx_other_transmittal (transmittal_id)',
] as $sql) {
    try {
        exec_sql($pdo, $sql);
    } catch (Throwable $ignored) {
    }
}

exec_sql($pdo, 'SET FOREIGN_KEY_CHECKS=1');

$summary = [
    'transmittal_items' => (int) $pdo->query('SELECT COUNT(*) FROM transmittal_items')->fetchColumn(),
    'transmittal_items_equipment' => (int) $pdo->query("SELECT COUNT(*) FROM transmittal_items WHERE item_type = 'equipment'")->fetchColumn(),
    'transmittal_items_supplies' => (int) $pdo->query("SELECT COUNT(*) FROM transmittal_items WHERE item_type = 'supply'")->fetchColumn(),
    'transmittal_items_missing_source' => (int) $pdo->query('SELECT COUNT(*) FROM transmittal_items WHERE source_id IS NULL')->fetchColumn(),
    'supplies_with_transmittal' => (int) $pdo->query("SELECT COUNT(*) FROM other_equipment WHERE TRIM(COALESCE(transmittal_id, '')) <> ''")->fetchColumn(),
];

echo json_encode($summary, JSON_PRETTY_PRINT) . PHP_EOL;
