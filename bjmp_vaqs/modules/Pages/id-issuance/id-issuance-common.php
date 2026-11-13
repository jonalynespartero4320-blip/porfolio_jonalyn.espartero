<?php

function issuance_public_file_url(?string $path): ?string
{
    $path = ltrim(str_replace('\\', '/', trim((string) $path)), '/');
    if ($path === '') {
        return null;
    }

    $segments = array_map('rawurlencode', array_filter(explode('/', $path), 'strlen'));
    return BASE_URL . '/' . implode('/', $segments);
}

function issuance_default_photo_url(): string
{
    return BASE_URL . '/assets/images/demo-scene.svg';
}

function issuance_full_name(array $row, string $prefix = ''): string
{
    $parts = [
        trim((string) ($row[$prefix . 'firstname'] ?? $row[$prefix . 'fname'] ?? '')),
        trim((string) ($row[$prefix . 'middlename'] ?? $row[$prefix . 'mname'] ?? '')),
        trim((string) ($row[$prefix . 'lastname'] ?? $row[$prefix . 'lname'] ?? '')),
        trim((string) ($row[$prefix . 'suffix'] ?? '')),
    ];

    return preg_replace('/\s+/', ' ', trim(implode(' ', array_filter($parts, 'strlen'))));
}

function issuance_middle_initial(?string $middlename): string
{
    $middlename = trim((string) $middlename);
    return $middlename !== '' ? strtoupper(substr($middlename, 0, 1)) . '.' : '';
}

function issuance_qr_secret(): string
{
    return defined('QR_SECRET_KEY') ? QR_SECRET_KEY : hash('sha256', ROOT_PATH . '|bjmp_vaqs');
}

