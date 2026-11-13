<?php

function srq_clean_int($value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }
    return is_numeric($value) ? (int) $value : null;
}

function srq_clean_string($value): ?string
{
    $value = trim((string) ($value ?? ''));
    return $value === '' ? null : $value;
}

function srq_ensure_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS service_request (
            id INT AUTO_INCREMENT PRIMARY KEY,
            request_type VARCHAR(80) NOT NULL,
            end_user_id INT NULL,
            department_id INT NULL,
            request_description TEXT NULL,
            complain_problem TEXT NULL,
            remarks TEXT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'Pending',
            assigned_technician VARCHAR(255) NULL,
            admin_action_reason TEXT NULL,
            cancel_reason TEXT NULL,
            cancelled_at DATETIME NULL,
            created_by INT NULL,
            is_archived TINYINT(1) NOT NULL DEFAULT 0,
            archived_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            INDEX idx_service_request_department (department_id),
            INDEX idx_service_request_created_by (created_by),
            INDEX idx_service_request_status (status),
            INDEX idx_service_request_archived (is_archived)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    foreach ([
        'request_type' => "VARCHAR(80) NOT NULL DEFAULT 'Service Request'",
        'end_user_id' => 'INT NULL',
        'department_id' => 'INT NULL',
        'request_description' => 'TEXT NULL',
        'complain_problem' => 'TEXT NULL',
        'remarks' => 'TEXT NULL',
        'status' => "VARCHAR(30) NOT NULL DEFAULT 'Pending'",
        'assigned_technician' => 'VARCHAR(255) NULL',
        'admin_action_reason' => 'TEXT NULL',
        'cancel_reason' => 'TEXT NULL',
        'cancelled_at' => 'DATETIME NULL',
        'created_by' => 'INT NULL',
        'is_archived' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'archived_at' => 'DATETIME NULL',
        'updated_at' => 'DATETIME NULL',
    ] as $column => $definition) {
        if (!db_column_exists($pdo, 'service_request', $column)) {
            $pdo->exec("ALTER TABLE service_request ADD COLUMN {$column} {$definition}");
        }
    }
}

function srq_scope_sql(PDO $pdo, string $alias, array &$params): string
{
    if (user_can_view_all_records()) {
        return '';
    }

    $parts = [];
    $userId = record_scope_current_user_id();
    if ($userId > 0) {
        $parts[] = "{$alias}.created_by = :scope_user_id";
        $params['scope_user_id'] = $userId;
    }

    $departmentIds = user_department_scope_ids();
    if (!empty($departmentIds)) {
        $scopeParams = [];
        foreach ($departmentIds as $index => $departmentId) {
            $key = 'scope_department_' . $index;
            $scopeParams[] = ':' . $key;
            $params[$key] = (int) $departmentId;
        }
        $parts[] = "{$alias}.department_id IN (" . implode(',', $scopeParams) . ')';
    }

    return $parts ? '(' . implode(' OR ', $parts) . ')' : '1 = 0';
}

function srq_user_can_access(PDO $pdo, int $id): bool
{
    if (is_admin() || can('ictd.request.manage')) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM service_request WHERE id = ?');
        $stmt->execute([$id]);
        return (int) $stmt->fetchColumn() > 0;
    }

    $params = ['id' => $id];
    $scope = srq_scope_sql($pdo, 'sr', $params);
    $sql = 'SELECT COUNT(*) FROM service_request sr WHERE sr.id = :id';
    if ($scope !== '') {
        $sql .= ' AND ' . $scope;
    }
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    return (int) $stmt->fetchColumn() > 0;
}

function srq_select_options(PDO $pdo): array
{
    $users = [];
    if (db_table_exists($pdo, 'list_end_user')) {
        $sql = "
            SELECT eu.end_user_id AS id, eu.end_user_name AS text, eu.department_id, dep.department_name
            FROM list_end_user eu
            LEFT JOIN list_department dep ON dep.department_id = eu.department_id
        ";
        $params = [];
        if (!user_can_view_all_records()) {
            $departmentIds = user_department_scope_ids();
            if (empty($departmentIds)) {
                return ['users' => [], 'technicians' => []];
            }
            $sql .= ' WHERE eu.department_id IN (' . implode(',', array_fill(0, count($departmentIds), '?')) . ')';
            $params = $departmentIds;
        }
        $sql .= ' ORDER BY eu.end_user_name ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    $technicians = [];
    if (db_table_exists($pdo, 'list_technical')) {
        $stmt = $pdo->query("
            SELECT
                lt.technical_name AS id,
                lt.technical_name AS text,
                dep.department_name AS designation
            FROM list_technical lt
            LEFT JOIN list_department dep ON dep.department_id = lt.department_id
            ORDER BY lt.technical_name ASC
        ");
        $technicians = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    return compact('users', 'technicians');
}

function srq_status_badge(string $status): string
{
    return match (strtolower($status)) {
        'acknowledged' => 'info',
        'accomplished' => 'success',
        'rejected' => 'danger',
        'cancelled' => 'dark',
        default => 'secondary',
    };
}
