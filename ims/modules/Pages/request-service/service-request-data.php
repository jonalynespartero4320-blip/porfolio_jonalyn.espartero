<?php
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/functions.php';
require_once ROOT_PATH . '/modules/Pages/request-service/service-request-common.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_login();
require_permission('ictd.request.view');
header('Content-Type: application/json');

function srq_data_response(array $payload): void
{
    echo json_encode($payload);
    exit;
}

try {
    srq_ensure_schema($pdo);
    ensure_record_scope_schema($pdo);

    $draw = (int) ($_REQUEST['draw'] ?? 1);
    $start = max(0, (int) ($_REQUEST['start'] ?? 0));
    $length = (int) ($_REQUEST['length'] ?? 12);
    if ($length <= 0 || $length > 100) {
        $length = 12;
    }
    $archive = (int) ($_REQUEST['archive'] ?? 0) === 1;
    $status = trim((string) ($_REQUEST['status'] ?? ''));
    $manage = (int) ($_REQUEST['manage'] ?? 0) === 1;

    if ($manage && !is_admin() && !can('ictd.request.manage')) {
        srq_data_response(['draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'counts' => [], 'error' => 'Access denied.']);
    }

    $search = trim((string) ($_REQUEST['search']['value'] ?? ''));
    $orderColumnIndex = (int) ($_REQUEST['order'][0]['column'] ?? ($archive ? 10 : 9));
    $orderDir = strtolower((string) ($_REQUEST['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
    $columns = [
        1 => 'sr.id',
        2 => 'eu.end_user_name',
        3 => 'd.department_name',
        4 => 'sr.request_type',
        5 => 'sr.request_description',
        6 => 'sr.status',
        7 => 'sr.assigned_technician',
        8 => 'sr.created_at',
        9 => 'sr.archived_at',
    ];
    $orderColumn = $columns[$orderColumnIndex] ?? ($archive ? 'sr.archived_at' : 'sr.created_at');

    $where = ['COALESCE(sr.is_archived, 0) = ' . ($archive ? '1' : '0')];
    $params = [];
    if (!$manage) {
        $scope = srq_scope_sql($pdo, 'sr', $params);
        if ($scope !== '') {
            $where[] = $scope;
        }
    }
    if ($status !== '') {
        if ($status === 'assigned') {
            $where[] = "sr.status = 'Acknowledged' AND sr.assigned_technician IS NOT NULL AND sr.assigned_technician <> ''";
        } elseif ($status === 'acknowledged') {
            $where[] = "(sr.status = 'Acknowledged' AND (sr.assigned_technician IS NULL OR sr.assigned_technician = ''))";
        } else {
            $where[] = 'sr.status = :status';
            $params['status'] = $status;
        }
    }
    if ($search !== '') {
        $where[] = '(sr.id LIKE :search OR eu.end_user_name LIKE :search OR d.department_name LIKE :search OR sr.request_type LIKE :search OR sr.request_description LIKE :search OR sr.complain_problem LIKE :search OR sr.status LIKE :search OR sr.assigned_technician LIKE :search OR sr.admin_action_reason LIKE :search OR sr.cancel_reason LIKE :search)';
        $params['search'] = '%' . $search . '%';
    }
    $whereSql = 'WHERE ' . implode(' AND ', $where);

    $baseJoin = '
        FROM service_request sr
        LEFT JOIN list_end_user eu ON eu.end_user_id = sr.end_user_id
        LEFT JOIN list_department d ON d.department_id = sr.department_id
    ';

    $totalWhere = ['COALESCE(sr.is_archived, 0) = ' . ($archive ? '1' : '0')];
    $totalParams = [];
    if (!$manage) {
        $scope = srq_scope_sql($pdo, 'sr', $totalParams);
        if ($scope !== '') {
            $totalWhere[] = $scope;
        }
    }
    $totalStmt = $pdo->prepare('SELECT COUNT(*) FROM service_request sr WHERE ' . implode(' AND ', $totalWhere));
    foreach ($totalParams as $key => $value) {
        $totalStmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $totalStmt->execute();
    $total = (int) $totalStmt->fetchColumn();

    $countStmt = $pdo->prepare("SELECT COUNT(*) {$baseJoin} {$whereSql}");
    foreach ($params as $key => $value) {
        $countStmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $countStmt->execute();
    $filtered = (int) $countStmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT sr.*, eu.end_user_name, d.department_name
        {$baseJoin}
        {$whereSql}
        ORDER BY {$orderColumn} {$orderDir}
        LIMIT :start, :length
    ");
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':start', $start, PDO::PARAM_INT);
    $stmt->bindValue(':length', $length, PDO::PARAM_INT);
    $stmt->execute();

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $id = (int) $row['id'];
        $rowStatus = (string) ($row['status'] ?? 'Pending');
        $statusKey = strtolower($rowStatus);
        $reason = $statusKey === 'rejected' ? (string) ($row['admin_action_reason'] ?? '') : ($statusKey === 'cancelled' ? (string) ($row['cancel_reason'] ?? '') : '');
        $canChange = in_array($statusKey, ['pending', 'acknowledged'], true);
        $actions = '<div class="request-actions">';
        if ($manage && !$archive) {
            if ($statusKey === 'pending') {
                $actions .= '<button type="button" class="request-action-btn request-action-restore acknowledge-service-btn" data-id="' . $id . '"><i class="bi bi-check2-circle"></i> Acknowledge</button>';
                $actions .= '<button type="button" class="request-action-btn request-action-delete reject-service-btn" data-id="' . $id . '"><i class="bi bi-x-circle"></i> Reject</button>';
            }
            if ($statusKey === 'acknowledged') {
                $actions .= '<button type="button" class="request-action-btn request-action-view assign-service-btn" data-id="' . $id . '"><i class="bi bi-person-check"></i> Assign</button>';
                if (!empty($row['assigned_technician'])) {
                    $actions .= '<a class="request-action-btn request-action-view" href="' . BASE_URL . '/ictd/service-report?request_id=' . $id . '"><i class="bi bi-file-earmark-plus"></i> Report</a>';
                    $actions .= '<button type="button" class="request-action-btn request-action-restore accomplish-service-btn" data-id="' . $id . '"><i class="bi bi-clipboard-check"></i> Accomplish</button>';
                }
            }
        } elseif ($archive) {
            $actions .= '<button type="button" class="request-action-btn request-action-restore restore-service-btn" data-id="' . $id . '"><i class="bi bi-arrow-counterclockwise"></i> Restore</button>';
            if (is_admin()) {
                $actions .= '<button type="button" class="request-action-btn request-action-delete delete-service-btn" data-id="' . $id . '"><i class="bi bi-trash3"></i> Delete</button>';
            }
        } else {
            if ($canChange) {
                $actions .= '<button type="button" class="request-action-btn request-action-edit edit-service-btn" data-id="' . $id . '"><i class="bi bi-pencil-square"></i> Edit</button>';
                $actions .= '<button type="button" class="request-action-btn request-action-delete cancel-service-btn" data-id="' . $id . '"><i class="bi bi-x-circle"></i> Cancel</button>';
            }
            $actions .= '<button type="button" class="request-action-btn request-action-archive archive-service-btn" data-id="' . $id . '"><i class="bi bi-archive"></i> Archive</button>';
        }
        $actions .= '</div>';

        $rows[] = [
            $actions,
            $id,
            e($row['end_user_name'] ?? ''),
            e($row['department_name'] ?? ''),
            e($row['request_type'] ?? ''),
            e($row['request_description'] ?? ''),
            '<span class="badge text-bg-' . srq_status_badge($rowStatus) . '">' . e($rowStatus) . '</span>',
            e($row['assigned_technician'] ?: '-'),
            e($reason ?: ($row['remarks'] ?? '-')),
            e($row['created_at'] ?? ''),
            $archive ? e($row['archived_at'] ?? '') : null,
        ];
        if (!$archive) {
            array_pop($rows[count($rows) - 1]);
        }
    }

    $counts = [];
    if ($manage) {
        $counts = ['all' => 0, 'Pending' => 0, 'Acknowledged' => 0, 'Assigned' => 0, 'Accomplished' => 0, 'Rejected' => 0, 'Cancelled' => 0];
        $countRows = $pdo->query("SELECT status, assigned_technician, COUNT(*) AS total FROM service_request WHERE COALESCE(is_archived, 0) = 0 GROUP BY status, assigned_technician")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($countRows as $countRow) {
            $totalCount = (int) $countRow['total'];
            $statusName = (string) ($countRow['status'] ?: 'Pending');
            $counts['all'] += $totalCount;
            if ($statusName === 'Acknowledged' && trim((string) ($countRow['assigned_technician'] ?? '')) !== '') {
                $counts['Assigned'] += $totalCount;
            } elseif (isset($counts[$statusName])) {
                $counts[$statusName] += $totalCount;
            }
        }
    }

    srq_data_response([
        'draw' => $draw,
        'recordsTotal' => $total,
        'recordsFiltered' => $filtered,
        'data' => $rows,
        'counts' => $counts,
    ]);
} catch (Throwable $e) {
    srq_data_response(['draw' => (int) ($_REQUEST['draw'] ?? 1), 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'counts' => [], 'error' => $e->getMessage()]);
}