function issuance_ensure_history_table(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS visitor_id_issuance_history (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            visitor_id INT(11) NOT NULL,
            issue_mode ENUM('issue', 'reissue') NOT NULL DEFAULT 'issue',
            v_id VARCHAR(50) DEFAULT NULL,
            qr_token VARCHAR(64) DEFAULT NULL,
            issued_by_user INT(11) DEFAULT NULL,
            issued_by_name VARCHAR(100) DEFAULT NULL,
            notes VARCHAR(255) DEFAULT NULL,
            issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_visitor_id (visitor_id),
            INDEX idx_issued_at (issued_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $existing = [];
    foreach ($pdo->query("SHOW COLUMNS FROM visitor_id_issuance_history") as $column) {
        $existing[$column['Field']] = true;
    }

    $requiredColumns = [
        'issue_mode' => "ALTER TABLE visitor_id_issuance_history ADD COLUMN issue_mode ENUM('issue', 'reissue') NOT NULL DEFAULT 'issue' AFTER visitor_id",
        'v_id' => "ALTER TABLE visitor_id_issuance_history ADD COLUMN v_id VARCHAR(50) DEFAULT NULL AFTER issue_mode",
        'qr_token' => "ALTER TABLE visitor_id_issuance_history ADD COLUMN qr_token VARCHAR(64) DEFAULT NULL AFTER v_id",
        'issued_by_user' => "ALTER TABLE visitor_id_issuance_history ADD COLUMN issued_by_user INT(11) DEFAULT NULL AFTER qr_token",
        'issued_by_name' => "ALTER TABLE visitor_id_issuance_history ADD COLUMN issued_by_name VARCHAR(100) DEFAULT NULL AFTER issued_by_user",
        'notes' => "ALTER TABLE visitor_id_issuance_history ADD COLUMN notes VARCHAR(255) DEFAULT NULL AFTER issued_by_name",
        'issued_at' => "ALTER TABLE visitor_id_issuance_history ADD COLUMN issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER notes",
    ];

    foreach ($requiredColumns as $column => $sql) {
        if (!isset($existing[$column])) {
            $pdo->exec($sql);
        }
    }

    $indexes = [];
    foreach ($pdo->query("SHOW INDEX FROM visitor_id_issuance_history") as $indexRow) {
        $indexes[$indexRow['Key_name']] = true;
    }

    if (!isset($indexes['idx_visitor_id'])) {
        $pdo->exec("ALTER TABLE visitor_id_issuance_history ADD INDEX idx_visitor_id (visitor_id)");
    }

    if (!isset($indexes['idx_issued_at'])) {
        $pdo->exec("ALTER TABLE visitor_id_issuance_history ADD INDEX idx_issued_at (issued_at)");
    }
}

function issuance_load_visitor(PDO $pdo, int $visitorId): array
{
    $stmt = $pdo->prepare("
        SELECT
            vr.*,
            p.pdl_id AS pdl_code,
            p.pdl_fname,
            p.pdl_mname,
            p.pdl_lname,
            p.pdl_suffix,
            p.dorm_no,
            p.case_no,
            p.case_type,
            p.court
        FROM visitors_registration vr
        LEFT JOIN pdl_info p ON p.id = vr.pdl_id
        WHERE vr.id = ?
        LIMIT 1
    ");
    $stmt->execute([$visitorId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new Exception('Visitor registration not found.');
    }

    return $row;
}

function issuance_assert_verified(array $visitor): void
{
    if (strcasecmp(trim((string) ($visitor['visitor_status'] ?? '')), 'Verified') !== 0) {
        throw new Exception('Only verified visitors can be issued an ID.');
    }

    if ((int) ($visitor['is_banned'] ?? 0) === 1) {
        throw new Exception('Banned visitors cannot be issued an ID.');
    }
}

function issuance_generate_vid(array $visitor, int $visitorId): string
{
    $prefix = strtoupper(substr(trim((string) ($visitor['sex'] ?? 'F')), 0, 1));
    if ($prefix !== 'M') {
        $prefix = 'F';
    }

    return sprintf('V-%s-%s-%05d', $prefix, date('Y'), $visitorId);
}

function issuance_compact_payload(int $visitorId, string $qrToken, string $issuedAt): array
{
    $expiresTimestamp = strtotime($issuedAt . ' +3 years');
    $shortToken = substr($qrToken, 0, 32);
    $dataString = $visitorId . '.' . $shortToken . '.' . $expiresTimestamp;
    $signature = substr(hash_hmac('sha256', $dataString, issuance_qr_secret()), 0, 32);

    return [
        'payload' => $dataString . '.' . $signature,
        'expires_timestamp' => $expiresTimestamp,
        'signature' => $signature,
        'short_token' => $shortToken,
    ];
}

function issuance_process(PDO $pdo, int $visitorId, string $mode, array $actor): array
{
    issuance_ensure_history_table($pdo);

    $visitor = issuance_load_visitor($pdo, $visitorId);
    issuance_assert_verified($visitor);

    $pdo->beginTransaction();

    try {
        $vId = trim((string) ($visitor['v_id'] ?? ''));
        if ($vId === '') {
            $vId = issuance_generate_vid($visitor, $visitorId);
        }

        $historyStmt = $pdo->prepare("SELECT COUNT(*) FROM visitor_id_issuance_history WHERE visitor_id = ?");
        $historyStmt->execute([$visitorId]);
        $hasIssuanceHistory = (int) $historyStmt->fetchColumn() > 0;

        $issuedAt = trim((string) ($visitor['issued_at'] ?? ''));
        if ($issuedAt === '' || $mode === 'reissue' || !$hasIssuanceHistory) {
            $issuedAt = date('Y-m-d H:i:s');
        }

        $qrToken = trim((string) ($visitor['qr_token'] ?? ''));
        if ($qrToken === '' || $mode === 'reissue') {
            $qrToken = bin2hex(random_bytes(32));
        }

        $update = $pdo->prepare("
            UPDATE visitors_registration
            SET v_id = ?,
                issued_at = ?,
                qr_token = ?,
                qr_last_generated = NOW(),
                updated_at = NOW()
            WHERE id = ?
        ");
        $update->execute([$vId, $issuedAt, $qrToken, $visitorId]);

        $log = $pdo->prepare("
            INSERT INTO visitor_id_issuance_history (
                visitor_id, issue_mode, v_id, qr_token, issued_by_user, issued_by_name, notes, issued_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $log->execute([
            $visitorId,
            $mode === 'reissue' ? 'reissue' : 'issue',
            $vId,
            $qrToken,
            $actor['id'] ?: null,
            $actor['name'] ?: 'system',
            $mode === 'reissue' ? 'ID reissued by the authorized personnel' : 'ID issued by the authorized personnel',
            $issuedAt,
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return issuance_load_visitor($pdo, $visitorId);
}

function issuance_history(PDO $pdo, int $visitorId): array
{
    issuance_ensure_history_table($pdo);

    $stmt = $pdo->prepare("
        SELECT *
        FROM visitor_id_issuance_history
        WHERE visitor_id = ?
        ORDER BY id DESC
    ");
    $stmt->execute([$visitorId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function issuance_card_payload(array $visitor): array
{
    $visitorId = (int) ($visitor['id'] ?? 0);
    $issuedAt = trim((string) ($visitor['issued_at'] ?? ''));
    $issuedDate = $issuedAt !== '' ? new DateTime($issuedAt) : new DateTime();
    $expiryDate = (clone $issuedDate)->modify('+3 years');

    $photo = trim((string) ($visitor['visitor_photo'] ?? ''));
    $photoUrl = $photo !== ''
        ? (str_contains($photo, '/')
            ? issuance_public_file_url($photo)
            : issuance_public_file_url('demo-uploads/visitors/' . $photo))
        : issuance_default_photo_url();

    $compact = issuance_compact_payload($visitorId, trim((string) ($visitor['qr_token'] ?? '')), $issuedDate->format('Y-m-d H:i:s'));

    return [
        'visitor_id' => $visitorId,
        'v_id' => trim((string) ($visitor['v_id'] ?? '')),
        'issued_at' => $issuedDate->format('Y-m-d H:i:s'),
        'date_issued' => $issuedDate->format('m-d-Y'),
        'date_expired' => $expiryDate->format('m-d-Y'),
        'visitor_name' => issuance_full_name($visitor),
        'visitor_name_front' => strtoupper(trim(($visitor['firstname'] ?? '') . ' ' . issuance_middle_initial($visitor['middlename'] ?? '') . ' ' . ($visitor['lastname'] ?? ''))),
        'pdl_name' => issuance_full_name([
            'fname' => $visitor['pdl_fname'] ?? '',
            'mname' => $visitor['pdl_mname'] ?? '',
            'lname' => $visitor['pdl_lname'] ?? '',
            'suffix' => $visitor['pdl_suffix'] ?? '',
        ]),
        'dorm_no' => trim((string) ($visitor['dorm_no'] ?? '')),
        'photo_url' => $photoUrl,
        'qr_payload' => $compact['payload'],
        'emergency_name' => strtoupper(trim((string) ($visitor['ec_name'] ?? ''))),
        'emergency_contact' => trim((string) ($visitor['ec_contactno'] ?? '')),
        'emergency_address' => strtoupper(trim((string) ($visitor['ec_address'] ?? ''))),
    ];
}
