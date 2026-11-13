<?php

function gr_modules(PDO $pdo): array
{
    $equipmentWarranty = "CASE
        WHEN e.warranty_date >= CURDATE()
          OR (e.warranty_date IS NULL AND e.date_delivered IS NOT NULL AND e.no_yrs_warranty IS NOT NULL AND DATE_ADD(e.date_delivered, INTERVAL e.no_yrs_warranty YEAR) >= CURDATE())
        THEN 'Under Warranty'
        WHEN e.warranty_date IS NOT NULL OR (e.date_delivered IS NOT NULL AND e.no_yrs_warranty IS NOT NULL)
        THEN 'Expired'
        ELSE 'No Warranty'
    END";

    return [
        'inventory_overview' => [
            'label' => 'Inventory Overview',
            'title' => 'Inventory Overview Report',
            'icon' => 'bi-clipboard-data',
            'table' => 'equipment',
            'alias' => 'io',
            'date' => 'date_recorded',
            'description' => null,
            'status' => 'status',
            'order' => 'date_recorded DESC',
            'custom' => 'inventory_overview',
            'search' => [],
            'fields' => [
                'module' => ['Module', 'module'],
                'record_no' => ['Record No', 'record_no'],
                'item_name' => ['Item / Description', 'item_name'],
                'brand' => ['Brand', 'brand'],
                'serial_no' => ['Serial No', 'serial_no'],
                'status' => ['Status', 'status'],
                'end_user' => ['End User', 'end_user'],
                'department' => ['Department', 'department'],
                'date_recorded' => ['Date Recorded', 'date_recorded'],
                'price' => ['Amount / Value', 'price'],
                'remarks' => ['Remarks', 'remarks'],
            ],
        ],
        'equipment' => [
            'label' => 'Equipment',
            'title' => 'Equipment Reports',
            'icon' => 'bi-hdd-network',
            'table' => 'equipment',
            'alias' => 'e',
            'date' => 'e.created_at',
            'description' => 'd.description_name',
            'status' => 'e.delivery_stat',
            'order' => 'e.created_at DESC',
            'from' => 'equipment e LEFT JOIN list_description d ON d.id = e.description_id LEFT JOIN list_brand b ON b.id = e.brand_id',
            'search' => ['e.control_no', 'e.old_control_no', 'd.description_name', 'b.brand_name', 'e.serialno', 'e.os', 'e.ms_software', 'e.processor', 'e.property_no'],
            'fields' => [
                'warranty_status' => ['Warranty', $equipmentWarranty],
                'delivery_stat' => ['Delivery Status', 'e.delivery_stat'],
                'eta_date' => ['ETA Date', 'e.eta_date'],
                'control_no' => ['Control No', 'e.control_no'],
                'description_name' => ['Description', 'd.description_name'],
                'brand_name' => ['Brand', 'b.brand_name'],
                'serialno' => ['Serial No', 'e.serialno'],
                'os' => ['OS', 'e.os'],
                'ms_software' => ['MS Software', 'e.ms_software'],
                'processor' => ['Processor', 'e.processor'],
                'memory' => ['Memory', 'e.memory'],
                'hdd' => ['HDD', 'e.hdd'],
                'video_card' => ['Video Card', 'e.video_card'],
                'price' => ['Price', 'e.price'],
                'property_no' => ['Property No', 'e.property_no'],
                'old_control_no' => ['Old Control No', 'e.old_control_no'],
                'created_at' => ['Created At', 'e.created_at'],
            ],
        ],
        'equipment_requests' => [
            'label' => 'Equipment Requests',
            'title' => 'Equipment Request Reports',
            'icon' => 'bi-inboxes',
            'table' => 'equipment_requests',
            'alias' => 'er',
            'date' => 'er.date_requested',
            'description' => null,
            'status' => 'er.status',
            'order' => 'er.date_requested DESC, er.id DESC',
            'from' => 'equipment_requests er LEFT JOIN list_funds lf ON lf.id = er.fund_id LEFT JOIN list_department dep ON dep.department_id = er.department_id',
            'search' => ['er.control_no', 'lf.fund_name', 'dep.department_name', 'er.department_head', 'er.status', 'er.cancel_reason'],
            'fields' => [
                'control_no' => ['Control No', 'er.control_no'],
                'date_requested' => ['Date Requested', 'er.date_requested'],
                'fund_name' => ['Source of Funds', 'lf.fund_name'],
                'department_name' => ['Department', 'dep.department_name'],
                'department_head' => ['Department Head', 'er.department_head'],
                'budget_amount' => ['Budget Amount', 'er.budget_amount'],
                'total_amount' => ['Total Amount', 'er.total_amount'],
                'quarter' => ['Quarter', 'er.quarter'],
                'budget_balance' => ['Budget Balance', 'er.budget_balance'],
                'status' => ['Status', 'er.status'],
                'item_count' => ['Items', '(SELECT COUNT(*) FROM equipment_request_items eri WHERE eri.request_id = er.id)'],
                'cancel_reason' => ['Cancel Reason', 'er.cancel_reason'],
                'created_at' => ['Created At', 'er.created_at'],
            ],
        ],
        'service_requests' => [
            'label' => 'Service Requests',
            'title' => 'Service Request Reports',
            'icon' => 'bi-tools',
            'table' => 'service_request',
            'alias' => 'srq',
            'date' => 'srq.created_at',
            'description' => null,
            'status' => 'srq.status',
            'order' => 'srq.created_at DESC, srq.id DESC',
            'from' => 'service_request srq LEFT JOIN list_end_user eu ON eu.end_user_id = srq.end_user_id LEFT JOIN list_department dep ON dep.department_id = srq.department_id',
            'search' => ['srq.id', 'srq.request_type', 'eu.end_user_name', 'dep.department_name', 'srq.request_description', 'srq.complain_problem', 'srq.status', 'srq.assigned_technician', 'srq.admin_action_reason', 'srq.cancel_reason'],
            'fields' => [
                'id' => ['Request ID', 'srq.id'],
                'request_type' => ['Request Type', 'srq.request_type'],
                'end_user_name' => ['End User', 'eu.end_user_name'],
                'department_name' => ['Department', 'dep.department_name'],
                'request_description' => ['Description', 'srq.request_description'],
                'complain_problem' => ['Complaint / Problem', 'srq.complain_problem'],
                'status' => ['Status', 'srq.status'],
                'assigned_technician' => ['Technician', 'srq.assigned_technician'],
                'admin_action_reason' => ['Admin Reason', 'srq.admin_action_reason'],
                'cancel_reason' => ['Cancel Reason', 'srq.cancel_reason'],
                'created_at' => ['Created At', 'srq.created_at'],
            ],
        ],
        'assigned' => [
            'label' => 'Assigned',
            'title' => 'Assigned Reports',
            'icon' => 'bi-person-lines-fill',
            'table' => db_table_exists($pdo, 'assigned_record') ? 'assigned_record' : 'assigned_equipment',
            'alias' => 'ar',
            'date' => 'ar.created_at',
            'description' => null,
            'status' => null,
            'order' => 'ar.created_at DESC',
            'from' => (db_table_exists($pdo, 'assigned_record') ? 'assigned_record' : 'assigned_equipment') . ' ar LEFT JOIN list_end_user eu ON eu.end_user_id = ar.end_user_id LEFT JOIN list_department dep ON dep.department_id = ar.department_id',
            'search' => ['ar.control_no', 'eu.end_user_name', 'dep.department_name', 'ar.connection_ip', 'ar.program_installed', 'ar.equipment_analysis'],
            'fields' => [
                'assigned_id' => ['Assigned ID', 'ar.assigned_id'],
                'end_user_name' => ['End User', 'eu.end_user_name'],
                'department_name' => ['Department', 'dep.department_name'],
                'control_no' => ['Control No', 'ar.control_no'],
                'connection_ip' => ['Connection IP', 'ar.connection_ip'],
                'internet' => ['Internet', 'ar.internet'],
                'wifi' => ['WiFi', 'ar.wifi'],
                'program_installed' => ['Program Installed', 'ar.program_installed'],
                'assigned_date' => ['Assigned Date', 'ar.assigned_date'],
                'months_since_assigned' => ['Months Assigned', 'ar.months_since_assigned'],
                'equipment_analysis' => ['Equipment Analysis', 'ar.equipment_analysis'],
                'created_at' => ['Created At', 'ar.created_at'],
            ],
        ],
        'subscription' => [
            'label' => 'Subscription',
            'title' => 'Subscription Reports',
            'icon' => 'bi-box-arrow-up-right',
            'table' => 'subscription',
            'alias' => 's',
            'date' => 's.purchase_date',
            'description' => null,
            'status' => null,
            'order' => 's.expiry_date DESC',
            'from' => 'subscription s LEFT JOIN list_end_user eu ON eu.end_user_id = s.end_user_id LEFT JOIN list_department dep ON dep.department_id = s.department_id',
            'search' => ['s.software_description', 'eu.end_user_name', 'dep.department_name'],
            'fields' => [
                'subscription_id' => ['Subscription ID', 's.subscription_id'],
                'end_user_name' => ['End User', 'eu.end_user_name'],
                'department_name' => ['Department', 'dep.department_name'],
                'software_description' => ['Software Description', 's.software_description'],
                'purchase_date' => ['Purchase Date', 's.purchase_date'],
                'expiry_date' => ['Expiry Date', 's.expiry_date'],
            ],
        ],
        'borrowed' => [
            'label' => 'Borrowed',
            'title' => 'Borrowed Reports',
            'icon' => 'bi-arrow-counterclockwise',
            'table' => 'borrowed',
            'alias' => 'br',
            'date' => 'br.date_borrowed',
            'description' => null,
            'status' => null,
            'order' => 'br.date_borrowed DESC',
            'from' => 'borrowed br LEFT JOIN list_end_user eu ON eu.end_user_id = br.end_user_id LEFT JOIN list_department dep ON dep.department_id = br.department_id',
            'search' => ['br.item_borrowed', 'br.serialno', 'eu.end_user_name', 'dep.department_name'],
            'fields' => [
                'id' => ['Borrowed ID', 'br.id'],
                'end_user_name' => ['End User', 'eu.end_user_name'],
                'department_name' => ['Department', 'dep.department_name'],
                'item_borrowed' => ['Item Borrowed', 'br.item_borrowed'],
                'serialno' => ['Serial No', 'br.serialno'],
                'date_borrowed' => ['Date Borrowed', 'br.date_borrowed'],
                'date_returned' => ['Date Returned', 'br.date_returned'],
            ],
        ],
        'maintenance' => [
            'label' => 'Maintenance',
            'title' => 'Maintenance Reports',
            'icon' => 'bi-tools',
            'table' => 'maintenance_reports',
            'alias' => 'mr',
            'date' => 'mr.date_accomplished',
            'description' => null,
            'status' => 'mr.maintenance_status',
            'order' => 'mr.date_recorded DESC',
            'from' => 'maintenance_reports mr LEFT JOIN list_end_user eu ON eu.end_user_id = mr.end_user_id LEFT JOIN list_department dep ON dep.department_id = mr.department_id',
            'search' => ['mr.maintenance_id', 'mr.control_no', 'eu.end_user_name', 'dep.department_name', 'mr.inspected_by', 'mr.maintenance_status'],
            'fields' => [
                'maintenance_id' => ['Maintenance ID', 'mr.maintenance_id'],
                'control_no' => ['Control No', 'mr.control_no'],
                'status' => ['Status', 'mr.maintenance_status'],
                'branded' => ['Branded', 'mr.branded'],
                'assemble' => ['Assemble', 'mr.assemble'],
                'end_user_name' => ['End User', 'eu.end_user_name'],
                'department_name' => ['Department', 'dep.department_name'],
                'date_accomplished' => ['Date Accomplished', 'mr.date_accomplished'],
                'inspected_by' => ['Inspected By', 'mr.inspected_by'],
                'date_recorded' => ['Date Recorded', 'mr.date_recorded'],
            ],
        ],
        'service' => [
            'label' => 'Service Details',
            'title' => 'Service Detail Reports',
            'icon' => 'bi-card-text',
            'table' => 'service_report',
            'alias' => 'sr',
            'date' => 'sr.report_date',
            'description' => null,
            'status' => 'sr.employee_satisfactory',
            'order' => 'sr.created_at DESC',
            'from' => 'service_report sr LEFT JOIN list_end_user eu ON eu.end_user_id = sr.end_user_id LEFT JOIN list_department dep ON dep.department_id = sr.department_id',
            'search' => ['sr.service_controlno', 'sr.old_service_cn', 'eu.end_user_name', 'dep.department_name', 'sr.vicinity', 'sr.complain', 'sr.troubleshoot', 'sr.attending_technician'],
            'fields' => [
                'id' => ['ID', 'sr.id'],
                'service_controlno' => ['Service Control No', 'sr.service_controlno'],
                'old_service_cn' => ['Old Service No', 'sr.old_service_cn'],
                'end_user_name' => ['End User', 'eu.end_user_name'],
                'department_name' => ['Department', 'dep.department_name'],
                'vicinity' => ['Vicinity', 'sr.vicinity'],
                'report_date' => ['Report Date', 'sr.report_date'],
                'report_time' => ['Report Time', 'sr.report_time'],
                'complain' => ['Complain / Problem', 'sr.complain'],
                'troubleshoot' => ['Troubleshoot', 'sr.troubleshoot'],
                'attending_technician' => ['Technician', 'sr.attending_technician'],
                'employee_satisfactory' => ['Satisfaction', 'sr.employee_satisfactory'],
                'created_at' => ['Created At', 'sr.created_at'],
            ],
        ],
        'supplies' => [
            'label' => 'Supplies',
            'title' => 'Supplies Reports',
            'icon' => 'bi-briefcase-fill',
            'table' => 'other_equipment',
            'alias' => 'oe',
            'date' => 'oe.created_at',
            'description' => 'd.description_name',
            'status' => 'oe.status',
            'order' => 'oe.created_at DESC',
            'from' => 'other_equipment oe LEFT JOIN list_description d ON d.id = oe.description_id LEFT JOIN list_brand b ON b.id = oe.brand_id LEFT JOIN list_end_user eu ON eu.end_user_id = oe.end_user_id LEFT JOIN list_department dep ON dep.department_id = oe.department_id',
            'search' => ['oe.code', 'oe.control_no', 'oe.old_control_no', 'd.description_name', 'b.brand_name', 'oe.serialno', 'oe.status', 'oe.delivery_status', 'eu.end_user_name', 'dep.department_name'],
            'fields' => [
                'code' => ['Code', 'oe.code'],
                'control_no' => ['Control No', 'oe.control_no'],
                'old_control_no' => ['Old Control No', 'oe.old_control_no'],
                'description_name' => ['Description', 'd.description_name'],
                'brand_name' => ['Brand', 'b.brand_name'],
                'serialno' => ['Serial No', 'oe.serialno'],
                'status' => ['Status', 'oe.status'],
                'delivery_status' => ['Delivery Status', 'oe.delivery_status'],
                'end_user_name' => ['End User', 'eu.end_user_name'],
                'department_name' => ['Department', 'dep.department_name'],
                'delivered_date' => ['Delivered Date', 'oe.delivered_date'],
                'warranty_date' => ['Warranty Date', 'oe.warranty_date'],
                'created_at' => ['Created At', 'oe.created_at'],
            ],
        ],
    ];
}

