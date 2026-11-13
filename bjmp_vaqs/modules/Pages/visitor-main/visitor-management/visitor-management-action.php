<?php
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_login();

header('Content-Type: application/json');

function vm_json_response(string $status, string $message = '', array $extra = []): void
{
    echo json_encode(array_merge([
        'status' => $status,
        'message' => $message,
    ], $extra));
    exit;
}

function vm_current_actor(): array
{
    $user = current_user();

    return [
        'id' => (int) ($user['id'] ?? 0),
        'name' => trim((string) ($user['username'] ?? 'system')),
    ];
}

function vm_upload_dir(): string
{
    return ROOT_PATH . '/public/demo-uploads/visitors/';
}

function vm_normalize_public_relative_path(string $path): string
{
    return ltrim(str_replace('\\', '/', trim($path)), '/');
}

function vm_public_file_url(?string $path): ?string
{
    $path = vm_normalize_public_relative_path((string) $path);
    if ($path === '') {
        return null;
    }

    if (preg_match('/^https?:\/\//i', $path)) {
        return $path;
    }

    $segments = array_map('rawurlencode', array_filter(explode('/', $path), 'strlen'));
    return BASE_URL . '/' . implode('/', $segments);
}

function vm_public_asset_url(string $relativePath): string
{
    return BASE_URL . '/' . ltrim($relativePath, '/');
}

function vm_default_photo_url(): string
{
    return vm_public_asset_url('assets/images/demo-scene.svg');
}

function vm_ensure_upload_dir(): void
{
    if (!is_dir(vm_upload_dir())) {
        mkdir(vm_upload_dir(), 0755, true);
    }
}

function vm_remove_file(?string $fileName): void
{
    $fileName = vm_normalize_public_relative_path((string) $fileName);
    if ($fileName === '' || preg_match('/^https?:\/\//i', $fileName)) {
        return;
    }

    $fullPath = ROOT_PATH . '/public/' . $fileName;
    if (is_file($fullPath)) {
        unlink($fullPath);
    }
}

function vm_store_upload(array $file, array $allowedExtensions, int $maxBytes, string $prefix): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new Exception('File upload failed.');
    }

    if (($file['size'] ?? 0) > $maxBytes) {
        throw new Exception('File exceeds the maximum allowed size.');
    }

    $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions, true)) {
        throw new Exception('Invalid file type.');
    }

    vm_ensure_upload_dir();

    $fileName = $prefix . '_' . uniqid('', true) . '.' . $extension;
    $targetPath = vm_upload_dir() . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new Exception('Failed to store uploaded file.');
    }

    return 'demo-uploads/visitors/' . $fileName;
}

