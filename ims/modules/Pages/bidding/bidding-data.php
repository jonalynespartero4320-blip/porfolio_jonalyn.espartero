<?php

require_once __DIR__ . '/bidding-common.php';

require_login();
require_permission('ictd.bidding.view');
bidding_ensure_schema($pdo);

$archive = (int) ($_POST['archive'] ?? $_GET['archive'] ?? 0);
$stmt = $pdo->prepare("
    SELECT b.*, lf.fund_name, dep.department_name, COUNT(bi.id) AS item_count
    FROM bidding b
    LEFT JOIN list_funds lf ON lf.id = b.fund_id
    LEFT JOIN list_department dep ON dep.department_id = b.department_id
    LEFT JOIN bidding_items bi ON bi.bidding_id = b.id
    WHERE COALESCE(b.is_archived, 0) = ?
    GROUP BY b.id
    ORDER BY b.date_requested DESC, b.id DESC
");
$stmt->execute([$archive]);

$rows = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
    $id = (int) ($row['id'] ?? 0);
    $actions = $archive
        ? '<span class="bidding-actions">'
            . '<button type="button" class="bidding-action-btn text-success restore-bidding-btn" data-id="' . $id . '"><i class="bi bi-arrow-counterclockwise"></i> Restore</button>'
            . '<button type="button" class="bidding-action-btn bidding-action-delete permanent-delete-bidding-btn" data-id="' . $id . '"><i class="bi bi-trash3"></i> Delete</button>'
            . '</span>'
        : '<span class="bidding-actions">'
            . '<button type="button" class="bidding-action-btn bidding-action-toggle toggle-bidding-items-btn" data-id="' . $id . '"><i class="bi bi-chevron-right"></i></button>'
            . '<button type="button" class="bidding-action-btn bidding-action-edit edit-bidding-btn" data-id="' . $id . '"><i class="bi bi-pencil-square"></i> Edit</button>'
            . '<button type="button" class="bidding-action-btn bidding-action-delete archive-bidding-btn" data-id="' . $id . '"><i class="bi bi-archive"></i> Archive</button>'
            . '</span>';

    $rows[] = [
        $actions,
        e($row['date_requested'] ?? ''),
        e($row['fund_name'] ?? ''),
        e($row['department_name'] ?? ''),
        e($row['department_head'] ?? ''),
        '<button type="button" class="bidding-item-toggle toggle-bidding-items-btn" data-id="' . $id . '"><span class="badge bg-info-subtle text-info">' . (int) ($row['item_count'] ?? 0) . ' item(s)</span></button>',
    ];
}

bidding_response(['data' => $rows]);