function gr_active_module(PDO $pdo, string $requested): string
{
    return array_key_exists($requested, gr_modules($pdo)) ? $requested : 'equipment';
}

function gr_field_labels(array $config): array
{
    $labels = [];
    foreach ($config['fields'] as $key => $field) {
        $labels[$key] = $field[0];
    }
    return $labels;
}

function gr_fetch_rows(PDO $pdo, array $config, array $filters = []): array
{
    if (($config['custom'] ?? '') === 'inventory_overview') {
        return gr_inventory_overview_rows($pdo, $filters);
    }

    if (!db_table_exists($pdo, $config['table'])) {
        return [];
    }

    $select = [];
    foreach ($config['fields'] as $key => $field) {
        $select[] = $field[1] . ' AS `' . $key . '`';
    }

    $where = [];
    $params = [];
    ensure_record_scope_schema($pdo);
    apply_record_scope(
        $pdo,
        $where,
        $params,
        record_scope_column($pdo, $config['table'], 'created_by', $config['alias']),
        record_scope_column($pdo, $config['table'], 'department_id', $config['alias']),
        'report_' . $config['alias']
    );

    if (db_column_exists($pdo, $config['table'], 'is_archived')) {
        $where[] = 'COALESCE(' . $config['alias'] . '.is_archived, 0) = 0';
    }

    $search = trim((string) ($filters['search'] ?? ''));
    if ($search !== '' && !empty($config['search'])) {
        $parts = [];
        foreach ($config['search'] as $index => $expr) {
            $param = 'search_' . $index;
            $parts[] = $expr . ' LIKE :' . $param;
            $params[$param] = '%' . $search . '%';
        }
        $where[] = '(' . implode(' OR ', $parts) . ')';
    }

    $dateFrom = trim((string) ($filters['date_from'] ?? ''));
    $dateTo = trim((string) ($filters['date_to'] ?? ''));
    if ($dateFrom !== '' && !empty($config['date'])) {
        $where[] = 'DATE(' . $config['date'] . ') >= :date_from';
        $params['date_from'] = $dateFrom;
    }
    if ($dateTo !== '' && !empty($config['date'])) {
        $where[] = 'DATE(' . $config['date'] . ') <= :date_to';
        $params['date_to'] = $dateTo;
    }

    $description = trim((string) ($filters['description_name'] ?? ''));
    if ($description !== '' && !empty($config['description'])) {
        $where[] = $config['description'] . ' = :description_name';
        $params['description_name'] = $description;
    }

    $status = trim((string) ($filters['status'] ?? ''));
    if ($status !== '' && !empty($config['status'])) {
        $where[] = $config['status'] . ' = :status';
        $params['status'] = $status;
    }

    $whereSql = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);
    $stmt = $pdo->prepare('SELECT ' . implode(', ', $select) . ' FROM ' . $config['from'] . ' ' . $whereSql . ' ORDER BY ' . $config['order']);
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function gr_descriptions(PDO $pdo): array
{
    if (!db_table_exists($pdo, 'list_description')) {
        return [];
    }
    return $pdo->query('SELECT description_name FROM list_description ORDER BY description_name ASC')->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

function gr_status_options(PDO $pdo, array $config, array $filters = []): array
{
    if (($config['custom'] ?? '') === 'inventory_overview') {
        $statusFilters = $filters;
        $statusFilters['status'] = '';
        $statuses = array_values(array_unique(array_filter(array_map(
            static fn($row) => trim((string) ($row['status'] ?? '')),
            gr_inventory_overview_rows($pdo, $statusFilters)
        ))));
        sort($statuses, SORT_NATURAL | SORT_FLAG_CASE);
        return $statuses;
    }

    if (empty($config['status']) || !db_table_exists($pdo, $config['table'])) {
        return [];
    }

    $where = [];
    $params = [];
    ensure_record_scope_schema($pdo);
    apply_record_scope(
        $pdo,
        $where,
        $params,
        record_scope_column($pdo, $config['table'], 'created_by', $config['alias']),
        record_scope_column($pdo, $config['table'], 'department_id', $config['alias']),
        'report_status_' . $config['alias']
    );
    if (db_column_exists($pdo, $config['table'], 'is_archived')) {
        $where[] = 'COALESCE(' . $config['alias'] . '.is_archived, 0) = 0';
    }
    $where[] = 'TRIM(COALESCE(' . $config['status'] . ", '')) <> ''";
    $whereSql = 'WHERE ' . implode(' AND ', $where);
    $stmt = $pdo->prepare('SELECT DISTINCT ' . $config['status'] . ' AS status_value FROM ' . $config['from'] . ' ' . $whereSql . ' ORDER BY status_value ASC');
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->execute();
    return array_values(array_filter(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [])));
}

