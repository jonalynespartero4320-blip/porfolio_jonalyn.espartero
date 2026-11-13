<?php
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_login();
ensure_record_scope_schema($pdo);
header('Content-Type: application/json');

function sr_response(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function sr_payload(): array
{
    $raw = file_get_contents('php://input');
    $json = json_decode($raw ?: '', true);
    return is_array($json) ? $json : $_POST;
}

function sr_clean_string($value): ?string
{
    $value = trim((string) ($value ?? ''));
    return $value === '' ? null : $value;
}

function sr_clean_int($value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }
    return is_numeric($value) ? (int) $value : null;
}

function sr_normalize_item_type($value): string
{
    $type = strtolower(trim((string) ($value ?? 'equipment')));
    $legacyTypes = [
        '1' => 'equipment',
        '2' => 'supply',
        '3' => 'subscription',
        '4' => 'other',
        'supplies' => 'supply',
    ];
    $type = $legacyTypes[$type] ?? $type;
    return in_array($type, ['equipment', 'supply', 'subscription', 'other'], true) ? $type : 'other';
}

function sr_json_values($value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }
    $values = is_array($value) ? $value : json_decode((string) $value, true);
    if (!is_array($values)) {
        $values = array_map('trim', explode(',', (string) $value));
    }
    $values = array_values(array_filter(array_map('strval', $values), static fn(string $item): bool => trim($item) !== ''));
    return empty($values) ? null : json_encode($values);
}

function sr_decode_values($value): array
{
    $decoded = json_decode((string) $value, true);
    if (is_array($decoded)) {
        return array_values(array_map('strval', $decoded));
    }
    return array_values(array_filter(array_map('trim', explode(',', (string) $value))));
}

