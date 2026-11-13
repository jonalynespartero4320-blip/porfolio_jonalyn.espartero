<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/app/bootstrap.php';
require dirname(__DIR__).'/app/booking-session.php';
$checks=0;
function timeoutCheck(bool $ok,string $message): void { global $checks; if(!$ok) throw new RuntimeException($message); echo "PASS $message\n"; $checks++; }
$_SESSION=['uid'=>42,'csrf'=>'keep-auth-csrf','customer_draft'=>['full_name'=>'Juan Delacruz'],'customer_request'=>'old-request','customer_receipt'=>7];
start_booking_activity(); $guard=$_SESSION['booking_guard']; $_SESSION['booking_last_activity']=1000;
timeoutCheck(!expire_booking(1179),'Booking remains active before 180 seconds');
timeoutCheck(expire_booking(1180),'Booking expires at exactly 180 seconds');
foreach(['customer_draft','customer_request','customer_receipt','booking_guard','booking_last_activity'] as $key) timeoutCheck(!isset($_SESSION[$key]),"Timeout removes $key");
timeoutCheck($_SESSION['uid']===42 && $_SESSION['csrf']==='keep-auth-csrf','Form reset preserves signed-in staff session');
timeoutCheck(!booking_guard_valid($guard),'Expired form token is rejected');
start_booking_activity(); timeoutCheck($_SESSION['booking_guard']!==$guard,'New form receives a different booking token');
timeoutCheck(!expire_booking(time()),'Fresh form is active');
clear_booking(); timeoutCheck(!isset($_SESSION['customer_draft'],$_SESSION['booking_last_activity']),'Explicit reset clears the draft');
echo "$checks booking timeout checks passed.\n";
