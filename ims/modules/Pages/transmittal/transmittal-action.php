<?php
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_login();
header('Content-Type: application/json');

function tr_response(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function tr_payload(): array
{
    $raw = file_get_contents('php://input');
    $json = json_decode($raw ?: '', true);
    return is_array($json) ? $json : $_POST;
}

function tr_clean_string($value): ?string
{
    $value = trim((string) ($value ?? ''));
    return $value === '' ? null : $value;
}

function tr_clean_int($value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }
    return is_numeric($value) ? (int) $value : null;
}

function tr_item_source_id(array $item): ?int
{
    $sourceId = tr_clean_int($item['source_id'] ?? null);
    if ($sourceId) {
        return $sourceId;
    }

    $rawId = trim((string) ($item['id'] ?? ''));
    if (preg_match('/^(equipment|supply):(\d+)$/i', $rawId, $match)) {
        return (int) $match[2];
    }

    return tr_clean_int($rawId);
}

function tr_item_type(array $item): string
{
    $type = strtolower(tr_clean_string($item['item_type'] ?? '') ?: '');
    if (in_array($type, ['equipment', 'supply', 'manual'], true)) {
        return $type;
    }

    $rawId = trim((string) ($item['id'] ?? ''));
    if (preg_match('/^(equipment|supply):\d+$/i', $rawId, $match)) {
        return strtolower($match[1]);
    }

    return 'equipment';
}

