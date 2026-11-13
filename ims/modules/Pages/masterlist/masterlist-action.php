<?php
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_login();
require_permission('ictd.masterlist.view');
header('Content-Type: application/json');

function ml_response(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function ml_payload(): array
{
    $raw = file_get_contents('php://input');
    $json = json_decode($raw ?: '', true);
    return is_array($json) ? $json : $_POST;
}

function ml_configs(): array
{
    $text = static fn(string $name, string $label, bool $required = true): array => [
        'name' => $name,
        'label' => $label,
        'type' => 'text',
        'required' => $required,
    ];
    $select = static fn(string $name, string $label, string $source, bool $required = false): array => [
        'name' => $name,
        'label' => $label,
        'type' => 'select',
        'source' => $source,
        'required' => $required,
    ];

    return [
        'equipment-status' => [
            'title' => 'Equipment Status',
            'subtitle' => 'Status values shared by equipment and supplies.',
            'icon' => 'bi-ui-checks-grid',
            'table' => 'list_equipment_status',
            'pk' => 'id',
            'label' => 'equipment_status',
            'create' => "CREATE TABLE IF NOT EXISTS list_equipment_status (id INT AUTO_INCREMENT PRIMARY KEY, equipment_status VARCHAR(255) NOT NULL UNIQUE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            'fields' => [$text('equipment_status', 'Status Name')],
        ],
        'descriptions' => [
            'title' => 'Descriptions',
            'subtitle' => 'Equipment and supplies description options.',
            'icon' => 'bi-card-list',
            'table' => 'list_description',
            'pk' => 'id',
            'label' => 'description_name',
            'create' => "CREATE TABLE IF NOT EXISTS list_description (id INT AUTO_INCREMENT PRIMARY KEY, description_name VARCHAR(255) NOT NULL UNIQUE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            'fields' => [$text('description_name', 'Description')],
        ],
        'brands' => [
            'title' => 'Brands',
            'subtitle' => 'Brand names used by equipment and supplies.',
            'icon' => 'bi-tags-fill',
            'table' => 'list_brand',
            'pk' => 'id',
            'label' => 'brand_name',
            'create' => "CREATE TABLE IF NOT EXISTS list_brand (id INT AUTO_INCREMENT PRIMARY KEY, brand_name VARCHAR(255) NOT NULL UNIQUE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            'fields' => [$text('brand_name', 'Brand Name')],
        ],
        'suppliers' => [
            'title' => 'Suppliers',
            'subtitle' => 'Supplier names used during equipment batch entry.',
            'icon' => 'bi-truck',
            'table' => 'list_suppliers',
            'pk' => 'id',
            'label' => 'supplier_name',
            'create' => "CREATE TABLE IF NOT EXISTS list_suppliers (id INT AUTO_INCREMENT PRIMARY KEY, supplier_name VARCHAR(255) NOT NULL UNIQUE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            'fields' => [$text('supplier_name', 'Supplier Name')],
        ],
        'departments' => [
            'title' => 'Departments',
            'subtitle' => 'Departments used by assignment, maintenance, service, and transmittal records.',
            'icon' => 'bi-building',
            'table' => 'list_department',
            'pk' => 'department_id',
            'label' => 'department_name',
            'create' => "CREATE TABLE IF NOT EXISTS list_department (department_id INT AUTO_INCREMENT PRIMARY KEY, department_name VARCHAR(255) NOT NULL, department_head VARCHAR(255) NULL, department_office VARCHAR(255) NULL, department_division VARCHAR(255) NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            'fields' => [
                $text('department_name', 'Department Name'),
                $text('department_head', 'Department Head', false),
                $text('department_office', 'Office', false),
                $text('department_division', 'Division', false),
            ],
        ],
        'end-users' => [
            'title' => 'End Users',
            'subtitle' => 'People available for assignment, service reports, and maintenance reports.',
            'icon' => 'bi-person-vcard-fill',
            'table' => 'list_end_user',
            'pk' => 'end_user_id',
            'label' => 'end_user_name',
            'create' => "CREATE TABLE IF NOT EXISTS list_end_user (end_user_id INT AUTO_INCREMENT PRIMARY KEY, end_user_name VARCHAR(255) NOT NULL, department_id INT NULL, position_id INT NULL, empstat_id INT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            'fields' => [
                $text('end_user_name', 'End User Name'),
                $select('department_id', 'Department', 'departments', true),
                $select('position_id', 'Position', 'positions', false),
                $select('empstat_id', 'Employee Status', 'employee-statuses', false),
            ],
        ],
        'positions' => [
            'title' => 'Positions',
            'subtitle' => 'Position titles used by end user records.',
            'icon' => 'bi-person-badge',
            'table' => 'list_position',
            'pk' => 'id',
            'label' => 'position_name',
            'create' => "CREATE TABLE IF NOT EXISTS list_position (id INT AUTO_INCREMENT PRIMARY KEY, position_name VARCHAR(255) NOT NULL UNIQUE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            'fields' => [$text('position_name', 'Position Name')],
        ],
        'employee-status' => [
            'title' => 'Employee Status',
            'subtitle' => 'Employment status values attached to end users.',
            'icon' => 'bi-person-check',
            'table' => 'list_empstat',
            'pk' => 'id',
            'label' => 'empstat',
            'create' => "CREATE TABLE IF NOT EXISTS list_empstat (id INT AUTO_INCREMENT PRIMARY KEY, empstat VARCHAR(100) NOT NULL UNIQUE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            'fields' => [$text('empstat', 'Employee Status')],
        ],
        'funds' => [
            'title' => 'Funds',
            'subtitle' => 'Funding source options for inventory records.',
            'icon' => 'bi-cash-coin',
            'table' => 'list_funds',
            'pk' => 'id',
            'label' => 'fund_name',
            'create' => "CREATE TABLE IF NOT EXISTS list_funds (id INT AUTO_INCREMENT PRIMARY KEY, fund_name VARCHAR(100) NOT NULL UNIQUE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            'fields' => [$text('fund_name', 'Fund Name')],
        ],
        'technical' => [
            'title' => 'Technical',
            'subtitle' => 'Technicians available for service and maintenance reports.',
            'icon' => 'bi-tools',
            'table' => 'list_technical',
            'pk' => 'technical_id',
            'label' => 'technical_name',
            'create' => "CREATE TABLE IF NOT EXISTS list_technical (technical_id INT AUTO_INCREMENT PRIMARY KEY, technical_name VARCHAR(255) NOT NULL, department_id INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            'fields' => [
                $text('technical_name', 'Technician Name'),
                $select('department_id', 'Department / Designation', 'departments', true),
            ],
        ],
        'complain' => [
            'title' => 'Complain',
            'subtitle' => 'Complaint options used by service reports.',
            'icon' => 'bi-chat-square-text',
            'table' => 'complain_list',
            'pk' => 'id',
            'label' => 'complain_desc',
            'create' => "CREATE TABLE IF NOT EXISTS complain_list (id INT AUTO_INCREMENT PRIMARY KEY, complain_desc VARCHAR(255) NOT NULL UNIQUE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            'fields' => [$text('complain_desc', 'Complaint')],
        ],
        'troubleshoot' => [
            'title' => 'Troubleshoot',
            'subtitle' => 'Troubleshooting actions used by service reports.',
            'icon' => 'bi-wrench-adjustable',
            'table' => 'troubleshoot_list',
            'pk' => 'id',
            'label' => 'troubleshoot_desc',
            'create' => "CREATE TABLE IF NOT EXISTS troubleshoot_list (id INT AUTO_INCREMENT PRIMARY KEY, troubleshoot_desc VARCHAR(255) NOT NULL UNIQUE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            'fields' => [$text('troubleshoot_desc', 'Troubleshoot Description')],
        ],
        'service' => [
            'title' => 'Service',
            'subtitle' => 'Service detail / remarks options used by service reports.',
            'icon' => 'bi-clipboard2-check',
            'table' => 'service_list',
            'pk' => 'id',
            'label' => 'service_desc',
            'create' => "CREATE TABLE IF NOT EXISTS service_list (id INT AUTO_INCREMENT PRIMARY KEY, service_desc VARCHAR(255) NOT NULL UNIQUE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            'fields' => [$text('service_desc', 'Service Description')],
        ],
    ];
}

