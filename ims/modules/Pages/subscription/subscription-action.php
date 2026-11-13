<?php

require_once __DIR__ . '/subscription-common.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_login();
require_permission('ictd.subscription.view');
subscription_ensure_schema($pdo);

function subscription_clean_string($value): ?string
{
    $value = trim((string) ($value ?? ''));
    return $value === '' ? null : $value;
}

function subscription_clean_int($value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }
    return is_numeric($value) ? (int) $value : null;
}

function subscription_end_users(PDO $pdo): array
{
    if (!db_table_exists($pdo, 'list_end_user')) {
        return [];
    }

    $assignedWhere = '';
    if (db_table_exists($pdo, 'assigned_record')) {
        $assignedWhere = 'WHERE EXISTS (SELECT 1 FROM assigned_record ar WHERE ar.end_user_id = eu.end_user_id)';
    } elseif (db_table_exists($pdo, 'assigned_equipment')) {
        $assignedWhere = 'WHERE EXISTS (SELECT 1 FROM assigned_equipment ae WHERE ae.end_user_id = eu.end_user_id)';
    }

    $stmt = $pdo->query("
        SELECT
            eu.end_user_id AS id,
            eu.end_user_name AS text,
            eu.department_id,
            dep.department_name
        FROM list_end_user eu
        LEFT JOIN list_department dep ON dep.department_id = eu.department_id
        {$assignedWhere}
        ORDER BY eu.end_user_name ASC
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    if ($action === 'masterlists') {
        subscription_response([
            'success' => true,
            'data' => [
                'users' => subscription_end_users($pdo),
            ],
        ]);
    }

    if ($action === 'get') {
        $id = subscription_clean_int($_GET['id'] ?? null);
        if (!$id) {
            subscription_response(['success' => false, 'message' => 'Invalid subscription ID.'], 422);
        }

        $stmt = $pdo->prepare("
            SELECT s.*, eu.end_user_name, dep.department_name
            FROM subscription s
            LEFT JOIN list_end_user eu ON eu.end_user_id = s.end_user_id
            LEFT JOIN list_department dep ON dep.department_id = s.department_id
            WHERE s.subscription_id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            subscription_response(['success' => false, 'message' => 'Subscription not found.'], 404);
        }
        subscription_response(['success' => true, 'data' => $row]);
    }

    if (in_array($action, ['create', 'update'], true)) {
        $canWrite = $action === 'create'
            ? (can('ictd.subscription.create') || can('ictd.subscription.view'))
            : (can('ictd.subscription.edit') || can('ictd.subscription.view'));
        if (!$canWrite) {
            subscription_response(['success' => false, 'message' => 'Access denied.'], 403);
        }

        $id = subscription_clean_int($_POST['subscription_id'] ?? null);
        $description = subscription_clean_string($_POST['software_description'] ?? null);
        $purchaseDate = subscription_clean_string($_POST['purchase_date'] ?? null);
        $expiryDate = subscription_clean_string($_POST['expiry_date'] ?? null);
        $endUserId = subscription_clean_int($_POST['end_user_id'] ?? null);
        $departmentId = subscription_clean_int($_POST['department_id'] ?? null);

        if (!$description || !$purchaseDate || !$expiryDate || !$endUserId || !$departmentId) {
            subscription_response(['success' => false, 'message' => 'Complete all required fields.'], 422);
        }

        if ($action === 'update' && $id) {
            $stmt = $pdo->prepare("
                UPDATE subscription
                SET software_description = ?, purchase_date = ?, expiry_date = ?, end_user_id = ?, department_id = ?, updated_at = NOW()
                WHERE subscription_id = ?
            ");
            $stmt->execute([$description, $purchaseDate, $expiryDate, $endUserId, $departmentId, $id]);
            log_activity('SUBSCRIPTION_UPDATE', 'Updated subscription #' . $id);
            subscription_response(['success' => true, 'message' => 'Subscription updated successfully.']);
        }

        $stmt = $pdo->prepare("
            INSERT INTO subscription (software_description, purchase_date, expiry_date, end_user_id, department_id, created_by)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$description, $purchaseDate, $expiryDate, $endUserId, $departmentId, current_user()['id'] ?? null]);
        log_activity('SUBSCRIPTION_CREATE', 'Created subscription #' . $pdo->lastInsertId());
        subscription_response(['success' => true, 'message' => 'Subscription added successfully.']);
    }

    if (in_array($action, ['archive', 'restore', 'permanent_delete'], true)) {
        if (!(can('ictd.subscription.delete') || can('ictd.subscription.view'))) {
            subscription_response(['success' => false, 'message' => 'Access denied.'], 403);
        }

        $id = subscription_clean_int($_POST['subscription_id'] ?? $_POST['id'] ?? null);
        if (!$id) {
            subscription_response(['success' => false, 'message' => 'Invalid subscription ID.'], 422);
        }

        if ($action === 'archive') {
            $pdo->prepare('UPDATE subscription SET is_archived = 1, archived_at = NOW() WHERE subscription_id = ?')->execute([$id]);
            log_activity('SUBSCRIPTION_ARCHIVE', 'Archived subscription #' . $id);
            subscription_response(['success' => true, 'message' => 'Subscription moved to archive.']);
        }

        if ($action === 'restore') {
            $pdo->prepare('UPDATE subscription SET is_archived = 0, archived_at = NULL WHERE subscription_id = ?')->execute([$id]);
            log_activity('SUBSCRIPTION_RESTORE', 'Restored subscription #' . $id);
            subscription_response(['success' => true, 'message' => 'Subscription restored.']);
        }

        $pdo->prepare('DELETE FROM subscription WHERE subscription_id = ?')->execute([$id]);
        log_activity('SUBSCRIPTION_DELETE', 'Permanently deleted subscription #' . $id);
        subscription_response(['success' => true, 'message' => 'Subscription permanently deleted.']);
    }

    subscription_response(['success' => false, 'message' => 'Invalid action.'], 400);
} catch (Throwable $e) {
    subscription_response(['success' => false, 'message' => $e->getMessage()], 500);
}
