<?php

require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/functions.php';

function bidding_ensure_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS bidding (
            id INT AUTO_INCREMENT PRIMARY KEY,
            date_requested DATE NOT NULL,
            fund_id INT NULL,
            department_id INT NULL,
            department_head VARCHAR(255) NULL,
            is_archived TINYINT(1) NOT NULL DEFAULT 0,
            archived_at DATETIME NULL,
            created_by INT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            INDEX idx_bidding_date (date_requested),
            INDEX idx_bidding_department (department_id),
            INDEX idx_bidding_archive (is_archived)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS bidding_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            bidding_id INT NOT NULL,
            description VARCHAR(255) NOT NULL,
            qty INT NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_bidding_items_bid (bidding_id),
            CONSTRAINT fk_bidding_items_bid FOREIGN KEY (bidding_id) REFERENCES bidding(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    foreach ([
        'is_archived' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'archived_at' => 'DATETIME NULL',
        'created_by' => 'INT NULL',
        'created_at' => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP',
        'updated_at' => 'DATETIME NULL',
    ] as $column => $definition) {
        if (!db_column_exists($pdo, 'bidding', $column)) {
            $pdo->exec("ALTER TABLE bidding ADD COLUMN {$column} {$definition}");
        }
    }
}

function bidding_response(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}
