<?php

if (!function_exists('route_redirect_with_notice')) {
    function route_redirect_with_notice(string $path, string $type, string $message): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['notification'] = [
            'type' => $type,
            'message' => $message,
        ];

        header('Location: ' . BASE_URL . $path);
        exit;
    }
}

if (!function_exists('render_ictd_module_page')) {
    function render_ictd_module_page(array $modulePage): void
    {
        require ROOT_PATH . '/modules/Pages/module-placeholder.php';
    }
}

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Strip the exact application mount path, including the portfolio directory.
if (BASE_URL !== '' && ($uri === BASE_URL || strpos($uri, BASE_URL . '/') === 0)) {
    $uri = substr($uri, strlen(BASE_URL));
}

// Normalize
$uri = rtrim($uri, '/') ?: '/';

switch ($uri) {

    /* ---------- AUTH ---------- */

    case '/':
    case '':
        require ROOT_PATH . '/modules/Auth/views/login.php';
        break;

    case '/login':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            require ROOT_PATH . '/modules/Auth/controllers/AuthController.php';
            AuthController::login();
        } else {
            require ROOT_PATH . '/modules/Auth/views/login.php';
        }
        break;

    case '/register':
        require ROOT_PATH . '/modules/Auth/views/register.php';
        break;

    case '/register-save':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            require ROOT_PATH . '/modules/Auth/controllers/AuthController.php';
            AuthController::register();
        }
        break;

    case '/reset-request':
        require ROOT_PATH . '/modules/Auth/views/reset_request.php';
        break;

    case '/reset-request-save':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            require ROOT_PATH . '/modules/Auth/controllers/AuthController.php';
            AuthController::resetRequest();
        }
        break;

    case '/handle-reset-request':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            require ROOT_PATH . '/modules/Auth/controllers/AuthController.php';
            AuthController::handleResetRequest();
        }
        break;

    case '/change-password':
        require_login();
        log_route_activity($uri, 'PAGE_VIEW');
        require ROOT_PATH . '/modules/Auth/views/change_password.php';
        break;

    case '/change-password-save':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            require ROOT_PATH . '/modules/Auth/controllers/AuthController.php';
            AuthController::changePassword();
        }
        break;

    case '/account':
        require_login();
        log_route_activity($uri, 'PAGE_VIEW');
        require ROOT_PATH . '/modules/Auth/views/account.php';
        break;

    case '/account-save':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            require ROOT_PATH . '/modules/Auth/controllers/AuthController.php';
            AuthController::updateAccount();
        }
        break;

    /* ---------- DASHBOARD ---------- */

    case '/dashboard':
        require_login();
        require_permission('ictd.dashboard.view');
        log_route_activity($uri, 'PAGE_VIEW');
        require ROOT_PATH . '/modules/Pages/dashboard/index.php';
        break;

  

    /* ---------- ADMIN ---------- */

    case '/admin':
        require_login();
        require_permission('users.view');
        log_route_activity($uri, 'PAGE_VIEW');
        require ROOT_PATH . '/modules/Users/views/admin.php';
        break;

    case '/get-user-permissions':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            require ROOT_PATH . '/modules/Auth/controllers/UserController.php';
            UserController::getUserPermissions();
        }
        break;

    case '/assign-role-permissions':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            require ROOT_PATH . '/modules/Auth/controllers/UserController.php';
            UserController::assignRolePermissions();
        }
        break;

    /* ---------- ICTD MIGRATION ---------- */

    case '/ictd':
    case '/ictd/dashboard':
        require_login();
        require_permission('ictd.dashboard.view');
        log_route_activity($uri, 'PAGE_VIEW');
        require ROOT_PATH . '/modules/Pages/dashboard/index.php';
        break;

    case '/ictd/equipment':
        require_login();
        require_permission('ictd.equipment.view');
        log_route_activity($uri, 'PAGE_VIEW');
        require ROOT_PATH . '/modules/Pages/equipment/equipment.php';
        break;

    case '/ictd/equipment-data':
        require_login();
        require_permission('ictd.equipment.view');
        require ROOT_PATH . '/modules/Pages/equipment/equipment-data.php';
        break;

    case '/ictd/equipment-action':
        require_login();
        require ROOT_PATH . '/modules/Pages/equipment/equipment-action.php';
        break;

    case '/ictd/equipment-archive-data':
        require_login();
        require_permission('ictd.equipment.archive');
        require ROOT_PATH . '/modules/Pages/equipment/equipment-archive-data.php';
        break;

    case '/ictd/supplies':
        require_login();
        require_permission('ictd.supplies.view');
        log_route_activity($uri, 'PAGE_VIEW');
        require ROOT_PATH . '/modules/Pages/supplies/supplies.php';
        break;

    case '/ictd/supplies-data':
        require_login();
        require_permission('ictd.supplies.view');
        require ROOT_PATH . '/modules/Pages/supplies/supplies-data.php';
        break;

    case '/ictd/supplies-archive-data':
        require_login();
        require_permission('ictd.supplies.view');
        require ROOT_PATH . '/modules/Pages/supplies/supplies-archive-data.php';
        break;

    case '/ictd/supplies-action':
        require_login();
        require ROOT_PATH . '/modules/Pages/supplies/supplies-action.php';
        break;

    case '/ictd/supplies/archive':
        require_login();
        require_permission('ictd.supplies.view');
        log_route_activity($uri, 'PAGE_VIEW');
        require ROOT_PATH . '/modules/Pages/supplies/supplies-archive.php';
        break;

    case '/ictd/supplies/import':
        require_login();
        require_permission('ictd.supplies.view');
        log_route_activity($uri, 'PAGE_VIEW');
        $importModuleKey = 'supplies';
        require ROOT_PATH . '/modules/Pages/import/import.php';
        break;

    case '/ictd/requests/modules':
    case '/ictd/requests':
        require_login();
        require_permission('ictd.request.view');
        log_route_activity($uri, 'PAGE_VIEW');
        require ROOT_PATH . '/modules/Pages/request-modules/request-modules.php';
        break;

    case '/ictd/requests/equipment':
        require_login();
        require_permission('ictd.request.view');
        log_route_activity($uri, 'PAGE_VIEW');
        require ROOT_PATH . '/modules/Pages/request-equipment/request-equipment.php';
        break;

    case '/ictd/requests/equipment-data':
        require_login();
        require_permission('ictd.request.view');
        require ROOT_PATH . '/modules/Pages/request-equipment/request-equipment-data.php';
        break;

    case '/ictd/requests/equipment-action':
        require_login();
        require_permission('ictd.request.view');
        require ROOT_PATH . '/modules/Pages/request-equipment/request-equipment-action.php';
        break;

    case '/ictd/requests/service':
        require_login();
        require_permission('ictd.request.view');
        log_route_activity($uri, 'PAGE_VIEW');
        require ROOT_PATH . '/modules/Pages/request-service/service-request.php';
        break;

    case '/ictd/requests/service-data':
        require_login();
        require_permission('ictd.request.view');
        require ROOT_PATH . '/modules/Pages/request-service/service-request-data.php';
        break;

    case '/ictd/requests/service-action':
        require_login();
        require_any_permission(['ictd.request.view', 'ictd.request.manage']);
        require ROOT_PATH . '/modules/Pages/request-service/service-request-action.php';
        break;

    case '/ictd/requests/status':
        require_login();
        require_permission('ictd.request.view');
        log_route_activity($uri, 'PAGE_VIEW');
        render_ictd_module_page([
            'title' => 'Request Status',
            'subtitle' => 'Track all request statuses in one workspace.',
            'icon' => 'bi-list-check',
            'badge' => 'Requests',
        ]);
        break;

    case '/ictd/requests/equipment-status':
        require_login();
        require_any_permission(['ictd.request.manage', 'ictd.request.view']);
        log_route_activity($uri, 'PAGE_VIEW');
        require ROOT_PATH . '/modules/Pages/request-equipment/request-equipment-status.php';
        break;

    case '/ictd/requests/equipment-status-data':
        require_login();
        require_any_permission(['ictd.request.manage', 'ictd.request.view']);
        require ROOT_PATH . '/modules/Pages/request-equipment/request-equipment-status-data.php';
        break;

    case '/ictd/requests/equipment-status-action':
        require_login();
        require_any_permission(['ictd.request.manage', 'ictd.request.view']);
        require ROOT_PATH . '/modules/Pages/request-equipment/request-equipment-status-action.php';
        break;

    case '/ictd/requests/service-status':
        require_login();
        require_any_permission(['ictd.request.manage', 'ictd.request.view']);
        log_route_activity($uri, 'PAGE_VIEW');
        require ROOT_PATH . '/modules/Pages/request-service/service-request-status.php';
        break;

    case '/ictd/requests/archive':
        require_login();
        require_permission('ictd.request.view');
        log_route_activity($uri, 'PAGE_VIEW');
        render_ictd_module_page([
            'title' => 'Request Archive',
            'subtitle' => 'Review archived equipment and service request records.',
            'icon' => 'bi-archive-fill',
            'badge' => 'Requests',
        ]);
        break;

    case '/ictd/requests/equipment/archive':
        require_login();
        require_permission('ictd.request.view');
        log_route_activity($uri, 'PAGE_VIEW');
        require ROOT_PATH . '/modules/Pages/request-equipment/request-equipment-archive.php';
        break;

    case '/ictd/requests/equipment-archive-data':
        require_login();
        require_permission('ictd.request.view');
        require ROOT_PATH . '/modules/Pages/request-equipment/request-equipment-archive-data.php';
        break;

    case '/ictd/requests/service/archive':
        require_login();
        require_permission('ictd.request.view');
        log_route_activity($uri, 'PAGE_VIEW');
        require ROOT_PATH . '/modules/Pages/request-service/service-request-archive.php';
        break;

    case '/ictd/equipment/manage':
        require_login();
        require_permission('ictd.equipment.view');
        log_route_activity($uri, 'PAGE_VIEW');
        require ROOT_PATH . '/modules/Pages/equipment/equipment.php';
        break;

    case '/ictd/equipment/import':
        require_login();
        require_permission('ictd.equipment.import');
        log_route_activity($uri, 'PAGE_VIEW');
        $importModuleKey = 'equipment';
        require ROOT_PATH . '/modules/Pages/import/import.php';
        break;

    case '/ictd/equipment/archive':
        require_login();
        require_permission('ictd.equipment.archive');
        log_route_activity($uri, 'PAGE_VIEW');
        require ROOT_PATH . '/modules/Pages/equipment/equipment-archive.php';
        break;

    case '/ictd/assign':
        require_login();
        require_permission('ictd.assign.view');
        log_route_activity($uri, 'PAGE_VIEW');
        require ROOT_PATH . '/modules/Pages/assign/assign.php';
        break;

    case '/ictd/assign-data':
        require_login();
        require_permission('ictd.assign.view');
        require ROOT_PATH . '/modules/Pages/assign/assign-data.php';
        break;

    case '/ictd/assign-action':
        require_login();
        require ROOT_PATH . '/modules/Pages/assign/assign-action.php';
        break;

    case '/ictd/assign-history-data':
        require_login();
        require_permission('ictd.assign.view');
        require ROOT_PATH . '/modules/Pages/assign/assign-history-data.php';
        break;

    case '/ictd/assign/history':
        require_login();
        require_permission('ictd.assign.view');
        log_route_activity($uri, 'PAGE_VIEW');
        require ROOT_PATH . '/modules/Pages/assign/assign-history.php';
        break;

    case '/ictd/assign/manage':
        require_login();
        require_permission('ictd.assign.view');
        log_route_activity($uri, 'PAGE_VIEW');
        render_ictd_module_page([
            'title' => 'Manage Assigning',
            'subtitle' => 'Maintain assignment records and linked equipment history.',
            'icon' => 'bi-people-fill',
            'badge' => 'Assign',
        ]);
        break;

    case '/ictd/assign/import':
        require_login();
        require_permission('ictd.assign.view');
        log_route_activity($uri, 'PAGE_VIEW');
        $importModuleKey = 'assign';
        require ROOT_PATH . '/modules/Pages/import/import.php';
        break;

    case '/ictd/assign/archive':
        require_login();
        require_permission('ictd.assign.view');
        log_route_activity($uri, 'PAGE_VIEW');
        require ROOT_PATH . '/modules/Pages/assign/assign-archive.php';
        break;

    case '/ictd/assign-archive-data':
        require_login();
        require_permission('ictd.assign.view');
        require ROOT_PATH . '/modules/Pages/assign/assign-archive-data.php';
        break;

    case '/ictd/service-report':
        require_login();
        require_permission('ictd.service-report.view');
        log_route_activity($uri, 'PAGE_VIEW');
        require ROOT_PATH . '/modules/Pages/service-report/service-report.php';
        break;

    case '/ictd/service-report-data':
        require_login();
        require_permission('ictd.service-report.view');
        require ROOT_PATH . '/modules/Pages/service-report/service-report-data.php';
        break;

    case '/ictd/service-report-action':
        require_login();
        require ROOT_PATH . '/modules/Pages/service-report/service-report-action.php';
        break;

    case '/ictd/service-report-export':
        require_login();
        require_permission('ictd.service-report.view');
        require ROOT_PATH . '/modules/Pages/service-report/service-report-export.php';
        break;

    case '/ictd/service-report/entries':
        require_login();
        require_permission('ictd.service-report.view');
        log_route_activity($uri, 'PAGE_VIEW');
        render_ictd_module_page([
            'title' => 'Service Entries',
            'subtitle' => 'Manage service report entries and documentation.',
            'icon' => 'bi-file-earmark-medical',
            'badge' => 'Operations',
        ]);
        break;

    case '/ictd/service-report/archive':
        require_login();
        require_permission('ictd.service-report.view');
        log_route_activity($uri, 'PAGE_VIEW');
        require ROOT_PATH . '/modules/Pages/service-report/service-report-archive.php';
        break;

    case '/ictd/maintenance':
        require_login();
        require_permission('ictd.maintenance.view');
        log_route_activity($uri, 'PAGE_VIEW');
        require ROOT_PATH . '/modules/Pages/maintenance/maintenance.php';
        break;

    case '/ictd/maintenance-data':
        require_login();
        require_permission('ictd.maintenance.view');
        require ROOT_PATH . '/modules/Pages/maintenance/maintenance-data.php';
        break;

    case '/ictd/maintenance-action':
        require_login();
        require ROOT_PATH . '/modules/Pages/maintenance/maintenance-action.php';
        break;

    case '/ictd/maintenance-export':
        require_login();
        require_permission('ictd.maintenance.view');
        require ROOT_PATH . '/modules/Pages/maintenance/maintenance-export.php';
        break;

    case '/ictd/maintenance/archive':
        require_login();
        require_permission('ictd.maintenance.view');
        log_route_activity($uri, 'PAGE_VIEW');
        require ROOT_PATH . '/modules/Pages/maintenance/maintenance-archive.php';
        break;

    case '/ictd/transmittal':
        require_login();
        require_permission('ictd.transmittal.view');
        log_route_activity($uri, 'PAGE_VIEW');
        require ROOT_PATH . '/modules/Pages/transmittal/transmittal.php';
        break;

    case '/ictd/transmittal-data':
        require_login();
        require_permission('ictd.transmittal.view');
        require ROOT_PATH . '/modules/Pages/transmittal/transmittal-data.php';
        break;

    case '/ictd/transmittal-action':
        require_login();
        require ROOT_PATH . '/modules/Pages/transmittal/transmittal-action.php';
        break;

    case '/ictd/transmittal-export':
        require_login();
        require_permission('ictd.transmittal.view');
        require ROOT_PATH . '/modules/Pages/transmittal/transmittal-export.php';
        break;

    case '/ictd/transmittal/archive':
        require_login();
        require_permission('ictd.transmittal.view');
        log_route_activity($uri, 'PAGE_VIEW');
        require ROOT_PATH . '/modules/Pages/transmittal/transmittal-archive.php';
        break;

    case '/ictd/subscription':
        require_login();
        require_permission('ictd.subscription.view');
        log_route_activity($uri, 'PAGE_VIEW');
        require ROOT_PATH . '/modules/Pages/subscription/subscription.php';
        break;

    case '/ictd/subscription-data':
        require_login();
        require_permission('ictd.subscription.view');
        require ROOT_PATH . '/modules/Pages/subscription/subscription-data.php';
        break;

    case '/ictd/subscription-action':
        require_login();
        require_permission('ictd.subscription.view');
        require ROOT_PATH . '/modules/Pages/subscription/subscription-action.php';
        break;

    case '/ictd/subscription/archive':
        require_login();
        require_permission('ictd.subscription.view');
        log_route_activity($uri, 'PAGE_VIEW');
        require ROOT_PATH . '/modules/Pages/subscription/subscription-archive.php';
        break;

    case '/ictd/subscription/import':
        require_login();
        require_permission('ictd.subscription.view');
        log_route_activity($uri, 'PAGE_VIEW');
        $importModuleKey = 'subscription';
        require ROOT_PATH . '/modules/Pages/import/import.php';
        break;

    case '/ictd/borrowed':
        require_login();
        require_permission('ictd.borrowed.view');
        log_route_activity($uri, 'PAGE_VIEW');
        require ROOT_PATH . '/modules/Pages/borrowed/borrowed.php';
        break;

    case '/ictd/borrowed-data':
        require_login();
        require_permission('ictd.borrowed.view');
        require ROOT_PATH . '/modules/Pages/borrowed/borrowed-data.php';
        break;

    case '/ictd/borrowed-action':
        require_login();
        require_permission('ictd.borrowed.view');
        require ROOT_PATH . '/modules/Pages/borrowed/borrowed-action.php';
        break;

    case '/ictd/borrowed/archive':
        require_login();
        require_permission('ictd.borrowed.view');
        log_route_activity($uri, 'PAGE_VIEW');
        require ROOT_PATH . '/modules/Pages/borrowed/borrowed-archive.php';
        break;

    case '/ictd/borrowed/import':
        require_login();
        require_permission('ictd.borrowed.view');
        log_route_activity($uri, 'PAGE_VIEW');
        $importModuleKey = 'borrowed';
        require ROOT_PATH . '/modules/Pages/import/import.php';
        break;

    case '/ictd/bidding':
        require_login();
        require_permission('ictd.bidding.view');
        log_route_activity($uri, 'PAGE_VIEW');
        require ROOT_PATH . '/modules/Pages/bidding/bidding.php';
        break;

    case '/ictd/bidding-data':
        require_login();
        require_permission('ictd.bidding.view');
        require ROOT_PATH . '/modules/Pages/bidding/bidding-data.php';
        break;

    case '/ictd/bidding-action':
        require_login();
        require_permission('ictd.bidding.view');
        require ROOT_PATH . '/modules/Pages/bidding/bidding-action.php';
        break;

    case '/ictd/bidding-export':
        require_login();
        require_permission('ictd.bidding.view');
        require ROOT_PATH . '/modules/Pages/bidding/bidding-export.php';
        break;

    case '/ictd/bidding/archive':
        require_login();
        require_permission('ictd.bidding.view');
        log_route_activity($uri, 'PAGE_VIEW');
        require ROOT_PATH . '/modules/Pages/bidding/bidding-archive.php';
        break;

    case '/ictd/bidding/import':
        require_login();
        require_permission('ictd.bidding.view');
        log_route_activity($uri, 'PAGE_VIEW');
        $importModuleKey = 'bidding';
        require ROOT_PATH . '/modules/Pages/import/import.php';
        break;

    case '/ictd/title':
        require_login();
        require_permission('ictd.title.view');
        log_route_activity($uri, 'PAGE_VIEW');
        render_ictd_module_page([
            'title' => 'Title',
            'subtitle' => 'Maintain title records and related reference information.',
            'icon' => 'bi-type-bold',
            'badge' => 'Operations',
        ]);
        break;

    case '/ictd/title/archive':
        require_login();
        require_permission('ictd.title.view');
        log_route_activity($uri, 'PAGE_VIEW');
        render_ictd_module_page([
            'title' => 'Title Archive',
            'subtitle' => 'Review archived title records.',
            'icon' => 'bi-archive-fill',
            'badge' => 'Operations',
        ]);
        break;

    case '/ictd/title/import':
        require_login();
        require_permission('ictd.title.view');
        log_route_activity($uri, 'PAGE_VIEW');
        $importModuleKey = 'title';
        require ROOT_PATH . '/modules/Pages/import/import.php';
        break;

    case '/ictd/import-action':
        require_login();
        require ROOT_PATH . '/modules/Pages/import/import-action.php';
        break;

    case '/ictd/reports':
        require_login();
        require_permission('ictd.reports.view');
        log_route_activity($uri, 'PAGE_VIEW');
        require ROOT_PATH . '/modules/Reports/views/index.php';
        break;

    case '/documentation-process-flow':
        require_login();
        require_permission('documentation.view');
        log_route_activity($uri, 'PAGE_VIEW');
        require ROOT_PATH . '/modules/Reports/views/documentation.php';
        break;

    case '/ictd/reports-export':
    case '/reports-export':
        require_login();
        require_any_permission(['reports.view', 'ictd.reports.view']);
        require ROOT_PATH . '/modules/Reports/views/export.php';
        break;

    case '/ictd/masterlists':
    case '/ictd/masterlists/equipment-status':
    case '/ictd/masterlists/descriptions':
    case '/ictd/masterlists/brands':
    case '/ictd/masterlists/suppliers':
    case '/ictd/masterlists/departments':
    case '/ictd/masterlists/end-users':
    case '/ictd/masterlists/positions':
    case '/ictd/masterlists/employee-status':
    case '/ictd/masterlists/funds':
    case '/ictd/masterlists/technical':
    case '/ictd/masterlists/complain':
    case '/ictd/masterlists/troubleshoot':
    case '/ictd/masterlists/service':
        require_login();
        require_permission('ictd.masterlist.view');
        log_route_activity($uri, 'PAGE_VIEW');
        $masterlistRouteMap = [
            '/ictd/masterlists' => 'equipment-status',
            '/ictd/masterlists/equipment-status' => 'equipment-status',
            '/ictd/masterlists/descriptions' => 'descriptions',
            '/ictd/masterlists/brands' => 'brands',
            '/ictd/masterlists/suppliers' => 'suppliers',
            '/ictd/masterlists/departments' => 'departments',
            '/ictd/masterlists/end-users' => 'end-users',
            '/ictd/masterlists/positions' => 'positions',
            '/ictd/masterlists/employee-status' => 'employee-status',
            '/ictd/masterlists/funds' => 'funds',
            '/ictd/masterlists/technical' => 'technical',
            '/ictd/masterlists/complain' => 'complain',
            '/ictd/masterlists/troubleshoot' => 'troubleshoot',
            '/ictd/masterlists/service' => 'service',
        ];
        $masterlistActiveTab = $masterlistRouteMap[$uri] ?? 'equipment-status';
        require ROOT_PATH . '/modules/Pages/masterlist/masterlist.php';
        break;

    case '/ictd/masterlist-action':
        require_login();
        require_permission('ictd.masterlist.view');
        require ROOT_PATH . '/modules/Pages/masterlist/masterlist-action.php';
        break;

    case '/activity-logs':
        require_login();
        require_permission('reports.view');
        log_route_activity($uri, 'PAGE_VIEW');
        require ROOT_PATH . '/modules/Logs/activity_logs.php';
        break;

    case '/activity-logs-data':
        require_login();
        require_permission('reports.view');
        require ROOT_PATH . '/modules/Logs/activity_logs_data.php';
        break;

    case '/activity-logs-action':
        require_login();
        require ROOT_PATH . '/modules/Logs/activity_logs_action.php';
        break;

    case '/ictd/admin-users':
        require_login();
        require_permission('ictd.admin-users.view');
        log_route_activity($uri, 'PAGE_VIEW');
        require ROOT_PATH . '/modules/Users/views/admin.php';
        break;

    /* ---------- LOGOUT ---------- */

    case '/logout':
        require ROOT_PATH . '/modules/Auth/controllers/AuthController.php';
        AuthController::logout();
        break;

    /* ---------- 404 ---------- */

    default:
        http_response_code(404);
        echo '404 Page Not Found';
        break;
}
