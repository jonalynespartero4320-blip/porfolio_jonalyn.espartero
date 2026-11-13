<?php
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_login();
ensure_record_scope_schema($pdo);

header('Content-Type: application/json');

function equipment_action_response(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function equipment_action_payload(): array
{
    $raw = file_get_contents('php://input');
    $json = json_decode($raw ?: '', true);

    if (is_array($json)) {
        return $json;
    }

    return $_POST;
}

function equipment_action_clean_string($value): ?string
{
    $value = trim((string) ($value ?? ''));
    return $value === '' ? null : $value;
}

function equipment_action_clean_int($value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }

    return is_numeric($value) ? (int) $value : null;
}

function equipment_action_clean_price($value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }

    return is_numeric($value) ? (int) round((float) $value) : null;
}

function equipment_action_normalize_row(array $row): array
{
    $normalized = [
        'control_no' => equipment_action_clean_string($row['control_no'] ?? null),
        'computer_name' => equipment_action_clean_string($row['computer_name'] ?? null),
        'description_id' => equipment_action_clean_int($row['description_id'] ?? null),
        'brand_id' => equipment_action_clean_int($row['brand_id'] ?? null),
        'serialno' => equipment_action_clean_string($row['serialno'] ?? null),
        'os' => equipment_action_clean_string($row['os'] ?? null),
        'os_prokey' => equipment_action_clean_string($row['os_prokey'] ?? null),
        'ms_software' => equipment_action_clean_string($row['ms_software'] ?? null),
        'ms_uname' => equipment_action_clean_string($row['ms_uname'] ?? null),
        'ms_pass' => equipment_action_clean_string($row['ms_pass'] ?? null),
        'processor' => equipment_action_clean_string($row['processor'] ?? null),
        'memory' => equipment_action_clean_string($row['memory'] ?? null),
        'hdd' => equipment_action_clean_string($row['hdd'] ?? null),
        'video_card' => equipment_action_clean_string($row['video_card'] ?? null),
        'warranty_date' => equipment_action_clean_string($row['warranty_date'] ?? null),
        'no_yrs_warranty' => equipment_action_clean_int($row['no_yrs_warranty'] ?? null),
        'delivery_stat' => equipment_action_clean_string($row['delivery_stat'] ?? null) ?: 'To Follow',
        'eta_date' => equipment_action_clean_string($row['eta_date'] ?? null),
        'price' => equipment_action_clean_price($row['price'] ?? null),
        'property_no' => equipment_action_clean_string($row['property_no'] ?? null),
        'old_control_no' => equipment_action_clean_string($row['old_control_no'] ?? null),
        'batch_id' => equipment_action_clean_string($row['batch_id'] ?? null),
        'supplier_id' => equipment_action_clean_int($row['supplier_id'] ?? null),
        'date_delivered' => equipment_action_clean_string($row['date_delivered'] ?? null),
        'equipment_status_id' => equipment_action_clean_int($row['equipment_status_id'] ?? null),
    ];

    if (!empty($normalized['no_yrs_warranty'])) {
        $normalized['warranty_date'] = null;
    } elseif (!empty($normalized['warranty_date'])) {
        $normalized['no_yrs_warranty'] = null;
    }

    if (strtolower((string) $normalized['delivery_stat']) === 'delivered') {
        $normalized['eta_date'] = null;
    }

    return $normalized;
}

function equipment_action_archived_control_exists(PDO $pdo, string $controlNo): bool
{
    if (!db_table_exists($pdo, 'archive_equipment')) {
        return false;
    }

    $reuseClause = db_column_exists($pdo, 'archive_equipment', 'control_no_reused')
        ? ' AND (COALESCE(control_no_reused, 0) = 0 OR reused_equipment_id IS NOT NULL)'
        : '';
    $stmt = $pdo->prepare('SELECT id FROM archive_equipment WHERE control_no = ?' . $reuseClause . ' LIMIT 1');
    $stmt->execute([$controlNo]);

    return (bool) $stmt->fetchColumn();
}

function equipment_action_validate_row(PDO $pdo, array $row, ?int $ignoreId = null, bool $reuseArchivedControlNo = false): array
{
    $errors = [];

    if (empty($row['control_no'])) {
        $errors[] = 'Control No is required.';
    }

    if (!empty($row['control_no'])) {
        $sql = 'SELECT id FROM equipment WHERE control_no = ?';
        $params = [$row['control_no']];

        if ($ignoreId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $ignoreId;
        }

        $sql .= ' LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        if ($stmt->fetch()) {
            $errors[] = 'Control No "' . $row['control_no'] . '" already exists.';
        }

        if (!$reuseArchivedControlNo && equipment_action_archived_control_exists($pdo, $row['control_no'])) {
            $errors[] = 'Control No "' . $row['control_no'] . '" already exists in the equipment archive. Enable reuse only if this is intentionally a new item using the same control no.';
        }
    }

    return $errors;
}

function equipment_action_year_suffix(string $dateDelivered): string
{
    $timestamp = strtotime($dateDelivered);
    if ($timestamp === false) {
        throw new InvalidArgumentException('Invalid Date Delivered.');
    }

    return date('y', $timestamp);
}

function equipment_action_next_control_numbers(PDO $pdo, string $dateDelivered, int $count, ?int $ignoreEquipmentId = null): array
{
    $yearSuffix = equipment_action_year_suffix($dateDelivered);
    $prefix = 'IT-' . $yearSuffix . '-';

    $controlNos = [];
    $sql = 'SELECT control_no FROM equipment WHERE control_no LIKE ?';
    $params = [$prefix . '%'];
    if ($ignoreEquipmentId !== null) {
        $sql .= ' AND id <> ?';
        $params[] = $ignoreEquipmentId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
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
        if (preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', (string) $controlNo, $matches)) {
            $maxSequence = max($maxSequence, (int) $matches[1]);
        }
    }

    $numbers = [];
    for ($i = 1; $i <= $count; $i++) {
        $numbers[] = $prefix . str_pad((string) ($maxSequence + $i), 4, '0', STR_PAD_LEFT);
    }

    return $numbers;
}

