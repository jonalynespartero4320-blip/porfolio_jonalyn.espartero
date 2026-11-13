<?php

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

    /* ---------- DASHBOARD ---------- */

    case '/dashboard':
        require_login();
        log_route_activity($uri, 'PAGE_VIEW');
        require ROOT_PATH . '/modules/Dashboard/views/index.php';
        break;

    /* ---------- MASTERLIST ---------- */

    case '/masterlist':
        require_login();
        require_permission('case.view');
        log_route_activity($uri, 'PAGE_VIEW');
        require ROOT_PATH . '/modules/Dashboard/views/masterlist.php';
        break;

    case '/add-case':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            require ROOT_PATH . '/modules/Masterlist/controllers/MasterlistController.php';
            $case_no = $_POST['case_no'] ?? '';
            $case_type = $_POST['case_type'] ?? '';
            $result = MasterlistController::addCase($case_no, $case_type);
            echo json_encode($result);
        }
        break;

    case '/edit-case':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            require ROOT_PATH . '/modules/Masterlist/controllers/MasterlistController.php';
            $id = $_POST['id'] ?? '';
            $case_no = $_POST['case_no'] ?? '';
            $case_type = $_POST['case_type'] ?? '';
            $result = MasterlistController::editCase($id, $case_no, $case_type);
            echo json_encode($result);
        }
        break;

    case '/delete-case':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            require ROOT_PATH . '/modules/Masterlist/controllers/MasterlistController.php';
            $id = $_POST['id'] ?? '';
            $result = MasterlistController::deleteCase($id);
            echo json_encode($result);
        }
        break;

    case '/add-court':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            require ROOT_PATH . '/modules/Masterlist/controllers/MasterlistController.php';
            $court_name = $_POST['court_name'] ?? '';
            $result = MasterlistController::addCourt($court_name);
            echo json_encode($result);
        }
        break;

    case '/edit-court':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            require ROOT_PATH . '/modules/Masterlist/controllers/MasterlistController.php';
            $id = $_POST['id'] ?? '';
            $court_name = $_POST['court_name'] ?? '';
            $result = MasterlistController::editCourt($id, $court_name);
            echo json_encode($result);
        }
        break;

    case '/delete-court':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            require ROOT_PATH . '/modules/Masterlist/controllers/MasterlistController.php';
            $id = $_POST['id'] ?? '';
            $result = MasterlistController::deleteCourt($id);
            echo json_encode($result);
        }
        break;

    case '/add-dorm':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            require ROOT_PATH . '/modules/Masterlist/controllers/MasterlistController.php';
            $dorm_no = $_POST['dorm_no'] ?? '';
            $description = $_POST['description'] ?? '';
            $result = MasterlistController::addDorm($dorm_no, $description);
            echo json_encode($result);
        }
        break;

    case '/edit-dorm':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            require ROOT_PATH . '/modules/Masterlist/controllers/MasterlistController.php';
            $id = $_POST['id'] ?? '';
            $dorm_no = $_POST['dorm_no'] ?? '';
            $description = $_POST['description'] ?? '';
            $result = MasterlistController::editDorm($id, $dorm_no, $description);
            echo json_encode($result);
        }
        break;

    case '/delete-dorm':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            require ROOT_PATH . '/modules/Masterlist/controllers/MasterlistController.php';
            $id = $_POST['id'] ?? '';
            $result = MasterlistController::deleteDorm($id);
            echo json_encode($result);
        }
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

    /* ---------- PERMISSION PAGES ---------- */

    case '/pdl-management':
        require_login();
        require_permission('pdl-management.view');
        log_route_activity($uri, 'PAGE_VIEW');
        require ROOT_PATH . '/modules/Pages/pdl-management/pdl-management.php';
        break;

    case '/page2':
        require_login();
        require_permission('reports.view');
        header('Location: ' . BASE_URL . '/reports');
        exit;
        break;

    case '/page3':
        require_login();
        require_permission('page3.view');
        log_route_activity($uri, 'PAGE_VIEW');
        require ROOT_PATH . '/modules/Dashboard/views/page3.php';
        break;

    /* ---------- LOGS ---------- */

    case '/activity-logs':
        require_login();
        require_permission('reports.view');
        log_route_activity($uri, 'PAGE_VIEW');
        require ROOT_PATH . '/modules/Logs/activity_logs.php';
        break;

    case '/activity-logs-action':
        require ROOT_PATH . '/modules/Logs/activity_logs_action.php';
        break;

    case '/reports':
        require_login();
        require_permission('reports.view');
        log_route_activity($uri, 'PAGE_VIEW');
        require ROOT_PATH . '/modules/Reports/views/index.php';
        break;

    case '/documentation-process-flow':
        require_login();
        require_permission('documentation.view');
        log_route_activity($uri, 'PAGE_VIEW');
        require ROOT_PATH . '/modules/Reports/views/documentation.php';
        break;

    case '/reports-export':
        require_login();
        require ROOT_PATH . '/modules/Reports/views/export.php';
        break;

    /* ---------- PDL ---------- */

    case '/pdl':
        require_login();
        log_route_activity($uri, 'PAGE_VIEW');
        require ROOT_PATH . '/modules/PDL/views/index.php';
        break;

    case '/pdl-form':
        require_login();
        log_route_activity($uri, 'PAGE_VIEW');
        require ROOT_PATH . '/modules/Pages/pdl-management/pdl-form.php';
        break;

    case '/pdl-import':
        require_login();
        require_permission((($_GET['mode'] ?? 'pdl') === 'visitors') ? 'pdl.import' : 'pdl.create');
        log_route_activity($uri, 'PAGE_VIEW');
        require ROOT_PATH . '/modules/Pages/pdl-management/pdl-import.php';
        break;

    case '/pdl-import-action':
        require ROOT_PATH . '/modules/Pages/pdl-management/pdl-import-action.php';
        break;

    case '/pdl-action':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            require ROOT_PATH . '/modules/Pages/pdl-management/pdl-action.php';
        }
        break;

    case '/visitor-registration':
        require_login();
        require_permission('visitors.view');
        log_route_activity($uri, 'PAGE_VIEW');
        require ROOT_PATH . '/modules/Pages/visitor-main/visitor-registration/visitor-registration.php';
        break;

    case '/visitor-registration-action':
        require ROOT_PATH . '/modules/Pages/visitor-main/visitor-registration/visitor-registration-action.php';
        break;

    case '/queue':
        require_login();
        require_permission('queue.view');
        log_route_activity($uri, 'PAGE_VIEW');
        require ROOT_PATH . '/modules/Pages/queuing/queue.php';
        break;

    case '/special-cases':
        require_login();
        require_super_admin();
        log_route_activity($uri, 'PAGE_VIEW');
        require ROOT_PATH . '/modules/Pages/queuing/special-cases.php';
        break;

    case '/queue-action':
        require ROOT_PATH . '/modules/Pages/queuing/queue-action.php';
        break;

    case '/queue-board':
        require_login();
        require_permission('queue.view');
        log_route_activity($uri, 'PAGE_VIEW');
        require ROOT_PATH . '/modules/Pages/queuing/queue-board.php';
        break;

    case '/queue-monitor':
        require_login();
        require_permission('queue.view');
        log_route_activity($uri, 'PAGE_VIEW');
        require ROOT_PATH . '/modules/Pages/queuing/queue-monitor.php';
        break;

    case '/visitor-management':
        require_login();
        require_permission('visitors.view');
        log_route_activity($uri, 'PAGE_VIEW');
        require ROOT_PATH . '/modules/Pages/visitor-main/visitor-management/visitor-management.php';
        break;

    case '/visitor-management-action':
        require ROOT_PATH . '/modules/Pages/visitor-main/visitor-management/visitor-management-action.php';
        break;

    case '/id-issuance':
        require_login();
        require_permission('id-issuance.view');
        log_route_activity($uri, 'PAGE_VIEW');
        require ROOT_PATH . '/modules/Pages/id-issuance/id-issuance.php';
        break;

    case '/id-issuance-action':
        require ROOT_PATH . '/modules/Pages/id-issuance/id-issuance-action.php';
        break;

    case '/id-card':
        require_login();
        require_permission('id-issuance.view');
        log_route_activity($uri, 'PAGE_VIEW', ['visitor_id' => (int) ($_GET['visitor_id'] ?? 0)]);
        require ROOT_PATH . '/modules/Pages/id-issuance/id-card.php';
        break;

    case '/pdl-delete':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            require ROOT_PATH . '/modules/Auth/controllers/PDLController.php';
            require ROOT_PATH . '/config/database.php';

            $id = $_POST['id'] ?? null;
            $result = PDLController::delete($pdo, $id);

            echo json_encode($result);
        }
        break;

    case '/pdl-view':
        require_login();
        log_route_activity($uri, 'PAGE_VIEW', ['id' => (int) ($_GET['id'] ?? 0)]);
        require ROOT_PATH . '/modules/Auth/controllers/PDLController.php';
        require ROOT_PATH . '/config/database.php';

        $id = $_GET['id'] ?? null;
        $pdl = PDLController::view($pdo, $id);

        require ROOT_PATH . '/modules/PDL/views/view.php';
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
