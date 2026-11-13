<?php
  $page_title = 'ICTD Dashboard';
  require_once ROOT_PATH . '/modules/layout/header.php';
  require_once ROOT_PATH . '/config/database.php';
  require_once ROOT_PATH . '/helpers/functions.php';

  require_login();
  require_permission('ictd.dashboard.view');

  $user = current_user();
  ensure_record_scope_schema($pdo);

  if (!function_exists('dashboard_scoped_count')) {
  function dashboard_scoped_count(PDO $pdo, string $table, string $alias, ?string $extraWhere = null): int {
      if (!db_table_exists($pdo, $table)) {
          return 0;
      }

      $where = [];
      $params = [];
      if (!is_admin()) {
          apply_record_scope(
              $pdo,
              $where,
              $params,
              record_scope_column($pdo, $table, 'created_by', $alias),
              record_scope_column($pdo, $table, 'department_id', $alias),
              'dashboard_' . preg_replace('/[^a-z0-9_]/i', '_', $table)
          );
      }

      if ($extraWhere) {
          $where[] = $extraWhere;
      }

      $whereSql = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);
      $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} {$alias} {$whereSql}");
      foreach ($params as $key => $value) {
          $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
      }
      $stmt->execute();

      return (int) $stmt->fetchColumn();
  }
  }

  if (!function_exists('dashboard_month_value')) {
  function dashboard_month_value(?string $value, string $fallback): string {
      $value = trim((string) $value);
      if (!preg_match('/^\d{4}-\d{2}$/', $value)) {
          return $fallback;
      }
      return $value;
  }
  }

  $dashboardTables = [
      'equipment' => db_table_exists($pdo, 'equipment'),
      'assigned_record' => db_table_exists($pdo, 'assigned_record'),
      'maintenance_reports' => db_table_exists($pdo, 'maintenance_reports'),
      'service_report' => db_table_exists($pdo, 'service_report'),
      'transmittal' => db_table_exists($pdo, 'transmittal'),
  ];

  $counts = [
      'equipment' => dashboard_scoped_count($pdo, 'equipment', 'e'),
      'assigned' => dashboard_scoped_count($pdo, 'assigned_record', 'ar'),
      'maintenance' => dashboard_scoped_count($pdo, 'maintenance_reports', 'mr', db_column_exists($pdo, 'maintenance_reports', 'is_archived') ? 'COALESCE(mr.is_archived, 0) = 0' : null),
      'service' => dashboard_scoped_count($pdo, 'service_report', 'sr', db_column_exists($pdo, 'service_report', 'is_archived') ? 'COALESCE(sr.is_archived, 0) = 0' : null),
      'transmittal' => dashboard_scoped_count($pdo, 'transmittal', 't', db_column_exists($pdo, 'transmittal', 'is_archived') ? 'COALESCE(t.is_archived, 0) = 0' : null),
      'equipment_requests' => dashboard_scoped_count($pdo, 'equipment_requests', 'er', db_column_exists($pdo, 'equipment_requests', 'is_archived') ? 'COALESCE(er.is_archived, 0) = 0' : null),
      'service_requests' => dashboard_scoped_count($pdo, 'service_request', 'srq', db_column_exists($pdo, 'service_request', 'is_archived') ? 'COALESCE(srq.is_archived, 0) = 0' : null),
  ];
  $totalRecords = array_sum($counts);

  $displayName = trim((string) ($user['name'] ?? $user['username'] ?? 'User'));
  $displayRole = trim((string) ($user['role_name'] ?? 'User'));
  $userManualDownloadUrl = dirname(BASE_URL, 2) . '/demo/';

  $tiles = [
      'All Records' => ['count' => $totalRecords, 'tone' => 'red', 'icon' => 'collection', 'href' => BASE_URL . '/ictd/dashboard'],
      'Equipment' => ['count' => $counts['equipment'], 'tone' => 'blue', 'icon' => 'pc-display', 'href' => BASE_URL . '/ictd/equipment/manage'],
      'Assigned' => ['count' => $counts['assigned'], 'tone' => 'green', 'icon' => 'person-check', 'href' => BASE_URL . '/ictd/assign'],
      'Maintenance' => ['count' => $counts['maintenance'], 'tone' => 'amber', 'icon' => 'tools', 'href' => BASE_URL . '/ictd/maintenance'],
      'Service Reports' => ['count' => $counts['service'], 'tone' => 'cyan', 'icon' => 'clipboard-data', 'href' => BASE_URL . '/ictd/service-report'],
      'Transmittals' => ['count' => $counts['transmittal'], 'tone' => 'slate', 'icon' => 'send', 'href' => BASE_URL . '/ictd/transmittal'],
      'Requests' => ['count' => $counts['equipment_requests'] + $counts['service_requests'], 'tone' => 'indigo', 'icon' => 'inboxes', 'href' => BASE_URL . '/ictd/requests/equipment'],
  ];

  $shortcuts = array_values(array_filter([
      can('ictd.equipment.view') ? ['Equipment', BASE_URL . '/ictd/equipment/manage', 'pc-display'] : null,
      can('ictd.assign.view') ? ['Assign Equipment', BASE_URL . '/ictd/assign', 'people-fill'] : null,
      can('ictd.maintenance.view') ? ['Maintenance', BASE_URL . '/ictd/maintenance', 'tools'] : null,
      can('ictd.service-report.view') ? ['Service Reports', BASE_URL . '/ictd/service-report', 'clipboard-data'] : null,
      can('ictd.request.view') ? ['Equipment Requests', BASE_URL . '/ictd/requests/equipment', 'hdd-stack'] : null,
      can('ictd.request.view') ? ['Service Requests', BASE_URL . '/ictd/requests/service', 'wrench-adjustable-circle'] : null,
      can('ictd.transmittal.view') ? ['Transmittals', BASE_URL . '/ictd/transmittal', 'send'] : null,
      can('ictd.subscription.view') ? ['Subscriptions', BASE_URL . '/ictd/subscription', 'file-earmark-text'] : null,
      can('documentation.view') ? ['User Manual', $userManualDownloadUrl, 'download', true] : null,
      can('users.view') ? ['Users', BASE_URL . '/admin', 'person-gear'] : null,
      ['Logout', BASE_URL . '/logout', 'box-arrow-right'],
  ]));
