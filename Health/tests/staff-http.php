<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require __DIR__.'/require-sqlite.php';
$base=rtrim(getenv('HEALTH_TEST_URL')?:'http://127.0.0.1:8080','/'); $cookies=[]; $checks=0;
date_default_timezone_set('Asia/Singapore');
function staffRequest(string $path,?array $data=null): array {
    global $base,$cookies;
    $headers='Cookie: '.implode('; ',array_map(fn($k,$v)=>"$k=$v",array_keys($cookies),$cookies))."\r\nContent-Type: application/x-www-form-urlencoded\r\n";
    $body=file_get_contents($base.'/'.$path,false,stream_context_create(['http'=>['method'=>$data===null?'GET':'POST','header'=>$headers,'content'=>$data===null?'':http_build_query($data),'ignore_errors'=>true,'follow_location'=>0,'timeout'=>10]]));
    if($body===false) throw new RuntimeException('Local test server is unavailable.');
    foreach($http_response_header as $header) if(preg_match('/^Set-Cookie: ([^=]+)=([^;]*)/i',$header,$m)) $cookies[$m[1]]=$m[2];
    preg_match('/\s(\d{3})\s/',$http_response_header[0],$m); return [(int)$m[1],$body];
}
function staffCheck(bool $ok,string $message): void { global $checks; if(!$ok) throw new RuntimeException($message); echo "PASS $message\n"; $checks++; }
function staffToken(string $body): string { preg_match('/name="csrf" value="([^"]+)"/',$body,$m); return $m[1]??''; }
$db=new PDO('sqlite:'.dirname(__DIR__).'/storage/health.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$tag='stafftest-'.bin2hex(random_bytes(6)); $password='TemporaryTestPassword!489';
$superUsername=$tag.'-super'; $adminUsername=$tag.'-admin'; $patientUsername=$tag.'-patient';
function readStaffBooking(PDO $db,int $id): array { $q=$db->prepare('SELECT * FROM customer_bookings WHERE id=?'); $q->execute([$id]); $row=$q->fetch(PDO::FETCH_ASSOC); $q->closeCursor(); return $row; }
try {
    $db->prepare("INSERT INTO users(name,username,password,role) VALUES(?,?,?,'super_admin')")->execute(['Test Super',$superUsername,password_hash($password,PASSWORD_DEFAULT)]);
    [, $body]=staffRequest('login'); staffCheck(str_contains($body,'Username') && str_contains($body,'type="text"'),'Login accepts username input'); $csrf=staffToken($body);
    [$status]=staffRequest('login',['csrf'=>$csrf,'username'=>$tag.'-super','password'=>$password]); staffCheck($status===303,'Super admin signs in by username');
    [$status,$body]=staffRequest('admin'); staffCheck($status===200 && str_contains($body,'Manage admins'),'Super admin can view all appointments');
    [$status,$body]=staffRequest('manage-admins'); staffCheck($status===200,'Super admin opens account management');
    staffCheck(!str_contains($body,'name="email"') && !str_contains($body,'<th>Email</th>'),'Admin creation and listing do not use email');
    $newAdmin=['csrf'=>$csrf,'name'=>'Test Desk Admin','username'=>$tag.'-admin','password'=>$password,'role'=>'super_admin'];
    [$status]=staffRequest('manage-admins',$newAdmin); staffCheck($status===303,'Super admin creates an admin');
    $q=$db->prepare('SELECT id,role,password FROM users WHERE username=?'); $q->execute([$adminUsername]); $admin=$q->fetch(PDO::FETCH_ASSOC); $q->closeCursor();
    staffCheck($admin['role']==='admin' && password_verify($password,$admin['password']) && $admin['password']!==$password,'Created role is always admin and password is hashed');
    [, $body]=staffRequest('manage-admins',$newAdmin); staffCheck(str_contains($body,'already in use'),'Duplicate admin username is rejected');
    [$status]=staffRequest('manage-admins',array_replace($newAdmin,['csrf'=>'bad'])); staffCheck($status===419,'Admin creation requires CSRF');
    $cookies=[]; [, $body]=staffRequest('login'); $csrf=staffToken($body);
    staffRequest('login',['csrf'=>$csrf,'username'=>strtoupper($tag.'-admin'),'password'=>$password]);
    [$status]=staffRequest('admin'); staffCheck($status===200,'New admin can sign in by username case-insensitively');
    [$status,$body]=staffRequest('manage-admins'); staffCheck($status===403 && !str_contains($body,'Create an admin account'),'Regular admin cannot open account management');
    [$status]=staffRequest('manage-admins',$newAdmin); staffCheck($status===403,'Regular admin cannot create or escalate accounts');
    $insert=$db->prepare('INSERT INTO customer_bookings(full_name,gender,company,job,date,period,reference,request_token) VALUES(?,?,?,?,?,?,?,?)');
    $insert->execute(['Arrival Test','Male',$tag,'Tester',date('Y-m-d'),'AM',$tag.'-arrival',bin2hex(random_bytes(32))]); $id=(int)$db->lastInsertId();
    [$status]=staffRequest('arrival',['csrf'=>'bad','type'=>'customer','id'=>$id]); staffCheck($status===419 && readStaffBooking($db,$id)['arrived_at']===null,'Arrival requires CSRF');
    [$status]=staffRequest('arrival',['csrf'=>$csrf,'type'=>'customer','id'=>$id]); $arrived=readStaffBooking($db,$id);
    staffCheck($status===303 && $arrived['arrived_at']!==null && (int)$arrived['arrived_by']===(int)$admin['id'] && $arrived['status']==='confirmed','Admin records arrival time and staff identity without releasing capacity');
    staffRequest('arrival',['csrf'=>$csrf,'type'=>'customer','id'=>$id]); staffCheck(readStaffBooking($db,$id)['arrived_at']===$arrived['arrived_at'],'Repeated check-in preserves original arrival');
    [$status,$body]=staffRequest('admin'); staffCheck(str_contains($body,'Recorded by Test Desk Admin') && str_contains($body,'Arrived'),'Dashboard displays attendance audit');
    $db->prepare('INSERT INTO appointments(user_id,doctor_id,date,time,reason) VALUES(?,1,?,?,?)')->execute([$admin['id'],date('Y-m-d'),'00:01',$tag]); $appointmentId=(int)$db->lastInsertId();
    staffRequest('arrival',['csrf'=>$csrf,'type'=>'appointment','id'=>$appointmentId]);
    $q=$db->prepare('SELECT arrived_at,arrived_by FROM appointments WHERE id=?'); $q->execute([$appointmentId]); $doctorArrival=$q->fetch(PDO::FETCH_ASSOC); $q->closeCursor();
    staffCheck($doctorArrival['arrived_at']!==null && (int)$doctorArrival['arrived_by']===(int)$admin['id'],'Staff can record patient appointment arrivals too');
    $insert->execute(['Future Test','Female',$tag,'Tester',date('Y-m-d',strtotime('+2 days')),'PM',$tag.'-future',bin2hex(random_bytes(32))]); $future=(int)$db->lastInsertId();
    staffRequest('arrival',['csrf'=>$csrf,'type'=>'customer','id'=>$future]); staffCheck(readStaffBooking($db,$future)['arrived_at']===null,'Future bookings cannot be marked arrived');
    $db->prepare("UPDATE customer_bookings SET status='cancelled',date=? WHERE id=?")->execute([date('Y-m-d'),$future]);
    staffRequest('arrival',['csrf'=>$csrf,'type'=>'customer','id'=>$future]); staffCheck(readStaffBooking($db,$future)['arrived_at']===null,'Cancelled bookings cannot be marked arrived');
    $cookies=[]; [, $body]=staffRequest('login'); $csrf=staffToken($body);
    [$status]=staffRequest('register',['csrf'=>$csrf,'name'=>'Test Patient','username'=>$patientUsername,'password'=>$password,'role'=>'super_admin']); staffCheck($status===404,'Public registration is disabled');
    $db->prepare("INSERT INTO users(name,username,password,role) VALUES(?,?,?,'patient')")->execute(['Legacy Patient',$patientUsername,password_hash($password,PASSWORD_DEFAULT)]);
    [, $body]=staffRequest('login',['csrf'=>$csrf,'username'=>$patientUsername,'password'=>$password]); staffCheck(str_contains($body,'incorrect'),'Legacy customer accounts cannot sign into the staff portal');
    [$status]=staffRequest('arrival',['csrf'=>$csrf,'type'=>'customer','id'=>$id]); staffCheck($status===303,'Guests cannot record arrivals');
    echo "$checks staff checks passed.\n";
} finally {
    $db->prepare('DELETE FROM customer_bookings WHERE company=?')->execute([$tag]);
    $db->prepare('DELETE FROM appointments WHERE reason=?')->execute([$tag]);
    $db->prepare('DELETE FROM users WHERE username IN (?,?,?)')->execute([$superUsername,$adminUsername,$patientUsername]);
}