function equipment_action_control_year_suffix(?string $controlNo): ?string
{
    if (preg_match('/^IT-(\d{2})-\d+$/', trim((string) $controlNo), $matches)) {
        return $matches[1];
    }

    return null;
}

function equipment_action_should_generate_control_no(array $currentRow, array $nextRow): bool
{
    if (empty($nextRow['date_delivered'])) {
        return false;
    }

    $nextYearSuffix = equipment_action_year_suffix((string) $nextRow['date_delivered']);
    $currentControlNo = equipment_action_clean_string($currentRow['control_no'] ?? null);
    $postedControlNo = equipment_action_clean_string($nextRow['control_no'] ?? null);

    if (!$postedControlNo || !$currentControlNo) {
        return true;
    }

    $currentDateDelivered = equipment_action_clean_string($currentRow['date_delivered'] ?? null);
    $dateChanged = $currentDateDelivered !== equipment_action_clean_string($nextRow['date_delivered'] ?? null);
    $controlChanged = $postedControlNo !== $currentControlNo;
    $postedYearSuffix = equipment_action_control_year_suffix($postedControlNo);

    return $dateChanged && !$controlChanged && $postedYearSuffix !== $nextYearSuffix;
}

function equipment_action_mark_reused_archive_control(PDO $pdo, string $controlNo, int $equipmentId): void
{
    if (!db_table_exists($pdo, 'archive_equipment') || !db_column_exists($pdo, 'archive_equipment', 'control_no_reused')) {
        return;
    }

    $stmt = $pdo->prepare("
        UPDATE archive_equipment
        SET reused_equipment_id = ?
        WHERE control_no = ?
          AND COALESCE(control_no_reused, 0) = 1
          AND reused_equipment_id IS NULL
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$equipmentId, $controlNo]);
}

