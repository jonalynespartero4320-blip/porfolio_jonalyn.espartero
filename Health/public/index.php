<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/customer.php';
require dirname(__DIR__) . '/app/booking-session.php';
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
if (BASE && ($path === BASE || str_starts_with($path, BASE . '/'))) $path = substr($path, strlen(BASE));
$route = trim($path, '/');
$method = $_SERVER['REQUEST_METHOD'];
$error = '';
try {
    $db = db();
    $me = user();
    if ($me && !is_staff($me)) { unset($_SESSION['uid']); $me=false; }
    $expiredBooking=expire_booking();
    if (!in_array($route, ['', 'login', 'logout', 'admin', 'status','customer','schedule','availability','booking-confirmation','customer-status','manage-admins','arrival','booking-activity','booking-reset','cancel-appointment'], true)) { http_response_code(404); $route = '404'; }
    $publicRoutes=['','login','404','customer','schedule','availability','booking-confirmation','booking-activity','booking-reset','cancel-appointment'];
    if (!$me && !in_array($route, $publicRoutes, true)) go('login');
    if (in_array($route, ['admin', 'status','customer-status','arrival'], true) && !is_staff($me)) {
        http_response_code(403); $route = '403';
        throw new RuntimeException('Administrator access is required.');
    }
    if ($route==='manage-admins' && (!$me || $me['role']!=='super_admin')) {
        http_response_code(403); $route='403'; throw new RuntimeException('Super administrator access is required.');
    }
    if ($route==='404' && $method==='POST') throw new RuntimeException('This page is unavailable.');
    if (in_array($route,['booking-activity','booking-reset'],true)) {
        header('Content-Type: application/json; charset=utf-8');
        if ($method!=='POST') { http_response_code(405); header('Allow: POST'); echo json_encode(['error'=>'Method not allowed.']); exit; }
        if (!is_string($_POST['csrf']??null) || !isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'],$_POST['csrf'])) { http_response_code(419); echo json_encode(['error'=>'Invalid session token.']); exit; }
        if ($expiredBooking || !booking_guard_valid($_POST['booking_guard']??null)) { http_response_code(409); echo json_encode(['expired'=>true,'redirect'=>url('customer?reset=inactive')]); exit; }
        if ($route==='booking-reset') clear_booking(); else $_SESSION['booking_last_activity']=time();
        echo json_encode(['ok'=>true,'timeout'=>BOOKING_IDLE_SECONDS]); exit;
    }
    if ($expiredBooking && in_array($route,['customer','schedule','booking-confirmation','cancel-appointment'],true) && !($method==='GET' && $route==='customer')) go('customer?reset=inactive');
    if ($method==='GET' && $route==='customer' && isset($_SESSION['customer_receipt'])) clear_booking();
    if ($method==='GET' && in_array($route,['customer','schedule','booking-confirmation','cancel-appointment'],true)) start_booking_activity();
    if ($route==='availability') {
        header('Content-Type: application/json; charset=utf-8');
        if ($method!=='GET') { http_response_code(405); header('Allow: GET'); echo json_encode(['error'=>'Method not allowed.']); exit; }
        try {
            if (isset($_GET['month'])) echo json_encode(customer_month_availability($db,is_string($_GET['month'])?$_GET['month']:''));
            else echo json_encode(customer_availability($db,is_string($_GET['date']??null)?$_GET['date']:''));
        }
        catch(RuntimeException $ex) { http_response_code(422); echo json_encode(['error'=>$ex->getMessage()]); }
        exit;
    }
    if ($method === 'POST') {
        if ((int)($_SERVER['CONTENT_LENGTH']??0)>16384) { $_POST=[]; http_response_code(413); throw new RuntimeException('The submitted form is too large.'); }
        foreach($_POST as $value) if(!is_string($value)) { $_POST=[]; http_response_code(400); throw new RuntimeException('Invalid form field format. Refresh the form and try again.'); }
        if (!isset($_SESSION['csrf']) || !is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) { $_POST=[]; http_response_code(419); throw new RuntimeException('Your session expired. Refresh this page and try again.'); }
        if (in_array($route,['customer','cancel-appointment'],true) && !booking_guard_valid($_POST['booking_guard']??null)) { $_POST=[]; http_response_code(419); throw new RuntimeException('This booking form has expired. Reload the page to start a new form.'); }
        if(in_array($route,['customer','schedule','cancel-appointment'],true)) $_SESSION['booking_last_activity']=time();
        if ($route==='cancel-appointment') {
            $identity=hash('sha256','customer-cancellation|'.($_SERVER['REMOTE_ADDR']??''));
            $q=$db->prepare('SELECT attempts,expires FROM login_attempts WHERE identity=?'); $q->execute([$identity]); $attempt=$q->fetch(); $q->closeCursor();
            if($attempt && $attempt['expires']>time() && $attempt['attempts']>=10) { http_response_code(429); header('Retry-After: '.max(1,$attempt['expires']-time())); throw new RuntimeException('Too many unsuccessful cancellation attempts. Please try again in 15 minutes or contact the care team.'); }
            try {
                if(($_POST['confirm_cancel']??'')!=='yes') throw new RuntimeException('Confirm that you want to release your reserved slot.');
                $cancelled=cancel_customer($db,$_POST['reference']??'',$_POST['full_name']??'',$_POST['reason']??'');
                if(!$cancelled) throw new RuntimeException('Unable to cancel. Check your reference and full name. Only upcoming, confirmed appointments that have not checked in can be cancelled.');
            } catch(RuntimeException $ex) {
                if($ex instanceof PDOException) throw $ex;
                $count=$attempt && $attempt['expires']>time()?(int)$attempt['attempts']+1:1;
                $db->prepare('REPLACE INTO login_attempts(identity,attempts,expires) VALUES(?,?,?)')->execute([$identity,$count,time()+900]); throw $ex;
            }
            flash('Your appointment has been cancelled. Its slot is now available again for the original date and AM/PM time.'); go('cancel-appointment');
        }
        if ($route==='manage-admins') {
            $name=is_string($_POST['name']??null)?trim($_POST['name']):'';
            $username=is_string($_POST['username']??null)?strtolower(trim($_POST['username'])):'';
            $password=is_string($_POST['password']??null)?$_POST['password']:'';
            if (strlen($name)<2 || strlen($name)>100 || !preg_match('/^[a-z0-9][a-z0-9._-]{2,49}$/',$username) || strlen($password)<12 || strlen($password)>72) throw new RuntimeException('Enter a full name, a username of 3–50 letters/numbers/dots/hyphens/underscores, and a password of 12–72 characters.');
            try { $db->prepare("INSERT INTO users(name,username,password,role) VALUES(?,?,?,'admin')")->execute([$name,$username,password_hash($password,PASSWORD_DEFAULT)]); }
            catch(PDOException $ex) { if($ex->getCode()!=='23000') throw $ex; throw new RuntimeException('That username is already in use.'); }
            flash('Admin account created. They can now sign in with their username.'); go('manage-admins');
        }
        if ($route==='arrival') {
            $type=is_string($_POST['type']??null)?$_POST['type']:'';
            if (!in_array($type,['customer','appointment'],true)) throw new RuntimeException('Invalid appointment type.');
            $table=$type==='customer'?'customer_bookings':'appointments';
            $id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT);
            $q=$db->prepare("UPDATE $table SET arrived_at=?,arrived_by=? WHERE id=? AND arrived_at IS NULL AND date<=? AND status IN ('pending','confirmed')");
            $q->execute([date('Y-m-d H:i:s'),$me['id'],$id,date('Y-m-d')]);
            flash($q->rowCount()?'Arrival confirmed. Attendance status updated to Arrived.':'Arrival cannot be recorded: this is a future, cancelled, completed, or already checked-in appointment.'); go('admin');
        }
        if ($route==='customer') {
            $details=customer_details($_POST);
            unset($_SESSION['customer_receipt']);
            $_SESSION['customer_draft']=$details;
            $_SESSION['customer_request']=bin2hex(random_bytes(32));
            go('schedule');
        }
        if ($route==='schedule') {
            if (empty($_SESSION['customer_draft']) || empty($_SESSION['customer_request'])) go('customer');
            if (!is_string($_POST['request_token']??null) || !hash_equals($_SESSION['customer_request'],$_POST['request_token'])) throw new RuntimeException('Your booking details changed in another tab. Refresh this page and review them before confirming.');
            $reservation=reserve_customer($db,$_SESSION['customer_draft'],is_string($_POST['date']??null)?$_POST['date']:'',is_string($_POST['period']??null)?$_POST['period']:'',$_SESSION['customer_request']);
            $_SESSION['customer_receipt']=$reservation['id'];
            go('booking-confirmation');
        }
        if ($route==='customer-status') {
            $newStatus=is_string($_POST['status']??null)?$_POST['status']:'';
            if (!in_array($newStatus,['cancelled','completed'],true)) throw new RuntimeException('Invalid customer booking status.');
            $id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT);
            $q=$db->prepare('SELECT * FROM customer_bookings WHERE id=?'); $q->execute([$id]); $booking=$q->fetch(); $q->closeCursor();
            if (!$booking || $booking['status']!=='confirmed' || ($newStatus==='completed' && strtotime($booking['date'].' '.($booking['period']==='AM'?'12:00':'17:00'))>time())) throw new RuntimeException('That status change is not available.');
            if($newStatus==='cancelled') $db->prepare("UPDATE customer_bookings SET status='cancelled',cancelled_at=?,cancellation_source='admin' WHERE id=? AND status='confirmed'")->execute([date('Y-m-d H:i:s'),$id]);
            else $db->prepare("UPDATE customer_bookings SET status=? WHERE id=? AND status='confirmed'")->execute([$newStatus,$id]);
            flash('Customer booking updated.'); go('admin');
        }
        if ($route==='login') {
            $username = strtolower(trim((string)($_POST['username'] ?? ''))); $password = (string)($_POST['password'] ?? '');
            if (!preg_match('/^[a-z0-9][a-z0-9._-]{2,49}$/',$username)) throw new RuntimeException('Enter a valid username.');
            $identity = hash('sha256', $username . '|' . ($_SERVER['REMOTE_ADDR'] ?? ''));
            $q=$db->prepare('SELECT * FROM login_attempts WHERE identity=?'); $q->execute([$identity]); $attempt=$q->fetch();
            if ($attempt && $attempt['expires'] > time() && $attempt['attempts'] >= 5) throw new RuntimeException('Too many attempts. Please try again in 15 minutes.');
            $q=$db->prepare('SELECT * FROM users WHERE username=?'); $q->execute([$username]); $account=$q->fetch();
            if (!$account || !password_verify($password,$account['password']) || !is_staff($account)) {
                $count = $attempt && $attempt['expires'] > time() ? $attempt['attempts']+1 : 1;
                $db->prepare('REPLACE INTO login_attempts(identity,attempts,expires) VALUES(?,?,?)')->execute([$identity,$count,time()+900]);
                throw new RuntimeException('Username or password is incorrect.');
            }
            $db->prepare('DELETE FROM login_attempts WHERE identity=?')->execute([$identity]);
            session_regenerate_id(true); $_SESSION['uid']=$account['id']; go('admin');
        }
        if (!$me) go('login');
        if ($route === 'logout') { $_SESSION=[]; setcookie(session_name(),'', ['expires'=>time()-3600,'path'=>'/','httponly'=>true,'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off','samesite'=>'Lax']); session_destroy(); go('login'); }
        if ($route === 'status') {
            if (!is_staff($me)) { http_response_code(403); throw new RuntimeException('Administrator access is required.'); }
            $status=(string)($_POST['status']??'');
            if (!in_array($status,['confirmed','cancelled','completed'],true)) throw new RuntimeException('Invalid status.');
            $q=$db->prepare("SELECT * FROM appointments WHERE id=?"); $q->execute([(int)($_POST['id']??0)]); $a=$q->fetch();
            if (!$a || !in_array($a['status'],['pending','confirmed'],true) || ($status==='completed' && strtotime($a['date'].' '.$a['time'])>time()) || ($status==='confirmed' && strtotime($a['date'].' '.$a['time'])<=time())) throw new RuntimeException('That status change is not available.');
            $db->prepare('UPDATE appointments SET status=? WHERE id=?')->execute([$status,$a['id']]); flash('Appointment updated.'); go('admin');
        }
    } elseif ($method !== 'GET') { http_response_code(405); header('Allow: GET, POST'); throw new RuntimeException('Method not allowed.'); }
    if (in_array($route,['logout','status','customer-status','arrival'],true)) { http_response_code(405); $route='404'; }
    if ($route === '') go($me ? 'admin' : 'customer');
    if (!$me && !in_array($route,$publicRoutes,true)) go('login');
    if ($route==='schedule' && empty($_SESSION['customer_draft'])) go('customer');
    if ($route==='booking-confirmation' && empty($_SESSION['customer_receipt'])) go('customer');
    if ($me && $route==='login') go('admin');
} catch (RuntimeException $ex) {
    if ($ex instanceof PDOException) { error_log((string)$ex); http_response_code(503); $error='The database is unavailable. Check the configured PDO driver, database connection, and imported schema. For SQLite, storage must be writable.'; $route='error'; }
    else $error=$ex->getMessage();
}
$titles=['dashboard'=>'Your health, in good hands.','appointments'=>'Your appointments','book'=>'Make time for your health','admin'=>'Overview Appointments','login'=>'Welcome back.','register'=>'Better care starts here.'];
$title=$titles[$route]??'Unable to open this page';
$title=['customer'=>'Book your appointment','schedule'=>'Choose your schedule','booking-confirmation'=>'Your appointment is booked'][$route]??$title;
$title=$route==='manage-admins'?'Manage admin accounts':$title;
$title=$route==='cancel-appointment'?'Cancel your appointment':$title;
$publicPage=in_array($route,['customer','schedule','booking-confirmation','cancel-appointment'],true);
$doctors=isset($db)?$db->query('SELECT * FROM doctors')->fetchAll():[];
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?=e($title)?> · Online Appointment</title>"><link rel="stylesheet" href="<?=e(url('assets/style.css'))?>"><link rel="stylesheet" href="<?=e(url('assets/customer.css'))?>"><link rel="stylesheet" href="<?=e(url('assets/security.css'))?>"><link rel="stylesheet" href="<?=e(url('assets/government.css'))?>"></head><body class="<?=$publicPage?'public-portal':'staff-portal'?> <?=$route==='login'?'login-page':''?>">
<?php if(!$publicPage): ?>
<aside class="sidebar"><a class="brand" href="<?=e(url())?>"><span>Appointments</span></a><div class="workspace">APPOINTMENT STAFF PORTAL</div><nav>
<?php if ($me??false): ?>

