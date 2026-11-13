<?php
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/functions.php';
require_once ROOT_PATH . '/modules/Pages/id-issuance/id-issuance-common.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_login();
require_permission('id-issuance.view');

header('Content-Type: application/json');

function id_issuance_response(string $status, string $message = '', array $extra = []): void
{
    echo json_encode(array_merge([
        'status' => $status,
        'ok' => $status === 'success',
        'message' => $message,
        'msg' => $message,
    ], $extra));
    exit;
}

function id_issuance_actor(): array
{
    $user = current_user();
    return [
        'id' => (int) ($user['id'] ?? 0),
        'name' => trim((string) ($user['username'] ?? 'system')),
    ];
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'load_visitors':
            require_permission('id-issuance.view');
            $pdlId = (int) ($_POST['pdl_id'] ?? $_GET['pdl_id'] ?? 0);
            if ($pdlId <= 0) {
                throw new Exception('Invalid Resident selected.');
            }

            issuance_ensure_history_table($pdo);

            $stmt = $pdo->prepare("
                SELECT
                    vr.*,
                    p.pdl_id AS pdl_code,
                    p.pdl_fname,
                    p.pdl_mname,
                    p.pdl_lname,
                    p.pdl_suffix,
                    p.case_no,
                    p.case_type,
                    p.court,
                    p.dorm_no,
                    pav.visitor_relationship,
                    (
                        SELECT MAX(vih.issued_at)
                        FROM visitor_id_issuance_history vih
                        WHERE vih.visitor_id = vr.id
                    ) AS last_issued_at
                FROM visitors_registration vr
                LEFT JOIN pdl_info p ON p.id = vr.pdl_id
                LEFT JOIN pdl_authorized_visitors pav ON pav.id = vr.authorized_visitor_id
                WHERE vr.pdl_id = ?
                  AND vr.visitor_status = 'Verified'
                ORDER BY vr.id DESC
            ");
            $stmt->execute([$pdlId]);

            $rows = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $rows[] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'visitor_name' => issuance_full_name($row),
                    'age' => $row['age'] ?? '',
                    'sex' => $row['sex'] ?? '',
                    'address' => $row['loc_add'] ?? '',
                    'contact' => $row['contact_no'] ?? '',
                    'birth_certificate' => !empty($row['birth_certificate_id']),
                    'marriage_contract' => !empty($row['marriage_contract_id']),
                    'cohabitation_certificate' => !empty($row['cohabitation_certificate_id']),
                    'status' => $row['visitor_status'] ?? '',
                    'submitted_at' => $row['created_at'] ?? '',
                    'v_id' => trim((string) ($row['v_id'] ?? '')),
                    'issued_at' => trim((string) ($row['issued_at'] ?? '')),
                    'last_issued_at' => trim((string) ($row['last_issued_at'] ?? '')),
                    'has_issued_id' => trim((string) ($row['last_issued_at'] ?? '')) !== '',
                    'relationship' => trim((string) ($row['visitor_relationship'] ?? '')),
                    'is_banned' => (int) ($row['is_banned'] ?? 0),
                ];
            }

            id_issuance_response('success', '', ['rows' => $rows]);
            break;

        case 'issue':
            require_permission('id-issuance.create');
            $visitorId = (int) ($_POST['visitor_id'] ?? 0);
            $mode = trim((string) ($_POST['mode'] ?? 'issue'));
            if ($visitorId <= 0) {
                throw new Exception('Invalid visitor selected.');
            }

            $mode = $mode === 'reissue' ? 'reissue' : 'issue';
            $issuedVisitor = issuance_process($pdo, $visitorId, $mode, id_issuance_actor());

            log_activity(
                $mode === 'reissue' ? 'VISITOR_ID_REISSUE' : 'VISITOR_ID_ISSUE',
                ucfirst($mode) . 'd visitor ID for registration ID: ' . $visitorId . ' | v_id: ' . trim((string) ($issuedVisitor['v_id'] ?? ''))
            );

            id_issuance_response('success', 'Visitor ID prepared successfully.', [
                'redirect_url' => BASE_URL . '/id-card?visitor_id=' . $visitorId,
            ]);
            break;

        case 'history':
            require_permission('id-issuance.view');
            $visitorId = (int) ($_POST['visitor_id'] ?? $_GET['visitor_id'] ?? 0);
            if ($visitorId <= 0) {
                throw new Exception('Invalid visitor selected.');
            }

            $history = issuance_history($pdo, $visitorId);
            id_issuance_response('success', '', ['rows' => $history]);
            break;

        default:
            throw new Exception('Invalid action.');
    }
} catch (Throwable $e) {
    id_issuance_response('error', $e->getMessage());
}
