<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require __DIR__.'/require-sqlite.php';
date_default_timezone_set('Asia/Singapore');
$base=rtrim(getenv('HEALTH_TEST_URL')?:'http://127.0.0.1:8080','/'); $cookies=[]; $checks=0;
function cancelRequest(string $path,?array $data=null): array {
    global $base,$cookies;
    $headers='Cookie: '.implode('; ',array_map(fn($k,$v)=>"$k=$v",array_keys($cookies),$cookies))."\r\nContent-Type: application/x-www-form-urlencoded\r\n";
    $body=file_get_contents($base.'/'.$path,false,stream_context_create(['http'=>['method'=>$data===null?'GET':'POST','header'=>$headers,'content'=>$data===null?'':http_build_query($data),'ignore_errors'=>true,'follow_location'=>0,'timeout'=>10]]));
    if($body===false) throw new RuntimeException('Start the local server.');
    foreach($http_response_header as $header) if(preg_match('/^Set-Cookie: ([^=]+)=([^;]*)/i',$header,$m)) $cookies[$m[1]]=$m[2];
    preg_match('/\s(\d{3})\s/',$http_response_header[0],$m); return [(int)$m[1],$body];
}
function cancellationCheck(bool $ok,string $message): void { global $checks; if(!$ok) throw new RuntimeException($message); echo "PASS $message\n"; $checks++; }
function cancellationField(string $body,string $name): string { preg_match('/name="'.preg_quote($name,'/').'" value="([^"]*)"/',$body,$m); return $m[1]??''; }
function cancellationCounts(string $date): array { [, $body]=cancelRequest('availability?date='.$date); return json_decode($body,true); }
$db=new PDO('sqlite:'.dirname(__DIR__).'/storage/health.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$company='Cancellation Test '.bin2hex(random_bytes(6)); $username='canceltest-'.bin2hex(random_bytes(5));
$weekend=date('Y-m-d',strtotime('+35 days')); while(date('N',strtotime($weekend))!=='6') $weekend=date('Y-m-d',strtotime($weekend.' +1 day'));
$date=date('Y-m-d',strtotime($weekend.' +2 days'));
$otherDate=date('Y-m-d',strtotime($date.' +1 day'));
$limiterIdentity=hash('sha256','customer-cancellation|127.0.0.1');
$q=$db->prepare('SELECT attempts,expires FROM login_attempts WHERE identity=?'); $q->execute([$limiterIdentity]); $oldLimiter=$q->fetch(PDO::FETCH_ASSOC); $q->closeCursor();
$db->prepare('DELETE FROM login_attempts WHERE identity=?')->execute([$limiterIdentity]);
try {
    [, $body]=cancelRequest('customer'); $csrf=cancellationField($body,'csrf'); $guard=cancellationField($body,'booking_guard');
    cancellationCheck(str_contains($body,'cancel-appointment'),'Public navigation includes cancellation');
    $before=cancellationCounts($date); $otherBefore=cancellationCounts($otherDate);
    cancelRequest('customer',['csrf'=>$csrf,'booking_guard'=>$guard,'full_name'=>'Juan Delacruz','gender'=>'Male','company'=>$company,'job'=>'Office Assistant']);
    [, $body]=cancelRequest('schedule'); $requestToken=cancellationField($body,'request_token');
    foreach([$weekend,date('Y-m-d',strtotime($weekend.' +1 day'))] as $closedDate) {
        [$status]=cancelRequest('availability?date='.$closedDate); cancellationCheck($status===422,'Weekend availability is rejected');
        [$status,$body]=cancelRequest('schedule',['csrf'=>$csrf,'request_token'=>$requestToken,'date'=>$closedDate,'period'=>'AM']); cancellationCheck($status===200 && str_contains($body,'Monday–Friday'),'Weekend booking submissions are rejected');
    }
    [$status]=cancelRequest('schedule',['csrf'=>$csrf,'request_token'=>$requestToken,'date'=>$date,'period'=>'AM']); cancellationCheck($status===303,'A far-future weekday can be reserved');
    [, $body]=cancelRequest('booking-confirmation'); preg_match('/\d{3,}-\d{6}-A/',$body,$match); $reference=$match[0];
    $reserved=cancellationCounts($date);
    cancellationCheck($reserved['AM']['remaining']===$before['AM']['remaining']-1 && $reserved['PM']['remaining']===$before['PM']['remaining'],'Future reservation consumes only its selected session');
    cancellationCheck(cancellationCounts($otherDate)===$otherBefore,'Future reservation does not affect other dates');
    // A different browser session can cancel without creating or signing into an account.
    $cookies=[]; [$status,$body]=cancelRequest('cancel-appointment'); $csrf=cancellationField($body,'csrf'); $guard=cancellationField($body,'booking_guard');
    cancellationCheck($status===200 && !str_contains($body,'name="password"') && !str_contains($body,'name="email"'),'Public cancellation needs no account or email');
    $reason='Cannot attend due to work commitments. <script>alert(1)</script>';
    $cancel=['csrf'=>$csrf,'booking_guard'=>$guard,'reference'=>$reference,'full_name'=>'Juan Delacruz','reason'=>$reason,'confirm_cancel'=>'yes'];
    [$status]=cancelRequest('cancel-appointment',array_replace($cancel,['csrf'=>'wrong'])); cancellationCheck($status===419,'Cancellation requires CSRF');
    [, $body]=cancelRequest('cancel-appointment',array_replace($cancel,['confirm_cancel'=>''])); cancellationCheck(str_contains($body,'Confirm that'),'Cancellation requires explicit confirmation');
    [, $body]=cancelRequest('cancel-appointment',array_replace($cancel,['full_name'=>'Another Person'])); cancellationCheck(str_contains($body,'Unable to cancel'),'Knowing a reference alone cannot cancel another person’s booking');
    [, $body]=cancelRequest('cancel-appointment',array_replace($cancel,['reason'=>'No'])); cancellationCheck(str_contains($body,'10–500'),'A reason is required');
    cancellationCheck(cancellationCounts($date)['AM']['remaining']===$reserved['AM']['remaining'],'Rejected requests leave the reservation intact');
    [$status]=cancelRequest('cancel-appointment',$cancel); cancellationCheck($status===303,'Correct reference, name, and reason cancel successfully');
    $after=cancellationCounts($date);
    cancellationCheck($after['AM']['remaining']===$before['AM']['remaining'] && $after['PM']['remaining']===$before['PM']['remaining'],'Cancellation restores exactly one original-date AM slot');
    cancellationCheck(cancellationCounts($otherDate)===$otherBefore,'Cancellation leaves other dates unchanged');
    $q=$db->prepare('SELECT status,cancellation_reason,cancelled_at,cancellation_source FROM customer_bookings WHERE reference=?'); $q->execute([$reference]); $row=$q->fetch(PDO::FETCH_ASSOC); $q->closeCursor();
    cancellationCheck($row['status']==='cancelled' && $row['cancellation_reason']===$reason && $row['cancelled_at'] && $row['cancellation_source']==='customer','Reason, timestamp, and cancellation source are retained');
    cancelRequest('cancel-appointment',$cancel); cancellationCheck(cancellationCounts($date)===$after,'Repeated cancellation cannot release a second slot');
    $db->prepare("INSERT INTO users(name,username,password,role) VALUES(?,?,?,'admin')")->execute(['Cancellation Reviewer',$username,password_hash('ReviewerPassword!127',PASSWORD_DEFAULT)]);
    [, $body]=cancelRequest('login'); cancelRequest('login',['csrf'=>cancellationField($body,'csrf'),'username'=>$username,'password'=>'ReviewerPassword!127']);
    [, $body]=cancelRequest('admin'); cancellationCheck(str_contains($body,'Cancellation reason') && str_contains($body,'&lt;script&gt;alert(1)&lt;/script&gt;') && !str_contains($body,'<script>alert(1)</script>'),'Admin can safely review the cancellation reason');
    // Neither attended nor completed appointments can be released publicly.
    $db->prepare("UPDATE customer_bookings SET status='confirmed',arrived_at=? WHERE reference=?")->execute([date('Y-m-d H:i:s'),$reference]);
    $cookies=[]; [, $body]=cancelRequest('cancel-appointment'); $cancel['csrf']=cancellationField($body,'csrf'); $cancel['booking_guard']=cancellationField($body,'booking_guard');
    [, $body]=cancelRequest('cancel-appointment',$cancel); cancellationCheck(str_contains($body,'Unable to cancel'),'Checked-in customers cannot release their slot');
    $db->prepare("UPDATE customer_bookings SET status='completed',arrived_at=NULL WHERE reference=?")->execute([$reference]);
    [, $body]=cancelRequest('cancel-appointment',$cancel); cancellationCheck(str_contains($body,'Unable to cancel'),'Completed appointments cannot be cancelled');
    $db->prepare("UPDATE customer_bookings SET status='confirmed',date='2020-01-01' WHERE reference=?")->execute([$reference]);
    [, $body]=cancelRequest('cancel-appointment',$cancel); cancellationCheck(str_contains($body,'Unable to cancel'),'Past sessions cannot be cancelled');
    $blocked=false;
    for($i=0;$i<12;$i++) { [$status]=cancelRequest('cancel-appointment',array_replace($cancel,['reference'=>'JD-999999999999999999-A'])); if($status===429) { $blocked=true; break; } }
    cancellationCheck($blocked,'Repeated guessing is rate-limited');
    echo "$checks cancellation checks passed.\n";
} finally {
    $db->prepare('DELETE FROM customer_bookings WHERE company=?')->execute([$company]);
    $db->prepare('DELETE FROM users WHERE username=?')->execute([$username]);
    $db->prepare('DELETE FROM login_attempts WHERE identity=?')->execute([$limiterIdentity]);
    if($oldLimiter) $db->prepare('INSERT INTO login_attempts(identity,attempts,expires) VALUES(?,?,?)')->execute([$limiterIdentity,$oldLimiter['attempts'],$oldLimiter['expires']]);
}