function tr_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ");
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function tr_ensure_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS transmittal (
            id INT AUTO_INCREMENT PRIMARY KEY,
            transmittal_id VARCHAR(15) NULL,
            department_id INT NULL,
            accountable_officer VARCHAR(255) NULL,
            attachment_pdf VARCHAR(255) NULL,
            transmittal_date DATE NULL,
            received_by VARCHAR(255) NULL,
            date_received DATE NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    foreach ([
        'created_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
        'updated_at' => 'DATETIME NULL',
        'created_by' => 'INT NULL',
        'designation_id' => 'INT NULL AFTER department_id',
        'is_archived' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'archived_at' => 'DATETIME NULL',
    ] as $column => $definition) {
        if (!tr_column_exists($pdo, 'transmittal', $column)) {
            $pdo->exec("ALTER TABLE transmittal ADD COLUMN {$column} {$definition}");
        }
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS transmittal_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            transmittal_id VARCHAR(100) NULL,
            transmittal_pk INT NULL,
            item_type VARCHAR(30) NOT NULL DEFAULT 'equipment',
            source_id INT NULL,
            equipment_id INT NULL,
            control_no VARCHAR(100) NULL,
            old_control_no VARCHAR(100) NULL,
            description TEXT NULL,
            brand VARCHAR(255) NULL,
            qty INT NULL DEFAULT 1,
            description_id INT NULL,
            brand_id INT NULL,
            serialno VARCHAR(100) NULL,
            remarks TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_transmittal_items_report (transmittal_pk),
            INDEX idx_transmittal_items_legacy_report (transmittal_id),
            INDEX idx_transmittal_items_source (item_type, source_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    foreach ([
        'transmittal_pk' => 'INT NULL AFTER transmittal_id',
        'item_type' => "VARCHAR(30) NOT NULL DEFAULT 'equipment' AFTER transmittal_pk",
        'source_id' => 'INT NULL AFTER item_type',
        'equipment_id' => 'INT NULL AFTER source_id',
        'control_no' => 'VARCHAR(100) NULL AFTER source_id',
        'old_control_no' => 'VARCHAR(100) NULL AFTER control_no',
        'description' => 'TEXT NULL AFTER old_control_no',
        'brand' => 'VARCHAR(255) NULL AFTER description',
        'qty' => 'INT NULL DEFAULT 1 AFTER brand',
        'description_id' => 'INT NULL AFTER qty',
        'brand_id' => 'INT NULL AFTER description_id',
        'remarks' => 'TEXT NULL AFTER serialno',
        'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
    ] as $column => $definition) {
        if (!tr_column_exists($pdo, 'transmittal_items', $column)) {
            $pdo->exec("ALTER TABLE transmittal_items ADD COLUMN {$column} {$definition}");
        }
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS transmittal_equipment (
            id INT AUTO_INCREMENT PRIMARY KEY,
            transmittal_id VARCHAR(100) NOT NULL,
            equipment_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_transmittal_equipment_id (transmittal_id),
            INDEX idx_transmittal_equipment_equipment (equipment_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function tr_generate_id(PDO $pdo, ?string $date, ?int $ignoreId = null): string
{
    $year = $date ? date('Y', strtotime($date)) : date('Y');
    $prefix = 'TRN-' . $year . '-';
    $sql = 'SELECT transmittal_id FROM transmittal WHERE transmittal_id LIKE ?';
    $params = [$prefix . '%'];
    if ($ignoreId) {
        $sql .= ' AND id <> ?';
        $params[] = $ignoreId;
    }
    $sql .= ' ORDER BY transmittal_id DESC LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $last = (string) ($stmt->fetchColumn() ?: '');
    $next = 1;
    if (preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', $last, $match)) {
        $next = ((int) $match[1]) + 1;
    }
    return $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
}

function tr_store_pdf(PDO $pdo, int $id, array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('PDF upload failed.');
    }
    if (($file['size'] ?? 0) > 1024 * 1024) {
        throw new RuntimeException('PDF must not exceed 1MB.');
    }
    if (strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION)) !== 'pdf') {
        throw new RuntimeException('Only PDF files are allowed.');
    }

    $stmt = $pdo->prepare('SELECT attachment_pdf FROM transmittal WHERE id = ?');
    $stmt->execute([$id]);
    $oldPath = (string) ($stmt->fetchColumn() ?: '');
    if ($oldPath !== '') {
        $absoluteOldPath = ROOT_PATH . '/public/' . ltrim($oldPath, '/');
        if (is_file($absoluteOldPath)) {
            unlink($absoluteOldPath);
        }
    }

    $dirRel = 'demo-uploads/transmittal/';
    $dirAbs = ROOT_PATH . '/public/' . $dirRel;
    if (!is_dir($dirAbs)) {
        mkdir($dirAbs, 0755, true);
    }
    $filename = 'TR_' . $id . '_' . time() . '.pdf';
    if (!move_uploaded_file($file['tmp_name'], $dirAbs . $filename)) {
        throw new RuntimeException('Failed to save uploaded PDF.');
    }
    return $dirRel . $filename;
}

function tr_masterlists(PDO $pdo): array
{
    $departmentSql = 'SELECT department_id AS id, department_name AS text, department_head FROM list_department';
    if (!user_can_view_all_records()) {
        $departmentIds = user_department_scope_ids();
        if (empty($departmentIds)) {
            $departments = [];
        } else {
            $placeholders = implode(',', array_fill(0, count($departmentIds), '?'));
            $stmt = $pdo->prepare($departmentSql . " WHERE department_id IN ({$placeholders}) ORDER BY department_name ASC");
            $stmt->execute($departmentIds);
            $departments = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
    } else {
        $departments = $pdo->query($departmentSql . ' ORDER BY department_name ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    $users = $pdo->query("
        SELECT eu.end_user_id AS id, eu.end_user_name AS text, eu.department_id, dep.department_name
        FROM list_end_user eu
        LEFT JOIN list_department dep ON dep.department_id = eu.department_id
        ORDER BY eu.end_user_name ASC
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $equipment = [];
    $supplies = [];
    return compact('departments', 'users', 'equipment', 'supplies');
}

function tr_scope_where(PDO $pdo, string $table, string $alias, ?int $departmentId): ?string
{
    $where = [];

    if ($departmentId && db_column_exists($pdo, $table, 'department_id')) {
        $where[] = "({$alias}.department_id = {$departmentId} OR {$alias}.department_id IS NULL OR {$alias}.department_id = 0)";
    }

    $canViewAll = user_can_view_all_records();
    $departmentIds = $canViewAll ? [] : user_department_scope_ids();

    if ($departmentId && !$canViewAll && !in_array($departmentId, $departmentIds, true)) {
        return '1 = 0';
    }

    if ($canViewAll) {
        return $where ? '(' . implode(' AND ', $where) . ')' : null;
    }

    $scopeParts = [];
    $userId = record_scope_current_user_id();
    if ($userId > 0 && db_column_exists($pdo, $table, 'created_by')) {
        $scopeParts[] = "{$alias}.created_by = {$userId}";
    }

    if (db_column_exists($pdo, $table, 'department_id') && !empty($departmentIds)) {
        $scopeParts[] = "{$alias}.department_id IN (" . implode(',', array_map('intval', $departmentIds)) . ')';
    }

    if (empty($scopeParts)) {
        return '1 = 0';
    }

    $where[] = '(' . implode(' OR ', $scopeParts) . ')';
    return '(' . implode(' AND ', $where) . ')';
}

function tr_available_items(PDO $pdo, ?int $departmentId, ?string $currentTransmittalId = null): array
{
    $currentTransmittalId = trim((string) ($currentTransmittalId ?? ''));
    $equipment = [];
    if (db_table_exists($pdo, 'equipment')) {
        $equipmentWhere = [];
        $equipmentParams = [];
        $scopeWhere = tr_scope_where($pdo, 'equipment', 'e', $departmentId);
        if ($scopeWhere) {
            $equipmentWhere[] = $scopeWhere;
        }
        if (db_column_exists($pdo, 'equipment', 'assigned_id')) {
            $equipmentWhere[] = '(e.assigned_id IS NULL OR e.assigned_id = 0)';
        }
        if (db_column_exists($pdo, 'equipment', 'transmittal_id')) {
            if ($currentTransmittalId !== '') {
                $equipmentWhere[] = "(e.transmittal_id IS NULL OR e.transmittal_id = '' OR e.transmittal_id = ? )";
                $equipmentParams[] = $currentTransmittalId;
            } else {
                $equipmentWhere[] = "(e.transmittal_id IS NULL OR e.transmittal_id = '')";
            }
        }
        if (db_column_exists($pdo, 'equipment', 'is_archived')) {
            $equipmentWhere[] = '(e.is_archived IS NULL OR e.is_archived = 0)';
        }
        if (db_table_exists($pdo, 'assigned_record')) {
            $equipmentWhere[] = "NOT EXISTS (
                SELECT 1
                FROM assigned_record ar
                WHERE (ar.equipment_id = e.id OR (e.control_no IS NOT NULL AND ar.control_no = e.control_no))
                LIMIT 1
            )";
        }
        if (db_table_exists($pdo, 'transmittal_equipment')) {
            $equipmentWhere[] = "NOT EXISTS (
                SELECT 1
                FROM transmittal_equipment te
                WHERE te.equipment_id = e.id
                  AND te.transmittal_id <> ?
                LIMIT 1
            )";
            $equipmentParams[] = $currentTransmittalId;
        }
        if (db_table_exists($pdo, 'transmittal_items')) {
            $equipmentWhere[] = "NOT EXISTS (
                SELECT 1
                FROM transmittal_items ti
                WHERE ti.item_type = 'equipment' AND ti.source_id = e.id
                  AND ti.transmittal_id <> ?
                LIMIT 1
            )";
            $equipmentParams[] = $currentTransmittalId;
        }
        $equipmentWhereSql = $equipmentWhere ? 'WHERE ' . implode(' AND ', $equipmentWhere) : '';
        $stmt = $pdo->prepare("
            SELECT
                CONCAT('equipment:', e.id) AS id,
                e.id AS source_id,
                'equipment' AS item_type,
                e.control_no,
                e.old_control_no,
                e.description_id,
                d.description_name,
                e.brand_id,
                b.brand_name,
                e.serialno,
                CONCAT(COALESCE(e.control_no, 'No Control'), ' | Serial: ', COALESCE(e.serialno, 'No Serial'), ' | ', COALESCE(d.description_name, 'Equipment')) AS text
            FROM equipment e
            LEFT JOIN list_description d ON d.id = e.description_id
            LEFT JOIN list_brand b ON b.id = e.brand_id
            {$equipmentWhereSql}
            ORDER BY e.control_no ASC
        ");
        $stmt->execute($equipmentParams);
        $equipment = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    $supplies = [];
    if (db_table_exists($pdo, 'other_equipment')) {
        $supplyWhere = [];
        $supplyParams = [];
        $scopeWhere = tr_scope_where($pdo, 'other_equipment', 'oe', $departmentId);
        if ($scopeWhere) {
            $supplyWhere[] = $scopeWhere;
        }
        if (db_column_exists($pdo, 'other_equipment', 'end_user_id')) {
            $supplyWhere[] = '(oe.end_user_id IS NULL OR oe.end_user_id = 0)';
        }
        if (db_column_exists($pdo, 'other_equipment', 'assigned_id')) {
            $supplyWhere[] = '(oe.assigned_id IS NULL OR oe.assigned_id = 0)';
        }
        if (db_column_exists($pdo, 'other_equipment', 'transmittal_id')) {
            if ($currentTransmittalId !== '') {
                $supplyWhere[] = "(oe.transmittal_id IS NULL OR oe.transmittal_id = '' OR oe.transmittal_id = ? )";
                $supplyParams[] = $currentTransmittalId;
            } else {
                $supplyWhere[] = "(oe.transmittal_id IS NULL OR oe.transmittal_id = '')";
            }
        }
        if (db_column_exists($pdo, 'other_equipment', 'is_archived')) {
            $supplyWhere[] = '(oe.is_archived IS NULL OR oe.is_archived = 0)';
        }
        if (db_table_exists($pdo, 'assigned_record')) {
            $supplyWhere[] = "NOT EXISTS (
                SELECT 1
                FROM assigned_record ar
                WHERE ar.assigned_id = oe.assigned_id
                   OR (COALESCE(oe.code, oe.control_no, oe.serialno) IS NOT NULL AND ar.control_no = COALESCE(oe.code, oe.control_no, oe.serialno))
                LIMIT 1
            )";
        }
        if (db_table_exists($pdo, 'transmittal_items')) {
            $supplyWhere[] = "NOT EXISTS (
                SELECT 1
                FROM transmittal_items ti
                WHERE ti.item_type = 'supply' AND ti.source_id = oe.id
                  AND ti.transmittal_id <> ?
                LIMIT 1
            )";
            $supplyParams[] = $currentTransmittalId;
        }
        $supplyWhereSql = $supplyWhere ? 'WHERE ' . implode(' AND ', $supplyWhere) : '';
        $stmt = $pdo->prepare("
            SELECT
                CONCAT('supply:', oe.id) AS id,
                oe.id AS source_id,
                'supply' AS item_type,
                COALESCE(oe.code, oe.control_no, oe.serialno) AS control_no,
                oe.old_control_no,
                oe.description_id,
                d.description_name,
                oe.brand_id,
                b.brand_name,
                oe.serialno,
                CONCAT('Supply | ', COALESCE(oe.code, oe.control_no, 'No Code'), ' | Serial: ', COALESCE(oe.serialno, 'No Serial')) AS text
            FROM other_equipment oe
            LEFT JOIN list_description d ON d.id = oe.description_id
            LEFT JOIN list_brand b ON b.id = oe.brand_id
            {$supplyWhereSql}
            ORDER BY COALESCE(oe.code, oe.control_no, oe.serialno) ASC
        ");
        $stmt->execute($supplyParams);
        $supplies = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    return compact('equipment', 'supplies');
}

function tr_unique_selected_items(array $items): array
{
    $seen = [];
    $unique = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $type = tr_item_type($item);
        $sourceId = tr_item_source_id($item);
        if (!$sourceId && $type === 'equipment') {
            $hasManualText = tr_clean_string($item['description'] ?? ($item['description_name'] ?? null))
                || tr_clean_string($item['brand'] ?? ($item['brand_name'] ?? null))
                || tr_clean_string($item['serialno'] ?? null)
                || tr_clean_string($item['remarks'] ?? null);
            if ($hasManualText) {
                $type = 'manual';
                $item['item_type'] = 'manual';
            }
        }
        if (in_array($type, ['equipment', 'supply'], true)) {
            if (!$sourceId) {
                continue;
            }
            $key = $type . ':' . $sourceId;
        } else {
            $key = 'manual:' . md5(json_encode([
                tr_clean_string($item['description'] ?? ($item['description_name'] ?? null)),
                tr_clean_string($item['brand'] ?? ($item['brand_name'] ?? null)),
                tr_clean_int($item['qty'] ?? 1) ?: 1,
                tr_clean_string($item['serialno'] ?? null),
                tr_clean_string($item['remarks'] ?? null),
            ]));
        }
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $unique[] = $item;
    }
    return $unique;
}

function tr_sync_items(PDO $pdo, int $id, array $items, array $manualItems, ?string $transmittalId): void
{
    $items = tr_unique_selected_items($items);
    $manualItems = tr_unique_selected_items(array_map(static function ($item) {
        if (is_array($item)) {
            $item['item_type'] = 'manual';
        }
        return $item;
    }, $manualItems));

    $oldEq = $pdo->prepare('SELECT equipment_id FROM transmittal_equipment WHERE transmittal_id = ?');
    $oldEq->execute([$transmittalId]);
    $oldEquipmentIds = $oldEq->fetchAll(PDO::FETCH_COLUMN) ?: [];
    $oldItemEq = $pdo->prepare("SELECT source_id FROM transmittal_items WHERE item_type = 'equipment' AND source_id IS NOT NULL AND (transmittal_pk = ? OR transmittal_id = ?)");
    $oldItemEq->execute([$id, $transmittalId]);
    $oldEquipmentIds = array_unique(array_merge($oldEquipmentIds, $oldItemEq->fetchAll(PDO::FETCH_COLUMN) ?: []));
    foreach ($oldEquipmentIds as $oldEquipmentId) {
        if ($oldEquipmentId) {
            $pdo->prepare('UPDATE equipment SET transmittal_id = NULL WHERE id = ?')->execute([(int) $oldEquipmentId]);
        }
    }
    if (db_table_exists($pdo, 'other_equipment') && db_column_exists($pdo, 'other_equipment', 'transmittal_id')) {
        $oldSupplies = $pdo->prepare("SELECT source_id FROM transmittal_items WHERE item_type = 'supply' AND source_id IS NOT NULL AND (transmittal_pk = ? OR transmittal_id = ?)");
        $oldSupplies->execute([$id, $transmittalId]);
        foreach ($oldSupplies->fetchAll(PDO::FETCH_COLUMN) ?: [] as $oldSupplyId) {
            if ($oldSupplyId) {
                $pdo->prepare('UPDATE other_equipment SET transmittal_id = NULL WHERE id = ?')->execute([(int) $oldSupplyId]);
            }
        }
    }

    $pdo->prepare('DELETE FROM transmittal_equipment WHERE transmittal_id = ?')->execute([$transmittalId]);
    $pdo->prepare('DELETE FROM transmittal_items WHERE transmittal_pk = ? OR transmittal_id = ?')->execute([$id, $transmittalId]);

    if (!empty($items)) {
        $insertEquipment = $pdo->prepare('INSERT INTO transmittal_equipment (transmittal_id, equipment_id) VALUES (?, ?)');
        $insertItem = $pdo->prepare("
            INSERT INTO transmittal_items
                (transmittal_id, transmittal_pk, item_type, source_id, control_no, old_control_no, description_id, brand_id, description, brand, qty, serialno, remarks)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($items as $item) {
            $type = tr_item_type($item);
            $sourceId = tr_item_source_id($item);
            if (!$sourceId) {
                continue;
            }
            if ($type === 'equipment') {
                $insertEquipment->execute([$transmittalId, $sourceId]);
                $pdo->prepare('UPDATE equipment SET transmittal_id = ? WHERE id = ?')->execute([$transmittalId, $sourceId]);
                $insertItem->execute([
                    $transmittalId,
                    $id,
                    'equipment',
                    $sourceId,
                    tr_clean_string($item['control_no'] ?? null),
                    tr_clean_string($item['old_control_no'] ?? null),
                    tr_clean_int($item['description_id'] ?? null),
                    tr_clean_int($item['brand_id'] ?? null),
                    tr_clean_string($item['description_name'] ?? null),
                    tr_clean_string($item['brand_name'] ?? null),
                    1,
                    tr_clean_string($item['serialno'] ?? null),
                    tr_clean_string($item['remarks'] ?? null),
                ]);
                continue;
            }
            if ($type === 'supply') {
                if (db_table_exists($pdo, 'other_equipment') && db_column_exists($pdo, 'other_equipment', 'transmittal_id')) {
                    $pdo->prepare('UPDATE other_equipment SET transmittal_id = ? WHERE id = ?')->execute([$transmittalId, $sourceId]);
                }
                $insertItem->execute([
                    $transmittalId,
                    $id,
                    'supply',
                    $sourceId,
                    tr_clean_string($item['control_no'] ?? null),
                    tr_clean_string($item['old_control_no'] ?? null),
                    tr_clean_int($item['description_id'] ?? null),
                    tr_clean_int($item['brand_id'] ?? null),
                    tr_clean_string($item['description_name'] ?? null),
                    tr_clean_string($item['brand_name'] ?? null),
                    1,
                    tr_clean_string($item['serialno'] ?? null),
                    tr_clean_string($item['remarks'] ?? null),
                ]);
            }
        }
    }

    if (!empty($manualItems)) {
        $insertManual = $pdo->prepare("
            INSERT INTO transmittal_items
                (transmittal_id, transmittal_pk, item_type, description, brand, qty, serialno, remarks)
            VALUES
                (?, ?, 'manual', ?, ?, ?, ?, ?)
        ");
        foreach ($manualItems as $item) {
            $description = tr_clean_string($item['description'] ?? null);
            $brand = tr_clean_string($item['brand'] ?? null);
            $serial = tr_clean_string($item['serialno'] ?? null);
            if (!$description && !$brand && !$serial) {
                continue;
            }
            $insertManual->execute([
                $transmittalId,
                $id,
                $description,
                $brand,
                max(1, tr_clean_int($item['qty'] ?? 1) ?: 1),
                $serial,
                tr_clean_string($item['remarks'] ?? null),
            ]);
        }
    }
}

try {
    tr_ensure_schema($pdo);
    $payload = tr_payload();
    $action = (string) ($_GET['action'] ?? $payload['action'] ?? '');

    if ($action === 'masterlists') {
        tr_response(['success' => true, 'data' => tr_masterlists($pdo)]);
    }

    if ($action === 'next_id') {
        $date = tr_clean_string($_GET['transmittal_date'] ?? $payload['transmittal_date'] ?? null) ?: date('Y-m-d');
        tr_response(['success' => true, 'transmittal_id' => tr_generate_id($pdo, $date)]);
    }

    if ($action === 'department_items') {
        tr_response(['success' => true, 'data' => tr_available_items(
            $pdo,
            tr_clean_int($_GET['department_id'] ?? $payload['department_id'] ?? null),
            tr_clean_string($_GET['transmittal_id'] ?? $payload['transmittal_id'] ?? null)
        )]);
    }

    if ($action === 'get') {
        $id = tr_clean_int($_GET['id'] ?? null);
        $stmt = $pdo->prepare("
            SELECT t.*, dep.department_name, des.department_name AS designation_name
            FROM transmittal t
            LEFT JOIN list_department dep ON dep.department_id = t.department_id
            LEFT JOIN list_department des ON des.department_id = t.designation_id
            WHERE t.id = ?
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            tr_response(['success' => false, 'message' => 'Transmittal not found.'], 404);
        }
        $items = $pdo->prepare("
            SELECT
                COALESCE(NULLIF(ti.item_type, ''), CASE WHEN ti.source_id IS NULL THEN 'manual' ELSE 'equipment' END) AS item_type,
                ti.source_id,
                COALESCE(ti.control_no, e.control_no, oe.code, oe.control_no) AS control_no,
                COALESCE(ti.old_control_no, e.old_control_no, oe.old_control_no) AS old_control_no,
                COALESCE(ti.description_id, e.description_id, oe.description_id) AS description_id,
                COALESCE(d2.description_name, de.description_name, ds.description_name, ti.description) AS description_name,
                COALESCE(ti.brand_id, e.brand_id, oe.brand_id) AS brand_id,
                COALESCE(b2.brand_name, be.brand_name, bs.brand_name, ti.brand) AS brand_name,
                COALESCE(ti.serialno, e.serialno, oe.serialno) AS serialno,
                ti.description,
                ti.brand,
                COALESCE(ti.qty, 1) AS qty,
                ti.remarks
            FROM transmittal_items ti
            LEFT JOIN list_description d2 ON d2.id = ti.description_id
            LEFT JOIN list_brand b2 ON b2.id = ti.brand_id
            LEFT JOIN equipment e ON ti.item_type = 'equipment' AND e.id = ti.source_id
            LEFT JOIN list_description de ON de.id = e.description_id
            LEFT JOIN list_brand be ON be.id = e.brand_id
            LEFT JOIN other_equipment oe ON ti.item_type = 'supply' AND oe.id = ti.source_id
            LEFT JOIN list_description ds ON ds.id = oe.description_id
            LEFT JOIN list_brand bs ON bs.id = oe.brand_id
            WHERE ti.transmittal_pk = ? OR ti.transmittal_id = ?
            ORDER BY FIELD(COALESCE(NULLIF(ti.item_type, ''), 'manual'), 'equipment', 'supply', 'manual'), ti.id ASC
        ");
        $items->execute([$id, $row['transmittal_id']]);
        tr_response(['success' => true, 'data' => $row, 'items' => tr_unique_selected_items($items->fetchAll(PDO::FETCH_ASSOC) ?: [])]);
    }

    if ($action === 'create' || $action === 'update') {
        $id = tr_clean_int($payload['id'] ?? null);
        $date = tr_clean_string($payload['transmittal_date'] ?? null) ?: date('Y-m-d');
        $transmittalId = tr_clean_string($payload['transmittal_id'] ?? null) ?: tr_generate_id($pdo, $date, $id);
        $items = json_decode((string) ($payload['items'] ?? '[]'), true);
        $items = is_array($items) ? $items : [];
        $manualItems = json_decode((string) ($payload['manual_items'] ?? '[]'), true);
        $manualItems = is_array($manualItems) ? $manualItems : [];
        $user = current_user() ?: [];

        $row = [
            'transmittal_id' => $transmittalId,
            'department_id' => tr_clean_int($payload['department_id'] ?? null),
            'designation_id' => tr_clean_int($payload['designation_id'] ?? null),
            'accountable_officer' => tr_clean_string($payload['accountable_officer'] ?? null),
            'transmittal_date' => $date,
            'received_by' => tr_clean_string($payload['received_by'] ?? null),
            'date_received' => tr_clean_string($payload['date_received'] ?? null),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $pdo->beginTransaction();
        if ($action === 'create') {
            $row['created_by'] = tr_clean_int($user['id'] ?? null);
            $columns = array_keys($row);
            $stmt = $pdo->prepare('INSERT INTO transmittal (`' . implode('`,`', $columns) . '`) VALUES (' . implode(',', array_fill(0, count($columns), '?')) . ')');
            $stmt->execute(array_values($row));
            $id = (int) $pdo->lastInsertId();
        } else {
            if (!$id) {
                throw new RuntimeException('Invalid transmittal ID.');
            }
            $sets = [];
            $values = [];
            foreach ($row as $column => $value) {
                $sets[] = "`{$column}` = ?";
                $values[] = $value;
            }
            $values[] = $id;
            $pdo->prepare('UPDATE transmittal SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($values);
        }

        $attachment = tr_store_pdf($pdo, (int) $id, $_FILES['attachment_pdf'] ?? []);
        if ($attachment) {
            $pdo->prepare('UPDATE transmittal SET attachment_pdf = ? WHERE id = ?')->execute([$attachment, $id]);
        }

        tr_sync_items($pdo, (int) $id, $items, $manualItems, $transmittalId);
        $pdo->commit();

        log_activity($action === 'create' ? 'TRANSMITTAL_CREATE' : 'TRANSMITTAL_UPDATE', ucfirst($action) . ' transmittal ' . $transmittalId);
        tr_response(['success' => true, 'message' => 'Transmittal saved successfully.']);
    }

    if ($action === 'archive') {
        $id = tr_clean_int($payload['id'] ?? null);
        if (!$id) {
            tr_response(['success' => false, 'message' => 'Invalid transmittal ID.'], 422);
        }
        $pdo->prepare('UPDATE transmittal SET is_archived = 1, archived_at = NOW() WHERE id = ?')->execute([$id]);
        log_activity('TRANSMITTAL_ARCHIVE', 'Archived transmittal #' . $id);
        tr_response(['success' => true, 'message' => 'Transmittal moved to archive.']);
    }

    if ($action === 'upload_pdf') {
        $id = tr_clean_int($payload['id'] ?? null);
        if (!$id) {
            tr_response(['success' => false, 'message' => 'Invalid transmittal ID.'], 422);
        }
        $attachment = tr_store_pdf($pdo, $id, $_FILES['attachment_pdf'] ?? []);
        if (!$attachment) {
            tr_response(['success' => false, 'message' => 'No PDF file uploaded.'], 422);
        }
        $pdo->prepare('UPDATE transmittal SET attachment_pdf = ? WHERE id = ?')->execute([$attachment, $id]);
        log_activity('TRANSMITTAL_PDF_UPLOAD', 'Uploaded transmittal PDF #' . $id);
        tr_response(['success' => true, 'message' => 'Transmittal PDF uploaded successfully.']);
    }

    if ($action === 'remove_pdf') {
        $id = tr_clean_int($payload['id'] ?? null);
        if (!$id) {
            tr_response(['success' => false, 'message' => 'Invalid transmittal ID.'], 422);
        }
        $stmt = $pdo->prepare('SELECT attachment_pdf FROM transmittal WHERE id = ?');
        $stmt->execute([$id]);
        $pdf = (string) ($stmt->fetchColumn() ?: '');
        if ($pdf !== '') {
            $abs = ROOT_PATH . '/public/' . ltrim($pdf, '/');
            if (is_file($abs)) {
                unlink($abs);
            }
        }
        $pdo->prepare('UPDATE transmittal SET attachment_pdf = NULL WHERE id = ?')->execute([$id]);
        log_activity('TRANSMITTAL_PDF_REMOVE', 'Removed transmittal PDF #' . $id);
        tr_response(['success' => true, 'message' => 'Transmittal PDF removed successfully.']);
    }

    if ($action === 'restore') {
        $id = tr_clean_int($payload['id'] ?? null);
        $pdo->prepare('UPDATE transmittal SET is_archived = 0, archived_at = NULL WHERE id = ?')->execute([$id]);
        log_activity('TRANSMITTAL_RESTORE', 'Restored transmittal #' . $id);
        tr_response(['success' => true, 'message' => 'Transmittal restored.']);
    }

    if ($action === 'delete') {
        $id = tr_clean_int($payload['id'] ?? null);
        $stmt = $pdo->prepare('SELECT attachment_pdf FROM transmittal WHERE id = ?');
        $stmt->execute([$id]);
        $pdf = (string) ($stmt->fetchColumn() ?: '');
        if ($pdf !== '') {
            $abs = ROOT_PATH . '/public/' . ltrim($pdf, '/');
            if (is_file($abs)) {
                unlink($abs);
            }
        }
        $pdo->prepare('DELETE FROM transmittal WHERE id = ?')->execute([$id]);
        log_activity('TRANSMITTAL_DELETE', 'Permanently deleted transmittal #' . $id);
        tr_response(['success' => true, 'message' => 'Transmittal permanently deleted.']);
    }

    tr_response(['success' => false, 'message' => 'Invalid action.'], 400);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    tr_response(['success' => false, 'message' => $e->getMessage()], 500);
}