<?php if(is_staff($me)): ?><a class="<?=$route==='admin'?'active':''?>" href="<?=e(url('admin'))?>">▤ All appointments</a><?php endif ?>
<?php if($me['role']==='super_admin'): ?><a class="<?=$route==='manage-admins'?'active':''?>" href="<?=e(url('manage-admins'))?>">+ Manage admins</a><?php endif ?>
<?php else: ?><a class="<?=$route==='login'?'active':''?>" href="<?=e(url('login'))?>">↗ Administrator sign in</a><?php endif ?></nav>
<div class="sidebar-bottom">
<!-- <div class="help-card">
    <strong>Online Appointment</strong>
<p>Appointment administration and customer attendance.</p>
</div> -->
<small>Online Appointment System</small></div></aside>
<?php endif ?>
<div class="shell">
<?php if($publicPage): ?>
<div class="government-strip"><div>ONLINE APPOINTMENT<span>BOOK YOUR VISIT</span></div></div>
<header class="government-masthead"><div class="government-identity"><div><span class="government-city">SIMPLE AND CONVENIENT BOOKING</span><strong>Online Appointment</strong><span>Online Appointment System</span></div></div><div class="service-label">PUBLIC ONLINE APPOINTMENT<br><span>Monday–Friday</span></div></header>
<?php else: ?><header><span>Online Appointment · Staff portal</span><div class="profile"><span class="avatar"><?=e(strtoupper(substr($me['name']??'S',0,1)))?></span><span><?=e($me['name']??'Staff access')?></span><?php if($me??false): ?><form method="post" action="<?=e(url('logout'))?>"><?=csrf()?><button class="text-button">Sign out</button></form><?php endif ?></div></header><?php endif ?>
<main>
<div class="eyebrow"><?=$publicPage?'ONLINE APPOINTMENT BOOKING':'AUTHORIZED PERSONNEL'?></div><h1><?=e($title)?></h1><p class="subtitle"><?=$publicPage?'Choose a date and time that works for you.':'Manage appointments, customer attendance, and health services.'?></p>
<?php if($error): ?><div class="alert error" role="alert"><?=e($error)?></div><?php endif ?>
<?php if(isset($_SESSION['flash'])): ?><div class="alert" role="status"><?=e($_SESSION['flash'])?></div><?php unset($_SESSION['flash']); endif ?>
<?php if(in_array($route,['customer','schedule','booking-confirmation','cancel-appointment'],true)): ?>
<div id="booking-idle" hidden data-activity-url="<?=e(url('booking-activity'))?>" data-reset-url="<?=e(url('booking-reset'))?>" data-start-url="<?=e(url('customer?reset=inactive'))?>" data-csrf="<?=e($_SESSION['csrf']??=bin2hex(random_bytes(32)))?>" data-guard="<?=e($_SESSION['booking_guard']??'')?>" data-remaining="<?=max(0,BOOKING_IDLE_SECONDS-(time()-(int)($_SESSION['booking_last_activity']??0)))?>"></div>
<script src="<?=e(url('assets/booking-idle.js'))?>" defer></script>
<?php endif ?>
<?php if($route==='cancel-appointment'): require dirname(__DIR__).'/app/customer-cancel.php'; ?>
<?php elseif(in_array($route,['customer','schedule','booking-confirmation'],true)): require dirname(__DIR__).'/app/customer-view.php'; ?>
<?php elseif($route==='manage-admins'): require dirname(__DIR__).'/app/manage-admins.php'; ?>
<?php elseif($route==='login'): require dirname(__DIR__).'/app/staff-login.php'; ?>
<?php elseif($route==='admin'): ?>
<?php
$admin=$route==='admin'; $q=$db->prepare('SELECT a.*,d.name AS doctor,d.specialty,d.initials,u.name AS patient,u.username FROM appointments a JOIN doctors d ON a.doctor_id=d.id JOIN users u ON a.user_id=u.id '.($admin?'':'WHERE a.user_id=? ').'ORDER BY a.date DESC,a.time DESC'); $q->execute($admin?[]:[$me['id']]); $appointments=$q->fetchAll();
$upcoming=array_values(array_filter($appointments,fn($a)=>in_array($a['status'],['pending','confirmed'],true) && strtotime($a['date'].' '.$a['time'])>time())); usort($upcoming,fn($a,$b)=>strcmp($a['date'].$a['time'],$b['date'].$b['time']));
?>
<?php if($admin) require dirname(__DIR__).'/app/customer-admin.php'; ?>
<?php
$appointments=array_values(array_filter($appointments,static function($a) use($filterDate,$filterScope,$filterPeriod,$customerSearch,$searchError) {
    if($searchError || ($filterScope==='upcoming' ? $a['date']<$filterDate : $a['date']!==$filterDate)) return false;
    if($filterPeriod!=='' && (strtotime($a['time'])<strtotime('12:00')?'AM':'PM')!==$filterPeriod) return false;
    return $customerSearch==='' || stripos($a['patient'],$customerSearch)!==false;
}));
if($appointments): ?>

