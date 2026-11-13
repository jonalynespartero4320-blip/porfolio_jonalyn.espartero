<?php

require_once __DIR__ . '/subscription-common.php';

require_login();
require_permission('ictd.subscription.view');
subscription_ensure_schema($pdo);

$archive = (int) ($_POST['archive'] ?? $_GET['archive'] ?? 0);
$stmt = $pdo->prepare("
    SELECT
        s.*,
        eu.end_user_name,
        dep.department_name
    FROM subscription s
    LEFT JOIN list_end_user eu ON eu.end_user_id = s.end_user_id
    LEFT JOIN list_department dep ON dep.department_id = s.department_id
    WHERE COALESCE(s.is_archived, 0) = ?
    ORDER BY
        CASE WHEN s.expiry_date IS NULL OR s.expiry_date = '0000-00-00' THEN 1 ELSE 0 END,
        s.expiry_date ASC,
        s.subscription_id DESC
");
$stmt->execute([$archive]);

$rows = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
    $id = (int) ($row['subscription_id'] ?? 0);
    [$statusText, $statusClass, $days] = subscription_status($row['expiry_date'] ?? null);
    $statusBadge = '<span class="badge ' . e($statusClass) . '">' . e($statusText) . '</span>';
    if ($days !== null && $days >= 0 && $days <= 30) {
        $statusBadge .= '<span class="subscription-days">' . $days . ' day(s)</span>';
    }

    $actions = $archive
        ? '<span class="subscription-actions">'
            . '<button type="button" class="subscription-action-btn text-success restore-subscription-btn" data-id="' . $id . '" title="Restore"><i class="bi bi-arrow-counterclockwise"></i> Restore</button>'
            . '<button type="button" class="subscription-action-btn subscription-action-delete permanent-delete-subscription-btn" data-id="' . $id . '" title="Delete permanently"><i class="bi bi-trash3"></i> Delete</button>'
            . '</span>'
        : '<span class="subscription-actions">'
            . '<button type="button" class="subscription-action-btn subscription-action-edit edit-subscription-btn" data-id="' . $id . '" title="Edit"><i class="bi bi-pencil-square"></i> Edit</button>'
            . '<button type="button" class="subscription-action-btn subscription-action-delete archive-subscription-btn" data-id="' . $id . '" title="Archive"><i class="bi bi-archive"></i> Archive</button>'
            . '</span>';

    $rows[] = [
        $actions,
        '<strong>' . e($row['software_description'] ?? '') . '</strong>',
        e($row['end_user_name'] ?? ''),
        e($row['department_name'] ?? ''),
        e($row['purchase_date'] ?? ''),
        e($row['expiry_date'] ?? ''),
        $statusBadge,
    ];
}

subscription_response(['data' => $rows]);
