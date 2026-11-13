<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require __DIR__.'/require-sqlite.php';
$base=rtrim(getenv('HEALTH_TEST_URL')?:'http://127.0.0.1:8080','/'); $cookies=[]; $checks=0;
function callCustomer(string $path,?array $data=null): array {
    global $base,$cookies;
    $headers='Cookie: '.implode('; ',array_map(fn($k,$v)=>"$k=$v",array_keys($cookies),$cookies))."\r\nContent-Type: application/x-www-form-urlencoded\r\n";
    $body=file_get_contents($base.'/'.$path,false,stream_context_create(['http'=>['method'=>$data===null?'GET':'POST','header'=>$headers,'content'=>$data===null?'':http_build_query($data),'ignore_errors'=>true,'follow_location'=>0,'timeout'=>10]]));
    if($body===false) throw new RuntimeException('Start the local server before running this test.');
    foreach($http_response_header as $h) if(preg_match('/^Set-Cookie: ([^=]+)=([^;]*)/i',$h,$m)) $cookies[$m[1]]=$m[2];
    preg_match('/\s(\d{3})\s/',$http_response_header[0],$m); return [(int)$m[1],$body,$http_response_header];
}
function assertCustomer(bool $ok,string $message): void { global $checks; if(!$ok) throw new RuntimeException($message); echo "PASS $message\n"; $checks++; }
function field(string $body,string $name): string { preg_match('/name="'.preg_quote($name,'/').'" value="([^"]*)"/',$body,$m); return $m[1]??''; }
$company='Test Company '.bin2hex(random_bytes(8)); $date=date('Y-m-d',strtotime('+65 days')); while((int)date('N',strtotime($date))>5) $date=date('Y-m-d',strtotime($date.' +1 day'));
$db=new PDO('sqlite:'.dirname(__DIR__).'/storage/health.sqlite'); $adminUsername='customer-test-'.bin2hex(random_bytes(8));
try {
    $accountsBefore=(int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();
    [$status,$body]=callCustomer('customer'); $csrf=field($body,'csrf'); $guard=field($body,'booking_guard'); assertCustomer($status===200 && str_contains($body,'Job applying for'),'Guest can open customer details form');
    [$status]=callCustomer('schedule'); assertCustomer($status===303,'Schedule requires customer details');
    [$status]=callCustomer('booking-confirmation?id=1'); assertCustomer($status===303,'Confirmation cannot be accessed using an arbitrary booking ID');
    [$status]=callCustomer('customer',['csrf'=>'bad']); assertCustomer($status===419,'Customer form requires CSRF');
    $details=['csrf'=>$csrf,'booking_guard'=>$guard,'full_name'=>'Juan Delacruz','gender'=>'Male','company'=>$company,'job'=>'Office Assistant'];
    [$status]=callCustomer('customer',$details); assertCustomer($status===303,'Valid customer details advance to scheduling');
    [$status,$body]=callCustomer('schedule'); $token=field($body,'request_token'); assertCustomer($status===200 && str_contains($body,'Select a date first') && strlen($token)===64,'Sessions wait for a date selection');
    [$status,$body]=callCustomer('availability?date='.urlencode($date)); $availability=json_decode($body,true); assertCustomer($status===200 && isset($availability['AM']['remaining'],$availability['PM']['remaining']),'Live endpoint returns both remaining session counts');
    [$status]=callCustomer('availability?date=not-a-date'); assertCustomer($status===422,'Availability rejects invalid dates');
    [$status]=callCustomer('availability',['date'=>$date]); assertCustomer($status===405,'Availability is read-only');
    [$status,$body]=callCustomer('schedule?date='.$date); assertCustomer($status===200 && str_contains($body,'250 slots available'),'Calendar date selection works without JavaScript');
    $booking=['csrf'=>$csrf,'request_token'=>$token,'date'=>$date,'period'=>'AM'];
    [$status,$body]=callCustomer('schedule',array_replace($booking,['request_token'=>'wrong'])); assertCustomer(str_contains($body,'booking details changed'),'Stale booking form is rejected');
    [$status]=callCustomer('schedule',$booking); assertCustomer($status===303,'AM booking confirms successfully');
    assertCustomer((int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn()===$accountsBefore,'Open customer booking creates no account');
    [$status,$body]=callCustomer('booking-confirmation'); assertCustomer($status===200 && preg_match('/\d{3,}-\d{6}-A/',$body) && str_contains($body,$company),'Confirmation shows customer details and AM reference');
    callCustomer('schedule',$booking);
    $q=$db->prepare('SELECT COUNT(*) FROM customer_bookings WHERE company=?'); $q->execute([$company]); $count=(int)$q->fetchColumn(); $q->closeCursor(); assertCustomer($count===1,'HTTP double submission does not reserve another slot');
    [, $body]=callCustomer('availability?date='.$date); $after=json_decode($body,true); assertCustomer($after['AM']['remaining']===$availability['AM']['remaining']-1 && $after['PM']['remaining']===$availability['PM']['remaining'],'Availability counts update after booking');
    callCustomer('customer',$details); [, $body]=callCustomer('schedule'); $token=field($body,'request_token');
    [$status]=callCustomer('schedule',array_replace($booking,['request_token'=>$token,'period'=>'PM'])); assertCustomer($status===303,'PM session can be booked');
    [, $body]=callCustomer('booking-confirmation'); assertCustomer((bool)preg_match('/\d{3,}-\d{6}-P/',$body),'PM confirmation uses P suffix');
    [, $body]=callCustomer('customer');
    assertCustomer(field($body,'full_name')==='' && field($body,'company')==='' && field($body,'job')==='' && !str_contains($body,'selected'),'New appointment starts with blank details and no selected gender');
    assertCustomer(field($body,'booking_guard')!==$guard,'New appointment invalidates the previous form token');
    [$status]=callCustomer('schedule'); assertCustomer($status===303,'Previous customer draft is cleared for the next appointment');
    $q=$db->prepare('SELECT COUNT(*) FROM customer_bookings WHERE company=?'); $q->execute([$company]); $count=(int)$q->fetchColumn(); $q->closeCursor(); assertCustomer($count===2,'Starting another appointment keeps both confirmed reservations');
    $cookies=[]; [$status]=callCustomer('booking-confirmation'); assertCustomer($status===303,'Another browser cannot view the confirmation');
    [$status]=callCustomer('customer-status',['csrf'=>$csrf,'id'=>1,'status'=>'cancelled']); assertCustomer($status===303,'Guests cannot change customer status');
    $adminPassword='TestAdminPassword!472';
    $db->prepare("INSERT INTO users(name,username,password,role) VALUES(?,?,?,'admin')")->execute(['Customer Test Admin',$adminUsername,password_hash($adminPassword,PASSWORD_DEFAULT)]);
    [, $body]=callCustomer('login'); $adminCsrf=field($body,'csrf');
    callCustomer('login',['csrf'=>$adminCsrf,'username'=>$adminUsername,'password'=>$adminPassword]);
    [$status,$body]=callCustomer('admin'); assertCustomer($status===200 && str_contains($body,$company) && str_contains($body,'Customer health schedules'),'Admin sees customer references and employment details');
    $q=$db->prepare("SELECT id FROM customer_bookings WHERE company=? AND period='AM'"); $q->execute([$company]); $id=$q->fetchColumn(); $q->closeCursor();
    [$status,$body]=callCustomer('customer-status',['csrf'=>$adminCsrf,'id'=>$id,'status'=>'completed']); assertCustomer(str_contains($body,'That status change is not available'),'Admin cannot complete a future customer session');
    [$status]=callCustomer('customer-status',['csrf'=>$adminCsrf,'id'=>$id,'status'=>'cancelled']); assertCustomer($status===303,'Admin can cancel a customer booking');
    [, $body]=callCustomer('availability?date='.$date); $released=json_decode($body,true); assertCustomer($released['AM']['remaining']===$availability['AM']['remaining'],'Admin cancellation releases the slot in live availability');
    echo "$checks customer HTTP checks passed.\n";
} finally {
    $q=$db->prepare('DELETE FROM customer_bookings WHERE company=?'); $q->execute([$company]);
    $db->prepare('DELETE FROM users WHERE username=?')->execute([$adminUsername]);
}
