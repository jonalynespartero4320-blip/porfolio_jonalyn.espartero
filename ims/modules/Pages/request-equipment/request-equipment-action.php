<?php
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_login();
require_permission('ictd.request.view');

header('Content-Type: application/json');

function reqeq_response(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function reqeq_payload(): array
{
    $raw = file_get_contents('php://input');
    $json = json_decode($raw ?: '', true);
    return is_array($json) ? $json : $_POST;
}

function reqeq_clean_int($value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }
    return is_numeric($value) ? (int) $value : null;
}

function reqeq_clean_string($value): ?string
{
    $value = trim((string) ($value ?? ''));
    return $value === '' ? null : $value;
}

function reqeq_ensure_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS list_funds (
            id INT AUTO_INCREMENT PRIMARY KEY,
            fund_name VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS equipment_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            control_no VARCHAR(50) NULL,
            date_requested DATE NOT NULL,
            fund_id INT NULL,
            department_id INT NULL,
            department_head VARCHAR(255) NULL,
            budget_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            quarter VARCHAR(20) NULL,
            budget_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
            status VARCHAR(30) NOT NULL DEFAULT 'Pending',
            cancel_reason TEXT NULL,
            cancelled_at DATETIME NULL,
            created_by INT NULL,
            is_archived TINYINT(1) NOT NULL DEFAULT 0,
            archived_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            INDEX idx_equipment_requests_department (department_id),
            INDEX idx_equipment_requests_created_by (created_by),
            INDEX idx_equipment_requests_archived (is_archived)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    foreach ([
        'control_no' => 'VARCHAR(50) NULL',
        'budget_amount' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
        'total_amount' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
        'quarter' => 'VARCHAR(20) NULL',
        'budget_balance' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
        'status' => "VARCHAR(30) NOT NULL DEFAULT 'Pending'",
        'cancel_reason' => 'TEXT NULL',
        'cancelled_at' => 'DATETIME NULL',
        'created_by' => 'INT NULL',
        'is_archived' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'archived_at' => 'DATETIME NULL',
        'updated_at' => 'DATETIME NULL',
    ] as $column => $definition) {
        if (!db_column_exists($pdo, 'equipment_requests', $column)) {
            $pdo->exec("ALTER TABLE equipment_requests ADD COLUMN {$column} {$definition}");
        }
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS equipment_request_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            request_id INT NOT NULL,
            item_type VARCHAR(30) NOT NULL DEFAULT 'equipment',
            quantity INT NOT NULL DEFAULT 1,
            description TEXT NOT NULL,
            estimated_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            status VARCHAR(30) NOT NULL DEFAULT 'Pending',
            rejection_reason TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_equipment_request_items_request (request_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    foreach ([
        'item_type' => "VARCHAR(30) NOT NULL DEFAULT 'equipment'",
        'estimated_amount' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
        'total_amount' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
        'status' => "VARCHAR(30) NOT NULL DEFAULT 'Pending'",
        'rejection_reason' => 'TEXT NULL',
    ] as $column => $definition) {
        if (!db_column_exists($pdo, 'equipment_request_items', $column)) {
            $pdo->exec("ALTER TABLE equipment_request_items ADD COLUMN {$column} {$definition}");
        }
    }
}

function reqeq_quarter(?string $date): string
{
    $month = $date ? (int) date('n', strtotime($date)) : (int) date('n');
    return 'Q' . (int) ceil($month / 3);
}

function reqeq_generate_control_no(PDO $pdo, ?string $date, ?int $ignoreId = null): string
{
    $year = $date ? date('Y', strtotime($date)) : date('Y');
    $prefix = 'REQ-' . $year . '-';
    $sql = 'SELECT control_no FROM equipment_requests WHERE control_no LIKE ?';
    $params = [$prefix . '%'];
    if ($ignoreId) {
        $sql .= ' AND id <> ?';
        $params[] = $ignoreId;
    }
    $sql .= ' ORDER BY control_no DESC LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $last = (string) ($stmt->fetchColumn() ?: '');
    $next = 1;
    if (preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', $last, $match)) {
        $next = ((int) $match[1]) + 1;
    }
    return $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
}

function reqeq_select_options(PDO $pdo): array
{
    $funds = db_table_exists($pdo, 'list_funds')
        ? ($pdo->query('SELECT id, fund_name AS text FROM list_funds ORDER BY fund_name ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [])
        : [];

    if (user_can_view_all_records()) {
        $departments = $pdo->query('SELECT department_id AS id, department_name AS text, department_head FROM list_department ORDER BY department_name ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } else {
        $departmentIds = user_department_scope_ids();
        if (empty($departmentIds)) {
            $departments = [];
        } else {
            $placeholders = implode(',', array_fill(0, count($departmentIds), '?'));
            $stmt = $pdo->prepare("SELECT department_id AS id, department_name AS text, department_head FROM list_department WHERE department_id IN ({$placeholders}) ORDER BY department_name ASC");
            $stmt->execute($departmentIds);
            $departments = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
    }

    return compact('funds', 'departments');
}

function reqeq_scope_sql(PDO $pdo, string $alias, array &$params): string
{
    if (user_can_view_all_records()) {
        return '';
    }

    $parts = [];
    $userId = record_scope_current_user_id();
    if ($userId > 0) {
        $parts[] = "{$alias}.created_by = :scope_user_id";
        $params['scope_user_id'] = $userId;
    }

    $departmentIds = user_department_scope_ids();
    if (!empty($departmentIds)) {
        $departmentParams = [];
        foreach ($departmentIds as $index => $departmentId) {
            $key = 'scope_department_' . $index;
            $departmentParams[] = ':' . $key;
            $params[$key] = (int) $departmentId;
        }
        $parts[] = "{$alias}.department_id IN (" . implode(',', $departmentParams) . ')';
    }

    return $parts ? '(' . implode(' OR ', $parts) . ')' : '1 = 0';
}

function reqeq_user_can_access(PDO $pdo, int $id): bool
{
    $params = [];
    $scope = reqeq_scope_sql($pdo, 'er', $params);
    $sql = 'SELECT COUNT(*) FROM equipment_requests er WHERE er.id = :id';
    $params['id'] = $id;
    if ($scope !== '') {
        $sql .= ' AND ' . $scope;
    }
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    return (int) $stmt->fetchColumn() > 0;
}

try {
    reqeq_ensure_schema($pdo);
    ensure_record_scope_schema($pdo);

    $payload = reqeq_payload();
    $action = (string) ($_GET['action'] ?? $payload['action'] ?? '');

    if ($action === 'options') {
        reqeq_response(['success' => true, 'data' => reqeq_select_options($pdo)]);
    }

    if ($action === 'get') {
        $id = reqeq_clean_int($_GET['id'] ?? $payload['id'] ?? null);
        if (!$id || !reqeq_user_can_access($pdo, $id)) {
            reqeq_response(['success' => false, 'message' => 'Request not found.'], 404);
        }

        $stmt = $pdo->prepare('SELECT * FROM equipment_requests WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);

        $items = $pdo->prepare('SELECT id, item_type, quantity, description, estimated_amount, total_amount, status, rejection_reason FROM equipment_request_items WHERE request_id = ? ORDER BY id ASC');
        $items->execute([$id]);

        reqeq_response(['success' => true, 'data' => $request, 'items' => $items->fetchAll(PDO::FETCH_ASSOC) ?: []]);
    }

    if ($action === 'items') {
        $id = reqeq_clean_int($_GET['request_id'] ?? $payload['request_id'] ?? null);
        if (!$id || !reqeq_user_can_access($pdo, $id)) {
            reqeq_response(['success' => false, 'message' => 'Request not found.'], 404);
        }

        $stmt = $pdo->prepare('SELECT item_type, quantity, description, estimated_amount, total_amount, status, rejection_reason FROM equipment_request_items WHERE request_id = ? ORDER BY id ASC');
        $stmt->execute([$id]);
        reqeq_response(['success' => true, 'items' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []]);
    }

    if ($action === 'save') {
        $id = reqeq_clean_int($payload['id'] ?? null);
        $dateRequested = reqeq_clean_string($payload['date_requested'] ?? null);
        $fundId = reqeq_clean_int($payload['fund_id'] ?? null);
        $departmentId = reqeq_clean_int($payload['department_id'] ?? null);
        $departmentHead = reqeq_clean_string($payload['department_head'] ?? null);
        $budgetAmount = max(0, (float) ($payload['budget_amount'] ?? 0));
        $items = json_decode((string) ($payload['items'] ?? '[]'), true);
        $items = is_array($items) ? $items : [];

        if (!$dateRequested || !$fundId || !$departmentId || !$departmentHead) {
            reqeq_response(['success' => false, 'message' => 'Date, funds, department, and department head are required.'], 422);
        }

        if (!user_can_view_all_records() && !in_array($departmentId, user_department_scope_ids(), true)) {
            reqeq_response(['success' => false, 'message' => 'You are not allowed to create requests for this department.'], 403);
        }

        $cleanItems = [];
        foreach ($items as $item) {
            $description = reqeq_clean_string($item['description'] ?? null);
            if (!$description) {
                continue;
            }
            $cleanItems[] = [
                'item_type' => in_array(strtolower((string) ($item['item_type'] ?? 'equipment')), ['equipment', 'supply'], true) ? strtolower((string) ($item['item_type'] ?? 'equipment')) : 'equipment',
                'quantity' => max(1, reqeq_clean_int($item['quantity'] ?? 1) ?: 1),
                'description' => $description,
            ];
        }

        if (empty($cleanItems)) {
            reqeq_response(['success' => false, 'message' => 'Add at least one request item.'], 422);
        }

        $userId = record_scope_current_user_id();
        $pdo->beginTransaction();

        if ($id) {
            if (!reqeq_user_can_access($pdo, $id)) {
                throw new RuntimeException('Request not found or not allowed.');
            }
            $statusStmt = $pdo->prepare('SELECT status FROM equipment_requests WHERE id = ? LIMIT 1');
            $statusStmt->execute([$id]);
            $currentStatus = strtolower((string) ($statusStmt->fetchColumn() ?: 'Pending'));
            if (in_array($currentStatus, ['approved', 'rejected', 'cancelled'], true)) {
                throw new RuntimeException('This request can no longer be edited.');
            }
            $stmt = $pdo->prepare('
                UPDATE equipment_requests
                SET date_requested = ?, fund_id = ?, department_id = ?, department_head = ?, budget_amount = ?, quarter = ?, budget_balance = CASE WHEN budget_balance = 0 THEN ? ELSE budget_balance END, updated_at = NOW()
                WHERE id = ?
            ');
            $stmt->execute([$dateRequested, $fundId, $departmentId, $departmentHead, $budgetAmount, reqeq_quarter($dateRequested), $budgetAmount, $id]);
            $requestId = $id;
            $pdo->prepare('DELETE FROM equipment_request_items WHERE request_id = ?')->execute([$requestId]);
        } else {
            $controlNo = reqeq_generate_control_no($pdo, $dateRequested);
            $stmt = $pdo->prepare('
                INSERT INTO equipment_requests (control_no, date_requested, fund_id, department_id, department_head, budget_amount, quarter, budget_balance, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ');
            $stmt->execute([$controlNo, $dateRequested, $fundId, $departmentId, $departmentHead, $budgetAmount, reqeq_quarter($dateRequested), $budgetAmount, $userId ?: null]);
            $requestId = (int) $pdo->lastInsertId();
        }

        $insertItem = $pdo->prepare('INSERT INTO equipment_request_items (request_id, item_type, quantity, description) VALUES (?, ?, ?, ?)');
        foreach ($cleanItems as $item) {
            $insertItem->execute([$requestId, $item['item_type'], $item['quantity'], $item['description']]);
        }

        $pdo->commit();
        log_activity($id ? 'EQUIPMENT_REQUEST_UPDATE' : 'EQUIPMENT_REQUEST_CREATE', ($id ? 'Updated' : 'Created') . ' equipment request #' . $requestId);
        reqeq_response(['success' => true, 'message' => 'Equipment request saved successfully.']);
    }

    if ($action === 'cancel') {
        $id = reqeq_clean_int($payload['id'] ?? null);
        $reason = reqeq_clean_string($payload['reason'] ?? null);
        if (!$id || !reqeq_user_can_access($pdo, $id)) {
            reqeq_response(['success' => false, 'message' => 'Request not found.'], 404);
        }
        if (!$reason) {
            reqeq_response(['success' => false, 'message' => 'Please enter a reason for cancelling this request.'], 422);
        }

        $stmt = $pdo->prepare("SELECT status FROM equipment_requests WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $currentStatus = strtolower((string) ($stmt->fetchColumn() ?: 'Pending'));
        if (in_array($currentStatus, ['approved', 'rejected', 'cancelled'], true)) {
            reqeq_response(['success' => false, 'message' => 'This request can no longer be cancelled.'], 422);
        }

        $stmt = $pdo->prepare("UPDATE equipment_requests SET status = 'Cancelled', cancel_reason = ?, cancelled_at = NOW(), updated_at = NOW() WHERE id = ?");
        $stmt->execute([$reason, $id]);

        log_activity('EQUIPMENT_REQUEST_CANCEL', 'Cancelled equipment request #' . $id);
        reqeq_response(['success' => true, 'message' => 'Request cancelled.']);
    }

    if ($action === 'delete') {
        $id = reqeq_clean_int($payload['id'] ?? null);
        if (!$id || !reqeq_user_can_access($pdo, $id)) {
            reqeq_response(['success' => false, 'message' => 'Request not found.'], 404);
        }

        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM equipment_request_items WHERE request_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM equipment_requests WHERE id = ?')->execute([$id]);
        $pdo->commit();

        log_activity('EQUIPMENT_REQUEST_DELETE', 'Deleted equipment request #' . $id);
        reqeq_response(['success' => true, 'message' => 'Equipment request deleted.']);
    }

    if ($action === 'archive') {
        $id = reqeq_clean_int($payload['id'] ?? null);
        if (!$id || !reqeq_user_can_access($pdo, $id)) {
            reqeq_response(['success' => false, 'message' => 'Request not found.'], 404);
        }

        $stmt = $pdo->prepare('UPDATE equipment_requests SET is_archived = 1, archived_at = NOW(), updated_at = NOW() WHERE id = ?');
        $stmt->execute([$id]);

        log_activity('EQUIPMENT_REQUEST_ARCHIVE', 'Archived equipment request #' . $id);
        reqeq_response(['success' => true, 'message' => 'Request moved to archive.']);
    }

    if ($action === 'restore') {
        $id = reqeq_clean_int($payload['id'] ?? null);
        if (!$id || !reqeq_user_can_access($pdo, $id)) {
            reqeq_response(['success' => false, 'message' => 'Request not found.'], 404);
        }

        $stmt = $pdo->prepare('UPDATE equipment_requests SET is_archived = 0, archived_at = NULL, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$id]);

        log_activity('EQUIPMENT_REQUEST_RESTORE', 'Restored equipment request #' . $id);
        reqeq_response(['success' => true, 'message' => 'Request restored.']);
    }

    reqeq_response(['success' => false, 'message' => 'Invalid action.'], 400);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    reqeq_response(['success' => false, 'message' => $e->getMessage()], 500);
}
