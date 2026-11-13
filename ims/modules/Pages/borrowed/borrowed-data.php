<?php

require_once __DIR__ . '/borrowed-common.php';

require_login();
require_permission('ictd.borrowed.view');
borrowed_ensure_schema($pdo);

$archive = (int) ($_POST['archive'] ?? $_GET['archive'] ?? 0);
$stmt = $pdo->prepare("
    SELECT b.*, eu.end_user_name, dep.department_name
    FROM borrowed b
    LEFT JOIN list_end_user eu ON eu.end_user_id = b.end_user_id
    LEFT JOIN list_department dep ON dep.department_id = b.department_id
    WHERE COALESCE(b.is_archived, 0) = ?
    ORDER BY b.date_borrowed DESC, b.id DESC
");
$stmt->execute([$archive]);

$rows = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
    $id = (int) ($row['id'] ?? 0);
    [$statusText, $statusClass] = borrowed_status($row['date_returned'] ?? null);
    $statusBadge = '<span class="badge ' . e($statusClass) . '">' . e($statusText) . '</span>';
    $actions = $archive
        ? '<span class="borrowed-actions">'
            . '<button type="button" class="borrowed-action-btn text-success restore-borrowed-btn" data-id="' . $id . '"><i class="bi bi-arrow-counterclockwise"></i> Restore</button>'
            . '<button type="button" class="borrowed-action-btn borrowed-action-delete permanent-delete-borrowed-btn" data-id="' . $id . '"><i class="bi bi-trash3"></i> Delete</button>'
            . '</span>'
        : '<span class="borrowed-actions">'
            . '<button type="button" class="borrowed-action-btn borrowed-action-edit edit-borrowed-btn" data-id="' . $id . '"><i class="bi bi-pencil-square"></i> Edit</button>'
            . '<button type="button" class="borrowed-action-btn borrowed-action-delete archive-borrowed-btn" data-id="' . $id . '"><i class="bi bi-archive"></i> Archive</button>'
            . '</span>';

    $rows[] = [
        $actions,
        $statusBadge,
        e($row['end_user_name'] ?? ''),
        e($row['department_name'] ?? ''),
        '<strong>' . e($row['item_borrowed'] ?? '') . '</strong>',
        e($row['serialno'] ?? ''),
        e($row['date_borrowed'] ?? ''),
        e($row['date_returned'] ?? ''),
    ];
}

borrowed_response(['data' => $rows]);
