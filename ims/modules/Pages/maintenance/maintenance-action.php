<?php
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_login();
ensure_record_scope_schema($pdo);
header('Content-Type: application/json');

function mr_response(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function mr_payload(): array
{
    $raw = file_get_contents('php://input');
    $json = json_decode($raw ?: '', true);
    return is_array($json) ? $json : $_POST;
}

function mr_clean_string($value): ?string
{
    $value = trim((string) ($value ?? ''));
    return $value === '' ? null : $value;
}

function mr_clean_int($value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }
    return is_numeric($value) ? (int) $value : null;
}

function mr_ensure_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS maintenance_reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            maintenance_id VARCHAR(255) NOT NULL,
            control_no VARCHAR(50) NOT NULL,
            maintenance_status ENUM('completed','incomplete') NOT NULL DEFAULT 'incomplete',
            branded VARCHAR(255) NULL,
            assemble VARCHAR(255) NULL,
            end_user_id VARCHAR(100) NULL,
            department_id VARCHAR(100) NULL,
            date_accomplished DATE NOT NULL,
            inspected_by VARCHAR(100) NOT NULL,
            attachment_pdf VARCHAR(255) NULL,
            date_recorded TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS maintenance_report_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            maintenance_report_id INT NOT NULL,
            item_type VARCHAR(30) NOT NULL DEFAULT 'equipment',
            source_id INT NULL,
            control_no VARCHAR(100) NULL,
            old_control_no VARCHAR(100) NULL,
            description_id INT NULL,
            description_name VARCHAR(255) NULL,
            brand_id INT NULL,
            brand_name VARCHAR(255) NULL,
            serialno VARCHAR(100) NULL,
            remarks TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_mr_items_report (maintenance_report_id),
            INDEX idx_mr_items_source (item_type, source_id),
            CONSTRAINT fk_mr_items_report
                FOREIGN KEY (maintenance_report_id) REFERENCES maintenance_reports(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS maintenance_report_checks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            maintenance_report_id INT NOT NULL,
            category VARCHAR(100) NOT NULL,
            item_label VARCHAR(255) NOT NULL,
            is_checked TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_mr_checks_report (maintenance_report_id),
            CONSTRAINT fk_mr_checks_report
                FOREIGN KEY (maintenance_report_id) REFERENCES maintenance_reports(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    foreach ([
        'created_by' => 'INT NULL',
        'designation_id' => 'INT NULL AFTER department_id',
        'is_archived' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'archived_at' => 'DATETIME NULL',
    ] as $column => $definition) {
        if (!db_column_exists($pdo, 'maintenance_reports', $column)) {
            $pdo->exec("ALTER TABLE maintenance_reports ADD COLUMN {$column} {$definition}");
        }
    }

    foreach ([
        'description_name' => 'VARCHAR(255) NULL',
        'brand_name' => 'VARCHAR(255) NULL',
    ] as $column => $definition) {
        if (!db_column_exists($pdo, 'maintenance_report_items', $column)) {
            $pdo->exec("ALTER TABLE maintenance_report_items ADD COLUMN {$column} {$definition}");
        }
    }
}

function mr_generate_id(PDO $pdo, ?string $dateAccomplished, ?int $ignoreId = null): string
{
    $timestamp = $dateAccomplished ? strtotime($dateAccomplished) : false;
    $year = $timestamp ? date('Y', $timestamp) : date('Y');
    $suffix = '-' . $year;
    $sql = 'SELECT maintenance_id FROM maintenance_reports WHERE maintenance_id REGEXP ?';
    $params = ['^[0-9]+-' . $year . '$'];
    if ($ignoreId) {
        $sql .= ' AND id <> ?';
        $params[] = $ignoreId;
    }
    $sql .= ' ORDER BY CAST(SUBSTRING_INDEX(maintenance_id, "-", 1) AS UNSIGNED) DESC LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $last = (string) ($stmt->fetchColumn() ?: '');
    $next = 1;
    if (preg_match('/^(\d+)-' . preg_quote($year, '/') . '$/', $last, $match)) {
        $next = ((int) $match[1]) + 1;
    }
    return str_pad((string) $next, 3, '0', STR_PAD_LEFT) . $suffix;
}

function mr_masterlists(PDO $pdo): array
{
    if (db_table_exists($pdo, 'other_equipment') && !db_column_exists($pdo, 'other_equipment', 'assigned_id')) {
        $pdo->exec('ALTER TABLE other_equipment ADD COLUMN assigned_id INT NULL');
    }

    $departments = $pdo->query('SELECT department_id AS id, department_name AS text FROM list_department ORDER BY department_name ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $technicians = db_table_exists($pdo, 'list_technical')
        ? ($pdo->query('SELECT technical_name AS id, technical_name AS text FROM list_technical ORDER BY technical_name ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [])
        : [];
    $users = $pdo->query("
        SELECT eu.end_user_id AS id, eu.end_user_name AS text, eu.department_id, dep.department_name
        FROM list_end_user eu
        LEFT JOIN list_department dep ON dep.department_id = eu.department_id
        ORDER BY eu.end_user_name ASC
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $equipment = [];
    $supplies = [];

    return compact('departments', 'technicians', 'users', 'equipment', 'supplies');
}

function mr_user_items(PDO $pdo, int $endUserId): array
{
    if (db_table_exists($pdo, 'other_equipment') && !db_column_exists($pdo, 'other_equipment', 'assigned_id')) {
        $pdo->exec('ALTER TABLE other_equipment ADD COLUMN assigned_id INT NULL');
    }

    $equipment = [];
    if (db_table_exists($pdo, 'equipment') && db_table_exists($pdo, 'assigned_record')) {
        $stmt = $pdo->prepare("
            SELECT DISTINCT
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
                CONCAT(COALESCE(e.control_no, 'No Control'), ' | Old: ', COALESCE(e.old_control_no, 'None'), ' | Serial: ', COALESCE(e.serialno, 'No Serial')) AS text
            FROM equipment e
            INNER JOIN assigned_record ar
                ON ar.end_user_id = ?
                AND (
                    ar.assigned_id = e.assigned_id
                    OR ar.equipment_id = e.id
                    OR ar.control_no = e.control_no
                    OR ar.control_no = e.serialno
                )
            LEFT JOIN list_description d ON d.id = e.description_id
            LEFT JOIN list_brand b ON b.id = e.brand_id
            ORDER BY e.control_no ASC
        ");
        $stmt->execute([$endUserId]);
        $equipment = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    $supplies = [];
    if (db_table_exists($pdo, 'other_equipment')) {
        if (db_table_exists($pdo, 'assigned_record')) {
            $stmt = $pdo->prepare("
                SELECT DISTINCT
                    CONCAT('supply:', oe.id) AS id,
                    oe.id AS source_id,
                    'supply' AS item_type,
                    COALESCE(oe.code, oe.serialno) AS control_no,
                    oe.old_control_no,
                    oe.description_id,
                    d.description_name,
                    oe.brand_id,
                    b.brand_name,
                    oe.serialno,
                    CONCAT('Supply | ', COALESCE(oe.code, 'No Code'), ' | Old: ', COALESCE(oe.old_control_no, 'None'), ' | Serial: ', COALESCE(oe.serialno, 'No Serial')) AS text
                FROM other_equipment oe
                LEFT JOIN assigned_record ar
                    ON ar.end_user_id = ?
                    AND (
                        ar.assigned_id = oe.assigned_id
                        OR ar.control_no = oe.serialno
                        OR ar.control_no = oe.code
                    )
                LEFT JOIN list_description d ON d.id = oe.description_id
                LEFT JOIN list_brand b ON b.id = oe.brand_id
                WHERE oe.end_user_id = ? OR ar.assigned_id IS NOT NULL
                ORDER BY oe.code ASC
            ");
            $stmt->execute([$endUserId, $endUserId]);
            $supplies = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } else {
            $stmt = $pdo->prepare("
                SELECT
                    CONCAT('supply:', oe.id) AS id,
                    oe.id AS source_id,
                    'supply' AS item_type,
                    COALESCE(oe.code, oe.serialno) AS control_no,
                    oe.old_control_no,
                    oe.description_id,
                    d.description_name,
                    oe.brand_id,
                    b.brand_name,
                    oe.serialno,
                    CONCAT('Supply | ', COALESCE(oe.code, 'No Code'), ' | Old: ', COALESCE(oe.old_control_no, 'None'), ' | Serial: ', COALESCE(oe.serialno, 'No Serial')) AS text
                FROM other_equipment oe
                LEFT JOIN list_description d ON d.id = oe.description_id
                LEFT JOIN list_brand b ON b.id = oe.brand_id
                WHERE oe.end_user_id = ?
                ORDER BY oe.code ASC
            ");
            $stmt->execute([$endUserId]);
            $supplies = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
    }

    return compact('equipment', 'supplies');
}

function mr_all_items(PDO $pdo): array
{
    if (db_table_exists($pdo, 'other_equipment') && !db_column_exists($pdo, 'other_equipment', 'assigned_id')) {
        $pdo->exec('ALTER TABLE other_equipment ADD COLUMN assigned_id INT NULL');
    }

    $equipment = [];
    if (db_table_exists($pdo, 'equipment')) {
        $where = [];
        if (db_column_exists($pdo, 'equipment', 'is_archived')) {
            $where[] = '(e.is_archived IS NULL OR e.is_archived = 0)';
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $stmt = $pdo->query("
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
                CONCAT(COALESCE(e.control_no, 'No Control'), ' | Old: ', COALESCE(e.old_control_no, 'None'), ' | Serial: ', COALESCE(e.serialno, 'No Serial'), ' | ', COALESCE(d.description_name, 'Equipment')) AS text
            FROM equipment e
            LEFT JOIN list_description d ON d.id = e.description_id
            LEFT JOIN list_brand b ON b.id = e.brand_id
            {$whereSql}
            ORDER BY e.control_no ASC, e.serialno ASC
        ");
        $equipment = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    $supplies = [];
    if (db_table_exists($pdo, 'other_equipment')) {
        $where = [];
        if (db_column_exists($pdo, 'other_equipment', 'is_archived')) {
            $where[] = '(oe.is_archived IS NULL OR oe.is_archived = 0)';
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $stmt = $pdo->query("
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
                CONCAT('Supply | ', COALESCE(oe.code, oe.control_no, 'No Code'), ' | Old: ', COALESCE(oe.old_control_no, 'None'), ' | Serial: ', COALESCE(oe.serialno, 'No Serial'), ' | ', COALESCE(d.description_name, 'Supply')) AS text
            FROM other_equipment oe
            LEFT JOIN list_description d ON d.id = oe.description_id
            LEFT JOIN list_brand b ON b.id = oe.brand_id
            {$whereSql}
            ORDER BY COALESCE(oe.code, oe.control_no, oe.serialno) ASC
        ");
        $supplies = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    return compact('equipment', 'supplies');
}

function mr_legacy_items_for_report(PDO $pdo, array $report): array
{
    $items = [];
    $equipmentId = mr_clean_int($report['equipment_id'] ?? null);
    $otherEquipmentId = mr_clean_int($report['other_equipment_id'] ?? null);

    if ($equipmentId && db_table_exists($pdo, 'equipment')) {
        $stmt = $pdo->prepare("
            SELECT
                NULL AS id,
                e.id AS source_id,
                'equipment' AS item_type,
                e.control_no,
                e.old_control_no,
                e.description_id,
                COALESCE(d.description_name, '') AS description_name,
                e.brand_id,
                COALESCE(b.brand_name, '') AS brand_name,
                e.serialno,
                '' AS remarks
            FROM equipment e
            LEFT JOIN list_description d ON d.id = e.description_id
            LEFT JOIN list_brand b ON b.id = e.brand_id
            WHERE e.id = ?
            LIMIT 1
        ");
        $stmt->execute([$equipmentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $items[] = $row;
        }
    }

    if ($otherEquipmentId && db_table_exists($pdo, 'other_equipment')) {
        $stmt = $pdo->prepare("
            SELECT
                NULL AS id,
                oe.id AS source_id,
                'supply' AS item_type,
                COALESCE(oe.code, oe.serialno) AS control_no,
                oe.old_control_no,
                oe.description_id,
                COALESCE(d.description_name, '') AS description_name,
                oe.brand_id,
                COALESCE(b.brand_name, '') AS brand_name,
                oe.serialno,
                '' AS remarks
            FROM other_equipment oe
            LEFT JOIN list_description d ON d.id = oe.description_id
            LEFT JOIN list_brand b ON b.id = oe.brand_id
            WHERE oe.id = ?
            LIMIT 1
        ");
        $stmt->execute([$otherEquipmentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $items[] = $row;
        }
    }

    $legacySupplyColumn = null;
    if (db_table_exists($pdo, 'maintenance_other_equipment')) {
        $legacySupplyColumn = db_column_exists($pdo, 'maintenance_other_equipment', 'other_equipment_id')
            ? 'other_equipment_id'
            : (db_column_exists($pdo, 'maintenance_other_equipment', 'oe_id') ? 'oe_id' : null);
    }
    if ($legacySupplyColumn && db_table_exists($pdo, 'other_equipment')) {
        $stmt = $pdo->prepare("
            SELECT
                NULL AS id,
                oe.id AS source_id,
                'supply' AS item_type,
                COALESCE(oe.code, oe.serialno) AS control_no,
                oe.old_control_no,
                oe.description_id,
                COALESCE(d.description_name, '') AS description_name,
                oe.brand_id,
                COALESCE(b.brand_name, '') AS brand_name,
                oe.serialno,
                '' AS remarks
            FROM maintenance_other_equipment moe
            INNER JOIN other_equipment oe ON oe.id = moe.`{$legacySupplyColumn}`
            LEFT JOIN list_description d ON d.id = oe.description_id
            LEFT JOIN list_brand b ON b.id = oe.brand_id
            WHERE moe.maintenance_id = ?
            ORDER BY moe.id ASC
        ");
        $stmt->execute([(int) ($report['id'] ?? 0)]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $exists = false;
            foreach ($items as $item) {
                if (($item['item_type'] ?? '') === 'supply' && (string) ($item['source_id'] ?? '') === (string) ($row['source_id'] ?? '')) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $items[] = $row;
            }
        }
    }

    if (empty($items) && !empty($report['control_no'])) {
        $items[] = [
            'id' => null,
            'source_id' => $equipmentId ?: $otherEquipmentId ?: null,
            'item_type' => $otherEquipmentId ? 'supply' : 'equipment',
            'control_no' => $report['control_no'],
            'old_control_no' => '',
            'description_id' => null,
            'description_name' => $report['branded'] ?: $report['assemble'] ?: '',
            'brand_id' => null,
            'brand_name' => $report['branded'] ?: '',
            'serialno' => '',
            'remarks' => '',
        ];
    }

    return $items;
}

function mr_sync_items(PDO $pdo, int $reportId, array $items): void
{
    $pdo->prepare('DELETE FROM maintenance_report_items WHERE maintenance_report_id = ?')->execute([$reportId]);
    if (empty($items)) {
        return;
    }
    $stmt = $pdo->prepare("
        INSERT INTO maintenance_report_items
            (maintenance_report_id, item_type, source_id, control_no, old_control_no, description_id, description_name, brand_id, brand_name, serialno, remarks)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $seen = [];
    foreach ($items as $item) {
        $type = mr_clean_string($item['item_type'] ?? 'equipment') ?: 'equipment';
        $sourceId = mr_clean_int($item['source_id'] ?? null);
        $controlNo = mr_clean_string($item['control_no'] ?? null);
        $dedupeKey = $type . ':' . ($sourceId ?: $controlNo ?: md5(json_encode($item)));
        if (isset($seen[$dedupeKey])) {
            continue;
        }
        $seen[$dedupeKey] = true;
        $stmt->execute([
            $reportId,
            $type,
            $sourceId,
            $controlNo,
            mr_clean_string($item['old_control_no'] ?? null),
            mr_clean_int($item['description_id'] ?? null),
            mr_clean_string($item['description_name'] ?? null),
            mr_clean_int($item['brand_id'] ?? null),
            mr_clean_string($item['brand_name'] ?? null),
            mr_clean_string($item['serialno'] ?? null),
            mr_clean_string($item['remarks'] ?? null),
        ]);
    }
}

function mr_sync_checks(PDO $pdo, int $reportId, array $checks): void
{
    $pdo->prepare('DELETE FROM maintenance_report_checks WHERE maintenance_report_id = ?')->execute([$reportId]);
    if (empty($checks)) {
        return;
    }
    $stmt = $pdo->prepare('INSERT INTO maintenance_report_checks (maintenance_report_id, category, item_label, is_checked) VALUES (?, ?, ?, ?)');
    foreach ($checks as $check) {
        $label = mr_clean_string($check['item_label'] ?? null);
        if (!$label) {
            continue;
        }
        $stmt->execute([
            $reportId,
            mr_clean_string($check['category'] ?? 'General') ?: 'General',
            $label,
            !empty($check['is_checked']) ? 1 : 0,
        ]);
    }
}

function mr_calculated_status(array $row, array $items, array $checks): string
{
    $detailsComplete = !empty($row['end_user_id'])
        && !empty($row['department_id'])
        && !empty($row['date_accomplished'])
        && !empty($row['inspected_by']);
    $itemsComplete = !empty($items);
    $checksComplete = !empty($checks);
    foreach ($checks as $check) {
        if (empty($check['is_checked'])) {
            $checksComplete = false;
            break;
        }
    }

    return ($detailsComplete && $itemsComplete && $checksComplete) ? 'completed' : 'incomplete';
}

function mr_build_type_from_items(array $items): array
{
    $haystack = '';
    $brandNames = [];
    foreach ($items as $item) {
        $haystack .= ' ' . implode(' ', [
            (string) ($item['description_name'] ?? ''),
            (string) ($item['brand_name'] ?? ''),
        ]);
        $brandName = mr_clean_string($item['brand_name'] ?? null);
        if ($brandName) {
            $brandNames[$brandName] = true;
        }
    }
    $isAssembled = stripos($haystack, 'assembled') !== false;
    $brandLabel = implode(', ', array_keys($brandNames));

    return [
        'branded' => empty($items) ? null : ($isAssembled ? 'No' : ($brandLabel ?: 'Branded')),
        'assemble' => $isAssembled ? 'Yes' : 'No',
    ];
}

function mr_store_pdf(PDO $pdo, int $id, array $file): string
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
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, ['application/pdf', 'application/x-pdf', 'application/octet-stream'], true)) {
        throw new RuntimeException('Invalid PDF file.');
    }

    $old = $pdo->prepare('SELECT attachment_pdf FROM maintenance_reports WHERE id = ?');
    $old->execute([$id]);
    $oldPath = (string) ($old->fetchColumn() ?: '');
    if ($oldPath !== '') {
        $absOld = ROOT_PATH . '/public/' . ltrim($oldPath, '/');
        if (is_file($absOld)) {
            unlink($absOld);
        }
    }

    $dirRel = 'demo-uploads/maintenance_reports/';
    $dirAbs = ROOT_PATH . '/public/' . $dirRel;
    if (!is_dir($dirAbs)) {
        mkdir($dirAbs, 0755, true);
    }
    $filename = 'MR_' . $id . '_' . time() . '.pdf';
    $relPath = $dirRel . $filename;
    if (!move_uploaded_file($file['tmp_name'], $dirAbs . $filename)) {
        throw new RuntimeException('Failed to save uploaded file.');
    }
    $pdo->prepare('UPDATE maintenance_reports SET attachment_pdf = ? WHERE id = ?')->execute([$relPath, $id]);
    return $relPath;
}

try {
    mr_ensure_schema($pdo);
    $action = (string) ($_POST['action'] ?? ($_GET['action'] ?? ''));

    if ($action === 'masterlists') {
        mr_response(['success' => true, 'data' => mr_masterlists($pdo)]);
    }

    if ($action === 'user_items') {
        $endUserId = mr_clean_int($_GET['end_user_id'] ?? $_POST['end_user_id'] ?? null);
        if (!$endUserId) {
            mr_response(['success' => false, 'message' => 'Select an end user first.'], 422);
        }
        mr_response(['success' => true, 'data' => mr_user_items($pdo, $endUserId)]);
    }

    if ($action === 'all_items') {
        mr_response(['success' => true, 'data' => mr_all_items($pdo)]);
    }

    if ($action === 'get') {
        $id = mr_clean_int($_GET['id'] ?? null);
        if (!$id) {
            mr_response(['success' => false, 'message' => 'Invalid maintenance report ID.'], 422);
        }
        $hasTechnicalTable = db_table_exists($pdo, 'list_technical');
        $technicalIdColumn = $hasTechnicalTable && db_column_exists($pdo, 'list_technical', 'technical_id')
            ? 'technical_id'
            : ($hasTechnicalTable && db_column_exists($pdo, 'list_technical', 'id') ? 'id' : null);
        $technicalJoin = $hasTechnicalTable
            ? 'LEFT JOIN list_technical lt ON lt.technical_name = mr.inspected_by' . ($technicalIdColumn ? ' OR CAST(lt.`' . $technicalIdColumn . '` AS CHAR) = CAST(mr.inspected_by AS CHAR)' : '')
            : '';
        $technicalSelect = $hasTechnicalTable
            ? 'COALESCE(lt.technical_name, mr.inspected_by) AS inspected_by_name'
            : 'mr.inspected_by AS inspected_by_name';

        $stmt = $pdo->prepare("
            SELECT mr.*, eu.end_user_name, dep.department_name, des.department_name AS designation_name, {$technicalSelect}
            FROM maintenance_reports mr
            LEFT JOIN list_end_user eu ON eu.end_user_id = mr.end_user_id
            LEFT JOIN list_department dep ON dep.department_id = mr.department_id
            LEFT JOIN list_department des ON des.department_id = mr.designation_id
            {$technicalJoin}
            WHERE mr.id = ?
        ");
        $stmt->execute([$id]);
        $report = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$report) {
            mr_response(['success' => false, 'message' => 'Maintenance report not found.'], 404);
        }
        $items = $pdo->prepare("
            SELECT
                mri.*,
                COALESCE(d.description_name, mri.description_name) AS description_name,
                COALESCE(b.brand_name, mri.brand_name) AS brand_name
            FROM maintenance_report_items mri
            LEFT JOIN list_description d ON d.id = mri.description_id
            LEFT JOIN list_brand b ON b.id = mri.brand_id
            WHERE mri.maintenance_report_id = ?
            ORDER BY mri.id ASC
        ");
        $items->execute([$id]);
        $itemRows = $items->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (empty($itemRows)) {
            $itemRows = mr_legacy_items_for_report($pdo, $report);
        }
        $checks = $pdo->prepare('SELECT category, item_label, is_checked FROM maintenance_report_checks WHERE maintenance_report_id = ? ORDER BY id ASC');
        $checks->execute([$id]);
        mr_response(['success' => true, 'data' => $report, 'items' => $itemRows, 'checks' => $checks->fetchAll(PDO::FETCH_ASSOC)]);
    }

    if ($action === 'create' || $action === 'update') {
        $payload = mr_payload();
        $id = mr_clean_int($payload['id'] ?? null);
        $date = mr_clean_string($payload['date_accomplished'] ?? null) ?: date('Y-m-d');
        $items = json_decode((string) ($payload['items'] ?? '[]'), true);
        $checks = json_decode((string) ($payload['checks'] ?? '[]'), true);
        $items = is_array($items) ? $items : [];
        $checks = is_array($checks) ? $checks : [];
        $firstItem = $items[0] ?? [];
        $controlNo = mr_clean_string($payload['control_no'] ?? null) ?: mr_clean_string($firstItem['control_no'] ?? null) ?: 'N/A';

        $buildType = mr_build_type_from_items($items);
        $row = [
            'maintenance_id' => mr_clean_string($payload['maintenance_id'] ?? null) ?: mr_generate_id($pdo, $date, $id),
            'control_no' => $controlNo,
            'branded' => $buildType['branded'],
            'assemble' => $buildType['assemble'],
            'end_user_id' => mr_clean_string($payload['end_user_id'] ?? null),
            'department_id' => mr_clean_string($payload['department_id'] ?? null),
            'designation_id' => mr_clean_int($payload['designation_id'] ?? null),
            'date_accomplished' => $date,
            'inspected_by' => mr_clean_string($payload['inspected_by'] ?? null),
        ];
        $row['maintenance_status'] = mr_calculated_status($row, $items, $checks);

        if ($action === 'create') {
            $existing = $pdo->prepare('SELECT id FROM maintenance_reports WHERE maintenance_id = ? LIMIT 1');
            $existing->execute([$row['maintenance_id']]);
            $existingId = (int) ($existing->fetchColumn() ?: 0);
            if ($existingId > 0) {
                $id = $existingId;
                $action = 'update';
            }
        }

        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
        }
        if ($action === 'create') {
            $row['created_by'] = (int) ((current_user()['id'] ?? $_SESSION['user']['id'] ?? 0) ?: 0) ?: null;
            $columns = array_keys($row);
            $stmt = $pdo->prepare('INSERT INTO maintenance_reports (`' . implode('`,`', $columns) . '`) VALUES (' . implode(',', array_fill(0, count($columns), '?')) . ')');
            $stmt->execute(array_values($row));
            $id = (int) $pdo->lastInsertId();
        } else {
            if (!$id) {
                throw new RuntimeException('Invalid maintenance report ID.');
            }
            $sets = [];
            $values = [];
            foreach ($row as $column => $value) {
                $sets[] = "`{$column}` = ?";
                $values[] = $value;
            }
            $values[] = $id;
            $stmt = $pdo->prepare('UPDATE maintenance_reports SET ' . implode(', ', $sets) . ' WHERE id = ?');
            $stmt->execute($values);
        }
        mr_sync_items($pdo, (int) $id, $items);
        mr_sync_checks($pdo, (int) $id, $checks);
        if ($pdo->inTransaction()) {
            $pdo->commit();
        }

        if (!empty($_FILES['attachment_pdf']) && ($_FILES['attachment_pdf']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            mr_store_pdf($pdo, (int) $id, $_FILES['attachment_pdf']);
        }

        log_activity('MAINTENANCE_' . strtoupper($action), 'Saved maintenance report ' . $row['maintenance_id']);
        mr_response(['success' => true, 'message' => 'Maintenance report saved successfully.']);
    }

    if ($action === 'upload_pdf') {
        $id = mr_clean_int($_POST['id'] ?? null);
        if (!$id) {
            mr_response(['success' => false, 'message' => 'Invalid maintenance report ID.'], 422);
        }
        mr_store_pdf($pdo, $id, $_FILES['attachment_pdf'] ?? []);
        log_activity('MAINTENANCE_PDF_UPLOAD', 'Uploaded maintenance report PDF #' . $id);
        mr_response(['success' => true, 'message' => 'PDF uploaded successfully.']);
    }

    if ($action === 'remove_pdf') {
        $id = mr_clean_int($_POST['id'] ?? null);
        if (!$id) {
            mr_response(['success' => false, 'message' => 'Invalid maintenance report ID.'], 422);
        }
        $stmt = $pdo->prepare('SELECT attachment_pdf FROM maintenance_reports WHERE id = ?');
        $stmt->execute([$id]);
        $path = (string) ($stmt->fetchColumn() ?: '');
        if ($path !== '') {
            $abs = ROOT_PATH . '/public/' . ltrim($path, '/');
            if (is_file($abs)) {
                unlink($abs);
            }
        }
        $pdo->prepare('UPDATE maintenance_reports SET attachment_pdf = NULL WHERE id = ?')->execute([$id]);
        log_activity('MAINTENANCE_PDF_REMOVE', 'Removed maintenance report PDF #' . $id);
        mr_response(['success' => true, 'message' => 'PDF removed successfully.']);
    }

    if ($action === 'archive') {
        $id = mr_clean_int($_POST['id'] ?? null);
        if (!$id) {
            mr_response(['success' => false, 'message' => 'Invalid maintenance report ID.'], 422);
        }
        $stmt = $pdo->prepare('SELECT maintenance_id FROM maintenance_reports WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            mr_response(['success' => false, 'message' => 'Maintenance report not found.'], 404);
        }
        $pdo->prepare('UPDATE maintenance_reports SET is_archived = 1, archived_at = NOW() WHERE id = ?')->execute([$id]);
        log_activity('MAINTENANCE_ARCHIVE', 'Archived maintenance report ' . ($row['maintenance_id'] ?? $id));
        mr_response(['success' => true, 'message' => 'Maintenance report moved to archive.']);
    }

    if ($action === 'restore') {
        $id = mr_clean_int($_POST['id'] ?? null);
        if (!$id) {
            mr_response(['success' => false, 'message' => 'Invalid maintenance report ID.'], 422);
        }
        $stmt = $pdo->prepare('SELECT maintenance_id FROM maintenance_reports WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            mr_response(['success' => false, 'message' => 'Maintenance report not found.'], 404);
        }
        $pdo->prepare('UPDATE maintenance_reports SET is_archived = 0, archived_at = NULL WHERE id = ?')->execute([$id]);
        log_activity('MAINTENANCE_RESTORE', 'Restored maintenance report ' . ($row['maintenance_id'] ?? $id));
        mr_response(['success' => true, 'message' => 'Maintenance report restored.']);
    }

    if ($action === 'permanent_delete') {
        $id = mr_clean_int($_POST['id'] ?? null);
        if (!$id) {
            mr_response(['success' => false, 'message' => 'Invalid maintenance report ID.'], 422);
        }
        $stmt = $pdo->prepare('SELECT maintenance_id, attachment_pdf FROM maintenance_reports WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            mr_response(['success' => false, 'message' => 'Maintenance report not found.'], 404);
        }
        if (!empty($row['attachment_pdf'])) {
            $abs = ROOT_PATH . '/public/' . ltrim((string) $row['attachment_pdf'], '/');
            if (is_file($abs)) {
                unlink($abs);
            }
        }
        $pdo->prepare('DELETE FROM maintenance_reports WHERE id = ?')->execute([$id]);
        log_activity('MAINTENANCE_DELETE', 'Permanently deleted maintenance report ' . ($row['maintenance_id'] ?? $id));
        mr_response(['success' => true, 'message' => 'Maintenance report permanently deleted.']);
    }

    mr_response(['success' => false, 'message' => 'Invalid action.'], 400);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    mr_response(['success' => false, 'message' => $e->getMessage()], 500);
}