function equipment_action_fetch_masterlist(PDO $pdo): array
{
    $descriptions = $pdo->query('SELECT id, description_name AS text FROM list_description ORDER BY description_name ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $brands = $pdo->query('SELECT id, brand_name AS text FROM list_brand ORDER BY brand_name ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $suppliers = db_table_exists($pdo, 'list_suppliers')
        ? ($pdo->query('SELECT id, supplier_name AS text FROM list_suppliers ORDER BY supplier_name ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [])
        : [];
    $statuses = db_table_exists($pdo, 'list_equipment_status')
        ? ($pdo->query('SELECT id, equipment_status AS text FROM list_equipment_status ORDER BY equipment_status ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [])
        : [];

    return compact('descriptions', 'brands', 'suppliers', 'statuses');
}

function equipment_action_is_auto_unassign_status(?string $statusName): bool
{
    return in_array(strtolower(trim((string) $statusName)), ['defective', 'condemned', 'for disposal', 'disposed'], true);
}

function equipment_action_write_assignment_history(PDO $pdo, string $action, array $record, string $details): void
{
    if (!db_table_exists($pdo, 'assignment_history')) {
        return;
    }

    $user = current_user() ?: ($_SESSION['user'] ?? []);
    $stmt = $pdo->prepare("
        INSERT INTO assignment_history
            (assigned_id, action, item_type, control_no, equipment_id, old_end_user_id, new_end_user_id, old_department_id, new_department_id, details, performed_by, performed_username)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        equipment_action_clean_int($record['assigned_id'] ?? null),
        $action,
        'equipment',
        equipment_action_clean_string($record['control_no'] ?? null),
        equipment_action_clean_int($record['equipment_id'] ?? null),
        equipment_action_clean_int($record['end_user_id'] ?? null),
        null,
        equipment_action_clean_int($record['department_id'] ?? null),
        null,
        $details,
        equipment_action_clean_int($user['id'] ?? null),
        equipment_action_clean_string($user['username'] ?? 'Guest'),
    ]);
}

function equipment_action_auto_unassign(PDO $pdo, int $equipmentId, string $statusName): int
{
    if (!db_table_exists($pdo, 'assigned_record')) {
        return 0;
    }

    $stmt = $pdo->prepare('SELECT * FROM equipment WHERE id = ? LIMIT 1');
    $stmt->execute([$equipmentId]);
    $equipment = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$equipment) {
        return 0;
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM assigned_record
        WHERE equipment_id = ?
           OR control_no = ?
           OR control_no = ?
    ");
    $stmt->execute([
        $equipmentId,
        $equipment['control_no'] ?? '',
        $equipment['serialno'] ?? '',
    ]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($records as $record) {
        equipment_action_write_assignment_history($pdo, 'auto_unassign', $record, 'Auto-unassigned because equipment status changed to ' . $statusName . '.');
    }

    $stmt = $pdo->prepare("
        DELETE FROM assigned_record
        WHERE equipment_id = ?
           OR control_no = ?
           OR control_no = ?
    ");
    $stmt->execute([
        $equipmentId,
        $equipment['control_no'] ?? '',
        $equipment['serialno'] ?? '',
    ]);
    $deleted = $stmt->rowCount();

    $stmt = $pdo->prepare('UPDATE equipment SET assigned_id = NULL, department_id = NULL WHERE id = ?');
    $stmt->execute([$equipmentId]);

    return $deleted;
}

function equipment_action_ensure_archive_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS archive_equipment (
            id INT AUTO_INCREMENT PRIMARY KEY,
            original_id INT NULL,
            control_no VARCHAR(255) NULL,
            computer_name VARCHAR(255) NULL,
            description_id INT NULL,
            brand_id INT NULL,
            serialno VARCHAR(50) NULL,
            os VARCHAR(255) NULL,
            os_prokey VARCHAR(100) NULL,
            ms_software VARCHAR(255) NULL,
            ms_uname VARCHAR(255) NULL,
            ms_pass VARCHAR(255) NULL,
            processor VARCHAR(255) NULL,
            memory VARCHAR(255) NULL,
            hdd VARCHAR(255) NULL,
            video_card VARCHAR(255) NULL,
            warranty_date VARCHAR(255) NULL,
            no_yrs_warranty INT NULL,
            delivery_stat VARCHAR(25) NULL,
            eta_date VARCHAR(20) NULL,
            price INT NULL,
            property_no VARCHAR(255) NULL,
            old_control_no VARCHAR(255) NULL,
            batch_id VARCHAR(255) NULL,
            supplier_id INT NULL,
            date_delivered VARCHAR(20) NULL,
            assigned_id INT NULL,
            transmittal_id VARCHAR(255) NULL,
            created_at TIMESTAMP NULL,
            department_id INT NULL,
            assigned_end_user_id INT NULL,
            assigned_end_user_name VARCHAR(255) NULL,
            assigned_department_name VARCHAR(255) NULL,
            assignment_snapshot LONGTEXT NULL,
            equipment_status_id INT NULL,
            control_no_reused TINYINT(1) NOT NULL DEFAULT 0,
            reused_at DATETIME NULL,
            reused_by INT NULL,
            reused_equipment_id INT NULL,
            archive_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            archived_by INT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $columns = [
        'original_id' => 'INT NULL AFTER id',
        'computer_name' => 'VARCHAR(255) NULL AFTER control_no',
        'assigned_id' => 'INT NULL AFTER date_delivered',
        'transmittal_id' => 'VARCHAR(255) NULL AFTER assigned_id',
        'department_id' => 'INT NULL AFTER created_at',
        'assigned_end_user_id' => 'INT NULL AFTER department_id',
        'assigned_end_user_name' => 'VARCHAR(255) NULL AFTER assigned_end_user_id',
        'assigned_department_name' => 'VARCHAR(255) NULL AFTER assigned_end_user_name',
        'assignment_snapshot' => 'LONGTEXT NULL AFTER assigned_department_name',
        'equipment_status_id' => 'INT NULL AFTER department_id',
        'control_no_reused' => 'TINYINT(1) NOT NULL DEFAULT 0 AFTER equipment_status_id',
        'reused_at' => 'DATETIME NULL AFTER control_no_reused',
        'reused_by' => 'INT NULL AFTER reused_at',
        'reused_equipment_id' => 'INT NULL AFTER reused_by',
        'archived_by' => 'INT NULL AFTER archive_at',
    ];

    foreach ($columns as $column => $definition) {
        if (!db_column_exists($pdo, 'archive_equipment', $column)) {
            $pdo->exec("ALTER TABLE archive_equipment ADD COLUMN {$column} {$definition}");
        }
    }

    try {
        $pdo->exec('ALTER TABLE archive_equipment MODIFY control_no VARCHAR(255) NULL');
        $pdo->exec('ALTER TABLE archive_equipment MODIFY description_id INT NULL');
    } catch (Throwable $e) {
        // Older MySQL variants may reject no-op MODIFY statements; archive actions can still continue.
    }
}

function equipment_action_table_columns(PDO $pdo, string $table): array
{
    $stmt = $pdo->prepare("
        SELECT COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
    ");
    $stmt->execute([$table]);

    return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
}

function equipment_action_ensure_assignment_archive_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS archive_assigned_record (
            id INT AUTO_INCREMENT PRIMARY KEY,
            original_assigned_id INT NULL,
            item_type VARCHAR(30) NOT NULL DEFAULT 'equipment',
            equipment_id INT NULL,
            control_no VARCHAR(255) NULL,
            end_user_id INT NULL,
            end_user_name VARCHAR(255) NULL,
            department_id INT NULL,
            department_name VARCHAR(255) NULL,
            connection_ip VARCHAR(255) NULL,
            internet VARCHAR(50) NULL,
            wifi VARCHAR(50) NULL,
            program_installed TEXT NULL,
            assigned_date DATE NULL,
            months_since_assigned VARCHAR(100) NULL,
            equipment_analysis TEXT NULL,
            attachment_pdf VARCHAR(255) NULL,
            assignment_snapshot LONGTEXT NULL,
            item_snapshot LONGTEXT NULL,
            archive_reason VARCHAR(255) NULL,
            archive_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            archived_by INT NULL,
            archived_username VARCHAR(255) NULL,
            INDEX idx_archive_assigned_original (original_assigned_id),
            INDEX idx_archive_assigned_control (control_no),
            INDEX idx_archive_assigned_equipment (equipment_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $columns = [
        'original_assigned_id' => 'INT NULL AFTER id',
        'item_type' => "VARCHAR(30) NOT NULL DEFAULT 'equipment' AFTER original_assigned_id",
        'equipment_id' => 'INT NULL AFTER item_type',
        'control_no' => 'VARCHAR(255) NULL AFTER equipment_id',
        'end_user_id' => 'INT NULL AFTER control_no',
        'end_user_name' => 'VARCHAR(255) NULL AFTER end_user_id',
        'department_id' => 'INT NULL AFTER end_user_name',
        'department_name' => 'VARCHAR(255) NULL AFTER department_id',
        'connection_ip' => 'VARCHAR(255) NULL AFTER department_name',
        'internet' => 'VARCHAR(50) NULL AFTER connection_ip',
        'wifi' => 'VARCHAR(50) NULL AFTER internet',
        'program_installed' => 'TEXT NULL AFTER wifi',
        'assigned_date' => 'DATE NULL AFTER program_installed',
        'months_since_assigned' => 'VARCHAR(100) NULL AFTER assigned_date',
        'equipment_analysis' => 'TEXT NULL AFTER months_since_assigned',
        'attachment_pdf' => 'VARCHAR(255) NULL AFTER equipment_analysis',
        'assignment_snapshot' => 'LONGTEXT NULL AFTER attachment_pdf',
        'item_snapshot' => 'LONGTEXT NULL AFTER assignment_snapshot',
        'archive_reason' => 'VARCHAR(255) NULL AFTER item_snapshot',
        'archived_by' => 'INT NULL AFTER archive_at',
        'archived_username' => 'VARCHAR(255) NULL AFTER archived_by',
    ];

    foreach ($columns as $column => $definition) {
        if (!db_column_exists($pdo, 'archive_assigned_record', $column)) {
            $pdo->exec("ALTER TABLE archive_assigned_record ADD COLUMN {$column} {$definition}");
        }
    }
}

function equipment_action_archive_assignment_records(PDO $pdo, array $equipment): int
{
    if (!db_table_exists($pdo, 'assigned_record')) {
        return 0;
    }

    $stmt = $pdo->prepare("
        SELECT
            ar.*,
            eu.end_user_name,
            dep.department_name
        FROM assigned_record ar
        LEFT JOIN list_end_user eu ON eu.end_user_id = ar.end_user_id
        LEFT JOIN list_department dep ON dep.department_id = ar.department_id
        WHERE ar.assigned_id = ?
           OR ar.equipment_id = ?
           OR (
                TRIM(COALESCE(ar.control_no, '')) <> ''
                AND (ar.control_no = ? OR ar.control_no = ?)
           )
    ");
    $stmt->execute([
        (int) ($equipment['assigned_id'] ?? 0),
        (int) ($equipment['id'] ?? 0),
        $equipment['control_no'] ?? null,
        $equipment['serialno'] ?? null,
    ]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $user = current_user() ?: [];
    $archiveStmt = $pdo->prepare("
        INSERT INTO archive_assigned_record
            (original_assigned_id, item_type, equipment_id, control_no, end_user_id, end_user_name, department_id, department_name,
             connection_ip, internet, wifi, program_installed, assigned_date, months_since_assigned, equipment_analysis, attachment_pdf,
             assignment_snapshot, item_snapshot, archive_reason, archived_by, archived_username)
        VALUES
            (?, 'equipment', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($records as $record) {
        $archiveStmt->execute([
            equipment_action_clean_int($record['assigned_id'] ?? null),
            equipment_action_clean_int($record['equipment_id'] ?? ($equipment['id'] ?? null)),
            $record['control_no'] ?? ($equipment['control_no'] ?? null),
            equipment_action_clean_int($record['end_user_id'] ?? null),
            $record['end_user_name'] ?? null,
            equipment_action_clean_int($record['department_id'] ?? null),
            $record['department_name'] ?? null,
            $record['connection_ip'] ?? null,
            $record['internet'] ?? null,
            $record['wifi'] ?? null,
            $record['program_installed'] ?? null,
            $record['assigned_date'] ?? null,
            $record['months_since_assigned'] ?? null,
            $record['equipment_analysis'] ?? null,
            $record['attachment_pdf'] ?? null,
            json_encode($record, JSON_UNESCAPED_UNICODE),
            json_encode($equipment, JSON_UNESCAPED_UNICODE),
            'Equipment moved to archive',
            equipment_action_clean_int($user['id'] ?? null),
            equipment_action_clean_string($user['username'] ?? null),
        ]);
        equipment_action_write_assignment_history($pdo, 'archive_unassign', $record, 'Auto-unassigned because equipment was moved to archive.');
    }

    $stmt = $pdo->prepare("
        DELETE FROM assigned_record
        WHERE assigned_id = ?
           OR equipment_id = ?
           OR (
                TRIM(COALESCE(control_no, '')) <> ''
                AND (control_no = ? OR control_no = ?)
           )
    ");
    $stmt->execute([
        (int) ($equipment['assigned_id'] ?? 0),
        (int) ($equipment['id'] ?? 0),
        $equipment['control_no'] ?? null,
        $equipment['serialno'] ?? null,
    ]);

    return $stmt->rowCount();
}

function equipment_action_archive_blocking_assignment(PDO $pdo, array $equipment): ?array
{
    if (db_table_exists($pdo, 'assigned_record')) {
        $stmt = $pdo->prepare("
            SELECT
                ar.*,
                eu.end_user_name,
                dep.department_name
            FROM assigned_record ar
            LEFT JOIN list_end_user eu ON eu.end_user_id = ar.end_user_id
            LEFT JOIN list_department dep ON dep.department_id = ar.department_id
            WHERE ar.assigned_id = ?
               OR ar.equipment_id = ?
               OR (
                    TRIM(COALESCE(ar.control_no, '')) <> ''
                    AND (ar.control_no = ? OR ar.control_no = ?)
               )
            LIMIT 1
        ");
        $stmt->execute([
            (int) ($equipment['assigned_id'] ?? 0),
            (int) ($equipment['id'] ?? 0),
            $equipment['control_no'] ?? null,
            $equipment['serialno'] ?? null,
        ]);
        $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($assignment) {
            return $assignment;
        }
    }

    if (!empty($equipment['assigned_id']) || !empty($equipment['department_id'])) {
        $fallback = [
            'end_user_name' => 'an assigned user',
            'department_name' => null,
        ];
        if (!empty($equipment['department_id']) && db_table_exists($pdo, 'list_department')) {
            $stmt = $pdo->prepare('SELECT department_name FROM list_department WHERE department_id = ? LIMIT 1');
            $stmt->execute([(int) $equipment['department_id']]);
            $fallback['department_name'] = $stmt->fetchColumn() ?: null;
        }

        return $fallback;
    }

    return null;
}

if (!db_table_exists($pdo, 'equipment')) {
    equipment_action_response(['success' => false, 'message' => 'Equipment table is missing.'], 500);
}

$payload = equipment_action_payload();
$action = trim((string) ($_GET['action'] ?? $payload['action'] ?? ''));

try {
    if ($action === 'masterlists') {
        equipment_action_response(['success' => true, 'data' => equipment_action_fetch_masterlist($pdo)]);
    }

    if ($action === 'next_control_numbers') {
        require_permission('ictd.equipment.create');
        $dateDelivered = equipment_action_clean_string($_GET['date_delivered'] ?? $payload['date_delivered'] ?? null);
        $count = max(1, (int) ($_GET['count'] ?? $payload['count'] ?? 1));

        if (!$dateDelivered) {
            equipment_action_response(['success' => false, 'message' => 'Date Delivered is required.'], 422);
        }

        equipment_action_response([
            'success' => true,
            'numbers' => equipment_action_next_control_numbers($pdo, $dateDelivered, $count),
        ]);
    }

    if ($action === 'view') {
        require_permission('ictd.equipment.view');
        $id = (int) ($_GET['id'] ?? $payload['id'] ?? 0);
        $hasEquipmentStatusColumn = db_column_exists($pdo, 'equipment', 'equipment_status_id');
        $statusSelect = $hasEquipmentStatusColumn ? 'es.equipment_status,' : 'NULL AS equipment_status,';
        $statusJoin = $hasEquipmentStatusColumn ? 'LEFT JOIN list_equipment_status es ON es.id = e.equipment_status_id' : '';

        $stmt = $pdo->prepare("
            SELECT
                e.*,
                d.description_name,
                b.brand_name,
                s.supplier_name,
                {$statusSelect}
                dep.department_name
            FROM equipment e
            LEFT JOIN list_description d ON d.id = e.description_id
            LEFT JOIN list_brand b ON b.id = e.brand_id
            LEFT JOIN list_suppliers s ON s.id = e.supplier_id
            {$statusJoin}
            LEFT JOIN list_department dep ON dep.department_id = e.department_id
            WHERE e.id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            equipment_action_response(['success' => false, 'message' => 'Equipment record not found.'], 404);
        }

        equipment_action_response(['success' => true, 'data' => $row]);
    }

    if ($action === 'status_create') {
        require_permission('ictd.equipment.edit');
        $statusName = equipment_action_clean_string($payload['equipment_status'] ?? null);
        if (!$statusName) {
            equipment_action_response(['success' => false, 'message' => 'Status name is required.'], 422);
        }
        if (in_array(strtolower($statusName), ['assigned', 'unassigned'], true)) {
            equipment_action_response(['success' => false, 'message' => 'Assigned and unassigned are assignment states, not equipment statuses.'], 422);
        }

        $stmt = $pdo->prepare('INSERT INTO list_equipment_status (equipment_status) VALUES (?)');
        $stmt->execute([$statusName]);

        log_activity('EQUIPMENT_STATUS_CREATE', 'Created equipment status: ' . $statusName);
        equipment_action_response(['success' => true, 'message' => 'Equipment status added successfully.']);
    }

    if ($action === 'status_update') {
        require_permission('ictd.equipment.edit');
        $id = (int) ($payload['id'] ?? 0);
        $statusName = equipment_action_clean_string($payload['equipment_status'] ?? null);
        if ($id <= 0 || !$statusName) {
            equipment_action_response(['success' => false, 'message' => 'Invalid status details.'], 422);
        }
        if (in_array(strtolower($statusName), ['assigned', 'unassigned'], true)) {
            equipment_action_response(['success' => false, 'message' => 'Assigned and unassigned are assignment states, not equipment statuses.'], 422);
        }

        $stmt = $pdo->prepare('UPDATE list_equipment_status SET equipment_status = ? WHERE id = ?');
        $stmt->execute([$statusName, $id]);

        log_activity('EQUIPMENT_STATUS_UPDATE', 'Updated equipment status ID: ' . $id);
        equipment_action_response(['success' => true, 'message' => 'Equipment status updated successfully.']);
    }

    if ($action === 'status_delete') {
        require_permission('ictd.equipment.edit');
        $id = (int) ($payload['id'] ?? 0);
        if ($id <= 0) {
            equipment_action_response(['success' => false, 'message' => 'Invalid status.'], 422);
        }

        if (db_column_exists($pdo, 'equipment', 'equipment_status_id')) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM equipment WHERE equipment_status_id = ?');
            $stmt->execute([$id]);
            if ((int) $stmt->fetchColumn() > 0) {
                equipment_action_response(['success' => false, 'message' => 'This status is being used by equipment records.'], 422);
            }
        }

        $stmt = $pdo->prepare('DELETE FROM list_equipment_status WHERE id = ?');
        $stmt->execute([$id]);

        log_activity('EQUIPMENT_STATUS_DELETE', 'Deleted equipment status ID: ' . $id);
        equipment_action_response(['success' => true, 'message' => 'Equipment status deleted successfully.']);
    }

    if ($action === 'mark_status') {
        require_permission('ictd.equipment.edit');
        if (!db_column_exists($pdo, 'equipment', 'equipment_status_id')) {
            equipment_action_response(['success' => false, 'message' => 'Run the equipment status SQL migration first.'], 422);
        }

        $equipmentId = (int) ($payload['id'] ?? 0);
        $statusId = equipment_action_clean_int($payload['equipment_status_id'] ?? null);
        if ($equipmentId <= 0) {
            equipment_action_response(['success' => false, 'message' => 'Invalid equipment record.'], 422);
        }

        $statusName = null;
        if ($statusId !== null) {
            $stmt = $pdo->prepare('SELECT equipment_status FROM list_equipment_status WHERE id = ? LIMIT 1');
            $stmt->execute([$statusId]);
            $statusName = (string) ($stmt->fetchColumn() ?: '');
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare('UPDATE equipment SET equipment_status_id = ? WHERE id = ?');
        $stmt->execute([$statusId, $equipmentId]);
        $unassigned = equipment_action_is_auto_unassign_status($statusName)
            ? equipment_action_auto_unassign($pdo, $equipmentId, $statusName)
            : 0;
        $pdo->commit();

        log_activity('EQUIPMENT_STATUS_MARK', 'Marked equipment ID ' . $equipmentId . ' with status ID ' . ($statusId ?: 'none') . ($unassigned ? ' | auto_unassigned=' . $unassigned : ''));
        equipment_action_response(['success' => true, 'message' => 'Equipment status updated successfully.' . ($unassigned ? ' Linked assignment was automatically removed.' : '')]);
    }

    if ($action === 'batch_create') {
        require_permission('ictd.equipment.create');

        $rows = $payload['rows'] ?? [];
        if (!is_array($rows) || empty($rows)) {
            equipment_action_response(['success' => false, 'message' => 'Add at least one equipment row.'], 422);
        }

        $batchSupplierId = equipment_action_clean_int($payload['batch_supplier_id'] ?? null);
        $batchDateDelivered = equipment_action_clean_string($payload['batch_date_delivered'] ?? null);
        $reuseControlNo = equipment_action_clean_string($payload['reuse_control_no'] ?? null);

        if (!$batchSupplierId || !$batchDateDelivered) {
            equipment_action_response(['success' => false, 'message' => 'Supplier and Date Delivered are required for the batch.'], 422);
        }
        if ($reuseControlNo && count($rows) !== 1) {
            equipment_action_response(['success' => false, 'message' => 'Reused control numbers can only be saved one equipment record at a time.'], 422);
        }
        if ($reuseControlNo) {
            equipment_action_ensure_archive_schema($pdo);
            $stmt = $pdo->prepare('SELECT id FROM equipment WHERE control_no = ? LIMIT 1');
            $stmt->execute([$reuseControlNo]);
            if ($stmt->fetchColumn()) {
                equipment_action_response(['success' => false, 'message' => 'Control No ' . $reuseControlNo . ' is already active and cannot be reused again.'], 422);
            }

            $stmt = $pdo->prepare('
                SELECT id
                FROM archive_equipment
                WHERE control_no = ?
                  AND COALESCE(control_no_reused, 0) = 1
                  AND reused_equipment_id IS NULL
                LIMIT 1
            ');
            $stmt->execute([$reuseControlNo]);
            if (!$stmt->fetchColumn()) {
                equipment_action_response(['success' => false, 'message' => 'Control No ' . $reuseControlNo . ' is not ready for reuse from the archive.'], 422);
            }
        }

        $batchId = 'BATCH-' . date('Ymd-His') . '-' . random_int(100, 999);
        $createdBy = (int) ((current_user()['id'] ?? $_SESSION['user']['id'] ?? 0) ?: 0);
        $normalizedRows = [];

        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            $normalized = equipment_action_normalize_row($row);
            if (!db_column_exists($pdo, 'equipment', 'equipment_status_id')) {
                unset($normalized['equipment_status_id']);
            }
            $normalized['batch_id'] = $normalized['batch_id'] ?: $batchId;
            $normalized['supplier_id'] = $batchSupplierId;
            $normalized['date_delivered'] = $batchDateDelivered;
            $normalized['control_no'] = $reuseControlNo ?: 'PENDING';
            $normalized['created_by'] = $createdBy ?: null;

            $errors = $reuseControlNo ? equipment_action_validate_row($pdo, $normalized, null, true) : [];

            if (!empty($errors)) {
                equipment_action_response([
                    'success' => false,
                    'message' => 'Row ' . ($index + 1) . ': ' . implode(' ', $errors),
                ], 422);
            }

            $normalizedRows[] = $normalized;
        }

        if (empty($normalizedRows)) {
            equipment_action_response(['success' => false, 'message' => 'No valid equipment rows to save.'], 422);
        }

        $columns = array_keys($normalizedRows[0]);
        $placeholders = implode(',', array_fill(0, count($columns), '?'));
        $sql = 'INSERT INTO equipment (`' . implode('`,`', $columns) . '`) VALUES (' . $placeholders . ')';
        $stmt = $pdo->prepare($sql);

        $pdo->beginTransaction();
        if (!$reuseControlNo) {
            $controlNumbers = equipment_action_next_control_numbers($pdo, $batchDateDelivered, count($normalizedRows));
            foreach ($normalizedRows as $index => $row) {
                $normalizedRows[$index]['control_no'] = $controlNumbers[$index];
            }
        }

        foreach ($normalizedRows as $row) {
            $stmt->execute(array_values($row));
            equipment_action_mark_reused_archive_control($pdo, (string) $row['control_no'], (int) $pdo->lastInsertId());
        }
        $pdo->commit();

        log_activity('EQUIPMENT_BATCH_CREATE', 'Created equipment batch ' . $batchId . ' with ' . count($normalizedRows) . ' record(s).');

        equipment_action_response([
            'success' => true,
            'message' => count($normalizedRows) . ' equipment record(s) saved successfully.',
            'batch_id' => $batchId,
        ]);
    }

    if ($action === 'update') {
        require_permission('ictd.equipment.edit');
        $id = (int) ($payload['id'] ?? 0);
        if ($id <= 0) {
            equipment_action_response(['success' => false, 'message' => 'Invalid equipment record.'], 422);
        }

        $stmt = $pdo->prepare('SELECT id, control_no, date_delivered FROM equipment WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $currentEquipment = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$currentEquipment) {
            equipment_action_response(['success' => false, 'message' => 'Equipment record not found.'], 404);
        }

        $row = equipment_action_normalize_row($payload);
        unset($row['batch_id']);
        if (!db_column_exists($pdo, 'equipment', 'equipment_status_id')) {
            unset($row['equipment_status_id']);
        }

        $reuseArchivedControlNo = !empty($payload['reuse_archived_control_no']);
        if (!$reuseArchivedControlNo && equipment_action_should_generate_control_no($currentEquipment, $row)) {
            $row['control_no'] = equipment_action_next_control_numbers($pdo, (string) $row['date_delivered'], 1, $id)[0];
        }

        $errors = equipment_action_validate_row($pdo, $row, $id, $reuseArchivedControlNo);
        if (!empty($errors)) {
            equipment_action_response([
                'success' => false,
                'message' => implode(' ', $errors),
                'archived_control_conflict' => !empty($row['control_no']) && !$reuseArchivedControlNo && equipment_action_archived_control_exists($pdo, $row['control_no']),
            ], 422);
        }

        $statusName = null;
        if (array_key_exists('equipment_status_id', $row) && $row['equipment_status_id'] !== null) {
            $stmt = $pdo->prepare('SELECT equipment_status FROM list_equipment_status WHERE id = ? LIMIT 1');
            $stmt->execute([$row['equipment_status_id']]);
            $statusName = $stmt->fetchColumn() ?: null;
        }

        $assignments = [];
        $values = [];
        foreach ($row as $column => $value) {
            $assignments[] = "`{$column}` = ?";
            $values[] = $value;
        }
        $values[] = $id;

        $pdo->beginTransaction();
        $stmt = $pdo->prepare('UPDATE equipment SET ' . implode(', ', $assignments) . ' WHERE id = ?');
        $stmt->execute($values);
        $unassigned = equipment_action_is_auto_unassign_status($statusName)
            ? equipment_action_auto_unassign($pdo, $id, (string) $statusName)
            : 0;
        $pdo->commit();

        log_activity('EQUIPMENT_UPDATE', 'Updated equipment ID: ' . $id . ($unassigned ? ' | auto_unassigned=' . $unassigned : ''));

        equipment_action_response(['success' => true, 'message' => 'Equipment record updated successfully.' . ($unassigned ? ' Linked assignment was automatically removed.' : '')]);
    }

    if ($action === 'delete') {
        require_permission('ictd.equipment.delete');
        $id = (int) ($payload['id'] ?? $_POST['id'] ?? 0);
        if ($id <= 0) {
            equipment_action_response(['success' => false, 'message' => 'Invalid equipment record.'], 422);
        }

        equipment_action_ensure_archive_schema($pdo);
        equipment_action_ensure_assignment_archive_schema($pdo);

        $stmt = $pdo->prepare('SELECT * FROM equipment WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$record) {
            equipment_action_response(['success' => false, 'message' => 'Equipment record not found.'], 404);
        }
        $blockingAssignment = equipment_action_archive_blocking_assignment($pdo, $record);
        $assignmentNotice = '';
        if ($blockingAssignment) {
            $assignedTo = trim((string) ($blockingAssignment['end_user_name'] ?? 'an assigned user'));
            $department = trim((string) ($blockingAssignment['department_name'] ?? ''));
            $assignmentNotice = $department !== '' ? $assignedTo . ' (' . $department . ')' : $assignedTo;
        }

        $equipmentColumns = equipment_action_table_columns($pdo, 'equipment');
        $archiveColumns = equipment_action_table_columns($pdo, 'archive_equipment');
        $copyColumns = array_values(array_filter($equipmentColumns, static function (string $column) use ($archiveColumns): bool {
            return $column !== 'id' && in_array($column, $archiveColumns, true);
        }));

        $archiveData = [];
        foreach ($copyColumns as $column) {
            $archiveData[$column] = $record[$column] ?? null;
        }
        if (in_array('original_id', $archiveColumns, true)) {
            $archiveData['original_id'] = (int) $record['id'];
        }
        if (in_array('archived_by', $archiveColumns, true)) {
            $user = current_user() ?: [];
            $archiveData['archived_by'] = equipment_action_clean_int($user['id'] ?? null);
        }
        if ($blockingAssignment) {
            if (in_array('assigned_end_user_id', $archiveColumns, true)) {
                $archiveData['assigned_end_user_id'] = equipment_action_clean_int($blockingAssignment['end_user_id'] ?? null);
            }
            if (in_array('assigned_end_user_name', $archiveColumns, true)) {
                $archiveData['assigned_end_user_name'] = equipment_action_clean_string($blockingAssignment['end_user_name'] ?? null);
            }
            if (in_array('assigned_department_name', $archiveColumns, true)) {
                $archiveData['assigned_department_name'] = equipment_action_clean_string($blockingAssignment['department_name'] ?? null);
            }
            if (in_array('assignment_snapshot', $archiveColumns, true)) {
                $archiveData['assignment_snapshot'] = json_encode($blockingAssignment, JSON_UNESCAPED_UNICODE);
            }
        }

        $columns = array_keys($archiveData);
        $placeholders = implode(',', array_fill(0, count($columns), '?'));

        $pdo->beginTransaction();
        $unassigned = equipment_action_archive_assignment_records($pdo, $record);
        $stmt = $pdo->prepare('INSERT INTO archive_equipment (`' . implode('`,`', $columns) . '`) VALUES (' . $placeholders . ')');
        $stmt->execute(array_values($archiveData));
        $stmt = $pdo->prepare('DELETE FROM equipment WHERE id = ?');
        $stmt->execute([$id]);
        if ($pdo->inTransaction()) {
            $pdo->commit();
        }

        log_activity('EQUIPMENT_ARCHIVE', 'Moved equipment to archive: ' . ($record['control_no'] ?? $id) . ($unassigned ? ' | auto_unassigned=' . $unassigned : ''));

        equipment_action_response([
            'success' => true,
            'message' => 'Equipment moved to archive successfully.' . ($unassigned && $assignmentNotice !== '' ? ' This was assigned to ' . $assignmentNotice . ' and was automatically unassigned first.' : ''),
        ]);
    }

    if ($action === 'restore') {
        require_permission('ictd.equipment.archive');
        $id = (int) ($payload['id'] ?? $_POST['id'] ?? 0);
        if ($id <= 0) {
            equipment_action_response(['success' => false, 'message' => 'Invalid archive record.'], 422);
        }

        equipment_action_ensure_archive_schema($pdo);

        $stmt = $pdo->prepare('SELECT * FROM archive_equipment WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$record) {
            equipment_action_response(['success' => false, 'message' => 'Archived equipment record not found.'], 404);
        }
        if (empty($record['control_no'])) {
            equipment_action_response(['success' => false, 'message' => 'Archived equipment has no control number and cannot be restored.'], 422);
        }

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM equipment WHERE control_no = ?');
        $stmt->execute([$record['control_no']]);
        if ((int) $stmt->fetchColumn() > 0) {
            equipment_action_response(['success' => false, 'message' => 'Cannot restore because this control number already exists in active equipment.'], 422);
        }

        $equipmentColumns = equipment_action_table_columns($pdo, 'equipment');
        $archiveColumns = equipment_action_table_columns($pdo, 'archive_equipment');
        $restoreColumns = array_values(array_filter($equipmentColumns, static function (string $column) use ($archiveColumns): bool {
            return $column !== 'id' && in_array($column, $archiveColumns, true);
        }));

        $restoreData = [];
        foreach ($restoreColumns as $column) {
            $restoreData[$column] = $record[$column] ?? null;
        }
        if (array_key_exists('assigned_id', $restoreData)) {
            $restoreData['assigned_id'] = null;
        }
        if (array_key_exists('department_id', $restoreData)) {
            $restoreData['department_id'] = null;
        }

        $columns = array_keys($restoreData);
        $placeholders = implode(',', array_fill(0, count($columns), '?'));

        $pdo->beginTransaction();
        $stmt = $pdo->prepare('INSERT INTO equipment (`' . implode('`,`', $columns) . '`) VALUES (' . $placeholders . ')');
        $stmt->execute(array_values($restoreData));
        $stmt = $pdo->prepare('DELETE FROM archive_equipment WHERE id = ?');
        $stmt->execute([$id]);
        $pdo->commit();

        log_activity('EQUIPMENT_RESTORE', 'Restored archived equipment: ' . ($record['control_no'] ?? $id));

        equipment_action_response(['success' => true, 'message' => 'Equipment restored successfully.']);
    }

    if ($action === 'reuse_control_no') {
        require_permission('ictd.equipment.create');
        $id = (int) ($payload['id'] ?? $_POST['id'] ?? 0);
        if ($id <= 0) {
            equipment_action_response(['success' => false, 'message' => 'Invalid archive record.'], 422);
        }

        equipment_action_ensure_archive_schema($pdo);

        $stmt = $pdo->prepare('SELECT id, control_no, control_no_reused, reused_equipment_id FROM archive_equipment WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$record) {
            equipment_action_response(['success' => false, 'message' => 'Archived equipment record not found.'], 404);
        }
        $controlNo = equipment_action_clean_string($record['control_no'] ?? null);
        if (!$controlNo) {
            equipment_action_response(['success' => false, 'message' => 'This archived equipment has no control number to reuse.'], 422);
        }
        if ((int) ($record['control_no_reused'] ?? 0) === 1 && !empty($record['reused_equipment_id'])) {
            equipment_action_response(['success' => false, 'message' => 'This archived control number is already reused by active equipment and cannot be used again.'], 422);
        }

        $stmt = $pdo->prepare('SELECT id FROM equipment WHERE control_no = ? LIMIT 1');
        $stmt->execute([$controlNo]);
        $activeId = $stmt->fetchColumn();
        if ($activeId) {
            if ((int) ($record['control_no_reused'] ?? 0) === 1 && empty($record['reused_equipment_id'])) {
                $pdo->prepare('UPDATE archive_equipment SET reused_equipment_id = ? WHERE id = ?')->execute([(int) $activeId, $id]);
            }
            equipment_action_response(['success' => false, 'message' => 'This control number is already active in equipment and cannot be reused again.'], 422);
        }

        if ((int) ($record['control_no_reused'] ?? 0) === 1) {
            equipment_action_response(['success' => false, 'message' => 'This archived control number is already marked for reuse. Create the new equipment using this control no first.'], 422);
        }

        $user = current_user() ?: [];
        $stmt = $pdo->prepare('UPDATE archive_equipment SET control_no_reused = 1, reused_at = NOW(), reused_by = ? WHERE id = ?');
        $stmt->execute([equipment_action_clean_int($user['id'] ?? null), $id]);

        log_activity('EQUIPMENT_CONTROL_REUSE', 'Marked archived control no for reuse: ' . $controlNo);

        equipment_action_response([
            'success' => true,
            'message' => 'Control No ' . $controlNo . ' is now allowed for reuse. The archive record was kept.',
            'control_no' => $controlNo,
        ]);
    }

    if ($action === 'permanent_delete') {
        require_permission('ictd.equipment.delete');
        $id = (int) ($payload['id'] ?? $_POST['id'] ?? 0);
        if ($id <= 0) {
            equipment_action_response(['success' => false, 'message' => 'Invalid archive record.'], 422);
        }

        equipment_action_ensure_archive_schema($pdo);

        $stmt = $pdo->prepare('SELECT control_no FROM archive_equipment WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$record) {
            equipment_action_response(['success' => false, 'message' => 'Archived equipment record not found.'], 404);
        }

        $stmt = $pdo->prepare('DELETE FROM archive_equipment WHERE id = ?');
        $stmt->execute([$id]);

        log_activity('EQUIPMENT_PERMANENT_DELETE', 'Permanently deleted archived equipment: ' . ($record['control_no'] ?? $id));

        equipment_action_response(['success' => true, 'message' => 'Archived equipment permanently deleted.']);
    }

    equipment_action_response(['success' => false, 'message' => 'Invalid equipment action.'], 400);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    equipment_action_response([
        'success' => false,
        'message' => 'Equipment action failed: ' . $e->getMessage(),
    ], 500);
}
