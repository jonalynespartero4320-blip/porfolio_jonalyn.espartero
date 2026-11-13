<?php
include ROOT_PATH . '/modules/layout/header.php';
require_once ROOT_PATH . '/config/database.php';

$user = current_user();
$sessionUser = $_SESSION['user'] ?? [];
$displayName = trim((string) ($user['name'] ?? $sessionUser['name'] ?? $user['username'] ?? $sessionUser['username'] ?? 'User'));
$displayRole = trim((string) ($user['role_name'] ?? $sessionUser['role_name'] ?? 'User'));

$monthly = [];
$monthlyStmt = $pdo->query("
    SELECT MONTH(request_time) AS m, COUNT(*) AS t
    FROM queuing_requests
    WHERE YEAR(request_time) = YEAR(CURDATE())
    GROUP BY MONTH(request_time)
");
while ($row = $monthlyStmt->fetch(PDO::FETCH_ASSOC)) {
    $monthly[(int) $row['m']] = (int) $row['t'];
}

$male = 0;
$female = 0;
$sexStmt = $pdo->query("
    SELECT v.sex, COUNT(*) AS t
    FROM queuing_requests qr
    JOIN visitors_registration v ON v.id = qr.visitor_id
    WHERE DATE(qr.request_time) = CURDATE()
    GROUP BY v.sex
");
while ($row = $sexStmt->fetch(PDO::FETCH_ASSOC)) {
    if (($row['sex'] ?? '') === 'Male') {
        $male = (int) $row['t'];
    }
    if (($row['sex'] ?? '') === 'Female') {
        $female = (int) $row['t'];
    }
}

$todaySummaryStmt = $pdo->query("
    SELECT
        COUNT(*) AS total_today,
        SUM(status = 'pending') AS pending_today,
        SUM(status = 'inside') AS inside_today,
        SUM(status = 'completed') AS completed_today,
        SUM(status = 'rejected') AS rejected_today
    FROM queuing_requests
    WHERE DATE(request_time) = CURDATE()
");
$todaySummary = $todaySummaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$verifiedVisitorsStmt = $pdo->query("
    SELECT COUNT(*) AS total_verified
    FROM visitors_registration
    WHERE visitor_status = 'Verified'
");
$totalVerifiedVisitors = (int) (($verifiedVisitorsStmt->fetch(PDO::FETCH_ASSOC)['total_verified'] ?? 0));

$latestAdmissionsStmt = $pdo->query("
    SELECT
        qr.id,
        qr.status,
        qr.request_time,
        qr.accepted_time,
        qr.rejected_time,
        qr.id_type,
        vr.firstname,
        vr.middlename,
        vr.lastname,
        vr.suffix
    FROM queuing_requests qr
    JOIN visitors_registration vr ON vr.id = qr.visitor_id
    ORDER BY qr.id DESC
    LIMIT 6
");
$latestAdmissions = $latestAdmissionsStmt->fetchAll(PDO::FETCH_ASSOC);

$isSuperAdmin = is_super_admin();
$hasPermissions = !empty($_SESSION['permissions']);
$canViewDashboard = $isSuperAdmin || can('dashboard.view');
$showLimitedAccess = !$isSuperAdmin && (!$hasPermissions || !$canViewDashboard);

$totalToday = (int) ($todaySummary['total_today'] ?? 0);
$pendingToday = (int) ($todaySummary['pending_today'] ?? 0);
$insideToday = (int) ($todaySummary['inside_today'] ?? 0);
$completedToday = (int) ($todaySummary['completed_today'] ?? 0);
$rejectedToday = (int) ($todaySummary['rejected_today'] ?? 0);
$monthTotal = array_sum($monthly);

$moduleCards = [
    [
        'permission' => 'queue.view',
        'label' => 'Queue Control',
        'subtitle' => 'Manage admissions, scanning, and time-out flow.',
        'href' => BASE_URL . '/queue',
        'icon' => 'bi-upc-scan',
        'accent' => 'success',
    ],
    [
        'permission' => 'visitors.view',
        'label' => 'Visitor Registration',
        'subtitle' => 'Register and verify authorized visitors.',
        'href' => BASE_URL . '/visitor-registration',
        'icon' => 'bi-person-vcard',
        'accent' => 'primary',
    ],
    [
        'permission' => 'visitors.view',
        'label' => 'Visitor Management',
        'subtitle' => 'Review visitor records, details, and bans.',
        'href' => BASE_URL . '/visitor-management',
        'icon' => 'bi-people-fill',
        'accent' => 'warning',
    ],
    [
        'permission' => 'reports.view',
        'label' => 'System Logs',
        'subtitle' => 'Monitor routes, activities, and admin actions.',
        'href' => BASE_URL . '/activity-logs',
        'icon' => 'bi-journal-text',
        'accent' => 'secondary',
    ],
    [
        'permission' => 'users.view',
        'label' => 'User Administration',
        'subtitle' => 'Manage roles, accounts, and permissions.',
        'href' => BASE_URL . '/admin',
        'icon' => 'bi-person-gear',
        'accent' => 'info',
    ],
];
?>

<style>
  .dashboard-shell {
    background:
      radial-gradient(circle at top left, rgba(54, 87, 246, 0.12), transparent 30%),
      radial-gradient(circle at top right, rgba(37, 99, 235, 0.12), transparent 32%),
      linear-gradient(180deg, rgba(255, 255, 255, 0.86) 0%, rgba(248, 250, 252, 0.96) 100%);
    border-radius: 28px;
    padding: 24px;
  }
  .dashboard-hero {
     background:
      radial-gradient(circle at top right, rgba(59, 130, 246, 0.18), transparent 28%),
      radial-gradient(circle at bottom left, rgba(31, 111, 229, 0.14), transparent 30%),
      linear-gradient(135deg, #111827 0%, #1d4ed8 100%);
    color: #fff;
    border: 0;
    border-radius: 28px;
    overflow: hidden;
    position: relative;
  }
  .dashboard-hero::after {
    content: "";
    position: absolute;
    inset: auto -60px -80px auto;
    width: 220px;
    height: 220px;
    border-radius: 50%;
    background: rgba(255,255,255,0.10);
  }
  .dashboard-kpi {
    border: 0;
    border-radius: 22px;
    box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
    overflow: hidden;
  }
  .dashboard-kpi .card-body {
    padding: 20px;
  }
  .kpi-label {
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #64748b;
    font-size: 0.76rem;
    font-weight: 800;
    margin-bottom: 8px;
  }
  .kpi-value {
    font-size: clamp(1.7rem, 2.5vw, 2.6rem);
    font-weight: 800;
    line-height: 1;
    margin-bottom: 8px;
  }
  .kpi-meta {
    color: #64748b;
    font-weight: 600;
    font-size: 0.92rem;
  }
  .analytics-card,
  .module-card,
  .activity-card,
  .limited-card {
    border: 0;
    border-radius: 24px;
    box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
  }
  .chart-wrap {
    position: relative;
    min-height: 320px;
  }
  .module-card {
    transition: transform .18s ease, box-shadow .18s ease;
  }
  .module-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 24px 46px rgba(15, 23, 42, 0.12);
  }
  .module-icon {
    width: 48px;
    height: 48px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 14px;
    font-size: 1.35rem;
  }
  .activity-status {
    min-width: 88px;
    text-align: center;
    font-size: 0.78rem;
    font-weight: 700;
    border-radius: 999px;
    padding: 6px 10px;
  }
  .hero-badge {
    border: 1px solid rgba(255,255,255,0.18);
    background: rgba(255,255,255,0.10);
    border-radius: 999px;
    color: #fff;
    padding: 9px 14px;
    font-weight: 700;
  }
  .hero-mini {
    border-radius: 20px;
    background: rgba(255,255,255,0.10);
    border: 1px solid rgba(255,255,255,0.14);
    padding: 16px;
  }
</style>

<div class="container-fluid py-3">
    <div class="dashboard-shell">
        <div class="card dashboard-hero shadow-sm mb-4">
            <div class="card-body p-4 p-lg-5">
                <div class="row align-items-center g-4">
                    <div class="col-lg-8">
                        <div class="d-flex align-items-center gap-2 flex-wrap mb-3">
                            <span class="hero-badge"><i class="bi bi-shield-check me-1"></i><?= strtoupper(e($displayRole)) ?></span>
                            <span class="hero-badge"><i class="bi bi-calendar-event me-1"></i><?= date('F d, Y') ?></span>
                        </div>
                        <h1 class="fw-bold mb-2" style="font-size:clamp(1.9rem,3vw,3.2rem);">
                            Dashboard Control Center
                        </h1>
                        <p class="mb-0" style="max-width:720px; color:rgba(255,255,255,0.88); font-size:1.02rem;">
                            Welcome back, <?= e($displayName) ?>. Here’s the live pulse of visitor admissions, queue flow, and operational access across the system.
                        </p>
                    </div>
                    <div class="col-lg-4">
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="hero-mini">
                                    <div class="small text-white-50 mb-1">Today's Requests</div>
                                    <div class="fs-2 fw-bold"><?= $totalToday ?></div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="hero-mini">
                                    <div class="small text-white-50 mb-1">Verified Visitors</div>
                                    <div class="fs-2 fw-bold"><?= $totalVerifiedVisitors ?></div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="hero-mini">
                                    <div class="small text-white-50 mb-1">Inside Now</div>
                                    <div class="fs-2 fw-bold"><?= $insideToday ?></div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="hero-mini">
                                    <div class="small text-white-50 mb-1">Month Volume</div>
                                    <div class="fs-2 fw-bold"><?= $monthTotal ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($showLimitedAccess): ?>
            <div class="card limited-card text-center py-5">
                <div class="card-body">
                    <h5 class="text-warning mb-3">
                        <i class="bi bi-exclamation-circle me-1"></i> Limited Access
                    </h5>
                    <p class="text-muted mb-2">
                        Your account is active, but no modules are currently assigned to your role.
                    </p>
                    <p class="text-muted mb-0">
                        Please wait while your access is being configured, or contact your system administrator.
                    </p>
                </div>
            </div>
        <?php else: ?>
            <div class="row g-3 mb-4">
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="card dashboard-kpi">
                        <div class="card-body">
                            <div class="kpi-label">Pending Admissions</div>
                            <div class="kpi-value text-primary"><?= $pendingToday ?></div>
                            <div class="kpi-meta">Waiting for acceptance today</div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="card dashboard-kpi">
                        <div class="card-body">
                            <div class="kpi-label">Inside Admissions</div>
                            <div class="kpi-value text-success"><?= $insideToday ?></div>
                            <div class="kpi-meta">Visitors currently processed today</div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="card dashboard-kpi">
                        <div class="card-body">
                            <div class="kpi-label">Completed Today</div>
                            <div class="kpi-value text-dark"><?= $completedToday ?></div>
                            <div class="kpi-meta">Successfully finished admissions</div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="card dashboard-kpi">
                        <div class="card-body">
                            <div class="kpi-label">Rejected Today</div>
                            <div class="kpi-value text-danger"><?= $rejectedToday ?></div>
                            <div class="kpi-meta">Rejected or blocked queue entries</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-12 col-xl-7">
                    <div class="card analytics-card h-100">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                <div>
                                    <h5 class="fw-bold mb-1">Monthly Admission Flow</h5>
                                    <small class="text-muted">Queue requests grouped by month for <?= date('Y') ?>.</small>
                                </div>
                                <span class="badge bg-success-subtle text-success-emphasis border"><?= $monthTotal ?> total requests</span>
                            </div>
                            <div class="chart-wrap">
                                <canvas id="monthlyChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-xl-5">
                    <div class="card analytics-card h-100">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                <div>
                                    <h5 class="fw-bold mb-1">Today’s Visitor Mix</h5>
                                    <small class="text-muted">Admission count by sex based on queue requests today.</small>
                                </div>
                                <span class="badge bg-primary-subtle text-primary-emphasis border"><?= $male + $female ?> tracked today</span>
                            </div>
                            <div class="chart-wrap">
                                <canvas id="sexChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-12 col-xl-7">
                    <div class="card activity-card h-100">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                <div>
                                    <h5 class="fw-bold mb-1">Recent Queue Activity</h5>
                                    <small class="text-muted">Latest queue actions to help staff monitor admission flow.</small>
                                </div>
                                <a href="<?= BASE_URL ?>/queue-monitor" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-activity me-1"></i> Open Live Monitor
                                </a>
                            </div>

                            <div class="table-responsive">
                                <table class="table align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Visitor</th>
                                            <th>Status</th>
                                            <th>Timestamp</th>
                                            <th>ID Type</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($latestAdmissions)): ?>
                                            <tr>
                                                <td colspan="4" class="text-center text-muted py-4">No queue activity recorded yet.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($latestAdmissions as $activity): ?>
                                                <?php
                                                $visitorName = preg_replace('/\s+/', ' ', trim(($activity['firstname'] ?? '') . ' ' . ($activity['middlename'] ?? '') . ' ' . ($activity['lastname'] ?? '') . ' ' . ($activity['suffix'] ?? '')));
                                                $status = strtolower(trim((string) ($activity['status'] ?? '')));
                                                $statusClass = 'bg-secondary-subtle text-secondary-emphasis';
                                                if ($status === 'pending') $statusClass = 'bg-primary-subtle text-primary-emphasis';
                                                if ($status === 'inside') $statusClass = 'bg-success-subtle text-success-emphasis';
                                                if ($status === 'completed') $statusClass = 'bg-dark-subtle text-dark-emphasis';
                                                if ($status === 'rejected') $statusClass = 'bg-danger-subtle text-danger-emphasis';
                                                $activityTime = $activity['rejected_time'] ?: ($activity['accepted_time'] ?: ($activity['request_time'] ?? ''));
                                                ?>
                                                <tr>
                                                    <td>
                                                        <div class="fw-semibold"><?= e($visitorName !== '' ? $visitorName : 'Unknown Visitor') ?></div>
                                                    </td>
                                                    <td>
                                                        <span class="activity-status <?= $statusClass ?>">
                                                            <?= strtoupper(e($status !== '' ? $status : 'unknown')) ?>
                                                        </span>
                                                    </td>
                                                    <td><?= e($activityTime) ?></td>
                                                    <td><?= e($activity['id_type'] ?? '-') ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-xl-5">
                    <div class="card activity-card h-100">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-1">Operational Snapshot</h5>
                            <small class="text-muted d-block mb-4">A quick summary of today’s admissions and verified visitor readiness.</small>

                            <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                <span class="text-muted">Total Requests Today</span>
                                <span class="fw-bold"><?= $totalToday ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                <span class="text-muted">Male Visitors Today</span>
                                <span class="fw-bold text-success"><?= $male ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                <span class="text-muted">Female Visitors Today</span>
                                <span class="fw-bold text-danger"><?= $female ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                <span class="text-muted">Verified Visitors Available</span>
                                <span class="fw-bold"><?= $totalVerifiedVisitors ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center py-2">
                                <span class="text-muted">Monthly Request Volume</span>
                                <span class="fw-bold"><?= $monthTotal ?></span>
                            </div>

                            <div class="mt-4 p-3 rounded-4" style="background:#f8fafc; border:1px solid #e2e8f0;">
                                <div class="fw-semibold mb-1">Recommended Action</div>
                                <div class="text-muted small">
                                    <?= $pendingToday > 0
                                        ? 'There are pending queue entries waiting for staff review. Open Queue Control to process admissions.'
                                        : 'Queue activity is stable right now. You can review the live monitor or check module records as needed.' ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card activity-card">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <div>
                            <h5 class="fw-bold mb-1">System Modules</h5>
                            <small class="text-muted">Jump straight into the parts of the system you can access.</small>
                        </div>
                    </div>

                    <div class="row g-3">
                        <?php foreach ($moduleCards as $card): ?>
                            <?php if (!can($card['permission'])) continue; ?>
                            <div class="col-12 col-md-6 col-xl-4">
                                <a href="<?= e($card['href']) ?>" class="card module-card text-decoration-none h-100">
                                    <div class="card-body p-4">
                                        <div class="d-flex align-items-start justify-content-between gap-3">
                                            <div>
                                                <span class="module-icon bg-<?= e($card['accent']) ?>-subtle text-<?= e($card['accent']) ?>-emphasis mb-3">
                                                    <i class="bi <?= e($card['icon']) ?>"></i>
                                                </span>
                                                <h6 class="fw-bold text-dark mb-2"><?= e($card['label']) ?></h6>
                                                <p class="text-muted mb-0 small"><?= e($card['subtitle']) ?></p>
                                            </div>
                                            <i class="bi bi-arrow-up-right text-muted"></i>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
if (document.getElementById('monthlyChart')) {
    new Chart(document.getElementById('monthlyChart'), {
        type: 'bar',
        data: {
            labels: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
            datasets: [{
                label: 'Requests',
                data: [<?= implode(',', array_map(fn($i) => $monthly[$i] ?? 0, range(1, 12))) ?>],
                backgroundColor: ['#3657f6','#1f6fe5','#2563eb','#1d4ed8','#38bdf8','#0ea5e9','#14b8a6','#06b6d4','#60a5fa','#93c5fd','#64748b','#475569'],
                borderRadius: 10,
                borderSkipped: false
            }]
        },
        options: {
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0 },
                    grid: { color: 'rgba(148, 163, 184, 0.18)' }
                },
                x: {
                    grid: { display: false }
                }
            }
        }
    });
}

if (document.getElementById('sexChart')) {
    new Chart(document.getElementById('sexChart'), {
        type: 'doughnut',
        data: {
            labels: ['Male', 'Female'],
            datasets: [{
                data: [<?= $male ?>, <?= $female ?>],
                backgroundColor: ['#3657f6', '#dc2626'],
                borderWidth: 0,
                hoverOffset: 8
            }]
        },
        options: {
            maintainAspectRatio: false,
            cutout: '66%',
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });
}
</script>

<?php include ROOT_PATH . '/modules/layout/footer.php'; ?>
