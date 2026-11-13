<?php

require_once __DIR__ . '/borrowed-common.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_login();
require_permission('ictd.borrowed.view');
borrowed_ensure_schema($pdo);

function borrowed_clean_string($value): ?string
{
    $value = trim((string) ($value ?? ''));
    return $value === '' ? null : $value;
}

function borrowed_clean_int($value): ?int
{
    if ($value === null || $value === '') return null;
    return is_numeric($value) ? (int) $value : null;
}

function borrowed_masterlists(PDO $pdo): array
{
    $users = db_table_exists($pdo, 'list_end_user')
        ? $pdo->query("
            SELECT eu.end_user_id AS id, eu.end_user_name AS text, eu.department_id, dep.department_name
            FROM list_end_user eu
            LEFT JOIN list_department dep ON dep.department_id = eu.department_id
            ORDER BY eu.end_user_name ASC
        ")->fetchAll(PDO::FETCH_ASSOC) ?: []
        : [];

    $departments = db_table_exists($pdo, 'list_department')
        ? $pdo->query("SELECT department_id AS id, department_name AS text FROM list_department ORDER BY department_name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: []
        : [];

    return compact('users', 'departments');
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    if ($action === 'masterlists') {
        borrowed_response(['success' => true, 'data' => borrowed_masterlists($pdo)]);
    }

    if ($action === 'get') {
        $id = borrowed_clean_int($_GET['id'] ?? null);
        if (!$id) borrowed_response(['success' => false, 'message' => 'Invalid borrowed record ID.'], 422);

        $stmt = $pdo->prepare("
            SELECT b.*, eu.end_user_name, dep.department_name
            FROM borrowed b
            LEFT JOIN list_end_user eu ON eu.end_user_id = b.end_user_id
            LEFT JOIN list_department dep ON dep.department_id = b.department_id
            WHERE b.id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) borrowed_response(['success' => false, 'message' => 'Borrowed record not found.'], 404);
        borrowed_response(['success' => true, 'data' => $row]);
    }

    if (in_array($action, ['create', 'update'], true)) {
        $canWrite = $action === 'create'
            ? (can('ictd.borrowed.create') || can('ictd.borrowed.view'))
            : (can('ictd.borrowed.edit') || can('ictd.borrowed.view'));
        if (!$canWrite) borrowed_response(['success' => false, 'message' => 'Access denied.'], 403);

        $id = borrowed_clean_int($_POST['id'] ?? null);
        $endUserId = borrowed_clean_int($_POST['end_user_id'] ?? null);
        $departmentId = borrowed_clean_int($_POST['department_id'] ?? null);
        $itemBorrowed = borrowed_clean_string($_POST['item_borrowed'] ?? null);
        $serialNo = borrowed_clean_string($_POST['serialno'] ?? $_POST['serial_no'] ?? null);
        $dateBorrowed = borrowed_clean_string($_POST['date_borrowed'] ?? null);
        $dateReturned = borrowed_clean_string($_POST['date_returned'] ?? null);

        if (!$endUserId || !$departmentId || !$itemBorrowed || !$dateBorrowed) {
            borrowed_response(['success' => false, 'message' => 'Complete all required fields.'], 422);
        }

        if ($action === 'update' && $id) {
            $stmt = $pdo->prepare("
                UPDATE borrowed
                SET end_user_id = ?, department_id = ?, item_borrowed = ?, serialno = ?, date_borrowed = ?, date_returned = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$endUserId, $departmentId, $itemBorrowed, $serialNo, $dateBorrowed, $dateReturned, $id]);
            log_activity('BORROWED_UPDATE', 'Updated borrowed record #' . $id);
            borrowed_response(['success' => true, 'message' => 'Borrowed item updated successfully.']);
        }

        $stmt = $pdo->prepare("
            INSERT INTO borrowed (end_user_id, department_id, item_borrowed, serialno, date_borrowed, date_returned, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$endUserId, $departmentId, $itemBorrowed, $serialNo, $dateBorrowed, $dateReturned, current_user()['id'] ?? null]);
        log_activity('BORROWED_CREATE', 'Created borrowed record #' . $pdo->lastInsertId());
        borrowed_response(['success' => true, 'message' => 'Borrowed item added successfully.']);
    }

    if (in_array($action, ['archive', 'restore', 'permanent_delete'], true)) {
        if (!(can('ictd.borrowed.delete') || can('ictd.borrowed.view'))) {
            borrowed_response(['success' => false, 'message' => 'Access denied.'], 403);
        }
        $id = borrowed_clean_int($_POST['id'] ?? null);
        if (!$id) borrowed_response(['success' => false, 'message' => 'Invalid borrowed record ID.'], 422);

        if ($action === 'archive') {
            $pdo->prepare('UPDATE borrowed SET is_archived = 1, archived_at = NOW() WHERE id = ?')->execute([$id]);
            log_activity('BORROWED_ARCHIVE', 'Archived borrowed record #' . $id);
            borrowed_response(['success' => true, 'message' => 'Borrowed item moved to archive.']);
        }
        if ($action === 'restore') {
            $pdo->prepare('UPDATE borrowed SET is_archived = 0, archived_at = NULL WHERE id = ?')->execute([$id]);
            log_activity('BORROWED_RESTORE', 'Restored borrowed record #' . $id);
            borrowed_response(['success' => true, 'message' => 'Borrowed item restored.']);
        }

        $pdo->prepare('DELETE FROM borrowed WHERE id = ?')->execute([$id]);
        log_activity('BORROWED_DELETE', 'Permanently deleted borrowed record #' . $id);
        borrowed_response(['success' => true, 'message' => 'Borrowed item permanently deleted.']);
    }

    borrowed_response(['success' => false, 'message' => 'Invalid action.'], 400);
} catch (Throwable $e) {
    borrowed_response(['success' => false, 'message' => $e->getMessage()], 500);
}
