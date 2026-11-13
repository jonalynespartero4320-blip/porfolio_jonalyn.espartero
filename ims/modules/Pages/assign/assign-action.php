<?php
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_login();

header('Content-Type: application/json');

function assign_response(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function assign_payload(): array
{
    $raw = file_get_contents('php://input');
    $json = json_decode($raw ?: '', true);

    return is_array($json) ? $json : $_POST;
}

function assign_clean_string($value): ?string
{
    $value = trim((string) ($value ?? ''));
    return $value === '' ? null : $value;
}

function assign_clean_int($value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }

    return is_numeric($value) ? (int) $value : null;
}

function assign_duration_label(?string $assignedDate): ?string
{
    if (!$assignedDate) {
        return null;
    }

    try {
        $start = new DateTimeImmutable($assignedDate);
        $today = new DateTimeImmutable('today');
    } catch (Throwable $e) {
        return null;
    }

    if ($start > $today) {
        return '0 days';
    }

    $diff = $start->diff($today);
    if ($diff->y > 0) {
        return $diff->y . ' year' . ($diff->y === 1 ? '' : 's');
    }
    if ($diff->m > 0) {
        return $diff->m . ' month' . ($diff->m === 1 ? '' : 's');
    }

    return $diff->d . ' day' . ($diff->d === 1 ? '' : 's');
}

function assign_item_type_from_record(array $record): string
{
    return !empty($record['equipment_id']) ? 'equipment' : 'supply';
}

function assign_store_pdf(PDO $pdo, int $assignedId, array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('No PDF file uploaded.');
    }
    if (($file['size'] ?? 0) > 1024 * 1024) {
        throw new RuntimeException('PDF must not exceed 1MB.');
    }
    if (strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION)) !== 'pdf') {
        throw new RuntimeException('Only PDF files are allowed.');
    }

    assign_remove_pdf($pdo, $assignedId);

    $uploadDir = ROOT_PATH . '/public/demo-uploads/assign-reports';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }
    $filename = 'assign-report-' . $assignedId . '-' . date('YmdHis') . '.pdf';
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $filename)) {
        throw new RuntimeException('Unable to save PDF attachment.');
    }

    return 'demo-uploads/assign-reports/' . $filename;
}

function assign_remove_pdf(PDO $pdo, int $assignedId): void
{
    $stmt = $pdo->prepare('SELECT attachment_pdf FROM assigned_record WHERE assigned_id = ?');
    $stmt->execute([$assignedId]);
    $pdf = trim((string) ($stmt->fetchColumn() ?: ''));
    if ($pdf !== '') {
        $absolutePath = ROOT_PATH . '/public/' . ltrim($pdf, '/');
        if (is_file($absolutePath)) {
            unlink($absolutePath);
        }
    }
    $pdo->prepare('UPDATE assigned_record SET attachment_pdf = NULL WHERE assigned_id = ?')->execute([$assignedId]);
}

function assign_write_history(PDO $pdo, string $action, array $record, array $changes = []): void
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
        assign_clean_int($record['assigned_id'] ?? null),
        $action,
        $changes['item_type'] ?? assign_item_type_from_record($record),
        assign_clean_string($record['control_no'] ?? null),
        assign_clean_int($record['equipment_id'] ?? null),
        assign_clean_int($changes['old_end_user_id'] ?? ($record['end_user_id'] ?? null)),
        assign_clean_int($changes['new_end_user_id'] ?? ($record['end_user_id'] ?? null)),
        assign_clean_int($changes['old_department_id'] ?? ($record['department_id'] ?? null)),
        assign_clean_int($changes['new_department_id'] ?? ($record['department_id'] ?? null)),
        $changes['details'] ?? null,
        assign_clean_int($user['id'] ?? null),
        assign_clean_string($user['username'] ?? 'Guest'),
    ]);
}

