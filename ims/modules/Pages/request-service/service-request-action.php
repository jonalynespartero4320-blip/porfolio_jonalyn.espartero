<?php
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/functions.php';
require_once ROOT_PATH . '/modules/Pages/request-service/service-request-common.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_login();
require_any_permission(['ictd.request.view', 'ictd.request.manage']);
header('Content-Type: application/json');

function srq_response(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function srq_payload(): array
{
    $raw = file_get_contents('php://input');
    $json = json_decode($raw ?: '', true);
    return is_array($json) ? $json : $_POST;
}

try {
    srq_ensure_schema($pdo);
    ensure_record_scope_schema($pdo);

    $payload = srq_payload();
    $action = (string) ($_GET['action'] ?? $payload['action'] ?? '');

    if ($action === 'options') {
        srq_response(['success' => true, 'data' => srq_select_options($pdo)]);
    }

    if ($action === 'get') {
        $id = srq_clean_int($_GET['id'] ?? $payload['id'] ?? null);
        if (!$id || !srq_user_can_access($pdo, $id)) {
            srq_response(['success' => false, 'message' => 'Request not found.'], 404);
        }
        $stmt = $pdo->prepare('SELECT * FROM service_request WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        srq_response(['success' => true, 'data' => $stmt->fetch(PDO::FETCH_ASSOC)]);
    }

    if ($action === 'save') {
        $id = srq_clean_int($payload['id'] ?? null);
        $requestType = srq_clean_string($payload['request_type'] ?? null);
        $endUserId = srq_clean_int($payload['end_user_id'] ?? null);
        $departmentId = srq_clean_int($payload['department_id'] ?? null);
        $description = srq_clean_string($payload['request_description'] ?? null);
        $complain = srq_clean_string($payload['complain_problem'] ?? null);
        $remarks = srq_clean_string($payload['remarks'] ?? null);

        if (!$requestType || !$endUserId || !$departmentId || !$description || !$complain) {
            srq_response(['success' => false, 'message' => 'Request type, end user, department, description, and complaint/problem are required.'], 422);
        }

        if (!user_can_view_all_records() && !in_array($departmentId, user_department_scope_ids(), true)) {
            srq_response(['success' => false, 'message' => 'You are not allowed to create requests for this department.'], 403);
        }

        if ($id) {
            if (!srq_user_can_access($pdo, $id)) {
                srq_response(['success' => false, 'message' => 'Request not found.'], 404);
            }
            $stmt = $pdo->prepare('SELECT status FROM service_request WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $status = strtolower((string) ($stmt->fetchColumn() ?: 'Pending'));
            if (!in_array($status, ['pending', 'acknowledged'], true)) {
                srq_response(['success' => false, 'message' => 'This request can no longer be edited.'], 422);
            }
            $stmt = $pdo->prepare('
                UPDATE service_request
                SET request_type = ?, end_user_id = ?, department_id = ?, request_description = ?, complain_problem = ?, remarks = ?, updated_at = NOW()
                WHERE id = ?
            ');
            $stmt->execute([$requestType, $endUserId, $departmentId, $description, $complain, $remarks, $id]);
            $requestId = $id;
        } else {
            $stmt = $pdo->prepare('
                INSERT INTO service_request
                    (request_type, end_user_id, department_id, request_description, complain_problem, remarks, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ');
            $stmt->execute([$requestType, $endUserId, $departmentId, $description, $complain, $remarks, record_scope_current_user_id() ?: null]);
            $requestId = (int) $pdo->lastInsertId();
        }

        log_activity($id ? 'SERVICE_REQUEST_UPDATE' : 'SERVICE_REQUEST_CREATE', ($id ? 'Updated' : 'Created') . ' service request #' . $requestId);
        srq_response(['success' => true, 'message' => 'Service request saved successfully.']);
    }

    if ($action === 'cancel') {
        $id = srq_clean_int($payload['id'] ?? null);
        $reason = srq_clean_string($payload['reason'] ?? null);
        if (!$id || !srq_user_can_access($pdo, $id)) {
            srq_response(['success' => false, 'message' => 'Request not found.'], 404);
        }
        if (!$reason) {
            srq_response(['success' => false, 'message' => 'Please enter a cancellation reason.'], 422);
        }
        $stmt = $pdo->prepare('SELECT status FROM service_request WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $status = strtolower((string) ($stmt->fetchColumn() ?: 'Pending'));
        if (!in_array($status, ['pending', 'acknowledged'], true)) {
            srq_response(['success' => false, 'message' => 'This request can no longer be cancelled.'], 422);
        }
        $pdo->prepare("UPDATE service_request SET status = 'Cancelled', cancel_reason = ?, cancelled_at = NOW(), updated_at = NOW() WHERE id = ?")->execute([$reason, $id]);
        log_activity('SERVICE_REQUEST_CANCEL', 'Cancelled service request #' . $id);
        srq_response(['success' => true, 'message' => 'Service request cancelled.']);
    }

    if (in_array($action, ['archive', 'restore', 'delete'], true)) {
        $id = srq_clean_int($payload['id'] ?? null);
        if (!$id || !srq_user_can_access($pdo, $id)) {
            srq_response(['success' => false, 'message' => 'Request not found.'], 404);
        }
        if ($action === 'archive') {
            $pdo->prepare('UPDATE service_request SET is_archived = 1, archived_at = NOW(), updated_at = NOW() WHERE id = ?')->execute([$id]);
            log_activity('SERVICE_REQUEST_ARCHIVE', 'Archived service request #' . $id);
            srq_response(['success' => true, 'message' => 'Service request archived.']);
        }
        if ($action === 'restore') {
            $pdo->prepare('UPDATE service_request SET is_archived = 0, archived_at = NULL, updated_at = NOW() WHERE id = ?')->execute([$id]);
            log_activity('SERVICE_REQUEST_RESTORE', 'Restored service request #' . $id);
            srq_response(['success' => true, 'message' => 'Service request restored.']);
        }
        if (!is_admin()) {
            srq_response(['success' => false, 'message' => 'Only admins can permanently delete archived requests.'], 403);
        }
        $pdo->prepare('DELETE FROM service_request WHERE id = ?')->execute([$id]);
        log_activity('SERVICE_REQUEST_DELETE', 'Permanently deleted service request #' . $id);
        srq_response(['success' => true, 'message' => 'Service request deleted.']);
    }

    if (in_array($action, ['acknowledge', 'reject', 'assign', 'accomplish'], true)) {
        if (!is_admin() && !can('ictd.request.manage')) {
            srq_response(['success' => false, 'message' => 'Access denied.'], 403);
        }
        $id = srq_clean_int($payload['id'] ?? null);
        if (!$id || !srq_user_can_access($pdo, $id)) {
            srq_response(['success' => false, 'message' => 'Request not found.'], 404);
        }
        if ($action === 'acknowledge') {
            $pdo->prepare("UPDATE service_request SET status = 'Acknowledged', admin_action_reason = NULL, updated_at = NOW() WHERE id = ? AND status = 'Pending'")->execute([$id]);
            srq_response(['success' => true, 'message' => 'Request acknowledged.']);
        }
        if ($action === 'reject') {
            $reason = srq_clean_string($payload['reason'] ?? null);
            if (!$reason) {
                srq_response(['success' => false, 'message' => 'Rejection reason is required.'], 422);
            }
            $pdo->prepare("UPDATE service_request SET status = 'Rejected', admin_action_reason = ?, updated_at = NOW() WHERE id = ?")->execute([$reason, $id]);
            srq_response(['success' => true, 'message' => 'Request rejected.']);
        }
        if ($action === 'assign') {
            $technician = srq_clean_string($payload['technician_name'] ?? null);
            if (!$technician) {
                srq_response(['success' => false, 'message' => 'Technician is required.'], 422);
            }
            if (db_table_exists($pdo, 'list_technical')) {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM list_technical WHERE technical_name = ?');
                $stmt->execute([$technician]);
                if ((int) $stmt->fetchColumn() < 1) {
                    srq_response(['success' => false, 'message' => 'Select a valid technician from the technical masterlist.'], 422);
                }
            }
            $pdo->prepare("UPDATE service_request SET status = 'Acknowledged', assigned_technician = ?, updated_at = NOW() WHERE id = ?")->execute([$technician, $id]);
            srq_response(['success' => true, 'message' => 'Technician assigned.']);
        }
        if ($action === 'accomplish') {
            $hasReport = false;
            if (db_table_exists($pdo, 'service_report') && db_column_exists($pdo, 'service_report', 'request_id')) {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM service_report WHERE request_id = ? AND COALESCE(is_archived, 0) = 0');
                $stmt->execute([$id]);
                $hasReport = (int) $stmt->fetchColumn() > 0;
            }
            if (!$hasReport) {
                srq_response(['success' => false, 'message' => 'No service report found for this request yet.'], 422);
            }
            $pdo->prepare("UPDATE service_request SET status = 'Accomplished', updated_at = NOW() WHERE id = ?")->execute([$id]);
            srq_response(['success' => true, 'message' => 'Request marked as accomplished.']);
        }
    }

    srq_response(['success' => false, 'message' => 'Invalid action.'], 400);
} catch (Throwable $e) {
    srq_response(['success' => false, 'message' => $e->getMessage()], 500);
}
