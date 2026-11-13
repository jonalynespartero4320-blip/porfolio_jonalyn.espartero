<?php
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/functions.php';

require_login();
require_permission('logs.delete');

header('Content-Type: application/json');

ensure_activity_logs_schema($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'ok' => false,
        'message' => 'Invalid request method.',
    ]);
    exit;
}

$mode = trim((string) ($_POST['mode'] ?? ''));
$date = trim((string) ($_POST['date'] ?? ''));
$dateFrom = trim((string) ($_POST['date_from'] ?? ''));
$dateTo = trim((string) ($_POST['date_to'] ?? ''));

$isValidDate = static function (string $value): bool {
    if ($value === '') {
        return false;
    }

    $dt = DateTime::createFromFormat('Y-m-d', $value);
    return $dt instanceof DateTime && $dt->format('Y-m-d') === $value;
};

try {
    $whereSql = '';
    $params = [];
    $label = '';

    if ($mode === 'today') {
        $whereSql = 'DATE(created_at) = CURDATE()';
        $label = 'today';
    } elseif ($mode === 'date') {
        if (!$isValidDate($date)) {
            throw new RuntimeException('Please choose a valid date to delete.');
        }

        $whereSql = 'DATE(created_at) = ?';
        $params[] = $date;
        $label = $date;
    } elseif ($mode === 'range') {
        if (!$isValidDate($dateFrom) || !$isValidDate($dateTo)) {
            throw new RuntimeException('Please choose a valid start and end date.');
        }

        if ($dateFrom > $dateTo) {
            throw new RuntimeException('Date range is invalid. Start date must be before end date.');
        }

        $whereSql = 'DATE(created_at) BETWEEN ? AND ?';
        $params[] = $dateFrom;
        $params[] = $dateTo;
        $label = $dateFrom . ' to ' . $dateTo;
    } else {
        throw new RuntimeException('Unknown log deletion mode.');
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs WHERE {$whereSql}");
    $countStmt->execute($params);
    $count = (int) $countStmt->fetchColumn();

    if ($count < 1) {
        echo json_encode([
            'ok' => false,
            'message' => 'No logs matched the selected date filter.',
        ]);
        exit;
    }

    $deleteStmt = $pdo->prepare("DELETE FROM activity_logs WHERE {$whereSql}");
    $deleteStmt->execute($params);

    log_activity('LOGS_DELETE', "Deleted {$count} log(s) for {$label}");

    echo json_encode([
        'ok' => true,
        'message' => "Deleted {$count} log(s) for {$label}.",
        'deleted' => $count,
    ]);
} catch (Throwable $e) {
    http_response_code(422);
    echo json_encode([
        'ok' => false,
        'message' => $e->getMessage(),
    ]);
}
