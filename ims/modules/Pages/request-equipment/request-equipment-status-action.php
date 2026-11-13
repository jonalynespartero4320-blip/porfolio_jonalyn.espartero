<?php
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_login();
require_any_permission(['ictd.request.manage', 'ictd.request.view']);
header('Content-Type: application/json');

if (!is_admin() && !can('ictd.request.manage')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit;
}

function reqstat_response(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function reqstat_payload(): array
{
    $raw = file_get_contents('php://input');
    $json = json_decode($raw ?: '', true);
    return is_array($json) ? $json : $_POST;
}

function reqstat_clean_int($value): ?int
{
    return ($value === null || $value === '') ? null : (is_numeric($value) ? (int) $value : null);
}

function reqstat_clean_string($value): ?string
{
    $value = trim((string) ($value ?? ''));
    return $value === '' ? null : $value;
}

function reqstat_ensure_schema(PDO $pdo): void
{
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
    ] as $column => $definition) {
        if (db_table_exists($pdo, 'equipment_requests') && !db_column_exists($pdo, 'equipment_requests', $column)) {
            $pdo->exec("ALTER TABLE equipment_requests ADD COLUMN {$column} {$definition}");
        }
    }

    foreach ([
        'item_type' => "VARCHAR(30) NOT NULL DEFAULT 'equipment'",
        'estimated_amount' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
        'total_amount' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
        'status' => "VARCHAR(30) NOT NULL DEFAULT 'Pending'",
        'rejection_reason' => 'TEXT NULL',
    ] as $column => $definition) {
        if (db_table_exists($pdo, 'equipment_request_items') && !db_column_exists($pdo, 'equipment_request_items', $column)) {
            $pdo->exec("ALTER TABLE equipment_request_items ADD COLUMN {$column} {$definition}");
        }
    }
}

function reqstat_recalculate(PDO $pdo, int $requestId): void
{
    $statusStmt = $pdo->prepare('SELECT status FROM equipment_requests WHERE id = ? LIMIT 1');
    $statusStmt->execute([$requestId]);
    if (strtolower((string) ($statusStmt->fetchColumn() ?: '')) === 'cancelled') {
        return;
    }

    $stmt = $pdo->prepare('SELECT quantity, estimated_amount, status FROM equipment_request_items WHERE request_id = ?');
    $stmt->execute([$requestId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $total = 0.0;
    $approved = 0.0;
    $statuses = [];
    foreach ($items as $item) {
        $lineTotal = (float) ($item['quantity'] ?? 0) * (float) ($item['estimated_amount'] ?? 0);
        $total += $lineTotal;
        $status = strtolower(trim((string) ($item['status'] ?? 'Pending')));
        $statuses[] = $status;
        if ($status === 'approved') {
            $approved += $lineTotal;
        }
    }

    $requestStatus = 'Pending';
    if (!empty($statuses)) {
        if (count(array_filter($statuses, static fn($s) => $s === 'approved')) === count($statuses)) {
            $requestStatus = 'Approved';
        } elseif (count(array_filter($statuses, static fn($s) => $s === 'rejected')) === count($statuses)) {
            $requestStatus = 'Rejected';
        } elseif (count(array_filter($statuses, static fn($s) => $s !== 'pending')) > 0 || $total > 0) {
            $requestStatus = 'In Review';
        }
    }

    $budget = (float) $pdo->query('SELECT budget_amount FROM equipment_requests WHERE id = ' . (int) $requestId)->fetchColumn();
    $balance = $budget - $approved;
    $stmt = $pdo->prepare('UPDATE equipment_requests SET total_amount = ?, budget_balance = ?, status = ?, updated_at = NOW() WHERE id = ?');
    $stmt->execute([$total, $balance, $requestStatus, $requestId]);
}

function reqstat_access_where(PDO $pdo, string $alias, array &$params): string
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
        $scopeParams = [];
        foreach ($departmentIds as $i => $departmentId) {
            $key = 'scope_department_' . $i;
            $scopeParams[] = ':' . $key;
            $params[$key] = (int) $departmentId;
        }
        $parts[] = "{$alias}.department_id IN (" . implode(',', $scopeParams) . ')';
    }
    return $parts ? '(' . implode(' OR ', $parts) . ')' : '1 = 0';
}

function reqstat_request_status(PDO $pdo, int $requestId): ?string
{
    $stmt = $pdo->prepare('SELECT status FROM equipment_requests WHERE id = ? LIMIT 1');
    $stmt->execute([$requestId]);
    $status = $stmt->fetchColumn();
    return $status === false ? null : (string) $status;
}