function gr_inventory_scope(PDO $pdo, string $table, string $alias, array &$where, array &$params, string $scopeKey): void
{
    ensure_record_scope_schema($pdo);
    apply_record_scope(
        $pdo,
        $where,
        $params,
        record_scope_column($pdo, $table, 'created_by', $alias),
        record_scope_column($pdo, $table, 'department_id', $alias),
        $scopeKey
    );
    if (db_column_exists($pdo, $table, 'is_archived')) {
        $where[] = 'COALESCE(' . $alias . '.is_archived, 0) = 0';
    }
}

function gr_inventory_fetch(PDO $pdo, string $sql, array $where, array $params): array
{
    $whereSql = empty($where) ? '' : ' WHERE ' . implode(' AND ', $where);
    $stmt = $pdo->prepare($sql . $whereSql);
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function gr_inventory_filter_rows(array $rows, array $filters): array
{
    $search = strtolower(trim((string) ($filters['search'] ?? '')));
    $dateFrom = trim((string) ($filters['date_from'] ?? ''));
    $dateTo = trim((string) ($filters['date_to'] ?? ''));
    $status = strtolower(trim((string) ($filters['status'] ?? '')));

    $rows = array_values(array_filter($rows, static function (array $row) use ($search, $dateFrom, $dateTo, $status): bool {
        if ($search !== '') {
            $haystack = strtolower(implode(' ', array_map(static fn($value) => (string) $value, $row)));
            if (strpos($haystack, $search) === false) {
                return false;
            }
        }
        if ($status !== '' && strtolower(trim((string) ($row['status'] ?? ''))) !== $status) {
            return false;
        }
        $date = trim((string) ($row['date_recorded'] ?? ''));
        if ($dateFrom !== '' && ($date === '' || substr($date, 0, 10) < $dateFrom)) {
            return false;
        }
        if ($dateTo !== '' && ($date === '' || substr($date, 0, 10) > $dateTo)) {
            return false;
        }
        return true;
    }));

    usort($rows, static fn($a, $b) => strcmp((string) ($b['date_recorded'] ?? ''), (string) ($a['date_recorded'] ?? '')));
    return $rows;
}

function gr_inventory_overview_rows(PDO $pdo, array $filters = []): array
{
    $rows = [];

    if (db_table_exists($pdo, 'equipment')) {
        $where = [];
        $params = [];
        gr_inventory_scope($pdo, 'equipment', 'e', $where, $params, 'report_inventory_equipment');
        $statusJoin = db_column_exists($pdo, 'equipment', 'equipment_status_id') && db_table_exists($pdo, 'list_equipment_status')
            ? 'LEFT JOIN list_equipment_status es ON es.id = e.equipment_status_id'
            : '';
        $statusExpr = $statusJoin ? "COALESCE(NULLIF(es.equipment_status, ''), e.delivery_stat, 'No Status')" : "COALESCE(e.delivery_stat, 'No Status')";
        $rows = array_merge($rows, gr_inventory_fetch($pdo, "
            SELECT
                'Equipment' AS module,
                e.control_no AS record_no,
                d.description_name AS item_name,
                b.brand_name AS brand,
                e.serialno AS serial_no,
                {$statusExpr} AS status,
                '' AS end_user,
                dep.department_name AS department,
                COALESCE(NULLIF(e.date_delivered, ''), e.created_at) AS date_recorded,
                e.price AS price,
                CONCAT_WS(' | ', NULLIF(e.property_no, ''), NULLIF(e.old_control_no, '')) AS remarks
            FROM equipment e
            LEFT JOIN list_description d ON d.id = e.description_id
            LEFT JOIN list_brand b ON b.id = e.brand_id
            LEFT JOIN list_department dep ON dep.department_id = e.department_id
            {$statusJoin}
        ", $where, $params));
    }

    if (db_table_exists($pdo, 'other_equipment')) {
        $where = [];
        $params = [];
        gr_inventory_scope($pdo, 'other_equipment', 'oe', $where, $params, 'report_inventory_supplies');
        $rows = array_merge($rows, gr_inventory_fetch($pdo, "
            SELECT
                'Supplies' AS module,
                COALESCE(NULLIF(oe.control_no, ''), oe.code) AS record_no,
                d.description_name AS item_name,
                b.brand_name AS brand,
                oe.serialno AS serial_no,
                COALESCE(NULLIF(oe.status, ''), oe.delivery_status, 'No Status') AS status,
                eu.end_user_name AS end_user,
                dep.department_name AS department,
                COALESCE(NULLIF(oe.delivered_date, ''), oe.created_at) AS date_recorded,
                0 AS price,
                CONCAT_WS(' | ', NULLIF(oe.old_control_no, ''), NULLIF(oe.delivery_status, '')) AS remarks
            FROM other_equipment oe
            LEFT JOIN list_description d ON d.id = oe.description_id
            LEFT JOIN list_brand b ON b.id = oe.brand_id
            LEFT JOIN list_end_user eu ON eu.end_user_id = oe.end_user_id
            LEFT JOIN list_department dep ON dep.department_id = oe.department_id
        ", $where, $params));
    }

    $assignedTable = db_table_exists($pdo, 'assigned_record') ? 'assigned_record' : (db_table_exists($pdo, 'assigned_equipment') ? 'assigned_equipment' : null);
    if ($assignedTable) {
        $where = [];
        $params = [];
        gr_inventory_scope($pdo, $assignedTable, 'ar', $where, $params, 'report_inventory_assigned');
        $rows = array_merge($rows, gr_inventory_fetch($pdo, "
            SELECT
                'Assigned Equipment' AS module,
                ar.control_no AS record_no,
                ar.equipment_analysis AS item_name,
                '' AS brand,
                '' AS serial_no,
                'Assigned' AS status,
                eu.end_user_name AS end_user,
                dep.department_name AS department,
                COALESCE(NULLIF(ar.assigned_date, ''), ar.created_at) AS date_recorded,
                0 AS price,
                CONCAT_WS(' | ', NULLIF(ar.connection_ip, ''), NULLIF(ar.program_installed, '')) AS remarks
            FROM {$assignedTable} ar
            LEFT JOIN list_end_user eu ON eu.end_user_id = ar.end_user_id
            LEFT JOIN list_department dep ON dep.department_id = ar.department_id
        ", $where, $params));
    }

    if (db_table_exists($pdo, 'maintenance_reports')) {
        $where = [];
        $params = [];
        gr_inventory_scope($pdo, 'maintenance_reports', 'mr', $where, $params, 'report_inventory_maintenance');
        $rows = array_merge($rows, gr_inventory_fetch($pdo, "
            SELECT
                'Maintenance' AS module,
                mr.maintenance_id AS record_no,
                mr.control_no AS item_name,
                '' AS brand,
                '' AS serial_no,
                mr.maintenance_status AS status,
                eu.end_user_name AS end_user,
                dep.department_name AS department,
                COALESCE(NULLIF(mr.date_accomplished, ''), mr.date_recorded) AS date_recorded,
                0 AS price,
                CONCAT_WS(' | ', NULLIF(mr.inspected_by, ''), NULLIF(mr.branded, ''), NULLIF(mr.assemble, '')) AS remarks
            FROM maintenance_reports mr
            LEFT JOIN list_end_user eu ON eu.end_user_id = mr.end_user_id
            LEFT JOIN list_department dep ON dep.department_id = mr.department_id
        ", $where, $params));
    }

    if (db_table_exists($pdo, 'service_report')) {
        $where = [];
        $params = [];
        gr_inventory_scope($pdo, 'service_report', 'sr', $where, $params, 'report_inventory_service');
        $rows = array_merge($rows, gr_inventory_fetch($pdo, "
            SELECT
                'Service Report' AS module,
                sr.service_controlno AS record_no,
                COALESCE(NULLIF(sr.complain, ''), 'Service Report') AS item_name,
                '' AS brand,
                '' AS serial_no,
                COALESCE(NULLIF(sr.employee_satisfactory, ''), 'Completed') AS status,
                eu.end_user_name AS end_user,
                dep.department_name AS department,
                COALESCE(NULLIF(sr.report_date, ''), sr.created_at) AS date_recorded,
                0 AS price,
                CONCAT_WS(' | ', NULLIF(sr.troubleshoot, ''), NULLIF(sr.attending_technician, '')) AS remarks
            FROM service_report sr
            LEFT JOIN list_end_user eu ON eu.end_user_id = sr.end_user_id
            LEFT JOIN list_department dep ON dep.department_id = sr.department_id
        ", $where, $params));
    }

    if (db_table_exists($pdo, 'transmittal')) {
        $where = [];
        $params = [];
        gr_inventory_scope($pdo, 'transmittal', 't', $where, $params, 'report_inventory_transmittal');
        $controlExpr = db_column_exists($pdo, 'transmittal', 'control_no') ? 't.control_no' : 't.id';
        $statusExpr = db_column_exists($pdo, 'transmittal', 'status') ? 't.status' : "'Transmitted'";
        $dateExpr = db_column_exists($pdo, 'transmittal', 'transmittal_date') ? 't.transmittal_date' : 't.created_at';
        $rows = array_merge($rows, gr_inventory_fetch($pdo, "
            SELECT
                'Transmittal' AS module,
                {$controlExpr} AS record_no,
                'Transmittal Record' AS item_name,
                '' AS brand,
                '' AS serial_no,
                {$statusExpr} AS status,
                '' AS end_user,
                dep.department_name AS department,
                {$dateExpr} AS date_recorded,
                0 AS price,
                '' AS remarks
            FROM transmittal t
            LEFT JOIN list_department dep ON dep.department_id = t.department_id
        ", $where, $params));
    }

    if (db_table_exists($pdo, 'equipment_requests')) {
        $where = [];
        $params = [];
        gr_inventory_scope($pdo, 'equipment_requests', 'er', $where, $params, 'report_inventory_equipment_requests');
        $rows = array_merge($rows, gr_inventory_fetch($pdo, "
            SELECT
                'Equipment Request' AS module,
                er.control_no AS record_no,
                'Requested Equipment / Supplies' AS item_name,
                '' AS brand,
                '' AS serial_no,
                er.status AS status,
                '' AS end_user,
                dep.department_name AS department,
                COALESCE(NULLIF(er.date_requested, ''), er.created_at) AS date_recorded,
                er.total_amount AS price,
                COALESCE(er.cancel_reason, '') AS remarks
            FROM equipment_requests er
            LEFT JOIN list_department dep ON dep.department_id = er.department_id
        ", $where, $params));
    }

    if (db_table_exists($pdo, 'service_request')) {
        $where = [];
        $params = [];
        gr_inventory_scope($pdo, 'service_request', 'srq', $where, $params, 'report_inventory_service_requests');
        $rows = array_merge($rows, gr_inventory_fetch($pdo, "
            SELECT
                'Service Request' AS module,
                srq.id AS record_no,
                srq.request_description AS item_name,
                '' AS brand,
                '' AS serial_no,
                srq.status AS status,
                eu.end_user_name AS end_user,
                dep.department_name AS department,
                srq.created_at AS date_recorded,
                0 AS price,
                COALESCE(srq.admin_action_reason, srq.cancel_reason, srq.remarks, '') AS remarks
            FROM service_request srq
            LEFT JOIN list_end_user eu ON eu.end_user_id = srq.end_user_id
            LEFT JOIN list_department dep ON dep.department_id = srq.department_id
        ", $where, $params));
    }

    return gr_inventory_filter_rows($rows, $filters);
}
