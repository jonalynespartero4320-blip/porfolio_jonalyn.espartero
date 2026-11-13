<?php
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_login();

header('Content-Type: application/json');

function json_response(string $status, string $message = '', array $extra = []): void
{
    echo json_encode(array_merge([
        'status' => $status,
        'message' => $message,
    ], $extra));
    exit;
}

function current_actor(): array
{
    $user = current_user();

    return [
        'id' => (int) ($user['id'] ?? 0),
        'name' => trim((string) ($user['username'] ?? 'system')),
    ];
}

function uploads_dir(): string
{
    return ROOT_PATH . '/public/demo-uploads/visitors/';
}

function uploads_url(): string
{
    return BASE_URL . '/demo-uploads/visitors/';
}

function ensure_upload_dir(): void
{
    $dir = uploads_dir();
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

function stored_file_url(?string $relativePath): ?string
{
    $file = trim((string) $relativePath);
    if ($file === '') {
        return null;
    }

    return uploads_url() . rawurlencode($file);
}

function remove_stored_file(?string $relativePath): void
{
    $file = trim((string) $relativePath);
    if ($file === '') {
        return;
    }

    $path = uploads_dir() . $file;
    if (file_exists($path)) {
        unlink($path);
    }
}

function upload_single_file(array $file, array $allowedExtensions, int $maxSize, string $prefix): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new Exception('File upload failed.');
    }

    if (($file['size'] ?? 0) > $maxSize) {
        throw new Exception('File exceeds the maximum allowed size.');
    }

    $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExtensions, true)) {
        throw new Exception('Invalid file type.');
    }

    ensure_upload_dir();

    $name = $prefix . '_' . uniqid('', true) . '.' . $ext;
    $target = uploads_dir() . $name;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new Exception('Failed to move uploaded file.');
    }

    return $name;
}

function ensure_document_upload_review_table(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS visitor_document_upload_reviews (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            registration_id INT(11) NOT NULL,
            document_column VARCHAR(100) NOT NULL,
            original_filename VARCHAR(255) DEFAULT NULL,
            stored_filename VARCHAR(255) DEFAULT NULL,
            review_status ENUM('reviewed','skipped') NOT NULL,
            confirmed_safe TINYINT(1) NOT NULL DEFAULT 0,
            uploaded_by INT(11) DEFAULT NULL,
            uploaded_by_username VARCHAR(150) DEFAULT NULL,
            ip_address VARCHAR(64) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_registration_id (registration_id),
            INDEX idx_document_column (document_column),
            INDEX idx_review_status (review_status),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $ensured = true;
}

function ensure_visitor_requirement_columns(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $columns = [
        'valid_id' => "ALTER TABLE visitors_registration ADD COLUMN valid_id VARCHAR(255) DEFAULT NULL AFTER visitor_photo",
        'valid_id_type' => "ALTER TABLE visitors_registration ADD COLUMN valid_id_type VARCHAR(150) DEFAULT NULL AFTER valid_id",
        'valid_id_no' => "ALTER TABLE visitors_registration ADD COLUMN valid_id_no VARCHAR(100) DEFAULT NULL AFTER valid_id_type",
    ];

    foreach ($columns as $column => $sql) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'visitors_registration'
              AND COLUMN_NAME = ?
        ");
        $stmt->execute([$column]);

        if ((int) $stmt->fetchColumn() === 0) {
            $pdo->exec($sql);
        }
    }

    $ensured = true;
}

function record_document_upload_review(PDO $pdo, int $registrationId, string $documentColumn, array $file, string $storedFile, string $reviewStatus): void
{
    ensure_document_upload_review_table($pdo);

    $actor = current_actor();
    $stmt = $pdo->prepare("
        INSERT INTO visitor_document_upload_reviews (
            registration_id,
            document_column,
            original_filename,
            stored_filename,
            review_status,
            confirmed_safe,
            uploaded_by,
            uploaded_by_username,
            ip_address
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $registrationId,
        $documentColumn,
        (string) ($file['name'] ?? ''),
        $storedFile,
        $reviewStatus,
        $reviewStatus === 'reviewed' ? 1 : 0,
        $actor['id'] ?: null,
        $actor['name'],
        $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN',
    ]);
}

