<?php

require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/functions.php';

function borrowed_ensure_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS borrowed (
            id INT AUTO_INCREMENT PRIMARY KEY,
            end_user_id INT NULL,
            department_id INT NULL,
            item_borrowed VARCHAR(255) NOT NULL,
            serialno VARCHAR(100) NULL,
            date_borrowed DATE NOT NULL,
            date_returned DATE NULL,
            is_archived TINYINT(1) NOT NULL DEFAULT 0,
            archived_at DATETIME NULL,
            created_by INT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            INDEX idx_borrowed_user (end_user_id),
            INDEX idx_borrowed_department (department_id),
            INDEX idx_borrowed_archive (is_archived),
            INDEX idx_borrowed_dates (date_borrowed, date_returned)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    foreach ([
        'is_archived' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'archived_at' => 'DATETIME NULL',
        'created_by' => 'INT NULL',
        'created_at' => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP',
        'updated_at' => 'DATETIME NULL',
    ] as $column => $definition) {
        if (!db_column_exists($pdo, 'borrowed', $column)) {
            $pdo->exec("ALTER TABLE borrowed ADD COLUMN {$column} {$definition}");
        }
    }
}

function borrowed_status($dateReturned): array
{
    $dateReturned = trim((string) ($dateReturned ?? ''));
    if ($dateReturned !== '' && $dateReturned !== '0000-00-00') {
        return ['Returned', 'bg-success'];
    }
    return ['Not Yet Returned', 'bg-danger'];
}

function borrowed_response(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}