// why did you put it on the dashbaord just create me a downloadable, no need to display the button inside the system, just create me a button to remember inside the system
// the common button that the user will always encounter.
  $requestSummary = [
      ['Equipment Requests', $counts['equipment_requests'], BASE_URL . '/ictd/requests/equipment', 'hdd-stack'],
      ['Service Requests', $counts['service_requests'], BASE_URL . '/ictd/requests/service', 'wrench-adjustable-circle'],
      ['Service Reports', $counts['service'], BASE_URL . '/ictd/service-report', 'clipboard-data'],
  ];

  $chartDefaultTo = date('Y-m');
  $chartDefaultFrom = date('Y-m', strtotime('-11 months'));
  $chartFromMonth = dashboard_month_value($_GET['equipment_chart_from'] ?? null, $chartDefaultFrom);
  $chartToMonth = dashboard_month_value($_GET['equipment_chart_to'] ?? null, $chartDefaultTo);
  if ($chartFromMonth > $chartToMonth) {
      [$chartFromMonth, $chartToMonth] = [$chartToMonth, $chartFromMonth];
  }
  $chartStart = new DateTimeImmutable($chartFromMonth . '-01');
  $chartEnd = new DateTimeImmutable($chartToMonth . '-01');
  $monthDiff = ((int) $chartStart->diff($chartEnd)->format('%r%y')) * 12 + (int) $chartStart->diff($chartEnd)->format('%r%m');
  if ($monthDiff > 59) {
      $chartStart = $chartEnd->modify('-59 months');
      $chartFromMonth = $chartStart->format('Y-m');
  }
  $chartEndExclusive = $chartEnd->modify('+1 month');
  $chartMonths = [];
  for ($cursor = $chartStart; $cursor < $chartEndExclusive; $cursor = $cursor->modify('+1 month')) {
      $chartMonths[$cursor->format('Y-m')] = $cursor->format('M Y');
  }

  $equipmentChartSeries = [];
  $equipmentStatusLabels = [];
  if (db_table_exists($pdo, 'equipment')) {
      $hasEquipmentStatusColumn = db_column_exists($pdo, 'equipment', 'equipment_status_id') && db_table_exists($pdo, 'list_equipment_status');
      if ($hasEquipmentStatusColumn) {
          $statusStmt = $pdo->query("SELECT equipment_status FROM list_equipment_status WHERE TRIM(COALESCE(equipment_status, '')) <> '' ORDER BY equipment_status ASC");
          foreach ($statusStmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $statusLabel) {
              $equipmentStatusLabels[] = (string) $statusLabel;
          }
      }
      if (empty($equipmentStatusLabels)) {
          $equipmentStatusLabels[] = 'No Status';
      }
      foreach ($equipmentStatusLabels as $statusLabel) {
          $equipmentChartSeries[$statusLabel] = array_fill_keys(array_keys($chartMonths), 0);
      }

      $periodExpr = "DATE_FORMAT(DATE(COALESCE(NULLIF(e.date_delivered, ''), e.created_at)), '%Y-%m')";
      $where = [
          "DATE(COALESCE(NULLIF(e.date_delivered, ''), e.created_at)) >= :chart_from",
          "DATE(COALESCE(NULLIF(e.date_delivered, ''), e.created_at)) < :chart_to",
      ];
      $params = [
          'chart_from' => $chartStart->format('Y-m-d'),
          'chart_to' => $chartEndExclusive->format('Y-m-d'),
      ];
      apply_record_scope(
          $pdo,
          $where,
          $params,
          record_scope_column($pdo, 'equipment', 'created_by', 'e'),
          record_scope_column($pdo, 'equipment', 'department_id', 'e'),
          'dashboard_equipment_chart'
      );
      if (db_column_exists($pdo, 'equipment', 'is_archived')) {
          $where[] = 'COALESCE(e.is_archived, 0) = 0';
      }
      $statusSelect = $hasEquipmentStatusColumn ? "COALESCE(NULLIF(es.equipment_status, ''), 'No Status')" : "'No Status'";
      $statusJoin = $hasEquipmentStatusColumn ? 'LEFT JOIN list_equipment_status es ON es.id = e.equipment_status_id' : '';
      $stmt = $pdo->prepare("
          SELECT {$periodExpr} AS period_label, {$statusSelect} AS status_label, COUNT(*) AS total
          FROM equipment e
          {$statusJoin}
          WHERE " . implode(' AND ', $where) . "
          GROUP BY period_label, status_label
          ORDER BY period_label ASC, status_label ASC
      ");
      foreach ($params as $key => $value) {
          $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
      }
      $stmt->execute();
      foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
          $period = (string) ($row['period_label'] ?? '');
          $status = (string) ($row['status_label'] ?? 'No Status');
          if (!isset($chartMonths[$period])) {
              continue;
          }
          if (!isset($equipmentChartSeries[$status])) {
              $equipmentChartSeries[$status] = array_fill_keys(array_keys($chartMonths), 0);
          }
          $equipmentChartSeries[$status][$period] = (int) ($row['total'] ?? 0);
      }
  }

  $chartPalette = ['#149aa0', '#07346c', '#11d6c4', '#ffc42e', '#ef3347', '#078a5b', '#283d76', '#a7df65'];
  $equipmentChartData = [
      'labels' => array_values($chartMonths),
      'datasets' => [],
  ];
  $paletteIndex = 0;
  foreach ($equipmentChartSeries as $status => $values) {
      $color = $chartPalette[$paletteIndex % count($chartPalette)];
      $equipmentChartData['datasets'][] = [
          'label' => $status,
          'data' => array_values($values),
          'borderColor' => $color,
          'backgroundColor' => $color,
          'tension' => 0.28,
          'pointRadius' => 2,
          'pointHoverRadius' => 4,
      ];
      $paletteIndex++;
  }
