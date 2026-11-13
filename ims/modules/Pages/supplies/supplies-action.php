<?php
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_login();
ensure_record_scope_schema($pdo);

header('Content-Type: application/json');

function supplies_action_response(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function supplies_action_payload(): array
{
    $raw = file_get_contents('php://input');
    $json = json_decode($raw ?: '', true);

    return is_array($json) ? $json : $_POST;
}

function supplies_clean_string($value): ?string
{
    $value = trim((string) ($value ?? ''));
    return $value === '' ? null : $value;
}

function supplies_clean_int($value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }

    return is_numeric($value) ? (int) $value : null;
}

function supplies_ensure_record_schema(PDO $pdo): void
{
    $columns = [
        'supplier_id' => 'INT NULL AFTER batch_id',
        'eta_date' => 'VARCHAR(20) NULL AFTER delivery_status',
        'batch_id' => 'VARCHAR(255) NULL AFTER eta_date',
    ];

    foreach ($columns as $column => $definition) {
        if (!db_column_exists($pdo, 'other_equipment', $column)) {
            $pdo->exec("ALTER TABLE other_equipment ADD COLUMN {$column} {$definition}");
        }
    }
}

function supplies_normalize_row(array $row, bool $includeAssignment = false): array
{
    $normalized = [
        'code' => supplies_clean_string($row['code'] ?? null),
        'control_no' => null,
        'old_control_no' => supplies_clean_string($row['old_control_no'] ?? null),
        'description_id' => supplies_clean_int($row['description_id'] ?? null),
        'brand_id' => supplies_clean_int($row['brand_id'] ?? null),
        'serialno' => supplies_clean_string($row['serialno'] ?? null),
        'remarks' => supplies_clean_string($row['remarks'] ?? null),
        'status' => supplies_clean_string($row['status'] ?? null),
        'delivered_date' => supplies_clean_string($row['delivered_date'] ?? null),
        'warranty_date' => supplies_clean_string($row['warranty_date'] ?? null),
        'delivery_status' => supplies_clean_string($row['delivery_status'] ?? null) ?: 'To Follow',
        'eta_date' => supplies_clean_string($row['eta_date'] ?? null),
        'batch_id' => supplies_clean_string($row['batch_id'] ?? null),
        'supplier_id' => supplies_clean_int($row['supplier_id'] ?? null),
    ];

    if (strtolower((string) $normalized['delivery_status']) === 'delivered') {
        $normalized['eta_date'] = null;
    }

    if ($includeAssignment || array_key_exists('end_user_id', $row)) {
        $normalized['end_user_id'] = supplies_clean_int($row['end_user_id'] ?? null);
    }
    if ($includeAssignment || array_key_exists('department_id', $row)) {
        $normalized['department_id'] = supplies_clean_int($row['department_id'] ?? null);
    }

    return $normalized;
}

function supplies_archived_control_exists(PDO $pdo, string $controlNo): bool
{
    if (!db_table_exists($pdo, 'archive_other_equipment')) {
        return false;
    }

    $reuseClause = db_column_exists($pdo, 'archive_other_equipment', 'control_no_reused')
        ? ' AND (COALESCE(control_no_reused, 0) = 0 OR reused_supply_id IS NOT NULL)'
        : '';
    $stmt = $pdo->prepare('SELECT id FROM archive_other_equipment WHERE control_no = ?' . $reuseClause . ' LIMIT 1');
    $stmt->execute([$controlNo]);

    return (bool) $stmt->fetchColumn();
}

function supplies_validate_row(PDO $pdo, array $row, ?int $ignoreId = null, bool $reuseArchivedControlNo = false): array
{
    $errors = [];

    if (empty($row['code'])) {
        $errors[] = 'Code is required.';
    }

    if (!empty($row['code'])) {
        $sql = 'SELECT id FROM other_equipment WHERE code = ?';
        $params = [$row['code']];

        if ($ignoreId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $ignoreId;
        }

        $sql .= ' LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        if ($stmt->fetch()) {
            $errors[] = 'Code "' . $row['code'] . '" already exists.';
        }
    }

    if (!empty($row['status']) && db_table_exists($pdo, 'list_equipment_status')) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM list_equipment_status WHERE equipment_status = ?');
        $stmt->execute([$row['status']]);
        if ((int) $stmt->fetchColumn() < 1) {
            $errors[] = 'Selected status is not in the equipment status masterlist.';
        }
    }

    return $errors;
}