function ml_config(string $tab): array
{
    $configs = ml_configs();
    if (!isset($configs[$tab])) {
        ml_response(['success' => false, 'message' => 'Unknown masterlist tab.'], 404);
    }
    return $configs[$tab];
}

function ml_safe_ident(string $identifier): string
{
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function ml_ensure(PDO $pdo, array $config): void
{
    $pdo->exec($config['create']);
}

function ml_options(PDO $pdo, string $source): array
{
    $sources = [
        'departments' => ['table' => 'list_department', 'pk' => 'department_id', 'label' => 'department_name'],
        'positions' => ['table' => 'list_position', 'pk' => 'id', 'label' => 'position_name'],
        'employee-statuses' => ['table' => 'list_empstat', 'pk' => 'id', 'label' => 'empstat'],
    ];
    if (!isset($sources[$source])) {
        return [];
    }
    $meta = $sources[$source];
    if (!db_table_exists($pdo, $meta['table'])) {
        return [];
    }
    $sql = 'SELECT ' . ml_safe_ident($meta['pk']) . ' AS id, ' . ml_safe_ident($meta['label']) . ' AS text FROM ' . ml_safe_ident($meta['table']) . ' ORDER BY ' . ml_safe_ident($meta['label']) . ' ASC';
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function ml_config_for_client(PDO $pdo, array $config): array
{
    $client = [
        'title' => $config['title'],
        'subtitle' => $config['subtitle'],
        'icon' => $config['icon'],
        'pk' => $config['pk'],
        'label' => $config['label'],
        'fields' => $config['fields'],
        'options' => [],
    ];
    foreach ($config['fields'] as $field) {
        if (($field['type'] ?? '') === 'select') {
            $client['options'][$field['source']] = ml_options($pdo, $field['source']);
        }
    }
    return $client;
}

function ml_data_response(PDO $pdo, array $config): void
{
    $draw = (int) ($_REQUEST['draw'] ?? 1);
    $start = max(0, (int) ($_REQUEST['start'] ?? 0));
    $length = (int) ($_REQUEST['length'] ?? 10);
    if ($length <= 0 || $length > 100) {
        $length = 10;
    }

    $search = trim((string) ($_REQUEST['search']['value'] ?? ''));
    $orderColumnIndex = (int) ($_REQUEST['order'][0]['column'] ?? 1);
    $orderDir = strtolower((string) ($_REQUEST['order'][0]['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
    $fields = array_column($config['fields'], 'name');
    $columns = array_merge([$config['pk']], $fields);
    $orderableColumns = array_merge([1 => $config['pk']], array_combine(range(2, count($fields) + 1), $fields) ?: []);
    $orderColumn = $orderableColumns[$orderColumnIndex] ?? $config['label'];

    $where = [];
    $params = [];
    if ($search !== '') {
        $likes = [];
        foreach ($columns as $column) {
            $likes[] = 'CAST(' . ml_safe_ident($column) . ' AS CHAR) LIKE :search';
        }
        $where[] = '(' . implode(' OR ', $likes) . ')';
        $params['search'] = '%' . $search . '%';
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $total = (int) $pdo->query('SELECT COUNT(*) FROM ' . ml_safe_ident($config['table']))->fetchColumn();

    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM ' . ml_safe_ident($config['table']) . ' ' . $whereSql);
    foreach ($params as $key => $value) {
        $countStmt->bindValue(':' . $key, $value);
    }
    $countStmt->execute();
    $filtered = (int) $countStmt->fetchColumn();

    $select = implode(', ', array_map('ml_safe_ident', $columns));
    $stmt = $pdo->prepare(
        'SELECT ' . $select .
        ' FROM ' . ml_safe_ident($config['table']) . ' ' .
        $whereSql .
        ' ORDER BY ' . ml_safe_ident($orderColumn) . ' ' . $orderDir .
        ' LIMIT :start, :length'
    );
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->bindValue(':start', $start, PDO::PARAM_INT);
    $stmt->bindValue(':length', $length, PDO::PARAM_INT);
    $stmt->execute();

    ml_response([
        'draw' => $draw,
        'recordsTotal' => $total,
        'recordsFiltered' => $filtered,
        'data' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
    ]);
}

function ml_clean_value(array $field, array $payload)
{
    $name = $field['name'];
    $value = trim((string) ($payload[$name] ?? ''));
    if (($field['required'] ?? false) && $value === '') {
        ml_response(['success' => false, 'message' => $field['label'] . ' is required.'], 422);
    }
    if (($field['type'] ?? '') === 'select') {
        return $value === '' ? null : (int) $value;
    }
    return $value === '' ? null : $value;
}

$payload = ml_payload();
$action = (string) ($_GET['action'] ?? $payload['action'] ?? 'list');
$tab = (string) ($_GET['tab'] ?? $payload['tab'] ?? 'equipment-status');
$config = ml_config($tab);
ml_ensure($pdo, $config);

try {
    if ($action === 'tabs') {
        $tabs = [];
        foreach (ml_configs() as $slug => $item) {
            ml_ensure($pdo, $item);
            $tabs[] = [
                'slug' => $slug,
                'title' => $item['title'],
                'subtitle' => $item['subtitle'],
                'icon' => $item['icon'],
            ];
        }
        ml_response(['success' => true, 'tabs' => $tabs]);
    }

    if ($action === 'list') {
        ml_response([
            'success' => true,
            'config' => ml_config_for_client($pdo, $config),
        ]);
    }

    if ($action === 'data') {
        ml_data_response($pdo, $config);
    }

    if ($action === 'save') {
        $id = trim((string) ($payload['id'] ?? ''));
        $data = [];
        foreach ($config['fields'] as $field) {
            $data[$field['name']] = ml_clean_value($field, $payload);
        }

        if ($id !== '') {
            $sets = implode(', ', array_map(static fn($column): string => ml_safe_ident($column) . ' = ?', array_keys($data)));
            $stmt = $pdo->prepare('UPDATE ' . ml_safe_ident($config['table']) . ' SET ' . $sets . ' WHERE ' . ml_safe_ident($config['pk']) . ' = ?');
            $stmt->execute([...array_values($data), $id]);
            log_activity('MASTERLIST_UPDATE', 'Updated ' . $config['title'] . ' #' . $id);
            ml_response(['success' => true, 'message' => $config['title'] . ' updated.']);
        }

        $columns = array_keys($data);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $stmt = $pdo->prepare('INSERT INTO ' . ml_safe_ident($config['table']) . ' (' . implode(', ', array_map('ml_safe_ident', $columns)) . ') VALUES (' . $placeholders . ')');
        $stmt->execute(array_values($data));
        log_activity('MASTERLIST_CREATE', 'Added ' . $config['title'] . ' #' . $pdo->lastInsertId());
        ml_response(['success' => true, 'message' => $config['title'] . ' added.']);
    }

    if ($action === 'delete') {
        $id = trim((string) ($payload['id'] ?? ''));
        if ($id === '') {
            ml_response(['success' => false, 'message' => 'Invalid record ID.'], 422);
        }
        $stmt = $pdo->prepare('DELETE FROM ' . ml_safe_ident($config['table']) . ' WHERE ' . ml_safe_ident($config['pk']) . ' = ?');
        $stmt->execute([$id]);
        log_activity('MASTERLIST_DELETE', 'Deleted ' . $config['title'] . ' #' . $id);
        ml_response(['success' => true, 'message' => $config['title'] . ' deleted.']);
    }

    ml_response(['success' => false, 'message' => 'Unsupported action.'], 400);
} catch (Throwable $e) {
    ml_response(['success' => false, 'message' => $e->getMessage()], 500);
}