<section class="card appointment-card"><div class="section-heading"><div><h2><?=$route==='dashboard'?'Coming up next':($admin?'All patient appointments':'Appointment history')?></h2><p><?=$admin?'Review requests and coordinate patient care.':'Your care, organized in one place.'?></p></div></div>
<?php $rows=$route==='dashboard'?array_slice($upcoming,0,3):$appointments; if(!$rows): ?><div class="empty"><span>▦</span><h3><?=$route==='dashboard'?'A fresh start for your health':'No appointments yet'?></h3><p>Choose a specialist and find your next convenient time.</p><a class="button" href="<?=e(url('customer'))?>">Book your first visit →</a></div><?php else: ?><div class="table-wrap"><table><thead><tr><?php if($admin): ?><th>Patient</th><?php endif ?><th>Specialist</th><th>Date & time</th><th>Status</th><th>Details / actions</th></tr></thead><tbody>
<?php foreach($rows as $a): ?><tr><?php if($admin): ?><td><strong><?=e($a['patient'])?></strong><small><?=e($a['username'])?></small></td><?php endif ?><td><div class="doctor-cell"><span class="avatar"><?=e($a['initials'])?></span><div><strong><?=e($a['doctor'])?></strong><small><?=e($a['specialty'])?></small></div></div></td><td><strong><?=e(date('M j, Y',strtotime($a['date'])))?></strong><small><?=e(date('g:i A',strtotime($a['time'])))?> · In-person</small></td><td><span class="status <?=e($a['status'])?>"><?=e(ucfirst($a['status']))?></span></td><td><details><summary>View details</summary><p class="reason"><?=e($a['reason'])?></p></details>
<?php if($admin): $arrivalRecord=$a; $arrivalType='appointment'; require dirname(__DIR__).'/app/arrival-control.php'; endif ?>
<?php if(in_array($a['status'],['pending','confirmed'],true)): ?>
<?php if($admin): ?><form class="inline-form" method="post" action="<?=e(url('status'))?>"><?=csrf()?><input type="hidden" name="id" value="<?=e($a['id'])?>"><select name="status" aria-label="New appointment status"><?php if(strtotime($a['date'].' '.$a['time'])>time()): ?><option value="confirmed">Confirm</option><?php else: ?><option value="completed">Complete</option><?php endif ?><option value="cancelled">Cancel</option></select><button class="text-button">Update</button></form>
<?php elseif(strtotime($a['date'].' '.$a['time'])>time()): ?><form method="post" action="<?=e(url('cancel'))?>"><?=csrf()?><input type="hidden" name="id" value="<?=e($a['id'])?>"><button class="text-button danger">Cancel visit</button></form><?php endif ?>
<?php endif ?></td></tr><?php endforeach ?></tbody></table></div><?php endif ?></section>
<?php endif ?><?php else: ?><section class="card empty"><h2><?=http_response_code()===403?'Access restricted':'Page unavailable'?></h2><p>Check the address or return to the portal.</p><a class="button" href="<?=e(url())?>">Return to Online Appointment</a></section><?php endif ?>
<footer><span>© <?=date('Y')?> Online Appointment</span><?php if($publicPage): ?><span><a href="<?=e(url($route==='cancel-appointment'?'customer':'cancel-appointment'))?>"><?=$route==='cancel-appointment'?'Return to appointment form':'Unable to attend? Cancel an appointment'?></a></span><?php else: ?><span>Online Appointment System</span><?php endif ?></footer></main></div></body></html>
