<?php
$page_title = 'Request Modules';
require_once ROOT_PATH . '/modules/layout/header.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/functions.php';
require_once ROOT_PATH . '/modules/Pages/request-service/service-request-common.php';

require_login();
require_permission('ictd.request.view');

function request_modules_ensure_equipment_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS equipment_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            control_no VARCHAR(50) NULL,
            date_requested DATE NOT NULL,
            fund_id INT NULL,
            department_id INT NULL,
            department_head VARCHAR(255) NULL,
            budget_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            quarter VARCHAR(20) NULL,
            budget_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
            status VARCHAR(30) NOT NULL DEFAULT 'Pending',
            cancel_reason TEXT NULL,
            cancelled_at DATETIME NULL,
            created_by INT NULL,
            is_archived TINYINT(1) NOT NULL DEFAULT 0,
            archived_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    foreach ([
        'control_no' => 'VARCHAR(50) NULL',
        'budget_amount' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
        'total_amount' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
        'quarter' => 'VARCHAR(20) NULL',
        'budget_balance' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
        'status' => "VARCHAR(30) NOT NULL DEFAULT 'Pending'",
        'cancel_reason' => 'TEXT NULL',
        'cancelled_at' => 'DATETIME NULL',
        'created_by' => 'INT NULL',
        'is_archived' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'archived_at' => 'DATETIME NULL',
        'updated_at' => 'DATETIME NULL',
    ] as $column => $definition) {
        if (!db_column_exists($pdo, 'equipment_requests', $column)) {
            $pdo->exec("ALTER TABLE equipment_requests ADD COLUMN {$column} {$definition}");
        }
    }
}

request_modules_ensure_equipment_schema($pdo);
srq_ensure_schema($pdo);

function request_module_scope_sql(string $alias, array &$params): string
{
    if (user_can_view_all_records() || can('ictd.request.manage')) {
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
        $placeholders = [];
        foreach ($departmentIds as $index => $departmentId) {
            $key = 'scope_department_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = (int) $departmentId;
        }
        $parts[] = "{$alias}.department_id IN (" . implode(',', $placeholders) . ')';
    }

    return $parts ? '(' . implode(' OR ', $parts) . ')' : '1 = 0';
}

function request_module_count(PDO $pdo, string $table, ?string $status = null): int
{
    if (!db_table_exists($pdo, $table)) {
        return 0;
    }

    $alias = 'r';
    $params = [];
    $where = ['COALESCE(r.is_archived, 0) = 0'];
    $scope = request_module_scope_sql($alias, $params);
    if ($scope !== '') {
        $where[] = $scope;
    }
    if ($status !== null) {
        $where[] = 'LOWER(r.status) = :status';
        $params['status'] = strtolower($status);
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} {$alias} WHERE " . implode(' AND ', $where));
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    return (int) $stmt->fetchColumn();
}

$equipmentCounts = [
    'total' => request_module_count($pdo, 'equipment_requests'),
    'pending' => request_module_count($pdo, 'equipment_requests', 'Pending'),
    'approved' => request_module_count($pdo, 'equipment_requests', 'Approved'),
    'rejected' => request_module_count($pdo, 'equipment_requests', 'Rejected'),
    'cancelled' => request_module_count($pdo, 'equipment_requests', 'Cancelled'),
];

$serviceCounts = [
    'total' => request_module_count($pdo, 'service_request'),
    'pending' => request_module_count($pdo, 'service_request', 'Pending'),
    'acknowledged' => request_module_count($pdo, 'service_request', 'Acknowledged'),
    'accomplished' => request_module_count($pdo, 'service_request', 'Accomplished'),
    'rejected' => request_module_count($pdo, 'service_request', 'Rejected'),
    'cancelled' => request_module_count($pdo, 'service_request', 'Cancelled'),
];

$modules = [
    [
        'title' => 'Equipment Requests',
        'subtitle' => 'Requests for equipment and supplies, including item approval and rejection reasons.',
        'icon' => 'bi-hdd-stack-fill',
        'counts' => $equipmentCounts,
        'primary' => ['label' => 'Open Requests', 'href' => BASE_URL . '/ictd/requests/equipment', 'icon' => 'bi-box-arrow-up-right'],
        'links' => array_filter([
            ['label' => 'New / My Requests', 'href' => BASE_URL . '/ictd/requests/equipment', 'icon' => 'bi-plus-circle'],
            can('ictd.request.manage') ? ['label' => 'Manage Status', 'href' => BASE_URL . '/ictd/requests/equipment-status', 'icon' => 'bi-list-check'] : null,
            ['label' => 'Archive', 'href' => BASE_URL . '/ictd/requests/equipment/archive', 'icon' => 'bi-archive'],
        ]),
    ],
    [
        'title' => 'Service Requests',
        'subtitle' => 'Requests for service support, assignment, technician action, cancellation, and status tracking.',
        'icon' => 'bi-tools',
        'counts' => $serviceCounts,
        'primary' => ['label' => 'Open Requests', 'href' => BASE_URL . '/ictd/requests/service', 'icon' => 'bi-box-arrow-up-right'],
        'links' => array_filter([
            ['label' => 'New / My Requests', 'href' => BASE_URL . '/ictd/requests/service', 'icon' => 'bi-plus-circle'],
            can('ictd.request.manage') ? ['label' => 'Manage Status', 'href' => BASE_URL . '/ictd/requests/service-status', 'icon' => 'bi-wrench-adjustable-circle'] : null,
            ['label' => 'Archive', 'href' => BASE_URL . '/ictd/requests/service/archive', 'icon' => 'bi-archive'],
        ]),
    ],
];
?>

