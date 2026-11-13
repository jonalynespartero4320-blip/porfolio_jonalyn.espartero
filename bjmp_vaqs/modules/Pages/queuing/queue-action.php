<?php
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_login();
require_permission('queue.view');

header('Content-Type: application/json');

function queue_response(string $status, string $message = '', array $extra = []): void
{
    echo json_encode(array_merge([
        'status' => $status,
        'ok' => $status === 'success',
        'msg' => $message,
        'message' => $message,
    ], $extra));
    exit;
}

function queue_actor(): array
{
    $user = current_user();

    return [
        'id' => (int) ($user['id'] ?? 0),
        'name' => trim((string) ($user['username'] ?? 'system')),
    ];
}

function queue_normalize_public_path(string $path): string
{
    return ltrim(str_replace('\\', '/', trim($path)), '/');
}

function queue_public_file_url(?string $path): ?string
{
    $path = queue_normalize_public_path((string) $path);
    if ($path === '') {
        return null;
    }

    if (preg_match('/^https?:\/\//i', $path)) {
        return $path;
    }

    $segments = array_map('rawurlencode', array_filter(explode('/', $path), 'strlen'));
    return BASE_URL . '/' . implode('/', $segments);
}

function queue_default_photo_url(): string
{
    return BASE_URL . '/assets/images/demo-scene.svg';
}

function queue_qr_secret(): string
{
    return defined('QR_SECRET_KEY') ? QR_SECRET_KEY : hash('sha256', ROOT_PATH . '|bjmp_vaqs');
}

function queue_build_name(array $row, string $prefix = ''): string
{
    $parts = [
        trim((string) ($row[$prefix . 'firstname'] ?? $row[$prefix . 'fname'] ?? '')),
        trim((string) ($row[$prefix . 'middlename'] ?? $row[$prefix . 'mname'] ?? '')),
        trim((string) ($row[$prefix . 'lastname'] ?? $row[$prefix . 'lname'] ?? '')),
        trim((string) ($row[$prefix . 'suffix'] ?? '')),
    ];

    return preg_replace('/\s+/', ' ', trim(implode(' ', array_filter($parts, 'strlen'))));
}

function queue_public_display_name(?string $name): string
{
    $name = preg_replace('/\s+/', ' ', trim((string) $name));
    if ($name === '') {
        return 'Visitor';
    }

    $parts = explode(' ', $name);
    if (count($parts) === 1) {
        return $parts[0];
    }

    $suffixes = ['jr', 'jr.', 'sr', 'sr.', 'ii', 'iii', 'iv', 'v'];
    $lastInitial = '';
    for ($i = count($parts) - 1; $i >= 0; $i--) {
        $candidate = strtolower(trim($parts[$i], " \t\n\r\0\x0B,."));
        if ($candidate !== '' && !in_array($candidate, $suffixes, true)) {
            $lastInitial = strtoupper(substr($parts[$i], 0, 1)) . '.';
            break;
        }
    }

    return trim($parts[0] . ' ' . $lastInitial);
}

function queue_dorm_display_sql(string $alias = 'p'): string
{
    return "COALESCE(
        (
            SELECT COALESCE(NULLIF(TRIM(dm.description), ''), NULLIF(TRIM(dm.dorm_no), ''))
            FROM dorm_masterlist dm
            WHERE TRIM(CAST(dm.id AS CHAR)) = TRIM({$alias}.dorm_no)
               OR TRIM(dm.dorm_no) = TRIM({$alias}.dorm_no)
            LIMIT 1
        ),
        TRIM({$alias}.dorm_no)
    )";
}

function queue_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ");
    $stmt->execute([$table, $column]);

    return (int) $stmt->fetchColumn() > 0;
}

function queue_column_nullable(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare("
        SELECT IS_NULLABLE
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
        LIMIT 1
    ");
    $stmt->execute([$table, $column]);

    return strtoupper((string) $stmt->fetchColumn()) === 'YES';
}

function queue_index_exists(PDO $pdo, string $table, string $index): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND INDEX_NAME = ?
    ");
    $stmt->execute([$table, $index]);

    return (int) $stmt->fetchColumn() > 0;
}

