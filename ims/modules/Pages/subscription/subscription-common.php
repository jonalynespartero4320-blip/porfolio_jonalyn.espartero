<?php

require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/functions.php';

function subscription_ensure_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS subscription (
            subscription_id INT AUTO_INCREMENT PRIMARY KEY,
            software_description VARCHAR(255) NOT NULL,
            purchase_date DATE NULL,
            expiry_date DATE NULL,
            end_user_id INT NULL,
            department_id INT NULL,
            is_archived TINYINT(1) NOT NULL DEFAULT 0,
            archived_at DATETIME NULL,
            created_by INT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            INDEX idx_subscription_expiry (expiry_date),
            INDEX idx_subscription_user (end_user_id),
            INDEX idx_subscription_department (department_id),
            INDEX idx_subscription_archive (is_archived)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    foreach ([
        'is_archived' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'archived_at' => 'DATETIME NULL',
        'created_by' => 'INT NULL',
        'created_at' => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP',
        'updated_at' => 'DATETIME NULL',
    ] as $column => $definition) {
        if (!db_column_exists($pdo, 'subscription', $column)) {
            $pdo->exec("ALTER TABLE subscription ADD COLUMN {$column} {$definition}");
        }
    }
}

function subscription_status(?string $expiryDate): array
{
    $expiryDate = trim((string) $expiryDate);
    if ($expiryDate === '' || $expiryDate === '0000-00-00') {
        return ['No Expiry', 'bg-secondary', null];
    }

    $today = new DateTimeImmutable('today');
    $expiry = DateTimeImmutable::createFromFormat('Y-m-d', $expiryDate) ?: new DateTimeImmutable($expiryDate);
    $days = (int) $today->diff($expiry)->format('%r%a');

    if ($days < 0) {
        return ['Expired', 'bg-danger', $days];
    }
    if ($days <= 30) {
        return ['Expiring Soon', 'bg-warning text-dark', $days];
    }
    return ['Valid', 'bg-success', $days];
}

function subscription_response(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}
