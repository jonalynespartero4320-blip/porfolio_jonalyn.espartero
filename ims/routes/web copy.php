<?php

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = str_replace(BASE_URL, '', $uri);

switch ($uri) {

    /* ---------- AUTH PAGES ---------- */

    case '':
    case '/':
        require ROOT_PATH . '/modules/Auth/views/login.php';
        break;

    case '/login':

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
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
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            require ROOT_PATH . '/modules/Auth/controllers/AuthController.php';
            AuthController::register();
        }
        break;

    case '/reset-request':
        require ROOT_PATH . '/modules/Auth/views/reset_request.php';
        break;

    case '/reset-request-save':
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            require ROOT_PATH . '/modules/Auth/controllers/AuthController.php';
            AuthController::resetRequest();
        }
        break;

    /* ---------- DASHBOARD ---------- */

    case '/dashboard':
        require_login();
        require ROOT_PATH . '/modules/Dashboard/views/index.php';
        break;

    /* ---------- MASTERLIST ---------- */

    case '/masterlist':
        require_login();
        require ROOT_PATH . '/modules/Dashboard/views/masterlist.php';
        break;

    case '/add-case':
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            require ROOT_PATH . '/modules/Masterlist/controllers/MasterlistController.php';
            $case_no = $_POST['case_no'];
            $case_type = $_POST['case_type'];
            $result = MasterlistController::addCase($case_no, $case_type);
            echo json_encode($result);
        }
        break;

    case '/edit-case':
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            require ROOT_PATH . '/modules/Masterlist/controllers/MasterlistController.php';
            $id = $_POST['id'];
            $case_no = $_POST['case_no'];
            $case_type = $_POST['case_type'];
            $result = MasterlistController::editCase($id, $case_no, $case_type);
            echo json_encode($result);
        }
        break;

    case '/delete-case':
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            require ROOT_PATH . '/modules/Masterlist/controllers/MasterlistController.php';
            $id = $_POST['id'];
            $result = MasterlistController::deleteCase($id);
            echo json_encode($result);
        }
        break;

    case '/add-court':
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            require ROOT_PATH . '/modules/Masterlist/controllers/MasterlistController.php';
            $court_name = $_POST['court_name'];
            $result = MasterlistController::addCourt($court_name);
            echo json_encode($result);
        }
        break;

    case '/edit-court':
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            require ROOT_PATH . '/modules/Masterlist/controllers/MasterlistController.php';
            $id = $_POST['id'];
            $court_name = $_POST['court_name'];
            $result = MasterlistController::editCourt($id, $court_name);
            echo json_encode($result);
        }
        break;

    case '/delete-court':
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            require ROOT_PATH . '/modules/Masterlist/controllers/MasterlistController.php';
            $id = $_POST['id'];
            $result = MasterlistController::deleteCourt($id);
            echo json_encode($result);
        }
        break;

    case '/add-dorm':
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            require ROOT_PATH . '/modules/Masterlist/controllers/MasterlistController.php';
            $dorm_no = $_POST['dorm_no'];
            $description = $_POST['description'];
            $result = MasterlistController::addDorm($dorm_no, $description);
            echo json_encode($result);
        }
        break;

    case '/edit-dorm':
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            require ROOT_PATH . '/modules/Masterlist/controllers/MasterlistController.php';
            $id = $_POST['id'];
            $dorm_no = $_POST['dorm_no'];
            $description = $_POST['description'];
            $result = MasterlistController::editDorm($id, $dorm_no, $description);
            echo json_encode($result);
        }
        break;

    case '/delete-dorm':
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            require ROOT_PATH . '/modules/Masterlist/controllers/MasterlistController.php';
            $id = $_POST['id'];
            $result = MasterlistController::deleteDorm($id);
            echo json_encode($result);
        }
        break;

    /* ================= RBAC ADMIN ================= */

    case '/admin':
        require_login();
        require_permission('manage_permissions');
        require ROOT_PATH . '/modules/Users/views/admin.php';
        break;


    case '/get-role-permissions':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            RolePermissionController::getRolePermissions();
        }
        break;

    case '/update-role-permissions':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            RolePermissionController::updateRolePermissions();
        }
        break;


    /* ---------- PAGES WITH PERMISSIONS ---------- */

    case '/pdl-management':
        require_login();
        require_permission('pdl-management');
        require ROOT_PATH . '/modules/Dashboard/views/pdl-record.php';
        break;

    case '/page2':
        require_login();
        require_permission('page2');
        require ROOT_PATH . '/modules/Dashboard/views/page2.php';
        break;

    case '/page3':
        require_login();
        require_permission('page3');
        require ROOT_PATH . '/modules/Dashboard/views/page3.php';
        break;

    /* ---------- ADMIN ---------- */

    case '/admin':
        require_login();
        require_permission('admin');
        require ROOT_PATH . '/modules/Users/views/admin.php';
        break;

    case '/update-permission':
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            require ROOT_PATH . '/modules/Users/controllers/UserController.php';
            UserController::updatePermission();
        }
        break;

    /* ---------- LOGS ---------- */

    case '/activity-logs':
        require_login();
        require_permission('admin');
        require ROOT_PATH . '/modules/Logs/activity_logs.php';
        break;

    /* ---------- LOGOUT ---------- */

    case '/logout':
        session_destroy();
        header("Location: " . BASE_URL);
        exit;

    /* ---------- PDL CRUD---------- */

    case '/pdl':
    require_login();
    require ROOT_PATH . '/modules/PDL/views/index.php';
    break;

    case '/pdl-form':
        require_login();
        require ROOT_PATH . '/modules/PDL/views/form.php';
        break;

    case '/pdl-save':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            require ROOT_PATH . '/modules/PDL/controllers/PDLController.php';
            PDLController::save();
        }
        break;

    case '/pdl-delete':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            require ROOT_PATH . '/modules/PDL/controllers/PDLController.php';
            PDLController::delete();
        }
        break;

    case '/pdl-view':
        require_login();
        require ROOT_PATH . '/modules/PDL/controllers/PDLController.php';

        $id = $_GET['id'] ?? null;
        $pdl = PDLController::view($id);

        require ROOT_PATH . '/modules/PDL/views/view.php';
        break;

            // ================= RESET REQUEST HANDLER =================
    case '/handle-reset-request':
        require_once ROOT_PATH . '/modules/Auth/controllers/AuthController.php';
        AuthController::handleResetRequest();
        break;




    /* ---------- 404 ---------- */

    default:
        http_response_code(404);
        echo "404 Page Not Found";
        break;


}

?>