function queue_ensure_special_case_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS queue_special_cases (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            firstname VARCHAR(150) NOT NULL,
            middlename VARCHAR(150) DEFAULT NULL,
            lastname VARCHAR(150) NOT NULL,
            age INT(11) DEFAULT NULL,
            gender ENUM('Male','Female','Other') NOT NULL,
            pdl_id INT(11) NOT NULL,
            pdl_name VARCHAR(255) NOT NULL,
            dorm_no VARCHAR(100) DEFAULT NULL,
            address TEXT NOT NULL,
            id_type VARCHAR(100) DEFAULT NULL,
            id_number VARCHAR(150) DEFAULT NULL,
            reason_for_visit TEXT NOT NULL,
            approved_by VARCHAR(150) NOT NULL,
            approved_date DATE NOT NULL,
            status ENUM('approved','queued','inside','completed','rejected') NOT NULL DEFAULT 'approved',
            created_by INT(11) DEFAULT NULL,
            queue_request_id INT(11) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_pdl_id (pdl_id),
            INDEX idx_approved_date (approved_date),
            INDEX idx_queue_request_id (queue_request_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    if (!queue_column_nullable($pdo, 'queuing_requests', 'visitor_id')) {
        $pdo->exec("ALTER TABLE queuing_requests MODIFY visitor_id INT(11) NULL");
    }

    if (!queue_column_exists($pdo, 'queuing_requests', 'special_case_id')) {
        $pdo->exec("ALTER TABLE queuing_requests ADD COLUMN special_case_id INT(11) DEFAULT NULL AFTER visitor_id");
    }
    if (!queue_index_exists($pdo, 'queuing_requests', 'idx_special_case_id')) {
        $pdo->exec("ALTER TABLE queuing_requests ADD INDEX idx_special_case_id (special_case_id)");
    }

    if (!queue_column_nullable($pdo, 'queue_display', 'visitor_id')) {
        $pdo->exec("ALTER TABLE queue_display MODIFY visitor_id INT(11) NULL");
    }

    if (!queue_column_exists($pdo, 'queue_display', 'special_case_id')) {
        $pdo->exec("ALTER TABLE queue_display ADD COLUMN special_case_id INT(11) DEFAULT NULL AFTER visitor_id");
    }
    if (!queue_index_exists($pdo, 'queue_display', 'idx_display_special_case_id')) {
        $pdo->exec("ALTER TABLE queue_display ADD INDEX idx_display_special_case_id (special_case_id)");
    }
}

function queue_load_registration(PDO $pdo, int $registrationId): array
{
    $dormDisplaySql = queue_dorm_display_sql('p');

    $stmt = $pdo->prepare("
        SELECT
            vr.*,
            p.pdl_id AS pdl_code,
            p.pdl_fname,
            p.pdl_mname,
            p.pdl_lname,
            p.pdl_suffix,
            p.dorm_no,
            $dormDisplaySql AS dorm_description,
            p.case_type,
            p.court
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

function queue_visitor_payload(array $registration): array
{
    $photo = trim((string) ($registration['visitor_photo'] ?? ''));
    $photoUrl = $photo !== ''
        ? (str_contains($photo, '/')
            ? queue_public_file_url($photo)
            : queue_public_file_url('demo-uploads/visitors/' . $photo))
        : queue_default_photo_url();

    return [
        'id' => (int) ($registration['id'] ?? 0),
        'v_id' => trim((string) ($registration['v_id'] ?? '')),
        'qr_value' => trim((string) ($registration['qr_token'] ?? $registration['v_id'] ?? '')),
        'firstname' => trim((string) ($registration['firstname'] ?? '')),
        'middlename' => trim((string) ($registration['middlename'] ?? '')),
        'lastname' => trim((string) ($registration['lastname'] ?? '')),
        'suffix' => trim((string) ($registration['suffix'] ?? '')),
        'pdl_name' => queue_build_name([
            'fname' => $registration['pdl_fname'] ?? '',
            'mname' => $registration['pdl_mname'] ?? '',
            'lname' => $registration['pdl_lname'] ?? '',
            'suffix' => $registration['pdl_suffix'] ?? '',
        ]),
        'pdl_id' => (int) ($registration['pdl_id'] ?? 0),
        'dorm_no' => trim((string) ($registration['dorm_description'] ?? $registration['dorm_no'] ?? '')),
        'visitor_photo' => $photoUrl,
        'visitor_status' => trim((string) ($registration['visitor_status'] ?? '')),
        'is_banned' => (int) ($registration['is_banned'] ?? 0),
    ];
}

function queue_parse_qr_candidates(string $qrData): array
{
    $qrData = trim($qrData);
    $candidates = [];

    if ($qrData !== '') {
        $candidates[] = $qrData;
    }

    $decoded = json_decode($qrData, true);
    if (is_array($decoded)) {
        foreach (['v_id', 'qr_token', 'visitor_id', 'registration_id', 'id'] as $key) {
            if (!empty($decoded[$key])) {
                $candidates[] = (string) $decoded[$key];
            }
        }
    }

    if (filter_var($qrData, FILTER_VALIDATE_URL)) {
        $query = parse_url($qrData, PHP_URL_QUERY);
        if (is_string($query)) {
            parse_str($query, $params);
            foreach (['v_id', 'qr_token', 'visitor_id', 'registration_id', 'id'] as $key) {
                if (!empty($params[$key])) {
                    $candidates[] = (string) $params[$key];
                }
            }
        }
    }

    return array_values(array_unique(array_filter(array_map('trim', $candidates), 'strlen')));
}

function queue_find_visitor_by_qr(PDO $pdo, string $qrData): array
{
    $dormDisplaySql = queue_dorm_display_sql('p');
    $compactParts = explode('.', trim($qrData));
    if (count($compactParts) === 4 && ctype_digit($compactParts[0]) && ctype_digit($compactParts[2])) {
        [$visitorIdPart, $shortToken, $expiresTimestamp, $signature] = $compactParts;
        $dataString = $visitorIdPart . '.' . $shortToken . '.' . $expiresTimestamp;
        $expectedSignature = substr(hash_hmac('sha256', $dataString, queue_qr_secret()), 0, 32);

        if (hash_equals($expectedSignature, $signature)) {
            if ((int) $expiresTimestamp < time()) {
                throw new Exception('This visitor ID QR has expired.');
            }

            $stmt = $pdo->prepare("
                SELECT
                    vr.*,
                    p.pdl_id AS pdl_code,
                    p.pdl_fname,
                    p.pdl_mname,
                    p.pdl_lname,
                    p.pdl_suffix,
                    p.dorm_no,
                    $dormDisplaySql AS dorm_description,
                    p.case_type,
                    p.court
                FROM visitors_registration vr
                LEFT JOIN pdl_info p ON p.id = vr.pdl_id
                WHERE vr.id = ?
                LIMIT 1
            ");
            $stmt->execute([(int) $visitorIdPart]);
            $registration = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($registration && str_starts_with(trim((string) ($registration['qr_token'] ?? '')), $shortToken)) {
                return $registration;
            }
        }
    }

    $candidates = queue_parse_qr_candidates($qrData);
    if (empty($candidates)) {
        throw new Exception('QR data is empty.');
    }

    foreach ($candidates as $candidate) {
        if (ctype_digit($candidate)) {
            $stmt = $pdo->prepare("
                SELECT
                    vr.*,
                    p.pdl_id AS pdl_code,
                    p.pdl_fname,
                    p.pdl_mname,
                    p.pdl_lname,
                    p.pdl_suffix,
                    p.dorm_no,
                    $dormDisplaySql AS dorm_description,
                    p.case_type,
                    p.court
                FROM visitors_registration vr
                LEFT JOIN pdl_info p ON p.id = vr.pdl_id
                WHERE vr.id = ?
                LIMIT 1
            ");
            $stmt->execute([(int) $candidate]);
        } else {
            $stmt = $pdo->prepare("
                SELECT
                    vr.*,
                    p.pdl_id AS pdl_code,
                    p.pdl_fname,
                    p.pdl_mname,
                    p.pdl_lname,
                    p.pdl_suffix,
                    p.dorm_no,
                    $dormDisplaySql AS dorm_description,
                    p.case_type,
                    p.court
                FROM visitors_registration vr
                LEFT JOIN pdl_info p ON p.id = vr.pdl_id
                WHERE vr.v_id = ?
                   OR vr.qr_token = ?
                LIMIT 1
            ");
            $stmt->execute([$candidate, $candidate]);
        }

        $registration = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($registration) {
            return $registration;
        }
    }

    throw new Exception('No visitor matched the scanned QR.');
}

function queue_assert_verified(array $registration): void
{
    if (strcasecmp(trim((string) ($registration['visitor_status'] ?? '')), 'Verified') !== 0) {
        throw new Exception('Only verified visitors can be queued.');
    }

    if ((int) ($registration['is_banned'] ?? 0) === 1) {
        throw new Exception('This visitor is currently banned and cannot enter the queue.');
    }
}

function queue_active_request(PDO $pdo, int $visitorId): ?array
{
    $stmt = $pdo->prepare("
        SELECT *
        FROM queuing_requests
        WHERE visitor_id = ?
          AND status IN ('pending', 'accepted', 'inside')
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$visitorId]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function queue_active_special_case_request(PDO $pdo, int $specialCaseId): ?array
{
    $stmt = $pdo->prepare("
        SELECT *
        FROM queuing_requests
        WHERE special_case_id = ?
          AND status IN ('pending', 'accepted', 'inside')
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$specialCaseId]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function queue_has_visit_today(PDO $pdo, int $visitorId): bool
{
    $stmt = $pdo->prepare("
        SELECT 1
        FROM queuing_requests
        WHERE visitor_id = ?
          AND DATE(request_time) = CURDATE()
          AND status IN ('pending', 'inside', 'completed')
        LIMIT 1
    ");
    $stmt->execute([$visitorId]);

    return (bool) $stmt->fetchColumn();
}

function queue_has_special_case_today(PDO $pdo, string $firstname, string $lastname, int $pdlId): bool
{
    $stmt = $pdo->prepare("
        SELECT 1
        FROM queue_special_cases
        WHERE LOWER(TRIM(firstname)) = LOWER(TRIM(?))
          AND LOWER(TRIM(lastname)) = LOWER(TRIM(?))
          AND pdl_id = ?
          AND DATE(created_at) = CURDATE()
          AND status IN ('approved', 'queued', 'inside', 'completed')
        LIMIT 1
    ");
    $stmt->execute([$firstname, $lastname, $pdlId]);

    return (bool) $stmt->fetchColumn();
}

function queue_inside_count(PDO $pdo): int
{
    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM queuing_requests
        WHERE status = 'inside'
    ");

    return (int) $stmt->fetchColumn();
}

function queue_format_duration($hoursStayed): ?string
{
    if ($hoursStayed === null || $hoursStayed === '') {
        return null;
    }

    $minutes = (int) round(((float) $hoursStayed) * 60);
    if ($minutes <= 0) {
        return '0 mins';
    }

    $hours = intdiv($minutes, 60);
    $remainingMinutes = $minutes % 60;
    $parts = [];

    if ($hours > 0) {
        $parts[] = $hours . ' hr' . ($hours === 1 ? '' : 's');
    }

    if ($remainingMinutes > 0) {
        $parts[] = $remainingMinutes . ' min' . ($remainingMinutes === 1 ? '' : 's');
    }

    return implode(' ', $parts);
}

function queue_next_waiting_position(PDO $pdo): int
{
    $stmt = $pdo->query("SELECT COALESCE(MAX(position), 0) + 1 AS next_position FROM queue_display WHERE status = 'waiting'");
    return (int) ($stmt->fetch(PDO::FETCH_ASSOC)['next_position'] ?? 1);
}

function queue_reindex_waiting(PDO $pdo): void
{
    $stmt = $pdo->query("SELECT id FROM queue_display WHERE status = 'waiting' ORDER BY position ASC, display_time ASC, id ASC");
    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $update = $pdo->prepare("UPDATE queue_display SET position = ? WHERE id = ?");
    $position = 1;
    foreach ($rows as $id) {
        $update->execute([$position, $id]);
        $position++;
    }
}

function queue_latest_display(PDO $pdo, int $visitorId, array $statuses): ?array
{
    $placeholders = implode(',', array_fill(0, count($statuses), '?'));
    $params = array_merge([$visitorId], $statuses);
    $stmt = $pdo->prepare("
        SELECT *
        FROM queue_display
        WHERE visitor_id = ?
          AND status IN ($placeholders)
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute($params);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function queue_latest_special_display(PDO $pdo, int $specialCaseId, array $statuses): ?array
{
    $placeholders = implode(',', array_fill(0, count($statuses), '?'));
    $params = array_merge([$specialCaseId], $statuses);
    $stmt = $pdo->prepare("
        SELECT *
        FROM queue_display
        WHERE special_case_id = ?
          AND status IN ($placeholders)
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute($params);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function queue_board_data(PDO $pdo): array
{
    $insideStmt = $pdo->query("
        SELECT *
        FROM queue_display
        WHERE status = 'inside'
          AND DATE(COALESCE(time_in, display_time)) = CURDATE()
        ORDER BY COALESCE(time_in, display_time) DESC, id DESC
    ");
    $insideList = $insideStmt->fetchAll(PDO::FETCH_ASSOC);
    $inside = $insideList[0] ?? null;

    $waitingStmt = $pdo->query("
        SELECT *
        FROM queue_display
        WHERE status = 'waiting'
          AND DATE(display_time) = CURDATE()
        ORDER BY position ASC, display_time ASC, id ASC
    ");
    $waiting = $waitingStmt->fetchAll(PDO::FETCH_ASSOC);

    $doneStmt = $pdo->query("
        SELECT *
        FROM queue_display
        WHERE status = 'done'
          AND DATE(COALESCE(time_out, display_time)) = CURDATE()
        ORDER BY COALESCE(time_out, display_time) DESC, id DESC
        LIMIT 8
    ");
    $done = $doneStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($done as &$doneRow) {
        $doneRow['hours_stayed_label'] = queue_format_duration($doneRow['hours_stayed'] ?? null) ?? '-';
    }
    unset($doneRow);

    $stats = $pdo->query("
        SELECT
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
            SUM(CASE WHEN status = 'inside' THEN 1 ELSE 0 END) AS inside_count,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_count,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected_count,
            AVG(CASE
                WHEN status IN ('inside', 'completed')
                 AND accepted_time IS NOT NULL
                 AND request_time IS NOT NULL
                THEN TIMESTAMPDIFF(MINUTE, request_time, accepted_time)
            END) AS avg_wait_minutes
        FROM queuing_requests
        WHERE DATE(request_time) = CURDATE()
    ")->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'inside' => $inside,
        'inside_list' => $insideList,
        'waiting' => $waiting,
        'done' => $done,
        'stats' => [
            'waiting_count' => count($waiting),
            'inside_count' => (int) ($stats['inside_count'] ?? 0),
            'inside_capacity' => 10,
            'inside_available_slots' => max(0, 10 - (int) ($stats['inside_count'] ?? 0)),
            'completed_count' => (int) ($stats['completed_count'] ?? 0),
            'rejected_count' => (int) ($stats['rejected_count'] ?? 0),
            'pending_count' => (int) ($stats['pending_count'] ?? 0),
            'avg_wait_minutes' => $stats['avg_wait_minutes'] !== null ? round((float) $stats['avg_wait_minutes'], 1) : null,
        ],
    ];
}

function queue_public_board_data(PDO $pdo): array
{
    $insideStmt = $pdo->query("
        SELECT id, display_name, display_time, time_in, position
        FROM queue_display
        WHERE status = 'inside'
          AND DATE(COALESCE(time_in, display_time)) = CURDATE()
        ORDER BY COALESCE(time_in, display_time) ASC, id ASC
    ");
    $insideList = $insideStmt->fetchAll(PDO::FETCH_ASSOC);

    $waitingStmt = $pdo->query("
        SELECT id, display_name, display_time, position
        FROM queue_display
        WHERE status = 'waiting'
          AND DATE(display_time) = CURDATE()
        ORDER BY position ASC, display_time ASC, id ASC
    ");
    $waiting = $waitingStmt->fetchAll(PDO::FETCH_ASSOC);

    $publicInside = array_map(static function (array $row): array {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'display_name' => queue_public_display_name($row['display_name'] ?? ''),
            'display_time' => $row['display_time'] ?? null,
            'time_in' => $row['time_in'] ?? null,
            'position' => $row['position'] !== null ? (int) $row['position'] : null,
        ];
    }, $insideList);

    $publicWaiting = array_map(static function (array $row): array {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'display_name' => queue_public_display_name($row['display_name'] ?? ''),
            'display_time' => $row['display_time'] ?? null,
            'position' => $row['position'] !== null ? (int) $row['position'] : null,
        ];
    }, $waiting);

    return [
        'inside_list' => $publicInside,
        'waiting' => $publicWaiting,
        'stats' => [
            'waiting_count' => count($publicWaiting),
            'inside_count' => count($publicInside),
        ],
    ];
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    queue_ensure_special_case_schema($pdo);

    switch ($action) {
        case 'get_visitor':
            require_permission('queue.view');
            $visitorId = (int) ($_GET['visitor_id'] ?? $_POST['visitor_id'] ?? 0);
            if ($visitorId <= 0) {
                throw new Exception('Invalid visitor selected.');
            }

            $registration = queue_load_registration($pdo, $visitorId);
            queue_assert_verified($registration);

            log_activity('QUEUE_VISITOR_LOAD', "Loaded verified visitor ID: {$visitorId}");
            queue_response('success', '', ['visitor' => queue_visitor_payload($registration)]);
            break;

        case 'validateQR':
            require_permission('queue.view');
            $qrData = trim((string) ($_POST['qr_data'] ?? ''));
            if ($qrData === '') {
                throw new Exception('QR data is required.');
            }

            $registration = queue_find_visitor_by_qr($pdo, $qrData);
            queue_assert_verified($registration);

            log_activity('QUEUE_QR_SCAN', "Validated QR for visitor ID: " . (int) ($registration['id'] ?? 0));
            queue_response('success', 'Visitor verified successfully.', ['visitor' => queue_visitor_payload($registration)]);
            break;

        case 'addToQueue':
            require_permission('queue.create');
            $visitorId = (int) ($_POST['visitor_id'] ?? 0);
            $idType = trim((string) ($_POST['id_type'] ?? ''));
            $idNumber = trim((string) ($_POST['id_number'] ?? ''));

            if ($visitorId <= 0) {
                throw new Exception('Invalid visitor selected.');
            }

            $registration = queue_load_registration($pdo, $visitorId);
            queue_assert_verified($registration);

            if (queue_active_request($pdo, $visitorId)) {
                throw new Exception('This visitor already has an active queue request.');
            }

            if (queue_has_visit_today($pdo, $visitorId)) {
                throw new Exception('This visitor has already used their one allowed visit for today.');
            }

            $displayName = queue_build_name($registration);
            $actor = queue_actor();
            $pdo->beginTransaction();

            try {
                $requestStmt = $pdo->prepare("
                    INSERT INTO queuing_requests (
                        visitor_id, status, pdl_name, dorm_no, id_type, id_number, request_time
                    ) VALUES (?, 'pending', ?, ?, ?, ?, NOW())
                ");
                $requestStmt->execute([
                    $visitorId,
                    queue_build_name([
                        'fname' => $registration['pdl_fname'] ?? '',
                        'mname' => $registration['pdl_mname'] ?? '',
                        'lname' => $registration['pdl_lname'] ?? '',
                        'suffix' => $registration['pdl_suffix'] ?? '',
                    ]),
                    trim((string) ($registration['dorm_description'] ?? $registration['dorm_no'] ?? '')),
                    $idType,
                    $idNumber,
                ]);

                $displayStmt = $pdo->prepare("
                    INSERT INTO queue_display (
                        visitor_id, display_name, position, status, display_time
                    ) VALUES (?, ?, ?, 'waiting', NOW())
                ");
                $displayStmt->execute([
                    $visitorId,
                    $displayName,
                    queue_next_waiting_position($pdo),
                ]);

                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }

            log_activity('QUEUE_ADD', "Queued visitor ID: {$visitorId} | actor: {$actor['name']}");
            queue_response('success', 'Visitor added to queue successfully.');
            break;

        case 'addSpecialCase':
            require_permission('queue.create');

            $firstname = trim((string) ($_POST['firstname'] ?? ''));
            $middlename = trim((string) ($_POST['middlename'] ?? ''));
            $lastname = trim((string) ($_POST['lastname'] ?? ''));
            $age = (int) ($_POST['age'] ?? 0);
            $gender = trim((string) ($_POST['gender'] ?? ''));
            $pdlId = (int) ($_POST['pdl_id'] ?? 0);
            $address = trim((string) ($_POST['address'] ?? ''));
            $idType = trim((string) ($_POST['id_type'] ?? ''));
            $idNumber = trim((string) ($_POST['id_number'] ?? ''));
            $reasonForVisit = trim((string) ($_POST['reason_for_visit'] ?? ''));
            $approvedBy = trim((string) ($_POST['approved_by'] ?? ''));
            $approvedDate = trim((string) ($_POST['approved_date'] ?? date('Y-m-d')));

            if ($firstname === '' || $lastname === '') {
                throw new Exception('First name and last name are required.');
            }
            if ($age <= 0) {
                throw new Exception('A valid age is required.');
            }
            if (!in_array($gender, ['Male', 'Female', 'Other'], true)) {
                throw new Exception('A valid gender is required.');
            }
            if ($pdlId <= 0) {
                throw new Exception('Please select the Resident to be visited.');
            }
            if ($address === '') {
                throw new Exception('Address is required.');
            }
            if ($reasonForVisit === '') {
                throw new Exception('Reason for visit is required.');
            }
            if ($approvedBy === '') {
                throw new Exception('Approved by is required.');
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $approvedDate)) {
                throw new Exception('Approved date is invalid.');
            }

            if (queue_has_special_case_today($pdo, $firstname, $lastname, $pdlId)) {
                throw new Exception('This special-case visitor already has an approved/queued visit for this Resident today.');
            }

            $dormDisplaySql = queue_dorm_display_sql('p');
            $pdlStmt = $pdo->prepare("
                SELECT
                    p.id,
                    p.pdl_id,
                    p.pdl_fname,
                    p.pdl_mname,
                    p.pdl_lname,
                    p.pdl_suffix,
                    $dormDisplaySql AS dorm_description
                FROM pdl_info p
                WHERE p.id = ?
                LIMIT 1
            ");
            $pdlStmt->execute([$pdlId]);
            $pdl = $pdlStmt->fetch(PDO::FETCH_ASSOC);
            if (!$pdl) {
                throw new Exception('Selected Resident was not found.');
            }

            $pdlName = queue_build_name([
                'fname' => $pdl['pdl_fname'] ?? '',
                'mname' => $pdl['pdl_mname'] ?? '',
                'lname' => $pdl['pdl_lname'] ?? '',
                'suffix' => $pdl['pdl_suffix'] ?? '',
            ]);
            $displayName = queue_build_name([
                'firstname' => $firstname,
                'middlename' => $middlename,
                'lastname' => $lastname,
            ]);
            $actor = queue_actor();

            $pdo->beginTransaction();

            try {
                $specialStmt = $pdo->prepare("
                    INSERT INTO queue_special_cases (
                        firstname, middlename, lastname, age, gender, pdl_id, pdl_name, dorm_no,
                        address, id_type, id_number, reason_for_visit, approved_by, approved_date,
                        status, created_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'queued', ?)
                ");
                $specialStmt->execute([
                    $firstname,
                    $middlename ?: null,
                    $lastname,
                    $age,
                    $gender,
                    $pdlId,
                    $pdlName,
                    trim((string) ($pdl['dorm_description'] ?? '')),
                    $address,
                    $idType ?: null,
                    $idNumber ?: null,
                    $reasonForVisit,
                    $approvedBy,
                    $approvedDate,
                    $actor['id'] ?: null,
                ]);

                $specialCaseId = (int) $pdo->lastInsertId();

                $requestStmt = $pdo->prepare("
                    INSERT INTO queuing_requests (
                        visitor_id, special_case_id, status, pdl_name, dorm_no, id_type, id_number, request_time
                    ) VALUES (NULL, ?, 'pending', ?, ?, ?, ?, NOW())
                ");
                $requestStmt->execute([
                    $specialCaseId,
                    $pdlName,
                    trim((string) ($pdl['dorm_description'] ?? '')),
                    $idType,
                    $idNumber,
                ]);

                $queueRequestId = (int) $pdo->lastInsertId();

                $displayStmt = $pdo->prepare("
                    INSERT INTO queue_display (
                        visitor_id, special_case_id, display_name, position, status, display_time
                    ) VALUES (NULL, ?, ?, ?, 'waiting', NOW())
                ");
                $displayStmt->execute([
                    $specialCaseId,
                    $displayName . ' (Special Case)',
                    queue_next_waiting_position($pdo),
                ]);

                $pdo->prepare("
                    UPDATE queue_special_cases
                    SET queue_request_id = ?
                    WHERE id = ?
                ")->execute([$queueRequestId, $specialCaseId]);

                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }

            log_activity('QUEUE_SPECIAL_CASE_ADD', "Queued special-case visitor: {$displayName} | Resident: {$pdlName} | approved_by: {$approvedBy} | approved_date: {$approvedDate}");
            queue_response('success', 'Special-case visitor added to queue successfully.');
            break;

        case 'listRequests':
            require_permission('queue.view');
            $stmt = $pdo->query("
                SELECT
                    qr.*,
                    COALESCE(vr.firstname, qsc.firstname) AS firstname,
                    COALESCE(vr.middlename, qsc.middlename) AS middlename,
                    COALESCE(vr.lastname, qsc.lastname) AS lastname,
                    vr.suffix,
                    qsc.reason_for_visit,
                    qsc.approved_by,
                    qsc.approved_date,
                    qd.status AS display_status,
                    qd.time_in,
                    qd.time_out,
                    qd.hours_stayed
                FROM queuing_requests qr
                LEFT JOIN visitors_registration vr ON vr.id = qr.visitor_id
                LEFT JOIN queue_special_cases qsc ON qsc.id = qr.special_case_id
                LEFT JOIN queue_display qd ON qd.id = (
                    SELECT qd2.id
                    FROM queue_display qd2
                    WHERE (
                        (qr.visitor_id IS NOT NULL AND qd2.visitor_id = qr.visitor_id)
                        OR (qr.special_case_id IS NOT NULL AND qd2.special_case_id = qr.special_case_id)
                    )
                    ORDER BY qd2.id DESC
                    LIMIT 1
                )
                WHERE DATE(qr.request_time) = CURDATE()
                ORDER BY
                    CASE
                        WHEN qr.status = 'inside' THEN 1
                        WHEN qr.status = 'pending' THEN 2
                        WHEN qr.status = 'accepted' THEN 3
                        WHEN qr.status = 'rejected' THEN 4
                        ELSE 5
                    END,
                    qr.request_time DESC,
                    qr.id DESC
            ");

            $rows = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $status = trim((string) ($row['status'] ?? ''));
                if ($status === 'completed') {
                    $status = 'done';
                }

                $rows[] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'visitor_id' => (int) ($row['visitor_id'] ?? 0),
                    'special_case_id' => (int) ($row['special_case_id'] ?? 0),
                    'is_special_case' => !empty($row['special_case_id']),
                    'firstname' => trim((string) ($row['firstname'] ?? '')),
                    'middlename' => trim((string) ($row['middlename'] ?? '')),
                    'lastname' => trim((string) ($row['lastname'] ?? '')),
                    'suffix' => trim((string) ($row['suffix'] ?? '')),
                    'pdl_name' => trim((string) ($row['pdl_name'] ?? '')),
                    'dorm_no' => trim((string) ($row['dorm_no'] ?? '')),
                    'id_type' => trim((string) ($row['id_type'] ?? '')),
                    'id_number' => trim((string) ($row['id_number'] ?? '')),
                    'reason_for_visit' => trim((string) ($row['reason_for_visit'] ?? '')),
                    'approved_by' => trim((string) ($row['approved_by'] ?? '')),
                    'approved_date' => $row['approved_date'] ?? null,
                    'status' => $status,
                    'time_in' => $row['time_in'] ?? null,
                    'time_out' => $row['time_out'] ?? null,
                    'hours_stayed' => $row['hours_stayed'] ?? null,
                    'hours_stayed_label' => queue_format_duration($row['hours_stayed'] ?? null) ?? '-',
                    'request_time' => $row['request_time'] ?? null,
                ];
            }

            $activeCount = 0;
            foreach ($rows as $row) {
                if (in_array($row['status'], ['pending', 'inside', 'accepted'], true)) {
                    $activeCount++;
                }
            }

            queue_response('success', '', [
                'rows' => $rows,
                'active_count' => $activeCount,
            ]);
            break;

        case 'acceptQueue':
            require_permission('queue.edit');
            $requestId = (int) ($_POST['id'] ?? 0);
            if ($requestId <= 0) {
                throw new Exception('Invalid queue request selected.');
            }

            $requestStmt = $pdo->prepare("SELECT * FROM queuing_requests WHERE id = ? LIMIT 1");
            $requestStmt->execute([$requestId]);
            $request = $requestStmt->fetch(PDO::FETCH_ASSOC);

            if (!$request) {
                throw new Exception('Queue request not found.');
            }
            if (trim((string) ($request['status'] ?? '')) !== 'pending') {
                throw new Exception('Only pending requests can be accepted.');
            }

            $actor = queue_actor();
            $pdo->beginTransaction();

            try {
                if (queue_inside_count($pdo) >= 10) {
                    throw new Exception('Inside capacity is full. Only 10 visitors can be inside at the same time.');
                }

                $updateRequest = $pdo->prepare("
                    UPDATE queuing_requests
                    SET status = 'inside',
                        accepted_by = ?,
                        accepted_time = NOW()
                    WHERE id = ?
                ");
                $updateRequest->execute([$actor['id'] ?: null, $requestId]);

                $display = queue_latest_display($pdo, (int) $request['visitor_id'], ['waiting', 'inside']);
                if (!$display && !empty($request['special_case_id'])) {
                    $display = queue_latest_special_display($pdo, (int) $request['special_case_id'], ['waiting', 'inside']);
                }
                if ($display) {
                    $updateDisplay = $pdo->prepare("
                        UPDATE queue_display
                        SET status = 'inside',
                            position = 0,
                            time_in = COALESCE(time_in, NOW())
                        WHERE id = ?
                    ");
                    $updateDisplay->execute([(int) $display['id']]);
                }

                if (!empty($request['special_case_id'])) {
                    $pdo->prepare("
                        UPDATE queue_special_cases
                        SET status = 'inside'
                        WHERE id = ?
                    ")->execute([(int) $request['special_case_id']]);
                }

                queue_reindex_waiting($pdo);
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }

            log_activity('QUEUE_ACCEPT', "Accepted queue request ID: {$requestId}");
            queue_response('success', 'Visitor allowed inside successfully.');
            break;

        case 'rejectQueue':
            require_permission('queue.edit');
            $requestId = (int) ($_POST['id'] ?? 0);
            if ($requestId <= 0) {
                throw new Exception('Invalid queue request selected.');
            }

            $requestStmt = $pdo->prepare("SELECT * FROM queuing_requests WHERE id = ? LIMIT 1");
            $requestStmt->execute([$requestId]);
            $request = $requestStmt->fetch(PDO::FETCH_ASSOC);

            if (!$request) {
                throw new Exception('Queue request not found.');
            }
            if (trim((string) ($request['status'] ?? '')) !== 'pending') {
                throw new Exception('Only pending requests can be rejected.');
            }

            $actor = queue_actor();
            $pdo->beginTransaction();

            try {
                $updateRequest = $pdo->prepare("
                    UPDATE queuing_requests
                    SET status = 'rejected',
                        rejected_by = ?,
                        rejected_time = NOW()
                    WHERE id = ?
                ");
                $updateRequest->execute([$actor['id'] ?: null, $requestId]);

                $display = queue_latest_display($pdo, (int) $request['visitor_id'], ['waiting']);
                if (!$display && !empty($request['special_case_id'])) {
                    $display = queue_latest_special_display($pdo, (int) $request['special_case_id'], ['waiting']);
                }
                if ($display) {
                    $deleteDisplay = $pdo->prepare("DELETE FROM queue_display WHERE id = ?");
                    $deleteDisplay->execute([(int) $display['id']]);
                }

                if (!empty($request['special_case_id'])) {
                    $pdo->prepare("
                        UPDATE queue_special_cases
                        SET status = 'rejected'
                        WHERE id = ?
                    ")->execute([(int) $request['special_case_id']]);
                }

                queue_reindex_waiting($pdo);
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }

            log_activity('QUEUE_REJECT', "Rejected queue request ID: {$requestId}");
            queue_response('success', 'Queue request rejected successfully.');
            break;

        case 'timeOut':
            require_permission('queue.edit');
            $visitorId = (int) ($_POST['visitor_id'] ?? 0);
            if ($visitorId <= 0) {
                throw new Exception('Invalid visitor selected.');
            }

            $requestStmt = $pdo->prepare("
                SELECT *
                FROM queuing_requests
                WHERE visitor_id = ?
                  AND status = 'inside'
                ORDER BY id DESC
                LIMIT 1
            ");
            $requestStmt->execute([$visitorId]);
            $request = $requestStmt->fetch(PDO::FETCH_ASSOC);

            if (!$request) {
                throw new Exception('No active inside visitor found for time-out.');
            }

            $pdo->beginTransaction();

            try {
                $updateRequest = $pdo->prepare("UPDATE queuing_requests SET status = 'completed' WHERE id = ?");
                $updateRequest->execute([(int) $request['id']]);

                $display = queue_latest_display($pdo, $visitorId, ['inside', 'waiting']);
                if ($display) {
                    $displayId = (int) $display['id'];
                    $updateDisplay = $pdo->prepare("
                        UPDATE queue_display
                        SET status = 'done',
                            position = NULL,
                            time_out = NOW(),
                            hours_stayed = ROUND(TIMESTAMPDIFF(MINUTE, COALESCE(time_in, display_time), NOW()) / 60, 2)
                        WHERE id = ?
                    ");
                    $updateDisplay->execute([$displayId]);
                }

                queue_reindex_waiting($pdo);
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }

            log_activity('QUEUE_TIMEOUT', "Timed out visitor ID: {$visitorId}");
            queue_response('success', 'Visitor timed out successfully.');
            break;

        case 'timeOutRequest':
            require_permission('queue.edit');
            $requestId = (int) ($_POST['request_id'] ?? 0);
            if ($requestId <= 0) {
                throw new Exception('Invalid queue request selected.');
            }

            $requestStmt = $pdo->prepare("
                SELECT *
                FROM queuing_requests
                WHERE id = ?
                  AND status = 'inside'
                LIMIT 1
            ");
            $requestStmt->execute([$requestId]);
            $request = $requestStmt->fetch(PDO::FETCH_ASSOC);

            if (!$request) {
                throw new Exception('No active inside queue request found for time-out.');
            }

            $pdo->beginTransaction();

            try {
                $updateRequest = $pdo->prepare("UPDATE queuing_requests SET status = 'completed' WHERE id = ?");
                $updateRequest->execute([$requestId]);

                $display = null;
                if (!empty($request['special_case_id'])) {
                    $display = queue_latest_special_display($pdo, (int) $request['special_case_id'], ['inside', 'waiting']);
                } elseif (!empty($request['visitor_id'])) {
                    $display = queue_latest_display($pdo, (int) $request['visitor_id'], ['inside', 'waiting']);
                }

                if ($display) {
                    $displayId = (int) $display['id'];
                    $updateDisplay = $pdo->prepare("
                        UPDATE queue_display
                        SET status = 'done',
                            position = NULL,
                            time_out = NOW(),
                            hours_stayed = ROUND(TIMESTAMPDIFF(MINUTE, COALESCE(time_in, display_time), NOW()) / 60, 2)
                        WHERE id = ?
                    ");
                    $updateDisplay->execute([$displayId]);
                }

                if (!empty($request['special_case_id'])) {
                    $pdo->prepare("
                        UPDATE queue_special_cases
                        SET status = 'completed'
                        WHERE id = ?
                    ")->execute([(int) $request['special_case_id']]);
                }

                queue_reindex_waiting($pdo);
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }

            log_activity('QUEUE_TIMEOUT', "Timed out queue request ID: {$requestId}");
            queue_response('success', 'Visitor timed out successfully.');
            break;

        case 'board_data':
            require_permission('queue.view');
            queue_response('success', '', queue_board_data($pdo));
            break;

        case 'public_board_data':
            require_permission('queue.view');
            queue_response('success', '', queue_public_board_data($pdo));
            break;

        default:
            throw new Exception('Invalid action.');
    }
} catch (Throwable $e) {
    queue_response('error', $e->getMessage());
}