function vm_ensure_ban_history_table(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS visitor_ban_history (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            visitor_id INT(11) NOT NULL,
            violation_type VARCHAR(255) NOT NULL,
            banned_by INT(11) DEFAULT NULL,
            lifted_by INT(11) DEFAULT NULL,
            effective_from DATE DEFAULT NULL,
            effective_until DATE DEFAULT NULL,
            lifted_on DATE DEFAULT NULL,
            lift_reason VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_visitor_id (visitor_id),
            INDEX idx_effective_until (effective_until)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $existing = [];
    foreach ($pdo->query("SHOW COLUMNS FROM visitor_ban_history") as $column) {
        $existing[$column['Field']] = true;
    }

    $requiredColumns = [
        'lifted_by' => "ALTER TABLE visitor_ban_history ADD COLUMN lifted_by INT(11) DEFAULT NULL AFTER banned_by",
        'effective_from' => "ALTER TABLE visitor_ban_history ADD COLUMN effective_from DATE DEFAULT NULL AFTER lifted_by",
        'effective_until' => "ALTER TABLE visitor_ban_history ADD COLUMN effective_until DATE DEFAULT NULL AFTER effective_from",
        'lifted_on' => "ALTER TABLE visitor_ban_history ADD COLUMN lifted_on DATE DEFAULT NULL AFTER effective_until",
        'lift_reason' => "ALTER TABLE visitor_ban_history ADD COLUMN lift_reason VARCHAR(255) DEFAULT NULL AFTER lifted_on",
        'created_at' => "ALTER TABLE visitor_ban_history ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER lift_reason",
        'updated_at' => "ALTER TABLE visitor_ban_history ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at",
    ];

    foreach ($requiredColumns as $column => $sql) {
        if (!isset($existing[$column])) {
            $pdo->exec($sql);
        }
    }

    $indexes = [];
    foreach ($pdo->query("SHOW INDEX FROM visitor_ban_history") as $indexRow) {
        $indexes[$indexRow['Key_name']] = true;
    }

    if (!isset($indexes['idx_visitor_id'])) {
        $pdo->exec("ALTER TABLE visitor_ban_history ADD INDEX idx_visitor_id (visitor_id)");
    }

    if (!isset($indexes['idx_effective_until'])) {
        $pdo->exec("ALTER TABLE visitor_ban_history ADD INDEX idx_effective_until (effective_until)");
    }
}

function vm_load_registration(PDO $pdo, int $registrationId): array
{
    $stmt = $pdo->prepare("
        SELECT
            vr.*,
            p.pdl_fname,
            p.pdl_mname,
            p.pdl_lname,
            p.pdl_suffix,
            p.case_type,
            p.court,
            p.dorm_no
        FROM visitors_registration vr
        LEFT JOIN pdl_info p ON p.id = vr.pdl_id
        WHERE vr.id = ?
        LIMIT 1
    ");
    $stmt->execute([$registrationId]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new Exception('Visitor registration not found.');
    }

    return $row;
}

function vm_requirement_map(): array
{
    return [
        'Valid_ID' => ['column' => 'birth_certificate_id', 'label' => 'Valid ID'],
        'birth_certificate' => ['column' => 'birth_certificate_id', 'label' => 'Birth Certificate'],
        'marriage_contract' => ['column' => 'marriage_contract_id', 'label' => 'Marriage Contract'],
        'cohabitation_certificate' => ['column' => 'cohabitation_certificate_id', 'label' => 'Cohabitation Certificate'],
    ];
}

function vm_build_requirements(array $registration): array
{
    $requirements = [];

    foreach (vm_requirement_map() as $key => $meta) {
        $fileName = trim((string) ($registration[$meta['column']] ?? ''));
        $fileUrl = null;
        if ($fileName !== '') {
            $fileUrl = str_contains($fileName, '/')
                ? vm_public_file_url($fileName)
                : vm_public_file_url('demo-uploads/visitors/' . $fileName);
        }

        $requirements[$key] = [
            'label' => $meta['label'],
            'file_name' => $fileName,
            'file_url' => $fileUrl,
        ];
    }

    return $requirements;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    vm_ensure_ban_history_table($pdo);

    switch ($action) {
        case 'get_details':
            require_permission('visitors.view');
            $registrationId = (int) ($_GET['id'] ?? 0);
            if ($registrationId <= 0) {
                throw new Exception('Invalid visitor selected.');
            }

            $registration = vm_load_registration($pdo, $registrationId);
            $pdlName = preg_replace('/\s+/', ' ', trim(($registration['pdl_fname'] ?? '') . ' ' . ($registration['pdl_mname'] ?? '') . ' ' . ($registration['pdl_lname'] ?? '') . ' ' . ($registration['pdl_suffix'] ?? '')));
            $visitorPhoto = trim((string) ($registration['visitor_photo'] ?? ''));
            $visitorPhotoUrl = $visitorPhoto !== ''
                ? (str_contains($visitorPhoto, '/')
                    ? vm_public_file_url($visitorPhoto)
                    : vm_public_file_url('demo-uploads/visitors/' . $visitorPhoto))
                : vm_default_photo_url();

            log_activity('VISITOR_MANAGEMENT_DETAIL_VIEW', "Viewed visitor details for registration ID: {$registrationId}");
            vm_json_response('success', '', [
                'data' => [
                    'id' => (int) $registration['id'],
                    'v_id' => $registration['v_id'],
                    'pdl_id' => $registration['pdl_id'],
                    'firstname' => $registration['firstname'],
                    'middlename' => $registration['middlename'],
                    'lastname' => $registration['lastname'],
                    'suffix' => $registration['suffix'],
                    'age' => $registration['age'],
                    'sex' => $registration['sex'],
                    'loc_add' => $registration['loc_add'],
                    'contact_no' => $registration['contact_no'],
                    'ec_name' => $registration['ec_name'],
                    'ec_contactno' => $registration['ec_contactno'],
                    'ec_address' => $registration['ec_address'],
                    'visitor_status' => $registration['visitor_status'],
                    'is_banned' => (int) ($registration['is_banned'] ?? 0),
                    'effective_until' => $registration['effective_until'],
                    'visitor_photo_url' => $visitorPhotoUrl,
                    'pdl_name' => $pdlName,
                    'dorm_no' => $registration['dorm_no'],
                    'case_type' => $registration['case_type'],
                    'court' => $registration['court'],
                    'requirements' => vm_build_requirements($registration),
                ],
            ]);
            break;

        case 'update_emergency_contact':
            require_permission('visitors.edit');
            $registrationId = (int) ($_POST['registration_id'] ?? 0);
            $ecName = trim((string) ($_POST['ec_name'] ?? ''));
            $ecContact = trim((string) ($_POST['ec_contactno'] ?? ''));
            $ecAddress = trim((string) ($_POST['ec_address'] ?? ''));

            if ($registrationId <= 0) {
                throw new Exception('Invalid registration selected.');
            }
            if ($ecName === '' || $ecContact === '' || $ecAddress === '') {
                throw new Exception('Emergency contact name, contact number, and address are required.');
            }

            $registration = vm_load_registration($pdo, $registrationId);
            $stmt = $pdo->prepare("
                UPDATE visitors_registration
                SET ec_name = ?,
                    ec_contactno = ?,
                    ec_address = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$ecName, $ecContact, $ecAddress, $registrationId]);
            $afterRegistration = vm_load_registration($pdo, $registrationId);

            archive_record_change(
                'visitors_registration',
                $registrationId,
                'updated',
                $registration,
                $afterRegistration,
                "Updated emergency contact for registration ID {$registrationId}"
            );

            log_activity('VISITOR_EMERGENCY_CONTACT_UPDATE', "Updated emergency contact for registration ID: {$registrationId}");
            vm_json_response('success', 'Emergency contact updated successfully.', [
                'data' => [
                    'ec_name' => $afterRegistration['ec_name'] ?? '',
                    'ec_contactno' => $afterRegistration['ec_contactno'] ?? '',
                    'ec_address' => $afterRegistration['ec_address'] ?? '',
                ],
            ]);
            break;

        case 'update_photo':
            require_permission('visitors.edit');
            $registrationId = (int) ($_POST['registration_id'] ?? 0);
            if ($registrationId <= 0) {
                throw new Exception('Invalid registration selected.');
            }
            if (empty($_FILES['visitor_photo']['name'])) {
                throw new Exception('Please select a photo first.');
            }

            $registration = vm_load_registration($pdo, $registrationId);
            $newFile = vm_store_upload($_FILES['visitor_photo'], ['jpg', 'jpeg', 'png'], 2 * 1024 * 1024, 'visitor_photo');
            vm_remove_file($registration['visitor_photo'] ?? null);

            $stmt = $pdo->prepare("UPDATE visitors_registration SET visitor_photo = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$newFile, $registrationId]);
            $afterRegistration = vm_load_registration($pdo, $registrationId);

            archive_record_change('visitors_registration', $registrationId, 'updated', $registration, $afterRegistration, "Updated visitor photo for registration ID {$registrationId}");
            log_activity('VISITOR_PHOTO_UPDATE', "Updated photo for registration ID: {$registrationId}");
            vm_json_response('success', 'Photo updated successfully.', [
                'photo_url' => vm_public_file_url($newFile),
            ]);
            break;

        case 'update_requirement':
            require_permission('visitors.edit');
            $registrationId = (int) ($_POST['registration_id'] ?? 0);
            $requirementType = trim((string) ($_POST['requirement_type'] ?? ''));
            $map = vm_requirement_map();

            if ($registrationId <= 0) {
                throw new Exception('Invalid registration selected.');
            }
            if (!isset($map[$requirementType])) {
                throw new Exception('Invalid requirement selected.');
            }
            if (empty($_FILES['file']['name'])) {
                throw new Exception('Please select a file first.');
            }

            $registration = vm_load_registration($pdo, $registrationId);
            $column = $map[$requirementType]['column'];
            $newFile = vm_store_upload($_FILES['file'], ['pdf', 'jpg', 'jpeg', 'png'], 1024 * 1024, $column);
            vm_remove_file($registration[$column] ?? null);

            $stmt = $pdo->prepare("UPDATE visitors_registration SET {$column} = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$newFile, $registrationId]);
            $afterRegistration = vm_load_registration($pdo, $registrationId);

            archive_record_change('visitors_registration', $registrationId, 'updated', $registration, $afterRegistration, "Updated {$map[$requirementType]['label']} for registration ID {$registrationId}");
            log_activity('VISITOR_REQUIREMENT_UPDATE', "Updated {$requirementType} for registration ID: {$registrationId}");
            vm_json_response('success', 'Requirement updated successfully.', [
                'file_url' => vm_public_file_url($newFile),
            ]);
            break;

        case 'ban_visitor':
            require_permission('visitors.ban');
            $registrationId = (int) ($_POST['visitor_id'] ?? 0);
            $violationType = trim((string) ($_POST['violation_type'] ?? ''));
            $effectiveUntil = trim((string) ($_POST['effective_until'] ?? ''));

            if ($registrationId <= 0) {
                throw new Exception('Invalid visitor selected.');
            }
            if ($violationType === '' || $effectiveUntil === '') {
                throw new Exception('Violation and effective-until date are required.');
            }
            if ($effectiveUntil < date('Y-m-d')) {
                throw new Exception('Effective-until date cannot be earlier than today.');
            }

            $registration = vm_load_registration($pdo, $registrationId);
            if ((int) ($registration['is_banned'] ?? 0) === 1) {
                throw new Exception('This visitor is already banned.');
            }

            $actor = vm_current_actor();
            $pdo->beginTransaction();

            try {
                $updateStmt = $pdo->prepare("
                    UPDATE visitors_registration
                    SET is_banned = 1,
                        violation_type = ?,
                        effective_until = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $updateStmt->execute([$violationType, $effectiveUntil, $registrationId]);

                $historyStmt = $pdo->prepare("
                    INSERT INTO visitor_ban_history (
                        visitor_id,
                        violation_type,
                        banned_by,
                        effective_from,
                        effective_until
                    ) VALUES (?, ?, ?, CURDATE(), ?)
                ");
                $historyStmt->execute([$registrationId, $violationType, $actor['id'], $effectiveUntil]);

                $pdo->commit();
                archive_record_change('visitors_registration', $registrationId, 'updated', $registration, vm_load_registration($pdo, $registrationId), "Banned visitor registration ID {$registrationId}");
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }

            log_activity('VISITOR_BAN', "Banned registration ID: {$registrationId} | violation: {$violationType} | until: {$effectiveUntil}");
            vm_json_response('success', 'Visitor banned successfully.');
            break;

        case 'lift_ban':
            require_permission('visitors.ban');
            $registrationId = (int) ($_POST['visitor_id'] ?? 0);
            $liftReason = trim((string) ($_POST['lift_reason'] ?? ''));

            if ($registrationId <= 0) {
                throw new Exception('Invalid visitor selected.');
            }
            if ($liftReason === '') {
                throw new Exception('Reason for lifting the ban is required.');
            }

            $registration = vm_load_registration($pdo, $registrationId);
            if ((int) ($registration['is_banned'] ?? 0) !== 1) {
                throw new Exception('This visitor is not currently banned.');
            }

            $actor = vm_current_actor();
            $pdo->beginTransaction();

            try {
                $updateStmt = $pdo->prepare("
                    UPDATE visitors_registration
                    SET is_banned = 0,
                        violation_type = NULL,
                        effective_until = NULL,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $updateStmt->execute([$registrationId]);

                $historyStmt = $pdo->prepare("
                    UPDATE visitor_ban_history
                    SET lifted_by = ?, lifted_on = CURDATE(), lift_reason = ?
                    WHERE visitor_id = ?
                      AND lifted_on IS NULL
                    ORDER BY id DESC
                    LIMIT 1
                ");
                $historyStmt->execute([$actor['id'], $liftReason, $registrationId]);

                $pdo->commit();
                archive_record_change('visitors_registration', $registrationId, 'updated', $registration, vm_load_registration($pdo, $registrationId), "Lifted visitor ban for registration ID {$registrationId}");
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }

            log_activity('VISITOR_BAN_LIFT', "Lifted ban for registration ID: {$registrationId} | reason: {$liftReason}");
            vm_json_response('success', 'Visitor ban lifted successfully.');
            break;

        case 'ban_history':
            require_permission('visitors.ban');
            $registrationId = (int) ($_GET['id'] ?? 0);
            if ($registrationId <= 0) {
                throw new Exception('Invalid visitor selected.');
            }

            $stmt = $pdo->prepare("
                SELECT
                    vbh.*,
                    banned_user.username AS banned_by_name,
                    lifted_user.username AS lifted_by_name
                FROM visitor_ban_history vbh
                LEFT JOIN users banned_user ON banned_user.id = vbh.banned_by
                LEFT JOIN users lifted_user ON lifted_user.id = vbh.lifted_by
                WHERE vbh.visitor_id = ?
                ORDER BY vbh.id DESC
            ");
            $stmt->execute([$registrationId]);

            log_activity('VISITOR_BAN_HISTORY_VIEW', "Viewed ban history for registration ID: {$registrationId}");
            vm_json_response('success', '', [
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            ]);
            break;

        case 'get_pdl_details':
            require_permission('visitors.view');
            $pdlId = (int) ($_GET['pdl_id'] ?? 0);
            if ($pdlId <= 0) {
                throw new Exception('Invalid Resident selected.');
            }

            $stmt = $pdo->prepare("SELECT * FROM pdl_info WHERE id = ? LIMIT 1");
            $stmt->execute([$pdlId]);
            $pdl = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$pdl) {
                throw new Exception('Resident record not found.');
            }

            $photo = trim((string) ($pdl['pdl_photo'] ?? ''));
            $pdl['pdl_photo_url'] = $photo !== ''
                ? (str_contains($photo, '/') ? vm_public_file_url($photo) : vm_public_file_url('demo-uploads/pdl/' . $photo))
                : vm_default_photo_url();

            log_activity('PDL_DETAIL_VIEW', "Viewed Resident details for Resident ID: {$pdlId}");
            vm_json_response('success', '', ['data' => $pdl]);
            break;

        default:
            throw new Exception('Invalid action.');
    }
} catch (Throwable $e) {
    vm_json_response('error', $e->getMessage());
}
