<?php

require_once __DIR__ . '/bidding-common.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_login();
require_permission('ictd.bidding.view');
bidding_ensure_schema($pdo);

function bidding_clean_string($value): ?string
{
    $value = trim((string) ($value ?? ''));
    return $value === '' ? null : $value;
}

function bidding_clean_int($value): ?int
{
    if ($value === null || $value === '') return null;
    return is_numeric($value) ? (int) $value : null;
}

function bidding_masterlists(PDO $pdo): array
{
    $departments = db_table_exists($pdo, 'list_department')
        ? $pdo->query("SELECT department_id AS id, department_name AS text, department_head FROM list_department ORDER BY department_name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: []
        : [];
    $funds = db_table_exists($pdo, 'list_funds')
        ? $pdo->query("SELECT id, fund_name AS text FROM list_funds ORDER BY fund_name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: []
        : [];
    return compact('departments', 'funds');
}

function bidding_sync_items(PDO $pdo, int $biddingId, array $items): void
{
    $pdo->prepare('DELETE FROM bidding_items WHERE bidding_id = ?')->execute([$biddingId]);
    if (empty($items)) return;

    $stmt = $pdo->prepare('INSERT INTO bidding_items (bidding_id, description, qty) VALUES (?, ?, ?)');
    foreach ($items as $item) {
        $description = bidding_clean_string($item['description'] ?? null);
        if (!$description) continue;
        $qty = max(1, (int) ($item['qty'] ?? 1));
        $stmt->execute([$biddingId, $description, $qty]);
    }
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    if ($action === 'masterlists') {
        bidding_response(['success' => true, 'data' => bidding_masterlists($pdo)]);
    }

    if ($action === 'get') {
        $id = bidding_clean_int($_GET['id'] ?? null);
        if (!$id) bidding_response(['success' => false, 'message' => 'Invalid bidding ID.'], 422);

        $stmt = $pdo->prepare("
            SELECT b.*, lf.fund_name, dep.department_name
            FROM bidding b
            LEFT JOIN list_funds lf ON lf.id = b.fund_id
            LEFT JOIN list_department dep ON dep.department_id = b.department_id
            WHERE b.id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) bidding_response(['success' => false, 'message' => 'Bidding record not found.'], 404);

        $items = $pdo->prepare('SELECT id, description, qty FROM bidding_items WHERE bidding_id = ? ORDER BY id ASC');
        $items->execute([$id]);
        bidding_response(['success' => true, 'data' => $row, 'items' => $items->fetchAll(PDO::FETCH_ASSOC) ?: []]);
    }

    if (in_array($action, ['create', 'update'], true)) {
        $canWrite = $action === 'create'
            ? (can('ictd.bidding.create') || can('ictd.bidding.view'))
            : (can('ictd.bidding.edit') || can('ictd.bidding.view'));
        if (!$canWrite) bidding_response(['success' => false, 'message' => 'Access denied.'], 403);

        $id = bidding_clean_int($_POST['id'] ?? null);
        $dateRequested = bidding_clean_string($_POST['date_requested'] ?? null);
        $fundId = bidding_clean_int($_POST['fund_id'] ?? null);
        $departmentId = bidding_clean_int($_POST['department_id'] ?? null);
        $departmentHead = bidding_clean_string($_POST['department_head'] ?? null);
        $items = json_decode((string) ($_POST['items'] ?? '[]'), true);
        $items = is_array($items) ? $items : [];

        if (!$dateRequested || !$fundId || !$departmentId || !$departmentHead || empty($items)) {
            bidding_response(['success' => false, 'message' => 'Complete the bidding details and add at least one item.'], 422);
        }

        $pdo->beginTransaction();
        if ($action === 'update' && $id) {
            $stmt = $pdo->prepare('UPDATE bidding SET date_requested = ?, fund_id = ?, department_id = ?, department_head = ?, updated_at = NOW() WHERE id = ?');
            $stmt->execute([$dateRequested, $fundId, $departmentId, $departmentHead, $id]);
            bidding_sync_items($pdo, $id, $items);
            $pdo->commit();
            log_activity('BIDDING_UPDATE', 'Updated bidding record #' . $id);
            bidding_response(['success' => true, 'message' => 'Bidding record updated successfully.']);
        }

        $stmt = $pdo->prepare('INSERT INTO bidding (date_requested, fund_id, department_id, department_head, created_by) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$dateRequested, $fundId, $departmentId, $departmentHead, current_user()['id'] ?? null]);
        $newId = (int) $pdo->lastInsertId();
        bidding_sync_items($pdo, $newId, $items);
        $pdo->commit();
        log_activity('BIDDING_CREATE', 'Created bidding record #' . $newId);
        bidding_response(['success' => true, 'message' => 'Bidding record added successfully.']);
    }

    if (in_array($action, ['archive', 'restore', 'permanent_delete'], true)) {
        if (!(can('ictd.bidding.delete') || can('ictd.bidding.view'))) {
            bidding_response(['success' => false, 'message' => 'Access denied.'], 403);
        }
        $id = bidding_clean_int($_POST['id'] ?? null);
        if (!$id) bidding_response(['success' => false, 'message' => 'Invalid bidding ID.'], 422);

        if ($action === 'archive') {
            $pdo->prepare('UPDATE bidding SET is_archived = 1, archived_at = NOW() WHERE id = ?')->execute([$id]);
            log_activity('BIDDING_ARCHIVE', 'Archived bidding record #' . $id);
            bidding_response(['success' => true, 'message' => 'Bidding record moved to archive.']);
        }
        if ($action === 'restore') {
            $pdo->prepare('UPDATE bidding SET is_archived = 0, archived_at = NULL WHERE id = ?')->execute([$id]);
            log_activity('BIDDING_RESTORE', 'Restored bidding record #' . $id);
            bidding_response(['success' => true, 'message' => 'Bidding record restored.']);
        }

        $pdo->prepare('DELETE FROM bidding WHERE id = ?')->execute([$id]);
        log_activity('BIDDING_DELETE', 'Permanently deleted bidding record #' . $id);
        bidding_response(['success' => true, 'message' => 'Bidding record permanently deleted.']);
    }

    bidding_response(['success' => false, 'message' => 'Invalid action.'], 400);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    bidding_response(['success' => false, 'message' => $e->getMessage()], 500);
}