function assign_ensure_items_schema(PDO $pdo): void
{
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

    $existing = (int) $pdo->query('SELECT COUNT(*) FROM assigned_record_items')->fetchColumn();
    if ($existing > 0) {
        return;
    }

    $rows = $pdo->query("
        SELECT
            ar.assigned_id,
            CASE WHEN ar.equipment_id IS NOT NULL AND ar.equipment_id > 0 THEN 'equipment' ELSE 'supply' END AS item_type,
            CASE WHEN ar.equipment_id IS NOT NULL AND ar.equipment_id > 0 THEN ar.equipment_id ELSE oe.id END AS source_id,
            ar.control_no,
            COALESCE(e.serialno, oe.serialno) AS serialno,
            COALESCE(es.equipment_status, oe.status) AS item_status
        FROM assigned_record ar
        LEFT JOIN equipment e ON e.id = ar.equipment_id OR e.control_no = ar.control_no OR e.serialno = ar.control_no
        LEFT JOIN list_equipment_status es ON es.id = e.equipment_status_id
        LEFT JOIN other_equipment oe ON oe.assigned_id = ar.assigned_id OR oe.serialno = ar.control_no OR oe.code = ar.control_no
        WHERE TRIM(COALESCE(ar.control_no, '')) <> ''
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $insert = $pdo->prepare('INSERT INTO assigned_record_items (assigned_id, item_type, source_id, control_no, serialno, item_status) VALUES (?, ?, ?, ?, ?, ?)');
    foreach ($rows as $row) {
        $insert->execute([
            (int) $row['assigned_id'],
            $row['item_type'] ?: 'equipment',
            assign_clean_int($row['source_id'] ?? null),
            assign_clean_string($row['control_no'] ?? null),
            assign_clean_string($row['serialno'] ?? null),
            assign_clean_string($row['item_status'] ?? null),
        ]);
    }
}

function assign_record_items(PDO $pdo, int $assignedId): array
{
    $stmt = $pdo->prepare("
        SELECT
            ari.*,
            CONCAT(ari.item_type, ':', ari.source_id) AS item_key,
            COALESCE(de.description_name, ds.description_name) AS description_name,
            COALESCE(be.brand_name, bs.brand_name) AS brand_name,
            COALESCE(e.old_control_no, oe.old_control_no) AS old_control_no
        FROM assigned_record_items ari
        LEFT JOIN equipment e ON ari.item_type = 'equipment' AND e.id = ari.source_id
        LEFT JOIN list_description de ON de.id = e.description_id
        LEFT JOIN list_brand be ON be.id = e.brand_id
        LEFT JOIN other_equipment oe ON ari.item_type = 'supply' AND oe.id = ari.source_id
        LEFT JOIN list_description ds ON ds.id = oe.description_id
        LEFT JOIN list_brand bs ON bs.id = oe.brand_id
        WHERE ari.assigned_id = ?
        ORDER BY ari.item_type ASC, ari.id ASC
    ");
    $stmt->execute([$assignedId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function assign_parse_item_key(string $itemKey): ?array
{
    [$type, $rawId] = array_pad(explode(':', $itemKey, 2), 2, null);
    $id = (int) ($rawId ?? 0);
    if ($id <= 0 || !in_array($type, ['equipment', 'supply'], true)) {
        return null;
    }
    return ['type' => $type, 'id' => $id];
}

function assign_masterlists(PDO $pdo): array
{
    $users = $pdo->query("
        SELECT
            eu.end_user_id AS id,
            eu.end_user_name AS text,
            eu.department_id,
            dep.department_name
        FROM list_end_user eu
        LEFT JOIN list_department dep ON dep.department_id = eu.department_id
        ORDER BY eu.end_user_name ASC
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $equipmentWhere = [];
    if (db_column_exists($pdo, 'equipment', 'department_id')) {
        $equipmentWhere[] = '(e.department_id IS NULL OR e.department_id = 0)';
    }
    if (db_column_exists($pdo, 'equipment', 'assigned_id')) {
        $equipmentWhere[] = '(e.assigned_id IS NULL OR e.assigned_id = 0)';
    }
    if (db_column_exists($pdo, 'equipment', 'is_archived')) {
        $equipmentWhere[] = '(e.is_archived IS NULL OR e.is_archived = 0)';
    }
    if (db_table_exists($pdo, 'assigned_record_items')) {
        $equipmentWhere[] = "NOT EXISTS (
            SELECT 1
            FROM assigned_record_items ari
            WHERE ari.item_type = 'equipment'
              AND (ari.source_id = e.id
                OR (TRIM(COALESCE(e.control_no, '')) <> '' AND ari.control_no = e.control_no)
                OR (TRIM(COALESCE(e.serialno, '')) <> '' AND ari.control_no = e.serialno))
            LIMIT 1
        )";
    }
    $equipmentWhereSql = $equipmentWhere ? 'WHERE ' . implode(' AND ', $equipmentWhere) : '';

    $supplyWhere = [];
    if (db_column_exists($pdo, 'other_equipment', 'department_id')) {
        $supplyWhere[] = '(oe.department_id IS NULL OR oe.department_id = 0)';
    }
    if (db_column_exists($pdo, 'other_equipment', 'end_user_id')) {
        $supplyWhere[] = '(oe.end_user_id IS NULL OR oe.end_user_id = 0)';
    }
    if (db_column_exists($pdo, 'other_equipment', 'assigned_id')) {
        $supplyWhere[] = '(oe.assigned_id IS NULL OR oe.assigned_id = 0)';
    }
    if (db_column_exists($pdo, 'other_equipment', 'is_archived')) {
        $supplyWhere[] = '(oe.is_archived IS NULL OR oe.is_archived = 0)';
    }
    if (db_table_exists($pdo, 'assigned_record_items')) {
        $supplyWhere[] = "NOT EXISTS (
            SELECT 1
            FROM assigned_record_items ari
            WHERE ari.item_type = 'supply'
              AND (ari.source_id = oe.id
                OR (TRIM(COALESCE(oe.serialno, '')) <> '' AND ari.control_no = oe.serialno)
                OR (TRIM(COALESCE(oe.code, '')) <> '' AND ari.control_no = oe.code))
            LIMIT 1
        )";
    }
    $supplyWhereSql = $supplyWhere ? 'WHERE ' . implode(' AND ', $supplyWhere) : '';

    $equipment = db_table_exists($pdo, 'equipment')
        ? ($pdo->query("
            SELECT
                CONCAT('equipment:', e.id) AS id,
                'equipment' AS item_type,
                e.id AS item_id,
                e.control_no,
                e.serialno,
                CONCAT(
                    'Control: ', COALESCE(e.control_no, 'No Control'),
                    ' | Serial: ', COALESCE(e.serialno, 'No Serial'),
                    ' | ', COALESCE(d.description_name, 'Equipment'),
                    ' ', COALESCE(b.brand_name, '')
                ) AS text
            FROM equipment e
            LEFT JOIN list_description d ON d.id = e.description_id
            LEFT JOIN list_brand b ON b.id = e.brand_id
            LEFT JOIN list_equipment_status es ON es.id = e.equipment_status_id
            {$equipmentWhereSql}
            ORDER BY e.control_no ASC
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [])
        : [];

    $supplies = db_table_exists($pdo, 'other_equipment')
        ? ($pdo->query("
            SELECT
                CONCAT('supply:', oe.id) AS id,
                'supply' AS item_type,
                oe.id AS item_id,
                COALESCE(oe.code, oe.serialno) AS control_no,
                oe.serialno,
                oe.code,
                CONCAT(
                    'Code: ', COALESCE(oe.code, 'No Code'),
                    ' | Old Control: ', COALESCE(oe.old_control_no, 'No Old Control'),
                    ' | Serial: ', COALESCE(oe.serialno, 'No Serial'),
                    ' | ', COALESCE(d.description_name, 'Supply'),
                    ' ', COALESCE(b.brand_name, '')
                ) AS text
            FROM other_equipment oe
            LEFT JOIN list_description d ON d.id = oe.description_id
            LEFT JOIN list_brand b ON b.id = oe.brand_id
            {$supplyWhereSql}
            ORDER BY oe.code ASC
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [])
        : [];

    return [
        'users' => $users,
        'equipment' => $equipment,
        'supplies' => $supplies,
        'items' => array_merge($equipment, $supplies),
    ];
}

function assign_item_record(PDO $pdo, string $itemKey): ?array
{
    [$type, $rawId] = array_pad(explode(':', $itemKey, 2), 2, null);
    $id = (int) $rawId;
    if ($id <= 0 || !in_array($type, ['equipment', 'supply'], true)) {
        return null;
    }

    if ($type === 'equipment') {
        $stmt = $pdo->prepare("
            SELECT
                e.*,
                COALESCE(e.control_no, e.serialno) AS assignment_control_no,
                COALESCE(es.equipment_status, 'New') AS assignment_status
            FROM equipment e
            LEFT JOIN list_equipment_status es ON es.id = e.equipment_status_id
            WHERE e.id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return [
            'type' => 'equipment',
            'id' => $id,
            'control_no' => assign_clean_string($row['assignment_control_no'] ?? null),
            'serialno' => assign_clean_string($row['serialno'] ?? null),
            'status' => assign_clean_string($row['assignment_status'] ?? 'New'),
            'department_id' => assign_clean_int($row['department_id'] ?? null),
            'assigned_id' => assign_clean_int($row['assigned_id'] ?? null),
        ];
    }

    $stmt = $pdo->prepare("
        SELECT
            *,
            COALESCE(code, serialno) AS assignment_control_no,
            COALESCE(status, 'New') AS assignment_status
        FROM other_equipment
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }

    return [
        'type' => 'supply',
        'id' => $id,
        'control_no' => assign_clean_string($row['assignment_control_no'] ?? null),
        'serialno' => assign_clean_string($row['serialno'] ?? null),
        'status' => assign_clean_string($row['assignment_status'] ?? 'New'),
        'department_id' => assign_clean_int($row['department_id'] ?? null),
        'end_user_id' => assign_clean_int($row['end_user_id'] ?? null),
        'assigned_id' => assign_clean_int($row['assigned_id'] ?? null),
    ];
}

if (!db_table_exists($pdo, 'assigned_record')) {
    assign_response(['success' => false, 'message' => 'The assigned_record table is missing.'], 500);
}
if (!db_column_exists($pdo, 'assigned_record', 'attachment_pdf')) {
    $pdo->exec('ALTER TABLE assigned_record ADD COLUMN attachment_pdf VARCHAR(255) NULL');
}
if (db_table_exists($pdo, 'other_equipment') && !db_column_exists($pdo, 'other_equipment', 'assigned_id')) {
    $pdo->exec('ALTER TABLE other_equipment ADD COLUMN assigned_id INT NULL');
}
assign_ensure_items_schema($pdo);

$payload = assign_payload();
$action = trim((string) ($_GET['action'] ?? $payload['action'] ?? ''));

try {
    if ($action === 'masterlists') {
        require_permission('ictd.assign.view');
        assign_response(['success' => true, 'data' => assign_masterlists($pdo)]);
    }

    if ($action === 'upload_pdf') {
        require_permission('ictd.assign.create');
        $id = (int) ($payload['assigned_id'] ?? $payload['id'] ?? 0);
        if ($id <= 0) {
            assign_response(['success' => false, 'message' => 'Invalid assignment record.'], 422);
        }
        $attachment = assign_store_pdf($pdo, $id, $_FILES['attachment_pdf'] ?? []);
        $stmt = $pdo->prepare('UPDATE assigned_record SET attachment_pdf = ? WHERE assigned_id = ?');
        $stmt->execute([$attachment, $id]);
        log_activity('ASSIGN_PDF_UPLOAD', 'Uploaded assignment PDF #' . $id);
        assign_response(['success' => true, 'message' => 'Assignment PDF uploaded successfully.']);
    }

    if ($action === 'remove_pdf') {
        require_permission('ictd.assign.create');
        $id = (int) ($payload['assigned_id'] ?? $payload['id'] ?? 0);
        if ($id <= 0) {
            assign_response(['success' => false, 'message' => 'Invalid assignment record.'], 422);
        }
        assign_remove_pdf($pdo, $id);
        log_activity('ASSIGN_PDF_REMOVE', 'Removed assignment PDF #' . $id);
        assign_response(['success' => true, 'message' => 'Assignment PDF removed successfully.']);
    }

    if ($action === 'create') {
        require_permission('ictd.assign.create');

        $endUserId = assign_clean_int($payload['end_user_id'] ?? null);
        $departmentId = assign_clean_int($payload['department_id'] ?? null);
        $equipmentItems = is_array($payload['equipment_items'] ?? null) ? $payload['equipment_items'] : [];
        $supplyItems = is_array($payload['supply_items'] ?? null) ? $payload['supply_items'] : [];
        $legacyItems = is_array($payload['items'] ?? null) ? $payload['items'] : [];
        $items = array_values(array_unique(array_merge($legacyItems, $equipmentItems, $supplyItems)));
        $connectionIp = assign_clean_string($payload['connection_ip'] ?? null);
        $internet = (($payload['internet'] ?? 'No') === 'Yes') ? 'Yes' : 'No';
        $wifi = (($payload['wifi'] ?? 'No') === 'Yes') ? 'Yes' : 'No';
        $programInstalled = assign_clean_string($payload['program_installed'] ?? null);
        $assignedDate = assign_clean_string($payload['assigned_date'] ?? null) ?: date('Y-m-d');
        $monthsSinceAssigned = assign_clean_string($payload['months_since_assigned'] ?? null) ?: assign_duration_label($assignedDate);
        $equipmentAnalysis = assign_clean_string($payload['equipment_analysis'] ?? null);
        $createdBy = (int) ((current_user()['id'] ?? null) ?: ($_SESSION['user']['id'] ?? 0));

        if (!$endUserId || !$departmentId) {
            assign_response(['success' => false, 'message' => 'End user and department are required.'], 422);
        }
        if (!is_array($items) || empty($items)) {
            assign_response(['success' => false, 'message' => 'Select at least one equipment or supply item.'], 422);
        }

        $pdo->beginTransaction();

        $created = [];
        $resolvedItems = [];

        foreach ($items as $itemKey) {
            $item = assign_item_record($pdo, (string) $itemKey);
            if (!$item || !$item['control_no']) {
                throw new RuntimeException('One selected item is invalid or has no control number/serial/code.');
            }

            if ($item['type'] === 'equipment' && (!empty($item['department_id']) || !empty($item['assigned_id']))) {
                throw new RuntimeException('Item ' . $item['control_no'] . ' is already assigned.');
            }

            if ($item['type'] === 'supply' && (!empty($item['department_id']) || !empty($item['end_user_id']) || !empty($item['assigned_id']))) {
                throw new RuntimeException('Item ' . $item['control_no'] . ' is already assigned.');
            }

            $stmt = $pdo->prepare('SELECT assigned_id FROM assigned_record_items WHERE control_no = ? LIMIT 1');
            $stmt->execute([$item['control_no']]);
            if ($stmt->fetch()) {
                throw new RuntimeException('Item ' . $item['control_no'] . ' is already assigned.');
            }

            $resolvedItems[] = $item;
        }

        $primary = $resolvedItems[0];
        $primaryEquipmentId = $primary['type'] === 'equipment' ? $primary['id'] : null;
        $stmt = $pdo->prepare('SELECT assigned_id, control_no, equipment_id FROM assigned_record WHERE end_user_id = ? LIMIT 1');
        $stmt->execute([$endUserId]);
        $existingRecord = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($existingRecord) {
            $assignedId = (int) $existingRecord['assigned_id'];
            $stmt = $pdo->prepare("
                UPDATE assigned_record
                SET department_id = ?,
                    connection_ip = COALESCE(NULLIF(?, ''), connection_ip),
                    internet = ?,
                    wifi = ?,
                    program_installed = COALESCE(NULLIF(?, ''), program_installed),
                    assigned_date = COALESCE(NULLIF(?, ''), assigned_date),
                    months_since_assigned = COALESCE(NULLIF(?, ''), months_since_assigned),
                    equipment_analysis = COALESCE(NULLIF(?, ''), equipment_analysis),
                    equipment_id = COALESCE(equipment_id, ?),
                    control_no = COALESCE(NULLIF(control_no, ''), ?)
                WHERE assigned_id = ?
            ");
            $stmt->execute([
                $departmentId,
                $connectionIp,
                $internet,
                $wifi,
                $programInstalled,
                $assignedDate,
                $monthsSinceAssigned,
                $equipmentAnalysis,
                $primaryEquipmentId,
                $primary['control_no'],
                $assignedId,
            ]);
        } else {
            $insert = $pdo->prepare("
                INSERT INTO assigned_record
                    (end_user_id, department_id, equipment_id, control_no, connection_ip, internet, wifi, program_installed, assigned_date, months_since_assigned, equipment_analysis, created_by)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $insert->execute([
                $endUserId,
                $departmentId,
                $primaryEquipmentId,
                $primary['control_no'],
                $connectionIp,
                $internet,
                $wifi,
                $programInstalled,
                $assignedDate,
                $monthsSinceAssigned,
                $equipmentAnalysis,
                $createdBy,
            ]);
            $assignedId = (int) $pdo->lastInsertId();
        }
        $itemInsert = $pdo->prepare('INSERT INTO assigned_record_items (assigned_id, item_type, source_id, control_no, serialno, item_status) VALUES (?, ?, ?, ?, ?, ?)');

        foreach ($resolvedItems as $item) {
            $equipmentId = $item['type'] === 'equipment' ? $item['id'] : null;
            $itemInsert->execute([
                $assignedId,
                $item['type'],
                $item['id'],
                $item['control_no'],
                $item['serialno'] ?? null,
                $item['status'] ?? null,
            ]);

            if ($item['type'] === 'equipment') {
                $stmt = $pdo->prepare('UPDATE equipment SET assigned_id = ?, department_id = ? WHERE id = ?');
                $stmt->execute([$assignedId, $departmentId, $item['id']]);
            } elseif ($item['type'] === 'supply') {
                $stmt = $pdo->prepare('UPDATE other_equipment SET assigned_id = ?, end_user_id = ?, department_id = ? WHERE id = ?');
                $stmt->execute([$assignedId, $endUserId, $departmentId, $item['id']]);
            }

            assign_write_history($pdo, 'assign', [
                'assigned_id' => $assignedId,
                'end_user_id' => $endUserId,
                'department_id' => $departmentId,
                'equipment_id' => $equipmentId,
                'control_no' => $item['control_no'],
            ], [
                'item_type' => $item['type'],
                'old_end_user_id' => null,
                'new_end_user_id' => $endUserId,
                'old_department_id' => null,
                'new_department_id' => $departmentId,
                'details' => 'Initial assignment | internet=' . $internet . ' | wifi=' . $wifi . ' | ip=' . ($connectionIp ?? 'none'),
            ]);
            $created[] = $item['type'] . ':' . $item['control_no'];
        }

        $pdo->commit();

        log_activity('ASSIGN_CREATE', 'Assigned ' . count($created) . ' item(s) to end_user_id=' . $endUserId . ' | items=' . implode(', ', $created));
        assign_response(['success' => true, 'message' => count($created) . ' item(s) assigned successfully.']);
    }

    if ($action === 'view') {
        require_permission('ictd.assign.view');
        $id = (int) ($_GET['id'] ?? $payload['id'] ?? 0);
        $stmt = $pdo->prepare("
            SELECT ar.*, eu.end_user_name, dep.department_name
            FROM assigned_record ar
            LEFT JOIN list_end_user eu ON eu.end_user_id = ar.end_user_id
            LEFT JOIN list_department dep ON dep.department_id = ar.department_id
            WHERE ar.assigned_id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$record) {
            assign_response(['success' => false, 'message' => 'Assignment record not found.'], 404);
        }

        $record['items'] = assign_record_items($pdo, $id);
        assign_response(['success' => true, 'data' => $record]);
    }

    if ($action === 'reassign') {
        require_permission('ictd.assign.create');
        $id = (int) ($payload['assigned_id'] ?? $payload['id'] ?? 0);
        $newEndUserId = assign_clean_int($payload['end_user_id'] ?? null);
        $newDepartmentId = assign_clean_int($payload['department_id'] ?? null);
        if ($id <= 0 || !$newEndUserId || !$newDepartmentId) {
            assign_response(['success' => false, 'message' => 'Assignment, new user, and department are required.'], 422);
        }

        $stmt = $pdo->prepare('SELECT * FROM assigned_record WHERE assigned_id = ? LIMIT 1');
        $stmt->execute([$id]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$record) {
            assign_response(['success' => false, 'message' => 'Assignment record not found.'], 404);
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare('UPDATE assigned_record SET end_user_id = ?, department_id = ? WHERE assigned_id = ?');
        $stmt->execute([$newEndUserId, $newDepartmentId, $id]);

        $pdo->prepare('UPDATE equipment SET department_id = ? WHERE assigned_id = ?')->execute([$newDepartmentId, $id]);
        $pdo->prepare('UPDATE other_equipment SET end_user_id = ?, department_id = ? WHERE assigned_id = ?')->execute([$newEndUserId, $newDepartmentId, $id]);

        assign_write_history($pdo, 'reassign', $record, [
            'old_end_user_id' => $record['end_user_id'] ?? null,
            'new_end_user_id' => $newEndUserId,
            'old_department_id' => $record['department_id'] ?? null,
            'new_department_id' => $newDepartmentId,
            'details' => 'Reassigned assignment and all included items to a different end user.',
        ]);

        $pdo->commit();

        log_activity('ASSIGN_REASSIGN', 'Reassigned assignment ID ' . $id . ' from end_user_id=' . ($record['end_user_id'] ?? 'none') . ' to end_user_id=' . $newEndUserId);
        assign_response(['success' => true, 'message' => 'Assignment reassigned successfully.']);
    }

    if ($action === 'update') {
        require_permission('ictd.assign.create');
        $id = (int) ($payload['assigned_id'] ?? $payload['id'] ?? 0);
        if ($id <= 0) {
            assign_response(['success' => false, 'message' => 'Invalid assignment record.'], 422);
        }

        $stmt = $pdo->prepare('SELECT * FROM assigned_record WHERE assigned_id = ? LIMIT 1');
        $stmt->execute([$id]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$record) {
            assign_response(['success' => false, 'message' => 'Assignment record not found.'], 404);
        }

        $connectionIp = assign_clean_string($payload['connection_ip'] ?? null);
        $internet = (($payload['internet'] ?? 'No') === 'Yes') ? 'Yes' : 'No';
        $wifi = (($payload['wifi'] ?? 'No') === 'Yes') ? 'Yes' : 'No';
        $programInstalled = assign_clean_string($payload['program_installed'] ?? null);
        $assignedDate = assign_clean_string($payload['assigned_date'] ?? null) ?: date('Y-m-d');
        $monthsSinceAssigned = assign_clean_string($payload['months_since_assigned'] ?? null) ?: assign_duration_label($assignedDate);
        $equipmentAnalysis = assign_clean_string($payload['equipment_analysis'] ?? null);

        $equipmentItems = is_array($payload['equipment_items'] ?? null) ? $payload['equipment_items'] : null;
        $supplyItems = is_array($payload['supply_items'] ?? null) ? $payload['supply_items'] : null;
        $itemKeys = null;
        if ($equipmentItems !== null || $supplyItems !== null) {
            $equipmentItems = is_array($equipmentItems) ? $equipmentItems : [];
            $supplyItems = is_array($supplyItems) ? $supplyItems : [];
            $itemKeys = array_values(array_unique(array_merge($equipmentItems, $supplyItems)));
            if (empty($itemKeys)) {
                assign_response(['success' => false, 'message' => 'Select at least one equipment or supply item.'], 422);
            }
        }

        $currentItems = assign_record_items($pdo, $id);
        $currentKeys = array_values(array_filter(array_map(static fn(array $item): ?string => trim((string) ($item['item_key'] ?? '')), $currentItems)));

        $pdo->beginTransaction();

        $primaryEquipmentId = $record['equipment_id'];
        $primaryControlNo = $record['control_no'];
        $itemChangeDetails = [];

        if ($itemKeys !== null) {
            $validatedItems = [];
            foreach ($itemKeys as $itemKey) {
                $item = assign_parse_item_key((string) $itemKey);
                if (!$item) {
                    throw new RuntimeException('One selected item is invalid.');
                }
                $itemRecord = assign_item_record($pdo, $itemKey);
                if (!$itemRecord || !$itemRecord['control_no']) {
                    throw new RuntimeException('One selected item is invalid or missing control information.');
                }

                $stmt = $pdo->prepare('SELECT assigned_id FROM assigned_record_items WHERE item_type = ? AND source_id = ? AND assigned_id <> ? LIMIT 1');
                $stmt->execute([$item['type'], $item['id'], $id]);
                if ($stmt->fetch()) {
                    throw new RuntimeException('Item ' . $itemRecord['control_no'] . ' is already assigned to another record.');
                }

                $validatedItems[] = $itemRecord;
            }

            $newKeys = array_values(array_unique(array_map(static fn(array $item): string => $item['type'] . ':' . $item['id'], $validatedItems)));
            $keysToRemove = array_values(array_diff($currentKeys, $newKeys));
            $keysToAdd = array_values(array_diff($newKeys, $currentKeys));

            if (!empty($keysToRemove) || !empty($keysToAdd)) {
                if (!empty($keysToRemove)) {
                    foreach ($keysToRemove as $itemKey) {
                        $item = assign_parse_item_key($itemKey);
                        if (!$item) {
                            continue;
                        }
                        if ($item['type'] === 'equipment') {
                            $pdo->prepare('UPDATE equipment SET assigned_id = NULL, department_id = NULL WHERE id = ?')->execute([$item['id']]);
                        } else {
                            $pdo->prepare('UPDATE other_equipment SET assigned_id = NULL, end_user_id = NULL, department_id = NULL WHERE id = ?')->execute([$item['id']]);
                        }
                        $pdo->prepare('DELETE FROM assigned_record_items WHERE assigned_id = ? AND item_type = ? AND source_id = ? LIMIT 1')->execute([$id, $item['type'], $item['id']]);
                    }
                    $itemChangeDetails[] = 'removed ' . count($keysToRemove) . ' item(s)';
                }

                if (!empty($keysToAdd)) {
                    $insert = $pdo->prepare('INSERT INTO assigned_record_items (assigned_id, item_type, source_id, control_no, serialno, item_status) VALUES (?, ?, ?, ?, ?, ?)');
                    foreach ($validatedItems as $item) {
                        $itemKey = $item['type'] . ':' . $item['id'];
                        if (!in_array($itemKey, $keysToAdd, true)) {
                            continue;
                        }
                        $insert->execute([
                            $id,
                            $item['type'],
                            $item['id'],
                            $item['control_no'],
                            $item['serialno'] ?? null,
                            $item['status'] ?? null,
                        ]);
                        if ($item['type'] === 'equipment') {
                            $pdo->prepare('UPDATE equipment SET assigned_id = ?, department_id = ? WHERE id = ?')->execute([$id, $record['department_id'], $item['id']]);
                        } else {
                            $pdo->prepare('UPDATE other_equipment SET assigned_id = ?, end_user_id = ?, department_id = ? WHERE id = ?')->execute([$id, $record['end_user_id'], $record['department_id'], $item['id']]);
                        }
                    }
                    $itemChangeDetails[] = 'added ' . count($keysToAdd) . ' item(s)';
                }

                $primary = null;
                foreach ($validatedItems as $item) {
                    if ($item['type'] === 'equipment') {
                        $primary = $item;
                        break;
                    }
                    if ($primary === null) {
                        $primary = $item;
                    }
                }
                if ($primary) {
                    $primaryEquipmentId = $primary['type'] === 'equipment' ? $primary['id'] : null;
                    $primaryControlNo = $primary['control_no'];
                }
            }
        }

        $updateSql = "UPDATE assigned_record
            SET connection_ip = ?,
                internet = ?,
                wifi = ?,
                program_installed = ?,
                assigned_date = ?,
                months_since_assigned = ?,
                equipment_analysis = ?";
        $params = [
            $connectionIp,
            $internet,
            $wifi,
            $programInstalled,
            $assignedDate,
            $monthsSinceAssigned,
            $equipmentAnalysis,
        ];

        if ($itemKeys !== null) {
            $updateSql .= ",
                equipment_id = ?,
                control_no = ?";
            $params[] = $primaryEquipmentId;
            $params[] = $primaryControlNo;
        }

        $updateSql .= "
            WHERE assigned_id = ?";
        $params[] = $id;

        $stmt = $pdo->prepare($updateSql);
        $stmt->execute($params);

        assign_write_history($pdo, 'edit', $record, [
            'details' => 'Edited assignment details' . (empty($itemChangeDetails) ? '' : ' (' . implode(', ', $itemChangeDetails) . ')') . ' | ip: ' . ($record['connection_ip'] ?? 'none') . ' -> ' . ($connectionIp ?? 'none') . ' | internet=' . $internet . ' | wifi=' . $wifi . ' | assigned_date=' . $assignedDate,
        ]);

        $pdo->commit();

        log_activity('ASSIGN_UPDATE', 'Updated assignment details ID ' . $id . ' | control_no=' . ($record['control_no'] ?? 'none'));
        assign_response(['success' => true, 'message' => 'Assignment updated successfully.']);
    }

    if ($action === 'delete') {
        require_permission('ictd.assign.delete');
        $id = (int) ($payload['assigned_id'] ?? $payload['id'] ?? 0);
        if ($id <= 0) {
            assign_response(['success' => false, 'message' => 'Invalid assignment record.'], 422);
        }

        $stmt = $pdo->prepare('SELECT * FROM assigned_record WHERE assigned_id = ? LIMIT 1');
        $stmt->execute([$id]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$record) {
            assign_response(['success' => false, 'message' => 'Assignment record not found.'], 404);
        }

        $pdo->beginTransaction();
        $pdo->prepare('UPDATE equipment SET assigned_id = NULL, department_id = NULL WHERE assigned_id = ?')->execute([$id]);
        $pdo->prepare('UPDATE other_equipment SET assigned_id = NULL, end_user_id = NULL, department_id = NULL WHERE assigned_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM assigned_record_items WHERE assigned_id = ?')->execute([$id]);
        $stmt = $pdo->prepare('DELETE FROM assigned_record WHERE assigned_id = ?');
        $stmt->execute([$id]);

        assign_write_history($pdo, 'unassign', $record, [
            'old_end_user_id' => $record['end_user_id'] ?? null,
            'new_end_user_id' => null,
            'old_department_id' => $record['department_id'] ?? null,
            'new_department_id' => null,
            'details' => 'Assignment removed from Assign module.',
        ]);

        $pdo->commit();

        log_activity('ASSIGN_DELETE', 'Unassigned record ID ' . $id . ' | control_no=' . ($record['control_no'] ?? 'none'));
        assign_response(['success' => true, 'message' => 'Assignment removed successfully.']);
    }

    assign_response(['success' => false, 'message' => 'Invalid assignment action.'], 400);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    assign_response(['success' => false, 'message' => 'Assignment failed: ' . $e->getMessage()], 500);
}
