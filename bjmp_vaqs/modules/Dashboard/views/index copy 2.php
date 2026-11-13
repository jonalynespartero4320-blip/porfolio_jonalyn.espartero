<?php
include ROOT_PATH.'/modules/layout/header.php'; 
require_once ROOT_PATH . '/config/database.php';

$user = current_user();

// ===============================
// Monthly Requests Data
// ===============================
$monthly = [];

$res = $pdo->query("
    SELECT MONTH(request_time) m, COUNT(*) t
    FROM queuing_requests
    WHERE YEAR(request_time)=YEAR(CURDATE())
    GROUP BY m
");

while($r=$res->fetch(PDO::FETCH_ASSOC)){
    $monthly[$r['m']]=$r['t'];
}

// ===============================
// Today's Visitors Sex Data
// ===============================
$male=0;
$female=0;

$res = $pdo->query("
    SELECT v.sex, COUNT(*) t
    FROM queuing_requests qr
    JOIN visitors_registration v ON v.id=qr.visitor_id
    WHERE DATE(qr.request_time)=CURDATE()
    GROUP BY v.sex
");

while($r=$res->fetch(PDO::FETCH_ASSOC)){
    if($r['sex']=="Male") $male=$r['t'];
    if($r['sex']=="Female") $female=$r['t'];
}

// ===============================
// PERMISSION CHECK
// ===============================
$hasPermissions = !empty($_SESSION['permissions']);
$canViewDashboard = can('dashboard.view');
?>

<div class="container-fluid py-3">

    <!-- HEADER -->
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <h4 class="fw-bold mb-1">Dashboard</h4>
            <p class="text-muted mb-0">
                Welcome back, <?= e($user['name'] ?? $user['username']) ?> 
                — <span class="badge bg-success"><?= e($user['role_name']) ?></span>
            </p>
        </div>
    </div>

    <?php if (!$hasPermissions || !$canViewDashboard): ?>

        <!-- 🔥 NO ACCESS MESSAGE -->
        <div class="card shadow-sm border-0 text-center py-5">
            <div class="card-body">
                <h5 class="text-warning mb-3">
                    <i class="bi bi-exclamation-circle"></i> Limited Access
                </h5>
                <p class="text-muted mb-2">
                    Your account is active, but no modules are currently assigned to your role.
                </p>
                <p class="text-muted">
                    Please wait while your access is being configured, or contact your system administrator.
                </p>
            </div>
        </div>

    <?php else: ?>

        <!-- STATS CARDS -->
        <div class="row g-3 mb-3">

            <div class="col-12 col-md-6 col-lg-4">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <h6 class="text-muted">Today's Male Visitors</h6>
                        <h3 class="fw-bold text-success"><?= $male ?></h3>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-6 col-lg-4">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <h6 class="text-muted">Today's Female Visitors</h6>
                        <h3 class="fw-bold text-danger"><?= $female ?></h3>
                    </div>
                </div>
            </div>

        </div>

        <!-- CHARTS -->
        <div class="row g-3 mb-3">

            <div class="col-12 col-lg-6">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <h6 class="mb-3">Monthly Requests (<?= date('Y') ?>)</h6>
                        <canvas id="monthlyChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-6">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <h6 class="mb-3">Today's Visitors</h6>
                        <canvas id="sexChart"></canvas>
                    </div>
                </div>
            </div>

        </div>

        <!-- MODULE SHORTCUTS -->
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h6 class="mb-3">System Modules</h6>

                <div class="row g-2">

                    <?php if (can('queue.view')): ?>
                    <div class="col-6 col-md-4 col-lg-2">
                        <a class="btn btn-outline-success w-100" href="<?= BASE_URL ?>/queue">Queue</a>
                    </div>
                    <?php endif; ?>

                    <?php if (can('visitors.view')): ?>
                    <div class="col-6 col-md-4 col-lg-2">
                        <a class="btn btn-outline-success w-100" href="<?= BASE_URL ?>/visitors">Visitors</a>
                    </div>
                    <?php endif; ?>

                    <?php if (can('requests.view')): ?>
                    <div class="col-6 col-md-4 col-lg-2">
                        <a class="btn btn-outline-success w-100" href="<?= BASE_URL ?>/requests">Requests</a>
                    </div>
                    <?php endif; ?>

                    <?php if (can('users.view')): ?>
                    <div class="col-6 col-md-4 col-lg-2">
                        <a class="btn btn-outline-success w-100" href="<?= BASE_URL ?>/users">Users</a>
                    </div>
                    <?php endif; ?>

                    <?php if (can('reports.view')): ?>
                    <div class="col-6 col-md-4 col-lg-2">
                        <a class="btn btn-outline-success w-100" href="<?= BASE_URL ?>/reports">Reports</a>
                    </div>
                    <?php endif; ?>

                    <?php if (can('products.view')): ?>
                    <div class="col-6 col-md-4 col-lg-2">
                        <a class="btn btn-outline-success w-100" href="<?= BASE_URL ?>/products">Products</a>
                    </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>

    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Monthly Chart
new Chart(document.getElementById('monthlyChart'), {
    type: 'doughnut',
    data: {
        labels: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
        datasets: [{
            data: [<?= implode(",", array_map(fn($i) => $monthly[$i] ?? 0, range(1,12))) ?>],
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'bottom' } }
    }
});

// Sex Chart
new Chart(document.getElementById('sexChart'), {
    type: 'pie',
    data: {
        labels: ['Male','Female'],
        datasets: [{
            data: [<?= $male ?>, <?= $female ?>],
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'bottom' } }
    }
});
</script>

<?php include ROOT_PATH.'/modules/layout/footer.php'; ?>