?>

<style>
  .dashboard-shell { display: grid; gap: 1.05rem; }
  .dashboard-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; }
  .dashboard-head-actions { display: flex; align-items: center; gap: .6rem; flex-wrap: wrap; justify-content: flex-end; }
  .dashboard-eyebrow { color: #667085; font-size: .78rem; font-weight: 750; letter-spacing: .01em; }
  .dashboard-title { color: #1f2937; font-size: 1.35rem; font-weight: 850; margin: 0; }
  .dashboard-subtitle { color: #667085; font-size: .82rem; margin-top: .15rem; }
  .dashboard-user-chip { display: inline-flex; align-items: center; gap: .45rem; min-height: 38px; border: 1px solid #eaecf0; background: #fff; border-radius: 4px; padding: 0 .8rem; color: #344054; font-size: .78rem; font-weight: 650; white-space: nowrap; }
  .dashboard-download-btn { display: inline-flex; align-items: center; gap: .45rem; min-height: 38px; border-radius: 4px; padding: 0 .85rem; font-size: .78rem; font-weight: 720; }
  .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: .8rem; }
  .dashboard-stat { display: block; text-decoration: none; background: rgba(255,255,255,.92); border: 1px solid #eef2f6; border-radius: 8px; padding: .9rem; min-height: 108px; color: #1f2937; transition: background .15s ease, border-color .15s ease; }
  .dashboard-stat:hover { background: #f8fbff; border-color: #dbe7ff; }
  .dashboard-stat-top { display: flex; align-items: center; justify-content: space-between; gap: .75rem; }
  .dashboard-stat-label { color: #667085; font-size: .76rem; font-weight: 760; }
  .dashboard-stat-icon { width: 32px; height: 32px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; background: #f3f4f6; color: #344054; }
  .dashboard-stat-value { font-size: 1.7rem; line-height: 1.1; font-weight: 850; margin-top: .75rem; }
  .tone-blue .dashboard-stat-icon { background: #eef4ff; color: #3657f6; }
  .tone-green .dashboard-stat-icon { background: #ecfdf3; color: #16803d; }
  .tone-amber .dashboard-stat-icon { background: #fffaeb; color: #b54708; }
  .tone-cyan .dashboard-stat-icon { background: #ecfeff; color: #0e7490; }
  .tone-slate .dashboard-stat-icon { background: #f3f4f6; color: #475467; }
  .tone-indigo .dashboard-stat-icon { background: #eef2ff; color: #4f46e5; }
  .tone-red .dashboard-stat-icon { background: #fff1f2; color: #be123c; }
  .dashboard-section-grid { display: grid; grid-template-columns: minmax(0, 1fr) 360px; gap: .9rem; align-items: start; }
  .dashboard-panel { border: 0; border-radius: 10px; background: rgba(255,255,255,.78); box-shadow: none; }
  .dashboard-panel .card-body { padding: 1rem; }
  .dashboard-panel-head { display: flex; justify-content: space-between; align-items: center; gap: .75rem; flex-wrap: wrap; }
  .dashboard-panel-title { color: #1f2937; font-size: .92rem; font-weight: 820; margin: 0; }
  .dashboard-chart-form { display: flex; align-items: center; gap: .45rem; flex-wrap: wrap; }
  .dashboard-chart-form .form-control { width: 145px; height: 34px; border-radius: 4px; border-color: #eaecf0; font-size: .78rem; }
  .dashboard-chart-form .btn { height: 34px; border-radius: 4px; font-size: .78rem; font-weight: 680; }
  .dashboard-chart-wrap { position: relative; height: 310px; margin-top: .9rem; }
  .dashboard-chart-empty { height: 310px; display: flex; align-items: center; justify-content: center; border: 1px dashed #d0d5dd; border-radius: 8px; color: #667085; font-size: .82rem; background: rgba(255,255,255,.58); margin-top: .9rem; text-align: center; padding: 1rem; }
  .dashboard-list { display: grid; gap: .55rem; margin-top: .8rem; }
  .dashboard-list-item { display: flex; align-items: center; justify-content: space-between; gap: .75rem; border: 1px solid #eef2f6; background: #fff; border-radius: 6px; padding: .7rem .75rem; text-decoration: none; color: #1f2937; }
  .dashboard-list-item:hover { background: #f8fbff; border-color: #dbe7ff; }
  .dashboard-list-left { display: inline-flex; align-items: center; gap: .55rem; min-width: 0; }
  .dashboard-list-left i { color: #3657f6; }
  .dashboard-list-label { font-size: .8rem; font-weight: 720; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .dashboard-list-count { color: #344054; font-size: .9rem; font-weight: 850; }
  .dashboard-shortcuts { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .55rem; margin-top: .8rem; }
  .dashboard-shortcut { display: flex; align-items: center; gap: .55rem; min-height: 38px; border: 1px solid #eef2f6; background: #fff; border-radius: 6px; padding: .55rem .65rem; text-decoration: none; color: #344054; font-size: .78rem; font-weight: 680; }
  .dashboard-shortcut:hover { background: #f8fbff; border-color: #dbe7ff; color: #1f2937; }
  .dashboard-shortcut i { color: #3657f6; }
  @media (max-width: 1200px) { .dashboard-grid { grid-template-columns: repeat(3, minmax(150px, 1fr)); } .dashboard-section-grid { grid-template-columns: 1fr; } }
  @media (max-width: 767px) { .dashboard-head { flex-direction: column; } .dashboard-head-actions, .dashboard-user-chip, .dashboard-download-btn { width: 100%; justify-content: center; } .dashboard-grid { grid-template-columns: 1fr; } .dashboard-shortcuts { grid-template-columns: 1fr; } .dashboard-chart-form, .dashboard-chart-form .form-control, .dashboard-chart-form .btn { width: 100%; } }
</style>

<div class="container-fluid py-3">
  <div class="dashboard-shell">
    <div class="dashboard-head">
      <div>
        <div class="dashboard-eyebrow">IMS Workspace</div>
        <h2 class="dashboard-title">Dashboard</h2>
        <div class="dashboard-subtitle">Overview of inventory, request, service, and transmittal records.</div>
      </div>
      <div class="dashboard-head-actions">
        <a href="<?= e($userManualDownloadUrl) ?>" download class="btn btn-primary dashboard-download-btn">
          <i class="bi bi-download"></i>
          Download Manual
        </a>
        <div class="dashboard-user-chip">
          <i class="bi bi-person-circle"></i>
          <?= e($displayName) ?> - <?= e($displayRole) ?>
        </div>
      </div>
    </div>

    <div class="dashboard-grid">
      <?php foreach ($tiles as $label => $d): ?>
        <a href="<?= e($d['href']) ?>" class="dashboard-stat tone-<?= e($d['tone']) ?>">
          <div class="dashboard-stat-top">
            <div class="dashboard-stat-label"><?= e($label) ?></div>
            <div class="dashboard-stat-icon"><i class="bi bi-<?= e($d['icon']) ?>"></i></div>
          </div>
          <div class="dashboard-stat-value"><?= number_format((int) $d['count']) ?></div>
        </a>
      <?php endforeach; ?>
    </div>

    <div class="card dashboard-panel">
      <div class="card-body">
        <div class="dashboard-panel-head">
          <div>
            <h3 class="dashboard-panel-title">Equipment Status Trend</h3>
            <div class="dashboard-subtitle">Monthly equipment counts grouped by current equipment status.</div>
          </div>
          <form method="get" class="dashboard-chart-form">
            <input type="month" class="form-control" name="equipment_chart_from" value="<?= e($chartFromMonth) ?>" aria-label="From month">
            <input type="month" class="form-control" name="equipment_chart_to" value="<?= e($chartToMonth) ?>" aria-label="To month">
            <button type="submit" class="btn btn-primary"><i class="bi bi-funnel"></i> Apply</button>
          </form>
        </div>
        <?php if (!empty($equipmentChartData['datasets'])): ?>
          <div class="dashboard-chart-wrap">
            <canvas id="equipmentStatusTrendChart"></canvas>
          </div>
        <?php else: ?>
          <div class="dashboard-chart-empty">No equipment status data found for the selected month range.</div>
        <?php endif; ?>
      </div>
    </div>

    <div class="dashboard-section-grid">
      <div class="card dashboard-panel">
        <div class="card-body">
          <h3 class="dashboard-panel-title">Request And Service Summary</h3>
          <div class="dashboard-list">
            <?php foreach ($requestSummary as $item): ?>
              <a href="<?= e($item[2]) ?>" class="dashboard-list-item">
                <span class="dashboard-list-left">
                  <i class="bi bi-<?= e($item[3]) ?>"></i>
                  <span class="dashboard-list-label"><?= e($item[0]) ?></span>
                </span>
                <span class="dashboard-list-count"><?= number_format((int) $item[1]) ?></span>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div class="card dashboard-panel">
        <div class="card-body">
          <h3 class="dashboard-panel-title">Quick Navigation</h3>
          <div class="dashboard-shortcuts">
            <?php foreach ($shortcuts as $m): ?>
              <a href="<?= e($m[1]) ?>" class="dashboard-shortcut"<?= !empty($m[3]) ? ' download' : '' ?>>
                <i class="bi bi-<?= e($m[2]) ?>"></i>
                <span><?= e($m[0]) ?></span>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const chartElement = document.getElementById('equipmentStatusTrendChart');
  const chartData = <?= json_encode($equipmentChartData, JSON_UNESCAPED_SLASHES) ?>;
  if (!chartElement || !window.Chart || !chartData.datasets.length) {
    return;
  }
  new Chart(chartElement, {
    type: 'line',
    data: chartData,
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: {
          position: 'bottom',
          labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true, font: { size: 11 } }
        },
        tooltip: { callbacks: { label: context => `${context.dataset.label}: ${context.parsed.y}` } }
      },
      scales: {
        x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkip: true } },
        y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: 'rgba(16,24,40,.08)' } }
      }
    }
  });
});
</script>

<?php require_once ROOT_PATH . '/modules/layout/footer.php'; ?>