<style>
  .request-module-shell { display: grid; gap: 1.05rem; }
  .request-module-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; }
  .request-module-eyebrow { color: #667085; font-size: .78rem; font-weight: 750; }
  .request-module-title { color: #1f2937; font-size: 1.35rem; font-weight: 850; margin: 0; }
  .request-module-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; }
  .request-module-panel { border: 0; border-radius: 10px; background: rgba(255,255,255,.82); overflow: hidden; }
  .request-module-panel-body { padding: 1rem; display: grid; gap: .9rem; }
  .request-module-panel-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; }
  .request-module-icon { width: 38px; height: 38px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; color: #3657f6; background: #eef4ff; }
  .request-module-name { margin: 0; color: #1f2937; font-size: 1rem; font-weight: 850; }
  .request-module-subtitle { color: #667085; font-size: .78rem; margin: .2rem 0 0; line-height: 1.4; }
  .request-module-primary { border-radius: 4px; background: #3657f6; border-color: #3657f6; font-size: .78rem; font-weight: 800; min-height: 36px; }
  .request-module-stats { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: .55rem; }
  .request-module-stat { padding: .7rem; border-radius: 8px; background: #f9fafb; border: 1px solid #eef2f7; }
  .request-module-stat-value { color: #1f2937; font-weight: 900; font-size: 1rem; line-height: 1; }
  .request-module-stat-label { color: #667085; font-size: .68rem; font-weight: 800; margin-top: .35rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .request-module-links { display: flex; align-items: center; flex-wrap: wrap; gap: .55rem; }
  .request-module-link { height: 34px; border: 1px solid #eaecf0; background: #fff; color: #344054; border-radius: 4px; padding: 0 .75rem; display: inline-flex; align-items: center; gap: .4rem; font-size: .76rem; font-weight: 750; text-decoration: none; }
  .request-module-link:hover { color: #3657f6; border-color: #dbe4ff; background: #f8fbff; }
  @media (max-width: 1100px) { .request-module-grid { grid-template-columns: 1fr; } }
  @media (max-width: 767px) { .request-module-head, .request-module-panel-top { flex-direction: column; } .request-module-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } .request-module-primary, .request-module-link { width: 100%; justify-content: center; } }
</style>

<div class="container-fluid py-3">
    <div class="request-module-shell">
        <div class="request-module-head">
            <div>
                <div class="request-module-eyebrow">Request</div>
                <h2 class="request-module-title">Equipment and Service Request Modules</h2>
            </div>
        </div>

        <div class="request-module-grid">
            <?php foreach ($modules as $module): ?>
                <section class="request-module-panel">
                    <div class="request-module-panel-body">
                        <div class="request-module-panel-top">
                            <div class="d-flex gap-3">
                                <div class="request-module-icon"><i class="bi <?= e($module['icon']) ?>"></i></div>
                                <div>
                                    <h3 class="request-module-name"><?= e($module['title']) ?></h3>
                                    <p class="request-module-subtitle"><?= e($module['subtitle']) ?></p>
                                </div>
                            </div>
                            <a class="btn btn-primary request-module-primary" href="<?= e($module['primary']['href']) ?>">
                                <i class="bi <?= e($module['primary']['icon']) ?>"></i> <?= e($module['primary']['label']) ?>
                            </a>
                        </div>

                        <div class="request-module-stats">
                            <?php foreach ($module['counts'] as $label => $count): ?>
                                <div class="request-module-stat">
                                    <div class="request-module-stat-value"><?= (int) $count ?></div>
                                    <div class="request-module-stat-label"><?= e(ucwords(str_replace('_', ' ', $label))) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="request-module-links">
                            <?php foreach ($module['links'] as $link): ?>
                                <a class="request-module-link" href="<?= e($link['href']) ?>">
                                    <i class="bi <?= e($link['icon']) ?>"></i> <?= e($link['label']) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require_once ROOT_PATH . '/modules/layout/footer.php'; ?>