try {
    reqstat_ensure_schema($pdo);
    ensure_record_scope_schema($pdo);
    $payload = reqstat_payload();
    $action = (string) ($_GET['action'] ?? $payload['action'] ?? '');

    if ($action === 'items') {
        $requestId = reqstat_clean_int($_GET['request_id'] ?? $payload['request_id'] ?? null);
        if (!$requestId) {
            reqstat_response(['success' => false, 'message' => 'Invalid request.'], 422);
        }
        $params = ['id' => $requestId];
        $sql = 'SELECT COUNT(*) FROM equipment_requests er WHERE er.id = :id';
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        if ((int) $stmt->fetchColumn() < 1) {
            reqstat_response(['success' => false, 'message' => 'Request not found.'], 404);
        }

        $stmt = $pdo->prepare('SELECT id, item_type, quantity, description, estimated_amount, total_amount, status, rejection_reason FROM equipment_request_items WHERE request_id = ? ORDER BY id ASC');
        $stmt->execute([$requestId]);
        reqstat_response(['success' => true, 'items' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []]);
    }

    if ($action === 'save_items') {
        $requestId = reqstat_clean_int($payload['request_id'] ?? null);
        $items = json_decode((string) ($payload['items'] ?? '[]'), true);
        $items = is_array($items) ? $items : [];
        if (!$requestId) {
            reqstat_response(['success' => false, 'message' => 'Invalid request.'], 422);
        }
        if (reqstat_request_status($pdo, $requestId) === null) {
            reqstat_response(['success' => false, 'message' => 'Request not found.'], 404);
        }
        if (strtolower((string) reqstat_request_status($pdo, $requestId)) === 'cancelled') {
            reqstat_response(['success' => false, 'message' => 'Cancelled requests cannot be updated.'], 422);
        }

        $stmt = $pdo->prepare('UPDATE equipment_request_items SET estimated_amount = ?, total_amount = quantity * ?, status = ?, rejection_reason = ? WHERE id = ? AND request_id = ?');
        foreach ($items as $item) {
            $itemId = reqstat_clean_int($item['item_id'] ?? null);
            if (!$itemId) {
                continue;
            }
            $amount = max(0, (float) ($item['estimated_amount'] ?? 0));
            $status = ucwords(strtolower(trim((string) ($item['status'] ?? 'Pending'))));
            if (!in_array($status, ['Pending', 'Approved', 'Rejected', 'In Review'], true)) {
                $status = 'Pending';
            }
            $reason = $status === 'Rejected' ? reqstat_clean_string($item['rejection_reason'] ?? null) : null;
            if ($status === 'Rejected' && !$reason) {
                reqstat_response(['success' => false, 'message' => 'Please add a rejection reason for every rejected item.'], 422);
            }
            $stmt->execute([$amount, $amount, $status, $reason, $itemId, $requestId]);
        }
        reqstat_recalculate($pdo, $requestId);
        log_activity('EQUIPMENT_REQUEST_STATUS_SAVE', 'Saved request item statuses for request #' . $requestId);
        reqstat_response(['success' => true, 'message' => 'Request items saved.']);
    }

    if ($action === 'update_item') {
        $requestId = reqstat_clean_int($payload['request_id'] ?? null);
        $itemId = reqstat_clean_int($payload['item_id'] ?? null);
        $status = ucwords(strtolower(trim((string) ($payload['status'] ?? 'Pending'))));
        $amount = max(0, (float) ($payload['estimated_amount'] ?? 0));
        $reason = $status === 'Rejected' ? reqstat_clean_string($payload['rejection_reason'] ?? null) : null;
        if (!$requestId || !$itemId || !in_array($status, ['Pending', 'Approved', 'Rejected', 'In Review'], true)) {
            reqstat_response(['success' => false, 'message' => 'Invalid item update.'], 422);
        }
        $requestStatus = reqstat_request_status($pdo, $requestId);
        if ($requestStatus === null) {
            reqstat_response(['success' => false, 'message' => 'Request not found.'], 404);
        }
        if (strtolower($requestStatus) === 'cancelled') {
            reqstat_response(['success' => false, 'message' => 'Cancelled requests cannot be updated.'], 422);
        }
        if ($status === 'Rejected' && !$reason) {
            reqstat_response(['success' => false, 'message' => 'Please enter a reason for rejecting this item.'], 422);
        }
        $stmt = $pdo->prepare('UPDATE equipment_request_items SET estimated_amount = ?, total_amount = quantity * ?, status = ?, rejection_reason = ? WHERE id = ? AND request_id = ?');
        $stmt->execute([$amount, $amount, $status, $reason, $itemId, $requestId]);
        reqstat_recalculate($pdo, $requestId);
        reqstat_response(['success' => true, 'message' => 'Item marked ' . strtolower($status) . '.']);
    }

    reqstat_response(['success' => false, 'message' => 'Invalid action.'], 400);
} catch (Throwable $e) {
    reqstat_response(['success' => false, 'message' => $e->getMessage()], 500);
}