function build_document_payload(string $column, ?string $path, ?string $certNo = null): array
{
    $file = trim((string) $path);

    return [
        'column' => $column,
        'file_name' => $file !== '' ? basename($file) : '',
        'file_path' => $file,
        'file_url' => stored_file_url($file),
        'cert_no' => trim((string) $certNo),
    ];
}

function build_valid_id_payload(array $registration): array
{
    $payload = build_document_payload('valid_id', $registration['valid_id'] ?? null, $registration['valid_id_no'] ?? null);
    $payload['id_type'] = trim((string) ($registration['valid_id_type'] ?? ''));
    return $payload;
}

function load_registration_documents(array $registration): array
{
    return [
        'valid_id' => build_valid_id_payload($registration),
        'birth_certificate' => build_document_payload('birth_certificate_id', $registration['birth_certificate_id'] ?? null, $registration['birth_certificate_no'] ?? null),
        'marriage_contract' => build_document_payload('marriage_contract_id', $registration['marriage_contract_id'] ?? null, $registration['marriage_contract_no'] ?? null),
        'cohabitation_certificate' => build_document_payload('cohabitation_certificate_id', $registration['cohabitation_certificate_id'] ?? null, $registration['cohabitation_certificate_no'] ?? null),
    ];
}

function load_registration(PDO $pdo, int $registrationId): array
{
    $stmt = $pdo->prepare("SELECT * FROM visitors_registration WHERE id = ? LIMIT 1");
    $stmt->execute([$registrationId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new Exception('Registration not found.');
    }

    return $row;
}

function find_registration_by_authorized_visitor(PDO $pdo, int $authorizedVisitorId): ?array
{
    if ($authorizedVisitorId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM visitors_registration
        WHERE authorized_visitor_id = ?
        ORDER BY updated_at DESC, id DESC
        LIMIT 1
    ");
    $stmt->execute([$authorizedVisitorId]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function latest_registration_for_visitor(PDO $pdo, array $visitor): ?array
{
    $linked = find_registration_by_authorized_visitor($pdo, (int) ($visitor['id'] ?? 0));
    if ($linked) {
        return $linked;
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM visitors_registration
        WHERE pdl_id = ?
          AND LOWER(TRIM(firstname)) = LOWER(TRIM(?))
          AND LOWER(TRIM(lastname)) = LOWER(TRIM(?))
          AND LOWER(TRIM(COALESCE(middlename, ''))) = LOWER(TRIM(?))
          AND LOWER(TRIM(COALESCE(suffix, ''))) = LOWER(TRIM(?))
        ORDER BY updated_at DESC, id DESC
        LIMIT 1
    ");
    $stmt->execute([
        (int) ($visitor['pdl_id'] ?? 0),
        trim((string) ($visitor['visitor_fname'] ?? '')),
        trim((string) ($visitor['visitor_lname'] ?? '')),
        trim((string) ($visitor['visitor_mname'] ?? '')),
        trim((string) ($visitor['visitor_suffix'] ?? '')),
    ]);

    $registration = $stmt->fetch(PDO::FETCH_ASSOC);
    return $registration ?: null;
}

function registration_document_count(array $registration): int
{
    $count = 0;
    foreach (['valid_id', 'birth_certificate_id', 'marriage_contract_id', 'cohabitation_certificate_id'] as $column) {
        if (!empty($registration[$column])) {
            $count++;
        }
    }

    return $count;
}

function normalize_requirement_text(string $value): string
{
    $value = strtolower(trim($value));
    return preg_replace('/[^a-z0-9]+/', ' ', $value);
}

function registration_relationship(PDO $pdo, array $registration): string
{
    if (!empty($registration['authorized_visitor_id'])) {
        $stmt = $pdo->prepare("SELECT visitor_relationship FROM pdl_authorized_visitors WHERE id = ? LIMIT 1");
        $stmt->execute([(int) $registration['authorized_visitor_id']]);
        return trim((string) $stmt->fetchColumn());
    }

    $stmt = $pdo->prepare("
        SELECT visitor_relationship
        FROM pdl_authorized_visitors
        WHERE pdl_id = ?
          AND LOWER(TRIM(visitor_fname)) = LOWER(TRIM(?))
          AND LOWER(TRIM(visitor_lname)) = LOWER(TRIM(?))
          AND LOWER(TRIM(COALESCE(visitor_mname, ''))) = LOWER(TRIM(?))
          AND LOWER(TRIM(COALESCE(visitor_suffix, ''))) = LOWER(TRIM(?))
        LIMIT 1
    ");
    $stmt->execute([
        (int) ($registration['pdl_id'] ?? 0),
        trim((string) ($registration['firstname'] ?? '')),
        trim((string) ($registration['lastname'] ?? '')),
        trim((string) ($registration['middlename'] ?? '')),
        trim((string) ($registration['suffix'] ?? '')),
    ]);

    return trim((string) $stmt->fetchColumn());
}

function registration_document_requirement_missing(PDO $pdo, array $registration): array
{
    $relationship = normalize_requirement_text(registration_relationship($pdo, $registration));
    $hasMarriageContract = trim((string) ($registration['marriage_contract_id'] ?? '')) !== '';

    $missing = [];

    if (in_array($relationship, ['wife', 'husband', 'spouse'], true) && !$hasMarriageContract) {
        $missing[] = 'Marriage certificate';
    }

    return $missing;
}

function registration_missing_requirements(PDO $pdo, array $registration): array
{
    $checks = [
        'Visitor photo' => $registration['visitor_photo'] ?? '',
        'Emergency contact name' => $registration['ec_name'] ?? '',
        'Emergency contact number' => $registration['ec_contactno'] ?? '',
        'Emergency contact address' => $registration['ec_address'] ?? '',
    ];

    $missing = [];
    foreach ($checks as $label => $value) {
        if (trim((string) $value) === '') {
            $missing[] = $label;
        }
    }

    return array_merge($missing, registration_document_requirement_missing($pdo, $registration));
}

function registration_is_complete(PDO $pdo, array $registration): bool
{
    return empty(registration_missing_requirements($pdo, $registration));
}

function registration_progress_payload(?array $registration): array
{
    if (!$registration) {
        return [
            'has_registration' => 0,
            'registration_id' => null,
            'registration_status' => null,
            'document_count' => 0,
            'completion_rate' => 0,
            'has_photo' => 0,
            'last_updated_at' => null,
        ];
    }

    $documentCount = registration_document_count($registration);

    return [
        'has_registration' => 1,
        'registration_id' => (int) ($registration['id'] ?? 0),
        'registration_status' => $registration['visitor_status'] ?? null,
        'document_count' => $documentCount,
        'completion_rate' => (int) round(($documentCount / 4) * 100),
        'has_photo' => !empty($registration['visitor_photo']) ? 1 : 0,
        'last_updated_at' => $registration['updated_at'] ?? null,
    ];
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    ensure_document_upload_review_table($pdo);
    ensure_visitor_requirement_columns($pdo);

    switch ($action) {
        case 'get_authorized_visitors':
            $pdlId = (int) ($_GET['pdl_id'] ?? 0);
            if ($pdlId <= 0) {
                throw new Exception('Invalid Resident selected.');
            }

            $stmt = $pdo->prepare("
                SELECT *
                FROM pdl_authorized_visitors
                WHERE pdl_id = ?
                ORDER BY id ASC
            ");
            $stmt->execute([$pdlId]);
            $visitors = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $data = [];
            foreach ($visitors as $visitor) {
                $registration = latest_registration_for_visitor($pdo, $visitor);
                $data[] = array_merge($visitor, registration_progress_payload($registration));
            }

            log_activity('VISITOR_REGISTRATION_VIEW', "Loaded authorized visitors for Resident ID: {$pdlId}");
            json_response('success', '', ['data' => $data]);
            break;

        case 'get_visitor_details':
            $visitorId = (int) ($_GET['visitor_id'] ?? 0);
            if ($visitorId <= 0) {
                throw new Exception('Invalid visitor selected.');
            }

            $stmt = $pdo->prepare("SELECT * FROM pdl_authorized_visitors WHERE id = ? LIMIT 1");
            $stmt->execute([$visitorId]);
            $visitor = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$visitor) {
                throw new Exception('Authorized visitor not found.');
            }

            log_activity('AUTHORIZED_VISITOR_VIEW', "Viewed authorized visitor ID: {$visitorId}");
            json_response('success', '', ['visitor' => $visitor]);
            break;

        case 'get_registration_data':
            $registrationId = (int) ($_GET['registration_id'] ?? 0);
            if ($registrationId <= 0) {
                throw new Exception('Invalid registration selected.');
            }

            $registration = load_registration($pdo, $registrationId);
            $visitor = [
                'id' => $registration['authorized_visitor_id'] ?? null,
                'pdl_id' => $registration['pdl_id'] ?? null,
                'firstname' => $registration['firstname'] ?? '',
                'middlename' => $registration['middlename'] ?? '',
                'lastname' => $registration['lastname'] ?? '',
                'suffix' => $registration['suffix'] ?? '',
                'age' => $registration['age'] ?? '',
                'sex' => $registration['sex'] ?? '',
                'loc_add' => $registration['loc_add'] ?? '',
                'contact_no' => $registration['contact_no'] ?? '',
                'ec_name' => $registration['ec_name'] ?? '',
                'ec_contactno' => $registration['ec_contactno'] ?? '',
                'ec_address' => $registration['ec_address'] ?? '',
                'visitor_relationship' => registration_relationship($pdo, $registration),
                'visitor_photo_url' => stored_file_url($registration['visitor_photo'] ?? null),
            ];

            log_activity('VISITOR_REGISTRATION_DETAIL_VIEW', "Viewed registration ID: {$registrationId}");
            json_response('success', '', [
                'visitor' => $visitor,
                'documents' => load_registration_documents($registration),
                'progress' => registration_progress_payload($registration),
            ]);
            break;

        case 'save_registration':
            $requiredFields = ['pdl_id', 'firstname', 'lastname', 'age', 'sex', 'loc_add', 'contact_no'];
            foreach ($requiredFields as $field) {
                if (trim((string) ($_POST[$field] ?? '')) === '') {
                    throw new Exception("Missing required field: {$field}");
                }
            }

            $actor = current_actor();
            $registrationId = (int) ($_POST['registration_id'] ?? 0);
            $visitorSourceId = (int) ($_POST['visitor_source_id'] ?? 0);
            $pdlId = (int) $_POST['pdl_id'];
            $firstname = trim((string) $_POST['firstname']);
            $middlename = trim((string) ($_POST['middlename'] ?? ''));
            $lastname = trim((string) $_POST['lastname']);
            $suffix = trim((string) ($_POST['suffix'] ?? ''));
            $age = (int) $_POST['age'];
            $sex = trim((string) $_POST['sex']);
            $locAdd = trim((string) $_POST['loc_add']);
            $contactNo = trim((string) $_POST['contact_no']);
            $ecName = trim((string) ($_POST['ec_name'] ?? ''));
            $ecContact = trim((string) ($_POST['ec_contactno'] ?? ''));
            $ecAddress = trim((string) ($_POST['ec_address'] ?? ''));

            if (!in_array($sex, ['Male', 'Female'], true)) {
                throw new Exception('Invalid sex selected.');
            }

            $pdo->beginTransaction();

            try {
                $existing = null;
                if ($registrationId > 0) {
                    $existing = load_registration($pdo, $registrationId);
                } elseif ($visitorSourceId > 0) {
                    $existing = find_registration_by_authorized_visitor($pdo, $visitorSourceId);
                    if ($existing) {
                        $registrationId = (int) $existing['id'];
                    }
                }

                if ($existing && $visitorSourceId > 0 && (int) ($existing['authorized_visitor_id'] ?? 0) !== 0 && (int) $existing['authorized_visitor_id'] !== $visitorSourceId) {
                    throw new Exception('This registration is linked to a different authorized visitor.');
                }

                $photoName = $existing['visitor_photo'] ?? null;

                if (!empty($_FILES['visitor_photo_camera']['name'])) {
                    if (!empty($photoName)) {
                        remove_stored_file($photoName);
                    }
                    $photoName = upload_single_file($_FILES['visitor_photo_camera'], ['jpg', 'jpeg', 'png'], 2 * 1024 * 1024, 'visitor_photo');
                } elseif (!empty($_FILES['visitor_photo']['name'])) {
                    if (!empty($photoName)) {
                        remove_stored_file($photoName);
                    }
                    $photoName = upload_single_file($_FILES['visitor_photo'], ['jpg', 'jpeg', 'png'], 2 * 1024 * 1024, 'visitor_photo');
                }

                if ($existing) {
                    $stmt = $pdo->prepare("
                        UPDATE visitors_registration SET
                            authorized_visitor_id = ?, pdl_id = ?, firstname = ?, middlename = ?, lastname = ?, suffix = ?,
                            age = ?, sex = ?, loc_add = ?, contact_no = ?, visitor_photo = ?,
                            ec_name = ?, ec_contactno = ?, ec_address = ?, last_edited_by = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $visitorSourceId ?: null, $pdlId, $firstname, $middlename, $lastname, $suffix,
                        $age, $sex, $locAdd, $contactNo, $photoName,
                        $ecName, $ecContact, $ecAddress, $actor['name'], $registrationId,
                    ]);
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO visitors_registration (
                            authorized_visitor_id, pdl_id, firstname, middlename, lastname, suffix, age, sex, loc_add, contact_no,
                            visitor_photo, ec_name, ec_contactno, ec_address,
                            visitor_status, created_by, created_by_user, last_edited_by
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Incomplete Requirements', ?, ?, ?)
                    ");
                    $stmt->execute([
                        $visitorSourceId ?: null, $pdlId, $firstname, $middlename, $lastname, $suffix, $age, $sex, $locAdd, $contactNo,
                        $photoName, $ecName, $ecContact, $ecAddress,
                        $actor['name'], $actor['id'] ?: null, $actor['name'],
                    ]);
                    $registrationId = (int) $pdo->lastInsertId();
                }

                if ($visitorSourceId > 0) {
                    $statusStmt = $pdo->prepare("
                        UPDATE pdl_authorized_visitors
                        SET visitor_status = 'Incomplete Requirements', updated_by = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $statusStmt->execute([$actor['id'] ?: null, $visitorSourceId]);
                }

                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }

            log_activity(
                $existing ? 'VISITOR_REGISTRATION_UPDATE' : 'VISITOR_REGISTRATION_CREATE',
                ($existing ? 'Updated' : 'Created') . " visitor registration ID: {$registrationId} | pdl_id: {$pdlId} | visitor: {$firstname} {$lastname}"
            );
            $savedRegistration = load_registration($pdo, $registrationId);
            json_response('success', 'Registration saved successfully.', [
                'registration_id' => $registrationId,
                'progress' => registration_progress_payload($savedRegistration),
            ]);
            break;

        case 'upload_document':
            $registrationId = (int) ($_POST['registration_id'] ?? 0);
            $documentColumn = trim((string) ($_POST['document_column'] ?? ''));
            $certField = trim((string) ($_POST['certificate_number_field'] ?? ''));
            $certValue = trim((string) ($_POST['certificate_number_value'] ?? ''));
            $validIdType = trim((string) ($_POST['valid_id_type'] ?? ''));
            $reviewStatus = trim((string) ($_POST['document_review_status'] ?? ''));
            $allowedColumns = [
                'valid_id' => 'valid_id_no',
                'birth_certificate_id' => 'birth_certificate_no',
                'marriage_contract_id' => 'marriage_contract_no',
                'cohabitation_certificate_id' => 'cohabitation_certificate_no',
            ];

            if ($registrationId <= 0) {
                throw new Exception('Save visitor details first.');
            }
            if (!isset($allowedColumns[$documentColumn])) {
                throw new Exception('Invalid document type.');
            }
            if ($allowedColumns[$documentColumn] !== $certField) {
                throw new Exception('Invalid certificate number field.');
            }
            if (empty($_FILES[$documentColumn]['name'])) {
                throw new Exception('No file selected.');
            }
            if ($documentColumn === 'valid_id' && $validIdType === '') {
                throw new Exception('Please select the accepted valid ID type.');
            }
            if (!in_array($reviewStatus, ['reviewed', 'skipped'], true)) {
                throw new Exception('Please review the document or explicitly skip review before uploading.');
            }

            $registration = load_registration($pdo, $registrationId);
            $newFile = upload_single_file($_FILES[$documentColumn], ['pdf', 'jpg', 'jpeg', 'png'], 1024 * 1024, $documentColumn);
            $oldFile = trim((string) ($registration[$documentColumn] ?? ''));

            if ($oldFile !== '') {
                remove_stored_file($oldFile);
            }

            $stmt = $pdo->prepare("
                UPDATE visitors_registration
                SET {$documentColumn} = ?, {$certField} = ?, valid_id_type = CASE WHEN ? = 'valid_id' THEN ? ELSE valid_id_type END, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$newFile, $certValue, $documentColumn, $validIdType, $registrationId]);

            record_document_upload_review($pdo, $registrationId, $documentColumn, $_FILES[$documentColumn], $newFile, $reviewStatus);
            archive_record_change(
                'visitors_registration',
                $registrationId,
                'updated',
                $registration,
                load_registration($pdo, $registrationId),
                "Uploaded {$documentColumn} for registration ID {$registrationId} ({$reviewStatus})"
            );

            log_activity('VISITOR_DOCUMENT_UPLOAD', "Uploaded {$documentColumn} for registration ID: {$registrationId} | review_status: {$reviewStatus}");
            json_response('success', 'Document uploaded successfully.', [
                'document' => $documentColumn === 'valid_id'
                    ? array_merge(build_document_payload($documentColumn, $newFile, $certValue), ['id_type' => $validIdType])
                    : build_document_payload($documentColumn, $newFile, $certValue),
            ]);
            break;

        case 'remove_document':
            $registrationId = (int) ($_POST['registration_id'] ?? 0);
            $documentColumn = trim((string) ($_POST['document_column'] ?? ''));
            $allowedColumns = [
                'valid_id' => 'valid_id_no',
                'birth_certificate_id' => 'birth_certificate_no',
                'marriage_contract_id' => 'marriage_contract_no',
                'cohabitation_certificate_id' => 'cohabitation_certificate_no',
            ];

            if ($registrationId <= 0 || !isset($allowedColumns[$documentColumn])) {
                throw new Exception('Invalid document selection.');
            }

            $registration = load_registration($pdo, $registrationId);
            remove_stored_file($registration[$documentColumn] ?? null);

            $stmt = $pdo->prepare("
                UPDATE visitors_registration
                SET {$documentColumn} = NULL,
                    {$allowedColumns[$documentColumn]} = NULL,
                    valid_id_type = CASE WHEN ? = 'valid_id' THEN NULL ELSE valid_id_type END,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$documentColumn, $registrationId]);

            archive_record_change(
                'visitors_registration',
                $registrationId,
                'updated',
                $registration,
                load_registration($pdo, $registrationId),
                "Removed {$documentColumn} from registration ID {$registrationId}"
            );

            log_activity('VISITOR_DOCUMENT_REMOVE', "Removed {$documentColumn} from registration ID: {$registrationId}");
            json_response('success', 'Document removed successfully.');
            break;

        case 'final_submit':
            $registrationId = (int) ($_POST['registration_id'] ?? 0);
            if ($registrationId <= 0) {
                throw new Exception('No registration selected.');
            }

            $actor = current_actor();
            $registration = load_registration($pdo, $registrationId);
            $missingRequirements = registration_missing_requirements($pdo, $registration);
            $isComplete = registration_is_complete($pdo, $registration);
            $status = $isComplete ? 'Verified' : 'Incomplete Requirements';
            $processedBy = $isComplete ? ($actor['id'] ?: null) : null;

            $pdo->beginTransaction();

            try {
                $stmt = $pdo->prepare("
                    UPDATE visitors_registration
                    SET visitor_status = ?, processed_by_user = ?, last_edited_by = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$status, $processedBy, $actor['name'], $registrationId]);

                if ($isComplete && empty($registration['v_id'])) {
                    $genderCode = ($registration['sex'] ?? '') === 'Male' ? 'M' : 'F';
                    $visitorCode = 'V-' . $genderCode . '-' . date('Y') . '-' . str_pad((string) $registrationId, 5, '0', STR_PAD_LEFT);
                    $vStmt = $pdo->prepare("UPDATE visitors_registration SET v_id = ? WHERE id = ?");
                    $vStmt->execute([$visitorCode, $registrationId]);
                }

                if (!empty($registration['authorized_visitor_id'])) {
                    $authStmt = $pdo->prepare("
                        UPDATE pdl_authorized_visitors
                        SET visitor_status = ?, updated_by = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $authStmt->execute([
                        $status,
                        $actor['id'] ?: null,
                        (int) $registration['authorized_visitor_id'],
                    ]);
                } else {
                    $authStmt = $pdo->prepare("
                        UPDATE pdl_authorized_visitors
                        SET visitor_status = ?, updated_by = ?, updated_at = NOW()
                        WHERE pdl_id = ?
                          AND LOWER(TRIM(visitor_fname)) = LOWER(TRIM(?))
                          AND LOWER(TRIM(visitor_lname)) = LOWER(TRIM(?))
                          AND LOWER(TRIM(COALESCE(visitor_mname, ''))) = LOWER(TRIM(?))
                          AND LOWER(TRIM(COALESCE(visitor_suffix, ''))) = LOWER(TRIM(?))
                    ");
                    $authStmt->execute([
                        $status,
                        $actor['id'] ?: null,
                        (int) ($registration['pdl_id'] ?? 0),
                        trim((string) ($registration['firstname'] ?? '')),
                        trim((string) ($registration['lastname'] ?? '')),
                        trim((string) ($registration['middlename'] ?? '')),
                        trim((string) ($registration['suffix'] ?? '')),
                    ]);
                }

                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }

            log_activity('VISITOR_REGISTRATION_SUBMIT', "Submitted registration ID: {$registrationId} | status: {$status}");
            $updatedRegistration = load_registration($pdo, $registrationId);
            $message = $isComplete
                ? 'Registration completed successfully.'
                : 'This registration will be saved but it will not proceed for ID issuance since you have incomplete registration.';

            json_response('success', $message, [
                'visitor_status' => $status,
                'missing_requirements' => $missingRequirements,
                'progress' => registration_progress_payload($updatedRegistration),
            ]);
            break;

        default:
            throw new Exception('Invalid action.');
    }
} catch (Throwable $e) {
    json_response('error', $e->getMessage());
}