function supplies_fetch_masterlists(PDO $pdo): array
{
    $descriptions = db_table_exists($pdo, 'list_description')
        ? ($pdo->query('SELECT id, description_name AS text FROM list_description ORDER BY description_name ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [])
        : [];
    $brands = db_table_exists($pdo, 'list_brand')
        ? ($pdo->query('SELECT id, brand_name AS text FROM list_brand ORDER BY brand_name ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [])
        : [];
    $endUsers = db_table_exists($pdo, 'list_end_user')
        ? ($pdo->query('SELECT end_user_id AS id, end_user_name AS text FROM list_end_user ORDER BY end_user_name ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [])
        : [];
    $departments = db_table_exists($pdo, 'list_department')
        ? ($pdo->query('SELECT department_id AS id, department_name AS text FROM list_department ORDER BY department_name ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [])
        : [];
    $statuses = db_table_exists($pdo, 'list_equipment_status')
        ? ($pdo->query('SELECT equipment_status AS id, equipment_status AS text FROM list_equipment_status ORDER BY equipment_status ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [])
        : [];
    $suppliers = db_table_exists($pdo, 'list_suppliers')
        ? ($pdo->query('SELECT id, supplier_name AS text FROM list_suppliers ORDER BY supplier_name ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [])
        : [];

    return compact('descriptions', 'brands', 'endUsers', 'departments', 'statuses', 'suppliers');
}

function supplies_mark_reused_archive_control(PDO $pdo, string $controlNo, int $supplyId): void
{
    if (!db_table_exists($pdo, 'archive_other_equipment') || !db_column_exists($pdo, 'archive_other_equipment', 'control_no_reused')) {
        return;
    }

    $stmt = $pdo->prepare("
        UPDATE archive_other_equipment
        SET reused_supply_id = ?
        WHERE control_no = ?
          AND COALESCE(control_no_reused, 0) = 1
          AND reused_supply_id IS NULL
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$supplyId, $controlNo]);
}

function supplies_next_codes(PDO $pdo, int $count): array
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
        if (preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', (string) $code, $matches)) {
            $max = max($max, (int) $matches[1]);
        }
    }

    $next = [];
    for ($i = 1; $i <= $count; $i++) {
        $next[] = $prefix . str_pad((string) ($max + $i), 4, '0', STR_PAD_LEFT);
    }

    return $next;
}

function supplies_fetch_record(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM other_equipment WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function supplies_assignment_identifiers(array $row): array
{
    $identifiers = [];
    foreach (['control_no', 'serialno', 'code'] as $column) {
        $value = supplies_clean_string($row[$column] ?? null);
        if ($value !== null) {
            $identifiers[] = $value;
        }
    }

    return array_values(array_unique($identifiers));
}

function supplies_is_auto_unassign_status(?string $statusName): bool
{
    return in_array(strtolower(trim((string) $statusName)), ['defective', 'condemned', 'for disposal', 'disposed'], true);
}

function supplies_write_assignment_history(PDO $pdo, string $action, array $record, string $details): void
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
        supplies_clean_int($record['assigned_id'] ?? null),
        $action,
        'supply',
        supplies_clean_string($record['control_no'] ?? null),
        null,
        supplies_clean_int($record['end_user_id'] ?? null),
        null,
        supplies_clean_int($record['department_id'] ?? null),
        null,
        $details,
        supplies_clean_int($user['id'] ?? null),
        supplies_clean_string($user['username'] ?? 'Guest'),
    ]);
}

function supplies_auto_unassign(PDO $pdo, int $supplyId, string $statusName): int
{
    if (!db_table_exists($pdo, 'assigned_record')) {
        return 0;
    }

    $record = supplies_fetch_record($pdo, $supplyId);
    if (!$record) {
        return 0;
    }

    $identifiers = supplies_assignment_identifiers($record);
    if (empty($identifiers)) {
        $stmt = $pdo->prepare('UPDATE other_equipment SET assigned_id = NULL, end_user_id = NULL, department_id = NULL WHERE id = ?');
        $stmt->execute([$supplyId]);
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($identifiers), '?'));
    $params = $identifiers;
    $assignedId = supplies_clean_int($record['assigned_id'] ?? null);
    $assignedClause = $assignedId ? ' OR assigned_id = ?' : '';
    if ($assignedId) {
        $params[] = $assignedId;
    }
    $stmt = $pdo->prepare("SELECT * FROM assigned_record WHERE control_no IN ({$placeholders}){$assignedClause}");
    $stmt->execute($params);
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($assignments as $assignment) {
        supplies_write_assignment_history($pdo, 'auto_unassign', $assignment, 'Auto-unassigned because supply status changed to ' . $statusName . '.');
    }

    $stmt = $pdo->prepare("DELETE FROM assigned_record WHERE control_no IN ({$placeholders}){$assignedClause}");
    $stmt->execute($params);
    $deleted = $stmt->rowCount();

    $stmt = $pdo->prepare('UPDATE other_equipment SET assigned_id = NULL, end_user_id = NULL, department_id = NULL WHERE id = ?');
    $stmt->execute([$supplyId]);

    return $deleted;
}

function supplies_ensure_archive_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS archive_other_equipment (
            id INT AUTO_INCREMENT PRIMARY KEY,
            original_id INT NULL,
            code VARCHAR(100) NULL,
            control_no VARCHAR(50) NULL,
            old_control_no VARCHAR(100) NULL,
            description_id INT NULL,
            brand_id INT NULL,
            serialno VARCHAR(100) NULL,
            remarks TEXT NULL,
            status VARCHAR(100) NULL,
            delivered_date VARCHAR(100) NULL,
            warranty_date VARCHAR(100) NULL,
            delivery_status VARCHAR(100) NULL,
            eta_date VARCHAR(20) NULL,
            batch_id VARCHAR(255) NULL,
            supplier_id INT NULL,
            end_user_id INT NULL,
            department_id INT NULL,
            assigned_end_user_id INT NULL,
            assigned_end_user_name VARCHAR(255) NULL,
            assigned_department_name VARCHAR(255) NULL,
            assignment_snapshot LONGTEXT NULL,
            control_no_reused TINYINT(1) NOT NULL DEFAULT 0,
            reused_at DATETIME NULL,
            reused_by INT NULL,
            reused_supply_id INT NULL,
            created_at TIMESTAMP NULL,
            archive_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            archived_by INT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $columns = [
        'original_id' => 'INT NULL AFTER id',
        'assigned_end_user_id' => 'INT NULL AFTER department_id',
        'assigned_end_user_name' => 'VARCHAR(255) NULL AFTER assigned_end_user_id',
        'assigned_department_name' => 'VARCHAR(255) NULL AFTER assigned_end_user_name',
        'assignment_snapshot' => 'LONGTEXT NULL AFTER assigned_department_name',
        'control_no_reused' => 'TINYINT(1) NOT NULL DEFAULT 0 AFTER assignment_snapshot',
        'reused_at' => 'DATETIME NULL AFTER control_no_reused',
        'reused_by' => 'INT NULL AFTER reused_at',
        'reused_supply_id' => 'INT NULL AFTER reused_by',
        'archived_by' => 'INT NULL AFTER archive_at',
        'eta_date' => 'VARCHAR(20) NULL AFTER delivery_status',
        'batch_id' => 'VARCHAR(255) NULL AFTER eta_date',
        'supplier_id' => 'INT NULL AFTER batch_id',
    ];

    foreach ($columns as $column => $definition) {
        if (!db_column_exists($pdo, 'archive_other_equipment', $column)) {
            $pdo->exec("ALTER TABLE archive_other_equipment ADD COLUMN {$column} {$definition}");
        }
    }

    try {
        $pdo->exec('ALTER TABLE archive_other_equipment MODIFY code VARCHAR(100) NULL');
    } catch (Throwable $e) {
    }
}

function supplies_table_columns(PDO $pdo, string $table): array
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

function supplies_ensure_assignment_archive_schema(PDO $pdo): void
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
            archived_username VARCHAR(255) NULL
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

function supplies_archive_assignment_records(PDO $pdo, array $supply): int
{
    if (!db_table_exists($pdo, 'assigned_record')) {
        return 0;
    }

    $identifiers = supplies_identifiers($supply);
    if (empty($identifiers)) {
        return 0;
    }

    supplies_ensure_assignment_archive_schema($pdo);

    $placeholders = implode(',', array_fill(0, count($identifiers), '?'));
    $stmt = $pdo->prepare("
        SELECT
            ar.*,
            eu.end_user_name,
            dep.department_name
        FROM assigned_record ar
        LEFT JOIN list_end_user eu ON eu.end_user_id = ar.end_user_id
        LEFT JOIN list_department dep ON dep.department_id = ar.department_id
        WHERE ar.control_no IN ({$placeholders})
    ");
    $stmt->execute($identifiers);
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $user = current_user() ?: [];
    $archiveStmt = $pdo->prepare("
        INSERT INTO archive_assigned_record
            (original_assigned_id, item_type, equipment_id, control_no, end_user_id, end_user_name, department_id, department_name,
             connection_ip, internet, wifi, program_installed, assigned_date, months_since_assigned, equipment_analysis, attachment_pdf,
             assignment_snapshot, item_snapshot, archive_reason, archived_by, archived_username)
        VALUES
            (?, 'supply', NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($assignments as $assignment) {
        $archiveStmt->execute([
            supplies_clean_int($assignment['assigned_id'] ?? null),
            $assignment['control_no'] ?? ($supply['control_no'] ?? $supply['code'] ?? $supply['serialno'] ?? null),
            supplies_clean_int($assignment['end_user_id'] ?? null),
            $assignment['end_user_name'] ?? null,
            supplies_clean_int($assignment['department_id'] ?? null),
            $assignment['department_name'] ?? null,
            $assignment['connection_ip'] ?? null,
            $assignment['internet'] ?? null,
            $assignment['wifi'] ?? null,
            $assignment['program_installed'] ?? null,
            $assignment['assigned_date'] ?? null,
            $assignment['months_since_assigned'] ?? null,
            $assignment['equipment_analysis'] ?? null,
            $assignment['attachment_pdf'] ?? null,
            json_encode($assignment, JSON_UNESCAPED_UNICODE),
            json_encode($supply, JSON_UNESCAPED_UNICODE),
            'Supply moved to archive',
            supplies_clean_int($user['id'] ?? null),
            supplies_clean_string($user['username'] ?? null),
        ]);
        supplies_write_assignment_history($pdo, 'archive_unassign', $assignment, 'Auto-unassigned because supply was moved to archive.');
    }

    $stmt = $pdo->prepare("DELETE FROM assigned_record WHERE control_no IN ({$placeholders})");
    $stmt->execute($identifiers);

    return $stmt->rowCount();
}

function supplies_archive_blocking_assignment(PDO $pdo, array $supply): ?array
{
    $identifiers = supplies_identifiers($supply);
    if (!empty($identifiers) && db_table_exists($pdo, 'assigned_record')) {
        $placeholders = implode(',', array_fill(0, count($identifiers), '?'));
        $stmt = $pdo->prepare("
            SELECT
                ar.*,
                eu.end_user_name,
                dep.department_name
            FROM assigned_record ar
            LEFT JOIN list_end_user eu ON eu.end_user_id = ar.end_user_id
            LEFT JOIN list_department dep ON dep.department_id = ar.department_id
            WHERE ar.control_no IN ({$placeholders})
            LIMIT 1
        ");
        $stmt->execute($identifiers);
        $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($assignment) {
            return $assignment;
        }
    }

    if (!empty($supply['end_user_id']) || !empty($supply['department_id'])) {
        $fallback = [
            'end_user_name' => 'an assigned user',
            'department_name' => null,
        ];
        if (!empty($supply['end_user_id']) && db_table_exists($pdo, 'list_end_user')) {
            $stmt = $pdo->prepare('SELECT end_user_name FROM list_end_user WHERE end_user_id = ? LIMIT 1');
            $stmt->execute([(int) $supply['end_user_id']]);
            $fallback['end_user_name'] = $stmt->fetchColumn() ?: $fallback['end_user_name'];
        }
        if (!empty($supply['department_id']) && db_table_exists($pdo, 'list_department')) {
            $stmt = $pdo->prepare('SELECT department_name FROM list_department WHERE department_id = ? LIMIT 1');
            $stmt->execute([(int) $supply['department_id']]);
            $fallback['department_name'] = $stmt->fetchColumn() ?: null;
        }

        return $fallback;
    }

    return null;
}

if (!db_table_exists($pdo, 'other_equipment')) {
    supplies_action_response(['success' => false, 'message' => 'The other_equipment table is missing.'], 500);
}
if (!db_column_exists($pdo, 'other_equipment', 'assigned_id')) {
    $pdo->exec('ALTER TABLE other_equipment ADD COLUMN assigned_id INT NULL');
}
supplies_ensure_record_schema($pdo);

$payload = supplies_action_payload();
$action = trim((string) ($_GET['action'] ?? $payload['action'] ?? ''));

try {
    if ($action === 'masterlists') {
        require_permission('ictd.supplies.view');
        supplies_action_response(['success' => true, 'data' => supplies_fetch_masterlists($pdo)]);
    }

    if ($action === 'view') {
        require_permission('ictd.supplies.view');
        $id = (int) ($_GET['id'] ?? $payload['id'] ?? 0);
        $assignmentSelect = db_table_exists($pdo, 'assigned_record')
            ? "CASE WHEN EXISTS (
                    SELECT 1
                    FROM assigned_record ae
                    WHERE ae.assigned_id = oe.assigned_id
                       OR (TRIM(COALESCE(ae.control_no, '')) <> '' AND (ae.control_no = oe.serialno OR ae.control_no = oe.code))
                ) THEN 1 ELSE 0 END AS is_assigned"
            : '0 AS is_assigned';

        $stmt = $pdo->prepare("
            SELECT
                oe.*,
                d.description_name,
                b.brand_name,
                s.supplier_name,
                eu.end_user_name,
                dep.department_name,
                {$assignmentSelect}
            FROM other_equipment oe
            LEFT JOIN list_description d ON d.id = oe.description_id
            LEFT JOIN list_brand b ON b.id = oe.brand_id
            LEFT JOIN list_suppliers s ON s.id = oe.supplier_id
            LEFT JOIN list_end_user eu ON eu.end_user_id = oe.end_user_id
            LEFT JOIN list_department dep ON dep.department_id = oe.department_id
            WHERE oe.id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            supplies_action_response(['success' => false, 'message' => 'Supply record not found.'], 404);
        }

        supplies_action_response(['success' => true, 'data' => $row]);
    }

    if ($action === 'batch_create') {
        require_permission('ictd.supplies.create');

        $rows = $payload['rows'] ?? [];
        if (!is_array($rows) || empty($rows)) {
            supplies_action_response(['success' => false, 'message' => 'Add at least one supply row.'], 422);
        }

        $reuseControlNo = supplies_clean_string($payload['reuse_control_no'] ?? null);
        $batchDateDelivered = supplies_clean_string($payload['batch_date_delivered'] ?? null);
        $batchSupplierId = supplies_clean_int($payload['batch_supplier_id'] ?? null);
        if (!$batchDateDelivered) {
            supplies_action_response(['success' => false, 'message' => 'Date Delivered is required for the batch.'], 422);
        }
        if (!$batchSupplierId) {
            supplies_action_response(['success' => false, 'message' => 'Supplier is required for the batch.'], 422);
        }
        if ($reuseControlNo && count($rows) !== 1) {
            supplies_action_response(['success' => false, 'message' => 'Reused control numbers can only be saved one supply record at a time.'], 422);
        }
        if ($reuseControlNo) {
            supplies_ensure_archive_schema($pdo);
            $stmt = $pdo->prepare('SELECT id FROM other_equipment WHERE control_no = ? LIMIT 1');
            $stmt->execute([$reuseControlNo]);
            if ($stmt->fetchColumn()) {
                supplies_action_response(['success' => false, 'message' => 'Control No ' . $reuseControlNo . ' is already active and cannot be reused again.'], 422);
            }

            $stmt = $pdo->prepare("
                SELECT id
                FROM archive_other_equipment
                WHERE control_no = ?
                  AND COALESCE(control_no_reused, 0) = 1
                  AND reused_supply_id IS NULL
                LIMIT 1
            ");
            $stmt->execute([$reuseControlNo]);
            if (!$stmt->fetchColumn()) {
                supplies_action_response(['success' => false, 'message' => 'Control No ' . $reuseControlNo . ' is not ready for reuse from the archive.'], 422);
            }
        }

        $batchId = 'SUP-BATCH-' . date('Ymd-His') . '-' . random_int(100, 999);
        $createdBy = (int) ((current_user()['id'] ?? $_SESSION['user']['id'] ?? 0) ?: 0);
        $normalizedRows = [];
        $seenCodes = [];
        $autoCodeIndexes = [];

        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            $normalized = supplies_normalize_row($row);
            if ($reuseControlNo) {
                $normalized['control_no'] = $reuseControlNo;
            }
            $normalized['delivered_date'] = $batchDateDelivered;
            $normalized['batch_id'] = $normalized['batch_id'] ?: $batchId;
            $normalized['supplier_id'] = $batchSupplierId;
            $usesAutoCode = empty($normalized['code']);
            if ($usesAutoCode) {
                $normalized['code'] = 'PENDING';
                $autoCodeIndexes[] = count($normalizedRows);
            }
            $normalized['created_by'] = $createdBy ?: null;
            $errors = supplies_validate_row($pdo, $normalized, null, (bool) $reuseControlNo);
            $codeKey = strtolower((string) ($normalized['code'] ?? ''));
            if (!$usesAutoCode && $codeKey !== '' && isset($seenCodes[$codeKey])) {
                $errors[] = 'Code "' . $normalized['code'] . '" is duplicated in this batch.';
            }
            if (!$usesAutoCode && $codeKey !== '') {
                $seenCodes[$codeKey] = true;
            }

            if (!empty($errors)) {
                supplies_action_response([
                    'success' => false,
                    'message' => 'Row ' . ($index + 1) . ': ' . implode(' ', $errors),
                ], 422);
            }

            $normalizedRows[] = $normalized;
        }

        if (empty($normalizedRows)) {
            supplies_action_response(['success' => false, 'message' => 'No valid supply rows to save.'], 422);
        }

        $columns = array_keys($normalizedRows[0]);
        $placeholders = implode(',', array_fill(0, count($columns), '?'));
        $stmt = $pdo->prepare('INSERT INTO other_equipment (`' . implode('`,`', $columns) . '`) VALUES (' . $placeholders . ')');

        $pdo->beginTransaction();
        if (!empty($autoCodeIndexes)) {
            $codes = supplies_next_codes($pdo, count($autoCodeIndexes));
            foreach ($autoCodeIndexes as $codeIndex => $rowIndex) {
                $normalizedRows[$rowIndex]['code'] = $codes[$codeIndex];
            }
        }
        foreach ($normalizedRows as $row) {
            $stmt->execute(array_values($row));
            if (!empty($row['control_no'])) {
                supplies_mark_reused_archive_control($pdo, (string) $row['control_no'], (int) $pdo->lastInsertId());
            }
        }
        $pdo->commit();

        log_activity('SUPPLY_BATCH_CREATE', 'Created supplies batch ' . $batchId . ' with ' . count($normalizedRows) . ' record(s).');
        supplies_action_response(['success' => true, 'message' => count($normalizedRows) . ' supply record(s) saved successfully.', 'batch_id' => $batchId]);
    }

    if ($action === 'create') {
        require_permission('ictd.supplies.create');
        $row = supplies_normalize_row($payload);
        unset($row['batch_id']);
        if (empty($row['code'])) {
            $row['code'] = supplies_next_codes($pdo, 1)[0];
        }
        $row['created_by'] = (int) ((current_user()['id'] ?? $_SESSION['user']['id'] ?? 0) ?: 0) ?: null;
        $errors = supplies_validate_row($pdo, $row, null, !empty($payload['reuse_archived_control_no']));
        if (!empty($errors)) {
            supplies_action_response(['success' => false, 'message' => implode(' ', $errors)], 422);
        }

        $columns = array_keys($row);
        $placeholders = implode(',', array_fill(0, count($columns), '?'));
        $stmt = $pdo->prepare('INSERT INTO other_equipment (`' . implode('`,`', $columns) . '`) VALUES (' . $placeholders . ')');
        $stmt->execute(array_values($row));
        if (!empty($row['control_no'])) {
            supplies_mark_reused_archive_control($pdo, (string) $row['control_no'], (int) $pdo->lastInsertId());
        }

        log_activity('SUPPLY_CREATE', 'Created supply: ' . $row['code'] . ' | control_no=' . ($row['control_no'] ?? 'none') . ' | serialno=' . ($row['serialno'] ?? 'none'));
        supplies_action_response(['success' => true, 'message' => 'Supply record added successfully.']);
    }

    if ($action === 'status_create') {
        require_permission('ictd.supplies.edit');
        if (!db_table_exists($pdo, 'list_equipment_status')) {
            supplies_action_response(['success' => false, 'message' => 'The equipment status masterlist table is missing.'], 500);
        }

        $statusName = supplies_clean_string($payload['equipment_status'] ?? null);
        if (!$statusName) {
            supplies_action_response(['success' => false, 'message' => 'Status name is required.'], 422);
        }
        if (in_array(strtolower($statusName), ['assigned', 'unassigned'], true)) {
            supplies_action_response(['success' => false, 'message' => 'Assigned and unassigned are assignment states, not equipment/supply statuses.'], 422);
        }

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM list_equipment_status WHERE equipment_status = ?');
        $stmt->execute([$statusName]);
        if ((int) $stmt->fetchColumn() > 0) {
            supplies_action_response(['success' => false, 'message' => 'This status already exists.'], 422);
        }

        $stmt = $pdo->prepare('INSERT INTO list_equipment_status (equipment_status) VALUES (?)');
        $stmt->execute([$statusName]);

        log_activity('SUPPLY_STATUS_CREATE', 'Created shared equipment/supply status: ' . $statusName);
        supplies_action_response(['success' => true, 'message' => 'Status added successfully.']);
    }

    if ($action === 'status_update') {
        require_permission('ictd.supplies.edit');
        if (!db_table_exists($pdo, 'list_equipment_status')) {
            supplies_action_response(['success' => false, 'message' => 'The equipment status masterlist table is missing.'], 500);
        }

        $id = (int) ($payload['id'] ?? 0);
        $statusName = supplies_clean_string($payload['equipment_status'] ?? null);
        if ($id <= 0 || !$statusName) {
            supplies_action_response(['success' => false, 'message' => 'Invalid status details.'], 422);
        }
        if (in_array(strtolower($statusName), ['assigned', 'unassigned'], true)) {
            supplies_action_response(['success' => false, 'message' => 'Assigned and unassigned are assignment states, not equipment/supply statuses.'], 422);
        }

        $stmt = $pdo->prepare('SELECT equipment_status FROM list_equipment_status WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $oldStatus = $stmt->fetchColumn();
        if (!$oldStatus) {
            supplies_action_response(['success' => false, 'message' => 'Status record not found.'], 404);
        }

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM list_equipment_status WHERE equipment_status = ? AND id <> ?');
        $stmt->execute([$statusName, $id]);
        if ((int) $stmt->fetchColumn() > 0) {
            supplies_action_response(['success' => false, 'message' => 'This status already exists.'], 422);
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare('UPDATE list_equipment_status SET equipment_status = ? WHERE id = ?');
        $stmt->execute([$statusName, $id]);
        $stmt = $pdo->prepare('UPDATE other_equipment SET status = ? WHERE status = ?');
        $stmt->execute([$statusName, $oldStatus]);
        $pdo->commit();

        log_activity('SUPPLY_STATUS_UPDATE', 'Updated shared equipment/supply status from "' . $oldStatus . '" to "' . $statusName . '"');
        supplies_action_response(['success' => true, 'message' => 'Status updated successfully.']);
    }

    if ($action === 'status_delete') {
        require_permission('ictd.supplies.edit');
        if (!db_table_exists($pdo, 'list_equipment_status')) {
            supplies_action_response(['success' => false, 'message' => 'The equipment status masterlist table is missing.'], 500);
        }

        $id = (int) ($payload['id'] ?? 0);
        if ($id <= 0) {
            supplies_action_response(['success' => false, 'message' => 'Invalid status.'], 422);
        }

        $stmt = $pdo->prepare('SELECT equipment_status FROM list_equipment_status WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $statusName = $stmt->fetchColumn();
        if (!$statusName) {
            supplies_action_response(['success' => false, 'message' => 'Status record not found.'], 404);
        }

        if (db_column_exists($pdo, 'equipment', 'equipment_status_id')) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM equipment WHERE equipment_status_id = ?');
            $stmt->execute([$id]);
            if ((int) $stmt->fetchColumn() > 0) {
                supplies_action_response(['success' => false, 'message' => 'This status is being used by equipment records.'], 422);
            }
        }

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM other_equipment WHERE status = ?');
        $stmt->execute([$statusName]);
        if ((int) $stmt->fetchColumn() > 0) {
            supplies_action_response(['success' => false, 'message' => 'This status is being used by supplies records.'], 422);
        }

        $stmt = $pdo->prepare('DELETE FROM list_equipment_status WHERE id = ?');
        $stmt->execute([$id]);

        log_activity('SUPPLY_STATUS_DELETE', 'Deleted shared equipment/supply status: ' . $statusName);
        supplies_action_response(['success' => true, 'message' => 'Status deleted successfully.']);
    }

    if ($action === 'mark_status') {
        require_permission('ictd.supplies.edit');
        $id = (int) ($payload['id'] ?? 0);
        $status = supplies_clean_string($payload['status'] ?? null);
        if ($id <= 0) {
            supplies_action_response(['success' => false, 'message' => 'Invalid supply record.'], 422);
        }

        $record = supplies_fetch_record($pdo, $id);
        if (!$record) {
            supplies_action_response(['success' => false, 'message' => 'Supply record not found.'], 404);
        }
        $blockingAssignment = supplies_archive_blocking_assignment($pdo, $record);
        $assignmentNotice = '';
        if ($blockingAssignment) {
            $assignedTo = trim((string) ($blockingAssignment['end_user_name'] ?? 'an assigned user'));
            $department = trim((string) ($blockingAssignment['department_name'] ?? ''));
            $assignmentNotice = $department !== '' ? $assignedTo . ' (' . $department . ')' : $assignedTo;
        }

        if ($status !== null && db_table_exists($pdo, 'list_equipment_status')) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM list_equipment_status WHERE equipment_status = ?');
            $stmt->execute([$status]);
            if ((int) $stmt->fetchColumn() < 1) {
                supplies_action_response(['success' => false, 'message' => 'Selected status is not in the equipment status masterlist.'], 422);
            }
        }

        $stmt = $pdo->prepare('UPDATE other_equipment SET status = ? WHERE id = ?');
        $pdo->beginTransaction();
        $stmt->execute([$status, $id]);
        $unassigned = supplies_is_auto_unassign_status($status)
            ? supplies_auto_unassign($pdo, $id, (string) $status)
            : 0;
        $pdo->commit();

        log_activity('SUPPLY_STATUS_UPDATE', 'Updated supply status: ' . ($record['code'] ?? $id) . ' from "' . ($record['status'] ?? 'No Status') . '" to "' . ($status ?? 'No Status') . '"' . ($unassigned ? ' | auto_unassigned=' . $unassigned : ''));
        supplies_action_response(['success' => true, 'message' => 'Supply status updated successfully.' . ($unassigned ? ' Linked assignment was automatically removed.' : '')]);
    }

    if ($action === 'assign') {
        supplies_action_response(['success' => false, 'message' => 'Assignments are now managed only in the Assign module.'], 400);
    }

    if ($action === 'unassign') {
        supplies_action_response(['success' => false, 'message' => 'Assignments are now managed only in the Assign module.'], 400);
    }

    if ($action === 'update') {
        require_permission('ictd.supplies.edit');
        $id = (int) ($payload['id'] ?? 0);
        if ($id <= 0) {
            supplies_action_response(['success' => false, 'message' => 'Invalid supply record.'], 422);
        }

        $row = supplies_normalize_row($payload);
        unset($row['batch_id']);
        $errors = supplies_validate_row($pdo, $row, $id, !empty($payload['reuse_archived_control_no']));
        if (!empty($errors)) {
            supplies_action_response(['success' => false, 'message' => implode(' ', $errors)], 422);
        }

        $assignments = [];
        $values = [];
        foreach ($row as $column => $value) {
            $assignments[] = "`{$column}` = ?";
            $values[] = $value;
        }
        $values[] = $id;

        $previous = supplies_fetch_record($pdo, $id);
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('UPDATE other_equipment SET ' . implode(', ', $assignments) . ' WHERE id = ?');
        $stmt->execute($values);
        $unassigned = supplies_is_auto_unassign_status($row['status'] ?? null)
            ? supplies_auto_unassign($pdo, $id, (string) $row['status'])
            : 0;
        $pdo->commit();

        log_activity('SUPPLY_UPDATE', 'Updated supply ID: ' . $id . ' | code=' . ($row['code'] ?? ($previous['code'] ?? $id)) . ' | previous_control_no=' . ($previous['control_no'] ?? 'none') . ' | new_control_no=' . ($row['control_no'] ?? 'none') . ($unassigned ? ' | auto_unassigned=' . $unassigned : ''));
        supplies_action_response(['success' => true, 'message' => 'Supply record updated successfully.' . ($unassigned ? ' Linked assignment was automatically removed.' : '')]);
    }

    if ($action === 'reuse_control_no') {
        require_permission('ictd.supplies.create');
        $id = (int) ($payload['id'] ?? $_POST['id'] ?? 0);
        if ($id <= 0) {
            supplies_action_response(['success' => false, 'message' => 'Invalid archive record.'], 422);
        }

        supplies_ensure_archive_schema($pdo);
        supplies_ensure_assignment_archive_schema($pdo);

        $stmt = $pdo->prepare('SELECT id, control_no, control_no_reused, reused_supply_id FROM archive_other_equipment WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$record) {
            supplies_action_response(['success' => false, 'message' => 'Archived supply record not found.'], 404);
        }
        $controlNo = supplies_clean_string($record['control_no'] ?? null);
        if (!$controlNo) {
            supplies_action_response(['success' => false, 'message' => 'This archived supply has no control number to reuse.'], 422);
        }
        if ((int) ($record['control_no_reused'] ?? 0) === 1 && !empty($record['reused_supply_id'])) {
            supplies_action_response(['success' => false, 'message' => 'This archived control number is already reused by active supplies and cannot be used again.'], 422);
        }

        $stmt = $pdo->prepare('SELECT id FROM other_equipment WHERE control_no = ? LIMIT 1');
        $stmt->execute([$controlNo]);
        $activeId = $stmt->fetchColumn();
        if ($activeId) {
            if ((int) ($record['control_no_reused'] ?? 0) === 1 && empty($record['reused_supply_id'])) {
                $pdo->prepare('UPDATE archive_other_equipment SET reused_supply_id = ? WHERE id = ?')->execute([(int) $activeId, $id]);
            }
            supplies_action_response(['success' => false, 'message' => 'This control number is already active in supplies and cannot be reused again.'], 422);
        }
        if ((int) ($record['control_no_reused'] ?? 0) === 1) {
            supplies_action_response(['success' => false, 'message' => 'This archived control number is already marked for reuse. Create the new supply using this control no first.'], 422);
        }

        $user = current_user() ?: [];
        $stmt = $pdo->prepare('UPDATE archive_other_equipment SET control_no_reused = 1, reused_at = NOW(), reused_by = ? WHERE id = ?');
        $stmt->execute([supplies_clean_int($user['id'] ?? null), $id]);

        log_activity('SUPPLY_CONTROL_REUSE', 'Marked archived supply control no for reuse: ' . $controlNo);

        supplies_action_response([
            'success' => true,
            'message' => 'Control No ' . $controlNo . ' is now allowed for reuse. The archive record was kept.',
            'control_no' => $controlNo,
        ]);
    }

    if ($action === 'delete') {
        require_permission('ictd.supplies.delete');
        $id = (int) ($payload['id'] ?? $_POST['id'] ?? 0);
        if ($id <= 0) {
            supplies_action_response(['success' => false, 'message' => 'Invalid supply record.'], 422);
        }

        supplies_ensure_archive_schema($pdo);

        $stmt = $pdo->prepare('SELECT * FROM other_equipment WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$record) {
            supplies_action_response(['success' => false, 'message' => 'Supply record not found.'], 404);
        }

        $supplyColumns = supplies_table_columns($pdo, 'other_equipment');
        $archiveColumns = supplies_table_columns($pdo, 'archive_other_equipment');
        $copyColumns = array_values(array_filter($supplyColumns, static function (string $column) use ($archiveColumns): bool {
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
            $archiveData['archived_by'] = supplies_clean_int($user['id'] ?? null);
        }
        if ($blockingAssignment) {
            if (in_array('assigned_end_user_id', $archiveColumns, true)) {
                $archiveData['assigned_end_user_id'] = supplies_clean_int($blockingAssignment['end_user_id'] ?? null);
            }
            if (in_array('assigned_end_user_name', $archiveColumns, true)) {
                $archiveData['assigned_end_user_name'] = supplies_clean_string($blockingAssignment['end_user_name'] ?? null);
            }
            if (in_array('assigned_department_name', $archiveColumns, true)) {
                $archiveData['assigned_department_name'] = supplies_clean_string($blockingAssignment['department_name'] ?? null);
            }
            if (in_array('assignment_snapshot', $archiveColumns, true)) {
                $archiveData['assignment_snapshot'] = json_encode($blockingAssignment, JSON_UNESCAPED_UNICODE);
            }
        }
        $columns = array_keys($archiveData);
        $placeholders = implode(',', array_fill(0, count($columns), '?'));

        $pdo->beginTransaction();
        $unassigned = supplies_archive_assignment_records($pdo, $record);
        $stmt = $pdo->prepare('INSERT INTO archive_other_equipment (`' . implode('`,`', $columns) . '`) VALUES (' . $placeholders . ')');
        $stmt->execute(array_values($archiveData));
        $stmt = $pdo->prepare('DELETE FROM other_equipment WHERE id = ?');
        $stmt->execute([$id]);
        if ($pdo->inTransaction()) {
            $pdo->commit();
        }

        log_activity('SUPPLY_ARCHIVE', 'Moved supply to archive: ' . ($record['code'] ?? $id) . ' | control_no=' . ($record['control_no'] ?? 'none') . ' | serialno=' . ($record['serialno'] ?? 'none') . ($unassigned ? ' | auto_unassigned=' . $unassigned : ''));
        supplies_action_response([
            'success' => true,
            'message' => 'Supply moved to archive successfully.' . ($unassigned && $assignmentNotice !== '' ? ' This was assigned to ' . $assignmentNotice . ' and was automatically unassigned first.' : ''),
        ]);
    }

    if ($action === 'restore') {
        require_permission('ictd.supplies.edit');
        $id = (int) ($payload['id'] ?? $_POST['id'] ?? 0);
        if ($id <= 0) {
            supplies_action_response(['success' => false, 'message' => 'Invalid archive record.'], 422);
        }

        supplies_ensure_archive_schema($pdo);

        $stmt = $pdo->prepare('SELECT * FROM archive_other_equipment WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$record) {
            supplies_action_response(['success' => false, 'message' => 'Archived supply record not found.'], 404);
        }
        if (empty($record['code'])) {
            supplies_action_response(['success' => false, 'message' => 'Archived supply has no code and cannot be restored.'], 422);
        }

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM other_equipment WHERE code = ?');
        $stmt->execute([$record['code']]);
        if ((int) $stmt->fetchColumn() > 0) {
            supplies_action_response(['success' => false, 'message' => 'Cannot restore because this supply code already exists.'], 422);
        }

        $supplyColumns = supplies_table_columns($pdo, 'other_equipment');
        $archiveColumns = supplies_table_columns($pdo, 'archive_other_equipment');
        $restoreColumns = array_values(array_filter($supplyColumns, static function (string $column) use ($archiveColumns): bool {
            return $column !== 'id' && in_array($column, $archiveColumns, true);
        }));

        $restoreData = [];
        foreach ($restoreColumns as $column) {
            $restoreData[$column] = $record[$column] ?? null;
        }
        if (array_key_exists('end_user_id', $restoreData)) {
            $restoreData['end_user_id'] = null;
        }
        if (array_key_exists('department_id', $restoreData)) {
            $restoreData['department_id'] = null;
        }

        $columns = array_keys($restoreData);
        $placeholders = implode(',', array_fill(0, count($columns), '?'));

        $pdo->beginTransaction();
        $stmt = $pdo->prepare('INSERT INTO other_equipment (`' . implode('`,`', $columns) . '`) VALUES (' . $placeholders . ')');
        $stmt->execute(array_values($restoreData));
        $stmt = $pdo->prepare('DELETE FROM archive_other_equipment WHERE id = ?');
        $stmt->execute([$id]);
        $pdo->commit();

        log_activity('SUPPLY_RESTORE', 'Restored archived supply: ' . ($record['code'] ?? $id));
        supplies_action_response(['success' => true, 'message' => 'Supply restored successfully.']);
    }

    if ($action === 'permanent_delete') {
        require_permission('ictd.supplies.delete');
        $id = (int) ($payload['id'] ?? $_POST['id'] ?? 0);
        if ($id <= 0) {
            supplies_action_response(['success' => false, 'message' => 'Invalid archive record.'], 422);
        }

        supplies_ensure_archive_schema($pdo);

        $stmt = $pdo->prepare('SELECT code, control_no, serialno FROM archive_other_equipment WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$record) {
            supplies_action_response(['success' => false, 'message' => 'Archived supply record not found.'], 404);
        }

        $stmt = $pdo->prepare('DELETE FROM archive_other_equipment WHERE id = ?');
        $stmt->execute([$id]);

        log_activity('SUPPLY_PERMANENT_DELETE', 'Permanently deleted archived supply: ' . ($record['code'] ?? $id) . ' | control_no=' . ($record['control_no'] ?? 'none') . ' | serialno=' . ($record['serialno'] ?? 'none'));
        supplies_action_response(['success' => true, 'message' => 'Archived supply permanently deleted.']);
    }

    supplies_action_response(['success' => false, 'message' => 'Invalid supply action.'], 400);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    supplies_action_response([
        'success' => false,
        'message' => 'Supply action failed: ' . $e->getMessage(),
    ], 500);
}