function sr_ensure_schema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS service_remarks_list (id INT AUTO_INCREMENT PRIMARY KEY, remarks_desc VARCHAR(255) NOT NULL UNIQUE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS service_report_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            service_report_id INT NOT NULL,
            type VARCHAR(30) NOT NULL DEFAULT 'equipment',
            source_id INT NULL,
            control_no VARCHAR(100) NULL,
            date_delivered VARCHAR(20) NULL,
            other_description VARCHAR(255) NULL,
            description_id INT NULL,
            brand_id INT NULL,
            serialno VARCHAR(100) NULL,
            item_status VARCHAR(100) NULL,
            unit_warranty VARCHAR(100) NULL,
            warranty_remarks TEXT NULL,
            remarks TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            INDEX idx_service_report_items_report (service_report_id),
            INDEX idx_service_report_items_type_source (type, source_id),
            CONSTRAINT fk_service_report_items_report
                FOREIGN KEY (service_report_id) REFERENCES service_report(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("ALTER TABLE service_report MODIFY service_controlno VARCHAR(50) NOT NULL, MODIFY complain TEXT NULL, MODIFY troubleshoot TEXT NULL, MODIFY remarks_service_details TEXT NULL, MODIFY attending_technician TEXT NULL, MODIFY technician_designation TEXT NULL, MODIFY attachment_pdf VARCHAR(255) NULL");
    $pdo->exec("ALTER TABLE service_report_items MODIFY type VARCHAR(30) NOT NULL DEFAULT 'equipment', MODIFY updated_at DATETIME NULL");
    foreach ([
        'source_id' => 'INT NULL AFTER type',
        'date_delivered' => 'VARCHAR(20) NULL AFTER control_no',
        'other_description' => 'VARCHAR(255) NULL AFTER control_no',
        'item_status' => 'VARCHAR(100) NULL AFTER serialno',
    ] as $column => $definition) {
        if (!db_column_exists($pdo, 'service_report_items', $column)) {
            $pdo->exec("ALTER TABLE service_report_items ADD COLUMN {$column} {$definition}");
        }
    }
    foreach ([
        'created_by' => 'INT NULL',
        'request_id' => 'INT NULL',
        'employee_designation_id' => 'INT NULL AFTER department_id',
        'is_archived' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'archived_at' => 'DATETIME NULL',
    ] as $column => $definition) {
        if (!db_column_exists($pdo, 'service_report', $column)) {
            $pdo->exec("ALTER TABLE service_report ADD COLUMN {$column} {$definition}");
        }
    }
    if (db_table_exists($pdo, 'other_equipment') && !db_column_exists($pdo, 'other_equipment', 'assigned_id')) {
        $pdo->exec('ALTER TABLE other_equipment ADD COLUMN assigned_id INT NULL');
    }
    if (db_table_exists($pdo, 'assigned_record')) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS assigned_record_items (
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
        foreach ([
            'serialno' => 'VARCHAR(100) NULL',
            'item_status' => 'VARCHAR(100) NULL',
        ] as $column => $definition) {
            if (!db_column_exists($pdo, 'assigned_record_items', $column)) {
                $pdo->exec("ALTER TABLE assigned_record_items ADD COLUMN {$column} {$definition}");
            }
        }
    }
    if (db_table_exists($pdo, 'subscription')) {
        foreach ([
            'end_user_id' => 'INT NULL',
            'department_id' => 'INT NULL',
        ] as $column => $definition) {
            if (!db_column_exists($pdo, 'subscription', $column)) {
                $pdo->exec("ALTER TABLE subscription ADD COLUMN {$column} {$definition}");
            }
        }
    }
}

function sr_generate_control_no(PDO $pdo, ?string $reportDate, ?int $ignoreId = null): string
{
    $year = $reportDate ? date('Y', strtotime($reportDate)) : date('Y');
    $prefix = 'SC-' . $year . '-';
    $sql = "SELECT service_controlno FROM service_report WHERE service_controlno LIKE ? ";
    $params = [$prefix . '%'];
    if ($ignoreId) {
        $sql .= 'AND id <> ? ';
        $params[] = $ignoreId;
    }
    $counterStart = strlen($prefix) + 1;
    $sql .= "ORDER BY CAST(SUBSTRING(service_controlno, {$counterStart}) AS UNSIGNED) DESC LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $last = (string) ($stmt->fetchColumn() ?: '');
    $next = 1;
    if (preg_match('/^SC-\d{4}-(\d+)$/', $last, $match)) {
        $next = ((int) $match[1]) + 1;
    }
    return $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
}

function sr_generate_equipment_control_no(PDO $pdo, ?string $reportDate): string
{
    $timestamp = $reportDate ? strtotime($reportDate) : time();
    $year = $timestamp ? date('y', $timestamp) : date('y');
    $prefix = 'IT-' . $year . '-';
    $controlNos = [];

    $stmt = $pdo->prepare('SELECT control_no FROM equipment WHERE control_no LIKE ?');
    $stmt->execute([$prefix . '%']);
    $controlNos = array_merge($controlNos, $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);

    if (db_table_exists($pdo, 'archive_equipment')) {
        $reuseClause = db_column_exists($pdo, 'archive_equipment', 'control_no_reused')
            ? ' AND (COALESCE(control_no_reused, 0) = 0 OR reused_equipment_id IS NOT NULL)'
            : '';
        $stmt = $pdo->prepare('SELECT control_no FROM archive_equipment WHERE control_no LIKE ?' . $reuseClause);
        $stmt->execute([$prefix . '%']);
        $controlNos = array_merge($controlNos, $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    $maxSequence = 0;
    foreach ($controlNos as $controlNo) {
        if (preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', (string) $controlNo, $match)) {
            $maxSequence = max($maxSequence, (int) $match[1]);
        }
    }

    return $prefix . str_pad((string) ($maxSequence + 1), 4, '0', STR_PAD_LEFT);
}

function sr_generate_supply_code(PDO $pdo, ?string $reportDate): string
{
    $prefix = 'SUP-' . date('y') . '-';
    $codes = [];

    $stmt = $pdo->prepare('SELECT code FROM other_equipment WHERE code LIKE ?');
    $stmt->execute([$prefix . '%']);
    $codes = array_merge($codes, $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);

    if (db_table_exists($pdo, 'archive_other_equipment')) {
        $stmt = $pdo->prepare('SELECT code FROM archive_other_equipment WHERE code LIKE ?');
        $stmt->execute([$prefix . '%']);
        $codes = array_merge($codes, $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    $max = 0;
    foreach ($codes as $code) {
        if (preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', (string) $code, $match)) {
            $max = max($max, (int) $match[1]);
        }
    }

    return $prefix . str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
}

function sr_options(PDO $pdo, string $table, string $idColumn, string $textColumn, ?string $extraColumn = null): array
{
    if (!db_table_exists($pdo, $table)) {
        return [];
    }
    $select = "{$idColumn} AS id, {$textColumn} AS text";
    if ($extraColumn) {
        $select .= ", {$extraColumn}";
    }
    return $pdo->query("SELECT {$select} FROM {$table} ORDER BY {$textColumn} ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function sr_available_equipment(PDO $pdo): array
{
    return db_table_exists($pdo, 'equipment')
        ? ($pdo->query("
            SELECT
                CONCAT('equipment:', e.id) AS id,
                CONCAT('Equipment | ', COALESCE(e.control_no, 'No Control'), ' | Serial: ', COALESCE(e.serialno, 'No Serial'), ' | ', COALESCE(d.description_name, 'Equipment')) AS text,
                e.id AS source_id,
                'equipment' AS item_type,
                e.control_no,
                e.description_id,
                d.description_name,
                e.brand_id,
                b.brand_name,
                e.serialno,
                e.date_delivered,
                e.warranty_date AS unit_warranty,
                es.equipment_status AS item_status
            FROM equipment e
            LEFT JOIN list_description d ON d.id = e.description_id
            LEFT JOIN list_brand b ON b.id = e.brand_id
            LEFT JOIN list_equipment_status es ON es.id = e.equipment_status_id
            ORDER BY e.control_no ASC
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [])
        : [];
}

function sr_available_supplies(PDO $pdo): array
{
    return db_table_exists($pdo, 'other_equipment')
        ? ($pdo->query("
            SELECT
                CONCAT('supply:', oe.id) AS id,
                CONCAT('Supply | ', COALESCE(oe.code, 'No Code'), ' | Old: ', COALESCE(oe.old_control_no, 'None'), ' | Serial: ', COALESCE(oe.serialno, 'No Serial')) AS text,
                oe.id AS source_id,
                'supply' AS item_type,
                COALESCE(oe.code, oe.serialno) AS control_no,
                oe.description_id,
                d.description_name,
                oe.brand_id,
                b.brand_name,
                oe.serialno,
                oe.warranty_date AS unit_warranty,
                oe.status AS item_status
            FROM other_equipment oe
            LEFT JOIN list_description d ON d.id = oe.description_id
            LEFT JOIN list_brand b ON b.id = oe.brand_id
            ORDER BY oe.code ASC
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [])
        : [];
}

function sr_available_subscriptions(PDO $pdo): array
{
    return db_table_exists($pdo, 'subscription')
        ? ($pdo->query("
            SELECT
                CONCAT('subscription:', s.subscription_id) AS id,
                CONCAT('Subscription | ', s.software_description, ' | Expiry: ', s.expiry_date) AS text,
                s.subscription_id AS source_id,
                'subscription' AS item_type,
                s.software_description AS control_no,
                s.software_description AS other_description,
                NULL AS description_id,
                s.software_description AS description_name,
                NULL AS brand_id,
                NULL AS brand_name,
                NULL AS serialno,
                s.expiry_date AS unit_warranty,
                NULL AS item_status
            FROM subscription s
            ORDER BY s.software_description ASC
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [])
        : [];
}

function sr_assigned_items_for_user(PDO $pdo, int $endUserId): array
{
    if ($endUserId <= 0) {
        return [];
    }

    $items = [];

    if (db_table_exists($pdo, 'assigned_record') && db_table_exists($pdo, 'assigned_record_items')) {
        $stmt = $pdo->prepare("
            SELECT
                ari.item_type,
                ari.source_id,
                COALESCE(ari.control_no, e.control_no, oe.code, oe.serialno) AS control_no,
                COALESCE(ari.serialno, e.serialno, oe.serialno) AS serialno,
                COALESCE(ari.item_status, es.equipment_status, oe.status) AS item_status,
                COALESCE(e.description_id, oe.description_id) AS description_id,
                COALESCE(de.description_name, ds.description_name) AS description_name,
                COALESCE(e.brand_id, oe.brand_id) AS brand_id,
                COALESCE(be.brand_name, bs.brand_name) AS brand_name,
                e.date_delivered,
                COALESCE(e.warranty_date, oe.warranty_date) AS unit_warranty
            FROM assigned_record ar
            INNER JOIN assigned_record_items ari ON ari.assigned_id = ar.assigned_id
            LEFT JOIN equipment e ON ari.item_type = 'equipment' AND e.id = ari.source_id
            LEFT JOIN list_description de ON de.id = e.description_id
            LEFT JOIN list_brand be ON be.id = e.brand_id
            LEFT JOIN list_equipment_status es ON es.id = e.equipment_status_id
            LEFT JOIN other_equipment oe ON ari.item_type = 'supply' AND oe.id = ari.source_id
            LEFT JOIN list_description ds ON ds.id = oe.description_id
            LEFT JOIN list_brand bs ON bs.id = oe.brand_id
            WHERE ar.end_user_id = ?
              AND ari.item_type IN ('equipment', 'supply')
            ORDER BY ari.item_type ASC, ari.id ASC
        ");
        $stmt->execute([$endUserId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $type = sr_normalize_item_type($row['item_type'] ?? 'equipment');
            $sourceId = sr_clean_int($row['source_id'] ?? null);
            if (!$sourceId && !sr_clean_string($row['control_no'] ?? null)) {
                continue;
            }
            $items[] = [
                'id' => $type . ':' . ($sourceId ?: sr_clean_string($row['control_no'] ?? '')),
                'text' => ucfirst($type) . ' | ' . ((string) ($row['control_no'] ?? 'No Control')) . ' | Serial: ' . ((string) ($row['serialno'] ?? 'No Serial')) . ' | ' . ((string) ($row['description_name'] ?? ucfirst($type))),
                'source_id' => $sourceId,
                'item_type' => $type,
                'control_no' => sr_clean_string($row['control_no'] ?? null),
                'other_description' => sr_clean_string($row['description_name'] ?? null),
                'description_id' => sr_clean_int($row['description_id'] ?? null),
                'description_name' => sr_clean_string($row['description_name'] ?? null),
                'brand_id' => sr_clean_int($row['brand_id'] ?? null),
                'brand_name' => sr_clean_string($row['brand_name'] ?? null),
                'serialno' => sr_clean_string($row['serialno'] ?? null),
                'date_delivered' => sr_clean_string($row['date_delivered'] ?? null),
                'item_status' => sr_clean_string($row['item_status'] ?? null),
                'unit_warranty' => sr_clean_string($row['unit_warranty'] ?? null),
            ];
        }
    }

    if (db_table_exists($pdo, 'subscription')) {
        $stmt = $pdo->prepare("
            SELECT
                s.subscription_id AS source_id,
                s.software_description,
                s.expiry_date
            FROM subscription s
            WHERE s.end_user_id = ?
            ORDER BY s.software_description ASC
        ");
        $stmt->execute([$endUserId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $description = sr_clean_string($row['software_description'] ?? null) ?: 'Subscription';
            $items[] = [
                'id' => 'subscription:' . (int) $row['source_id'],
                'text' => 'Subscription | ' . $description,
                'source_id' => (int) $row['source_id'],
                'item_type' => 'subscription',
                'control_no' => $description,
                'other_description' => $description,
                'description_id' => null,
                'description_name' => $description,
                'brand_id' => null,
                'brand_name' => null,
                'serialno' => null,
                'item_status' => null,
                'unit_warranty' => sr_clean_string($row['expiry_date'] ?? null),
            ];
        }
    }

    return $items;
}

function sr_text_value_options(array $rows): array
{
    return array_map(static function (array $row): array {
        $row['id'] = $row['text'] ?? $row['id'] ?? '';
        return $row;
    }, $rows);
}

function sr_upsert_masterlist_values(PDO $pdo, string $table, string $column, array $values): void
{
    if (!db_table_exists($pdo, $table)) {
        return;
    }
    $exists = $pdo->prepare("SELECT 1 FROM `{$table}` WHERE LOWER(TRIM(`{$column}`)) = LOWER(TRIM(?)) LIMIT 1");
    $insert = $pdo->prepare("INSERT INTO `{$table}` (`{$column}`) VALUES (?)");
    $seen = [];
    foreach ($values as $value) {
        $value = sr_clean_string($value);
        if (!$value) {
            continue;
        }
        $key = mb_strtolower($value);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $exists->execute([$value]);
        if (!$exists->fetchColumn()) {
            $insert->execute([$value]);
        }
    }
}

function sr_normalize_report(array $payload, PDO $pdo, ?int $id = null): array
{
    $reportDate = sr_clean_string($payload['report_date'] ?? null) ?: date('Y-m-d');
    $serviceControlNo = sr_clean_string($payload['service_controlno'] ?? null) ?: sr_generate_control_no($pdo, $reportDate, $id);

    return [
        'service_controlno' => $serviceControlNo,
        'old_service_cn' => sr_clean_string($payload['old_service_cn'] ?? null),
        'end_user_id' => sr_clean_int($payload['end_user_id'] ?? null),
        'department_id' => sr_clean_int($payload['department_id'] ?? null),
        'employee_designation_id' => sr_clean_int($payload['employee_designation_id'] ?? null),
        'vicinity' => sr_clean_string($payload['vicinity'] ?? null),
        'distance' => sr_clean_string($payload['distance'] ?? null),
        'travel_date' => sr_clean_string($payload['travel_date'] ?? null),
        'travel_departure' => sr_clean_string($payload['travel_departure'] ?? null),
        'travel_arrival' => sr_clean_string($payload['travel_arrival'] ?? null),
        'complain' => sr_json_values($payload['complain'] ?? null),
        'complain_inspection_time_start' => sr_clean_string($payload['complain_inspection_time_start'] ?? null),
        'complain_inspection_time_finish' => sr_clean_string($payload['complain_inspection_time_finish'] ?? null),
        'troubleshoot' => sr_json_values($payload['troubleshoot'] ?? null),
        'troubleshoot_start' => sr_clean_string($payload['troubleshoot_start'] ?? null),
        'troubleshoot_finish' => sr_clean_string($payload['troubleshoot_finish'] ?? null),
        'remarks_service_details' => sr_json_values($payload['remarks_service_details'] ?? null),
        'remarks_date_pullout' => sr_clean_string($payload['remarks_date_pullout'] ?? null),
        'remarks_time_pullout' => sr_clean_string($payload['remarks_time_pullout'] ?? null),
        'remarks_date_returned' => sr_clean_string($payload['remarks_date_returned'] ?? null),
        'remarks_time_returned' => sr_clean_string($payload['remarks_time_returned'] ?? null),
        'attending_technician' => sr_json_values($payload['attending_technician'] ?? null),
        'technician_designation' => sr_clean_string($payload['technician_designation'] ?? null),
        'report_date' => $reportDate,
        'report_time' => sr_clean_string($payload['report_time'] ?? null),
        'employee_satisfactory' => sr_clean_string($payload['employee_satisfactory'] ?? null),
        'request_id' => sr_clean_int($payload['request_id'] ?? null),
    ];
}

function sr_items_from_payload(array $payload): array
{
    $items = $payload['items'] ?? '[]';
    if (is_string($items)) {
        $items = json_decode($items, true);
    }
    return is_array($items) ? $items : [];
}

function sr_store_pdf(int $reportId): ?string
{
    if (empty($_FILES['attachment_pdf']) || !is_uploaded_file($_FILES['attachment_pdf']['tmp_name'])) {
        return null;
    }
    $file = $_FILES['attachment_pdf'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Unable to upload PDF attachment.');
    }
    $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    if ($extension !== 'pdf') {
        throw new RuntimeException('Only PDF attachments are allowed.');
    }
    if (($file['size'] ?? 0) > 1024 * 1024) {
        throw new RuntimeException('PDF must not exceed 1MB.');
    }
    $uploadDir = ROOT_PATH . '/public/demo-uploads/service-reports';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }
    $filename = 'service-report-' . $reportId . '-' . date('YmdHis') . '.pdf';
    $target = $uploadDir . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new RuntimeException('Unable to save PDF attachment.');
    }
    return 'demo-uploads/service-reports/' . $filename;
}

function sr_delete_pdf(PDO $pdo, int $reportId): void
{
    $stmt = $pdo->prepare('SELECT attachment_pdf FROM service_report WHERE id = ?');
    $stmt->execute([$reportId]);
    $pdf = trim((string) ($stmt->fetchColumn() ?: ''));
    if ($pdf !== '') {
        $absolutePath = ROOT_PATH . '/public/' . ltrim($pdf, '/');
        if (is_file($absolutePath)) {
            unlink($absolutePath);
        }
    }
    $pdo->prepare('UPDATE service_report SET attachment_pdf = NULL WHERE id = ?')->execute([$reportId]);
}

function sr_status_id(PDO $pdo, ?string $status): ?int
{
    $status = sr_clean_string($status);
    if (!$status || !db_table_exists($pdo, 'list_equipment_status')) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT id FROM list_equipment_status WHERE equipment_status = ? LIMIT 1');
    $stmt->execute([$status]);
    $id = $stmt->fetchColumn();
    return $id ? (int) $id : null;
}

function sr_upsert_assignment(PDO $pdo, string $type, ?int $sourceId, ?string $controlNo, ?int $endUserId, ?int $departmentId, ?string $assignedDate): ?int
{
    if (!db_table_exists($pdo, 'assigned_record') || !$controlNo || !$endUserId || !$departmentId) {
        return null;
    }

    $ensureItem = static function (int $assignedId) use ($pdo, $type, $sourceId, $controlNo): void {
        if (!db_table_exists($pdo, 'assigned_record_items')) {
            return;
        }

        if ($sourceId) {
            $stmt = $pdo->prepare('SELECT id, assigned_id FROM assigned_record_items WHERE item_type = ? AND source_id = ? LIMIT 1');
            $stmt->execute([$type, $sourceId]);
            $existingItem = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($existingItem) {
                $pdo->prepare('UPDATE assigned_record_items SET assigned_id = ?, control_no = ? WHERE id = ?')
                    ->execute([$assignedId, $controlNo, (int) $existingItem['id']]);
                return;
            }
        }

        $stmt = $pdo->prepare('SELECT id FROM assigned_record_items WHERE assigned_id = ? AND item_type = ? AND control_no = ? LIMIT 1');
        $stmt->execute([$assignedId, $type, $controlNo]);
        if ($stmt->fetch()) {
            return;
        }

        $pdo->prepare('INSERT INTO assigned_record_items (assigned_id, item_type, source_id, control_no) VALUES (?, ?, ?, ?)')
            ->execute([$assignedId, $type, $sourceId, $controlNo]);
    };

    $stmt = $pdo->prepare('SELECT assigned_id FROM assigned_record WHERE end_user_id = ? LIMIT 1');
    $stmt->execute([$endUserId]);
    $existingUserId = $stmt->fetchColumn();
    if ($existingUserId) {
        $stmt = $pdo->prepare("
            UPDATE assigned_record
            SET department_id = ?,
                equipment_id = COALESCE(equipment_id, ?),
                control_no = COALESCE(NULLIF(control_no, ''), ?)
            WHERE assigned_id = ?
        ");
        $stmt->execute([
            $departmentId,
            $type === 'equipment' ? $sourceId : null,
            $controlNo,
            (int) $existingUserId,
        ]);
        $ensureItem((int) $existingUserId);
        return (int) $existingUserId;
    }

    $stmt = $pdo->prepare('SELECT assigned_id FROM assigned_record WHERE control_no = ? LIMIT 1');
    $stmt->execute([$controlNo]);
    $existingId = $stmt->fetchColumn();
    if ($existingId) {
        $stmt = $pdo->prepare('UPDATE assigned_record SET end_user_id = ?, department_id = ?, equipment_id = ? WHERE assigned_id = ?');
        $stmt->execute([
            $endUserId,
            $departmentId,
            $type === 'equipment' ? $sourceId : null,
            (int) $existingId,
        ]);
        $ensureItem((int) $existingId);
        return (int) $existingId;
    }

    $user = current_user() ?: [];
    $stmt = $pdo->prepare("
        INSERT INTO assigned_record
            (end_user_id, department_id, equipment_id, control_no, assigned_date, created_by)
        VALUES
            (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $endUserId,
        $departmentId,
        $type === 'equipment' ? $sourceId : null,
        $controlNo,
        $assignedDate ?: date('Y-m-d'),
        sr_clean_int($user['id'] ?? null),
    ]);

    $assignedId = (int) $pdo->lastInsertId();
    $ensureItem($assignedId);

    return $assignedId;
}

function sr_resolve_item_source(PDO $pdo, array &$item, array $report): void
{
    $type = sr_normalize_item_type($item['type'] ?? 'equipment');
    $sourceId = sr_clean_int($item['source_id'] ?? null);
    $endUserId = sr_clean_int($report['end_user_id'] ?? null);
    $departmentId = sr_clean_int($report['department_id'] ?? null);
    $reportDate = sr_clean_string($report['report_date'] ?? null) ?: date('Y-m-d');
    $status = sr_clean_string($item['item_status'] ?? null);
    $dateDelivered = sr_clean_string($item['date_delivered'] ?? null);
    $controlDate = $dateDelivered ?: $reportDate;
    $user = current_user() ?: [];

    if ($type === 'subscription') {
        if (!db_table_exists($pdo, 'subscription')) {
            $description = sr_clean_string($item['other_description'] ?? null)
                ?: sr_clean_string($item['control_no'] ?? null)
                ?: 'Service Report Subscription';
            $item['source_id'] = null;
            $item['control_no'] = $description;
            $item['other_description'] = $description;
            return;
        }

        if ($sourceId) {
            $description = sr_clean_string($item['other_description'] ?? null)
                ?: sr_clean_string($item['control_no'] ?? null);
            $expiryDate = sr_clean_string($item['unit_warranty'] ?? null);
            $pdo->prepare('
                UPDATE subscription
                SET end_user_id = ?,
                    department_id = ?,
                    software_description = COALESCE(?, software_description),
                    expiry_date = COALESCE(?, expiry_date)
                WHERE subscription_id = ?
            ')->execute([$endUserId, $departmentId, $description, $expiryDate, $sourceId]);
            if ($description) {
                $item['control_no'] = $description;
                $item['other_description'] = $description;
            }
            return;
        }

        $description = sr_clean_string($item['other_description'] ?? null)
            ?: sr_clean_string($item['control_no'] ?? null)
            ?: 'Service Report Subscription';
        $purchaseDate = $reportDate;
        $expiryDate = sr_clean_string($item['unit_warranty'] ?? null) ?: date('Y-m-d', strtotime($purchaseDate . ' +1 year'));
        $stmt = $pdo->prepare('INSERT INTO subscription (end_user_id, department_id, software_description, purchase_date, expiry_date) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$endUserId, $departmentId, $description, $purchaseDate, $expiryDate]);
        $item['source_id'] = (int) $pdo->lastInsertId();
        $item['control_no'] = $description;
        $item['other_description'] = $description;
        return;
    }

    if ($type === 'equipment') {
        if ($sourceId) {
            $assignments = ['assigned_id = ?', 'department_id = ?'];
            $controlNo = sr_clean_string($item['control_no'] ?? null);
            if (!$controlNo) {
                $stmt = $pdo->prepare('SELECT control_no FROM equipment WHERE id = ? LIMIT 1');
                $stmt->execute([$sourceId]);
                $controlNo = sr_clean_string($stmt->fetchColumn());
            }
            if (!$controlNo) {
                $controlNo = sr_generate_equipment_control_no($pdo, $controlDate);
            }
            $assignedRecordId = sr_upsert_assignment($pdo, 'equipment', $sourceId, $controlNo, $endUserId, $departmentId, $reportDate);
            $values = [$assignedRecordId, $departmentId];
            $statusId = sr_status_id($pdo, $status);
            if ($statusId) {
                $assignments[] = 'equipment_status_id = ?';
                $values[] = $statusId;
            }
            foreach ([
                'control_no' => $controlNo,
                'description_id' => sr_clean_int($item['description_id'] ?? null),
                'brand_id' => sr_clean_int($item['brand_id'] ?? null),
                'serialno' => sr_clean_string($item['serialno'] ?? null),
                'date_delivered' => $dateDelivered,
                'warranty_date' => sr_clean_string($item['unit_warranty'] ?? null),
            ] as $column => $value) {
                if ($value !== null && db_column_exists($pdo, 'equipment', $column)) {
                    $assignments[] = "{$column} = ?";
                    $values[] = $value;
                }
            }
            $values[] = $sourceId;
            $pdo->prepare('UPDATE equipment SET ' . implode(', ', $assignments) . ' WHERE id = ?')->execute($values);
            $item['control_no'] = $controlNo;
            return;
        }

        $controlNo = sr_clean_string($item['control_no'] ?? null) ?: sr_generate_equipment_control_no($pdo, $controlDate);
        $statusId = sr_status_id($pdo, $status);
        $columns = ['control_no', 'description_id', 'brand_id', 'serialno', 'warranty_date', 'assigned_id', 'department_id', 'equipment_status_id', 'created_by'];
        $values = [
            $controlNo,
            sr_clean_int($item['description_id'] ?? null),
            sr_clean_int($item['brand_id'] ?? null),
            sr_clean_string($item['serialno'] ?? null),
            sr_clean_string($item['unit_warranty'] ?? null),
            null,
            $departmentId,
            $statusId,
            (int) ($user['id'] ?? 0) ?: null,
        ];
        if (db_column_exists($pdo, 'equipment', 'date_delivered')) {
            array_splice($columns, 4, 0, ['date_delivered']);
            array_splice($values, 4, 0, [$dateDelivered]);
        }
        $stmt = $pdo->prepare('INSERT INTO equipment (`' . implode('`,`', $columns) . '`) VALUES (' . implode(',', array_fill(0, count($columns), '?')) . ')');
        $stmt->execute($values);
        $item['source_id'] = (int) $pdo->lastInsertId();
        $item['control_no'] = $controlNo;
        $assignedRecordId = sr_upsert_assignment($pdo, 'equipment', (int) $item['source_id'], $controlNo, $endUserId, $departmentId, $reportDate);
        $pdo->prepare('UPDATE equipment SET assigned_id = ? WHERE id = ?')->execute([$assignedRecordId, (int) $item['source_id']]);
        return;
    }

    if ($type === 'supply') {
        if ($sourceId) {
            $controlNo = sr_clean_string($item['control_no'] ?? null);
            if (!$controlNo) {
                $stmt = $pdo->prepare('SELECT COALESCE(code, serialno) FROM other_equipment WHERE id = ? LIMIT 1');
                $stmt->execute([$sourceId]);
                $controlNo = sr_clean_string($stmt->fetchColumn());
            }
            $assignedRecordId = sr_upsert_assignment($pdo, 'supply', $sourceId, $controlNo, $endUserId, $departmentId, $reportDate);
            $assignments = [
                'assigned_id = ?',
                'end_user_id = ?',
                'department_id = ?',
                'status = COALESCE(?, status)',
            ];
            $values = [$assignedRecordId, $endUserId, $departmentId, $status];
            foreach ([
                'code' => $controlNo,
                'description_id' => sr_clean_int($item['description_id'] ?? null),
                'brand_id' => sr_clean_int($item['brand_id'] ?? null),
                'serialno' => sr_clean_string($item['serialno'] ?? null),
                'warranty_date' => sr_clean_string($item['unit_warranty'] ?? null),
            ] as $column => $value) {
                if ($value !== null && db_column_exists($pdo, 'other_equipment', $column)) {
                    $assignments[] = "{$column} = ?";
                    $values[] = $value;
                }
            }
            $values[] = $sourceId;
            $pdo->prepare('UPDATE other_equipment SET ' . implode(', ', $assignments) . ' WHERE id = ?')->execute($values);
            $item['control_no'] = $controlNo;
            return;
        }

        $code = sr_clean_string($item['control_no'] ?? null) ?: sr_generate_supply_code($pdo, $reportDate);
        $columns = ['code', 'description_id', 'brand_id', 'serialno', 'status', 'warranty_date', 'end_user_id', 'department_id', 'created_by'];
        $values = [
            $code,
            sr_clean_int($item['description_id'] ?? null),
            sr_clean_int($item['brand_id'] ?? null),
            sr_clean_string($item['serialno'] ?? null),
            $status,
            sr_clean_string($item['unit_warranty'] ?? null),
            $endUserId,
            $departmentId,
            (int) ($user['id'] ?? 0) ?: null,
        ];
        if (db_column_exists($pdo, 'other_equipment', 'control_no')) {
            array_splice($columns, 1, 0, ['control_no']);
            array_splice($values, 1, 0, [null]);
        }
        $stmt = $pdo->prepare('INSERT INTO other_equipment (`' . implode('`,`', $columns) . '`) VALUES (' . implode(',', array_fill(0, count($columns), '?')) . ')');
        $stmt->execute($values);
        $item['source_id'] = (int) $pdo->lastInsertId();
        $item['control_no'] = $code;
        $assignedRecordId = sr_upsert_assignment($pdo, 'supply', (int) $item['source_id'], $code, $endUserId, $departmentId, $reportDate);
        $pdo->prepare('UPDATE other_equipment SET assigned_id = ? WHERE id = ?')->execute([$assignedRecordId, (int) $item['source_id']]);
    }
}

function sr_sync_items(PDO $pdo, int $reportId, array $items, array $report): void
{
    $pdo->prepare('DELETE FROM service_report_items WHERE service_report_id = ?')->execute([$reportId]);
    if (empty($items)) {
        return;
    }

    $insert = $pdo->prepare("
        INSERT INTO service_report_items
            (service_report_id, type, source_id, control_no, date_delivered, other_description, description_id, brand_id, serialno, item_status, unit_warranty, warranty_remarks, remarks, updated_at)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    foreach ($items as $item) {
        $type = sr_normalize_item_type($item['type'] ?? 'equipment');
        $status = sr_clean_string($item['item_status'] ?? null);
        sr_resolve_item_source($pdo, $item, $report);
        $insert->execute([
            $reportId,
            $type,
            sr_clean_int($item['source_id'] ?? null),
            sr_clean_string($item['control_no'] ?? null),
            sr_clean_string($item['date_delivered'] ?? null),
            sr_clean_string($item['other_description'] ?? null),
            sr_clean_int($item['description_id'] ?? null),
            sr_clean_int($item['brand_id'] ?? null),
            sr_clean_string($item['serialno'] ?? null),
            $status,
            sr_clean_string($item['unit_warranty'] ?? null),
            sr_clean_string($item['warranty_remarks'] ?? null),
            sr_clean_string($item['remarks'] ?? null),
        ]);

    }
}

sr_ensure_schema($pdo);
$payload = sr_payload();
$action = trim((string) ($_GET['action'] ?? $payload['action'] ?? ''));

try {
    if ($action === 'masterlists') {
        require_permission('ictd.service-report.view');
        sr_response([
            'success' => true,
            'data' => [
                'users' => $pdo->query("
                    SELECT eu.end_user_id AS id, eu.end_user_name AS text, eu.department_id, dep.department_name
                    FROM list_end_user eu
                    LEFT JOIN list_department dep ON dep.department_id = eu.department_id
                    ORDER BY eu.end_user_name ASC
                ")->fetchAll(PDO::FETCH_ASSOC) ?: [],
                'departments' => db_table_exists($pdo, 'list_department')
                    ? ($pdo->query('SELECT department_id AS id, department_name AS text FROM list_department ORDER BY department_name ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [])
                    : [],
                'complaints' => sr_text_value_options(sr_options($pdo, 'complain_list', 'id', 'complain_desc')),
                'troubleshoots' => sr_text_value_options(sr_options($pdo, 'troubleshoot_list', 'id', 'troubleshoot_desc')),
                'remarks' => db_table_exists($pdo, 'service_list')
                    ? sr_text_value_options(sr_options($pdo, 'service_list', 'id', 'service_desc'))
                    : sr_text_value_options(sr_options($pdo, 'service_remarks_list', 'id', 'remarks_desc')),
                'technicians' => db_table_exists($pdo, 'list_technical')
                    ? ($pdo->query("
                        SELECT
                            lt.technical_name AS id,
                            lt.technical_name AS text,
                            dep.department_name AS designation
                        FROM list_technical lt
                        LEFT JOIN list_department dep ON dep.department_id = lt.department_id
                        ORDER BY lt.technical_name ASC
                    ")->fetchAll(PDO::FETCH_ASSOC) ?: [])
                    : [],
                'descriptions' => sr_options($pdo, 'list_description', 'id', 'description_name'),
                'brands' => sr_options($pdo, 'list_brand', 'id', 'brand_name'),
                'statuses' => db_table_exists($pdo, 'list_equipment_status')
                    ? sr_text_value_options(sr_options($pdo, 'list_equipment_status', 'id', 'equipment_status'))
                    : [],
                'equipment' => sr_available_equipment($pdo),
                'supplies' => sr_available_supplies($pdo),
                'subscriptions' => sr_available_subscriptions($pdo),
            ],
        ]);
    }

    if ($action === 'view') {
        require_permission('ictd.service-report.view');
        $id = (int) ($_GET['id'] ?? $payload['id'] ?? 0);
        $stmt = $pdo->prepare("
            SELECT sr.*, eu.end_user_name, dep.department_name, edes.department_name AS employee_designation_name
            FROM service_report sr
            LEFT JOIN list_end_user eu ON eu.end_user_id = sr.end_user_id
            LEFT JOIN list_department dep ON dep.department_id = sr.department_id
            LEFT JOIN list_department edes ON edes.department_id = sr.employee_designation_id
            WHERE sr.id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$record) {
            sr_response(['success' => false, 'message' => 'Service report not found.'], 404);
        }
        foreach (['complain', 'troubleshoot', 'remarks_service_details', 'attending_technician'] as $field) {
            $record[$field . '_values'] = sr_decode_values($record[$field] ?? '');
        }
        $stmt = $pdo->prepare("
            SELECT sri.*, d.description_name, b.brand_name
            FROM service_report_items sri
            LEFT JOIN list_description d ON d.id = sri.description_id
            LEFT JOIN list_brand b ON b.id = sri.brand_id
            WHERE sri.service_report_id = ?
            ORDER BY sri.id ASC
        ");
        $stmt->execute([$id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($items as &$item) {
            $item['type'] = sr_normalize_item_type($item['type'] ?? 'equipment');
        }
        unset($item);
        sr_response(['success' => true, 'data' => $record, 'items' => $items]);
    }

    if ($action === 'assigned_items') {
        require_permission('ictd.service-report.view');
        $endUserId = (int) ($_GET['end_user_id'] ?? $payload['end_user_id'] ?? 0);
        sr_response([
            'success' => true,
            'items' => sr_assigned_items_for_user($pdo, $endUserId),
        ]);
    }

    if ($action === 'create' || $action === 'update') {
        require_permission('ictd.service-report.view');
        $id = (int) ($payload['id'] ?? 0);
        if ($action === 'update' && $id <= 0) {
            sr_response(['success' => false, 'message' => 'Invalid service report.'], 422);
        }
        $row = sr_normalize_report($payload, $pdo, $action === 'update' ? $id : null);
        if (!$row['end_user_id'] || !$row['department_id']) {
            sr_response(['success' => false, 'message' => 'End user and department are required.'], 422);
        }

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM service_report WHERE service_controlno = ? AND id <> ?');
        $stmt->execute([$row['service_controlno'], $id]);
        if ((int) $stmt->fetchColumn() > 0) {
            sr_response(['success' => false, 'message' => 'Service control number already exists.'], 422);
        }

        $items = sr_items_from_payload($payload);
        $user = current_user() ?: [];
        $pdo->beginTransaction();

        sr_upsert_masterlist_values($pdo, 'complain_list', 'complain_desc', sr_decode_values($row['complain'] ?? ''));
        sr_upsert_masterlist_values($pdo, 'troubleshoot_list', 'troubleshoot_desc', sr_decode_values($row['troubleshoot'] ?? ''));
        if (db_table_exists($pdo, 'service_list')) {
            sr_upsert_masterlist_values($pdo, 'service_list', 'service_desc', sr_decode_values($row['remarks_service_details'] ?? ''));
        } else {
            sr_upsert_masterlist_values($pdo, 'service_remarks_list', 'remarks_desc', sr_decode_values($row['remarks_service_details'] ?? ''));
        }
        if ($action === 'create') {
            $row['created_by'] = sr_clean_int($user['id'] ?? null) ?: 0;
            $columns = array_keys($row);
            $stmt = $pdo->prepare('INSERT INTO service_report (`' . implode('`,`', $columns) . '`) VALUES (' . implode(',', array_fill(0, count($columns), '?')) . ')');
            $stmt->execute(array_values($row));
            $id = (int) $pdo->lastInsertId();
        } else {
            $row['updated_at'] = date('Y-m-d H:i:s');
            $assignments = [];
            $values = [];
            foreach ($row as $column => $value) {
                $assignments[] = "`{$column}` = ?";
                $values[] = $value;
            }
            $values[] = $id;
            $stmt = $pdo->prepare('UPDATE service_report SET ' . implode(', ', $assignments) . ' WHERE id = ?');
            $stmt->execute($values);
        }

        $attachment = sr_store_pdf($id);
        if ($attachment) {
            $stmt = $pdo->prepare('UPDATE service_report SET attachment_pdf = ? WHERE id = ?');
            $stmt->execute([$attachment, $id]);
        }

        sr_sync_items($pdo, $id, $items, $row);
        $pdo->commit();

        log_activity($action === 'create' ? 'SERVICE_REPORT_CREATE' : 'SERVICE_REPORT_UPDATE', ucfirst($action) . ' service report ' . $row['service_controlno']);
        sr_response(['success' => true, 'message' => 'Service report saved successfully.', 'id' => $id, 'service_controlno' => $row['service_controlno']]);
    }

    if ($action === 'upload_pdf') {
        require_permission('ictd.service-report.view');
        $id = (int) ($payload['id'] ?? 0);
        if ($id <= 0) {
            sr_response(['success' => false, 'message' => 'Invalid service report.'], 422);
        }
        $attachment = sr_store_pdf($id);
        if (!$attachment) {
            sr_response(['success' => false, 'message' => 'No PDF file uploaded.'], 422);
        }
        sr_delete_pdf($pdo, $id);
        $stmt = $pdo->prepare('UPDATE service_report SET attachment_pdf = ? WHERE id = ?');
        $stmt->execute([$attachment, $id]);
        log_activity('SERVICE_REPORT_PDF_UPLOAD', 'Uploaded service report PDF #' . $id);
        sr_response(['success' => true, 'message' => 'Service report PDF uploaded successfully.']);
    }

    if ($action === 'remove_pdf') {
        require_permission('ictd.service-report.view');
        $id = (int) ($payload['id'] ?? 0);
        if ($id <= 0) {
            sr_response(['success' => false, 'message' => 'Invalid service report.'], 422);
        }
        sr_delete_pdf($pdo, $id);
        log_activity('SERVICE_REPORT_PDF_REMOVE', 'Removed service report PDF #' . $id);
        sr_response(['success' => true, 'message' => 'Service report PDF removed successfully.']);
    }

    if ($action === 'delete' || $action === 'archive') {
        require_permission('ictd.service-report.view');
        $id = (int) ($payload['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT service_controlno FROM service_report WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $controlNo = $stmt->fetchColumn();
        if (!$controlNo) {
            sr_response(['success' => false, 'message' => 'Service report not found.'], 404);
        }
        $pdo->prepare('UPDATE service_report SET is_archived = 1, archived_at = NOW() WHERE id = ?')->execute([$id]);
        log_activity('SERVICE_REPORT_ARCHIVE', 'Archived service report ' . $controlNo);
        sr_response(['success' => true, 'message' => 'Service report moved to archive.']);
    }

    if ($action === 'restore') {
        require_permission('ictd.service-report.view');
        $id = (int) ($payload['id'] ?? 0);
        $pdo->prepare('UPDATE service_report SET is_archived = 0, archived_at = NULL WHERE id = ?')->execute([$id]);
        log_activity('SERVICE_REPORT_RESTORE', 'Restored service report #' . $id);
        sr_response(['success' => true, 'message' => 'Service report restored.']);
    }

    if ($action === 'permanent_delete') {
        require_permission('ictd.service-report.view');
        $id = (int) ($payload['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT service_controlno FROM service_report WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $controlNo = $stmt->fetchColumn();
        if (!$controlNo) {
            sr_response(['success' => false, 'message' => 'Service report not found.'], 404);
        }
        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM service_report_items WHERE service_report_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM service_report WHERE id = ?')->execute([$id]);
        $pdo->commit();
        log_activity('SERVICE_REPORT_DELETE', 'Permanently deleted service report ' . $controlNo);
        sr_response(['success' => true, 'message' => 'Service report permanently deleted.']);
    }

    sr_response(['success' => false, 'message' => 'Invalid service report action.'], 400);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    sr_response(['success' => false, 'message' => 'Service report action failed: ' . $e->getMessage()], 500);
}
