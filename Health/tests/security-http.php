<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require __DIR__.'/require-sqlite.php';
if(($argv[1]??'')==='expire-fixture') {
    $sessionId=trim(stream_get_contents(STDIN));
    if(!preg_match('/^[a-zA-Z0-9,-]{16,128}$/',$sessionId)) exit(1);
    session_id($sessionId);
    require dirname(__DIR__).'/app/bootstrap.php';
    $_SESSION['booking_last_activity']=time()-180;
    session_write_close(); exit;
}
$base=rtrim(getenv('HEALTH_TEST_URL')?:'http://127.0.0.1:8080','/'); $cookies=[]; $checks=0;
function securityRequest(string $path,?array $data=null): array {
    global $base,$cookies;
    $headers='Cookie: '.implode('; ',array_map(fn($k,$v)=>"$k=$v",array_keys($cookies),$cookies))."\r\nContent-Type: application/x-www-form-urlencoded\r\n";
    $body=file_get_contents($base.'/'.$path,false,stream_context_create(['http'=>['method'=>$data===null?'GET':'POST','header'=>$headers,'content'=>$data===null?'':http_build_query($data),'ignore_errors'=>true,'follow_location'=>0,'timeout'=>10]]));
    if($body===false) throw new RuntimeException('Start the local test server.');
    foreach($http_response_header as $h) if(preg_match('/^Set-Cookie: ([^=]+)=([^;]*)/i',$h,$m)) $cookies[$m[1]]=$m[2];
    preg_match('/\s(\d{3})\s/',$http_response_header[0],$m); return [(int)$m[1],$body,$http_response_header];
}
function securityCheck(bool $ok,string $message): void { global $checks; if(!$ok) throw new RuntimeException($message); echo "PASS $message\n"; $checks++; }
function securityField(string $body,string $name): string { preg_match('/name="'.preg_quote($name,'/').'" value="([^"]*)"/',$body,$m); return $m[1]??''; }
[, $body,$headers]=securityRequest('customer'); $csrf=securityField($body,'csrf'); $guard=securityField($body,'booking_guard');
securityCheck(str_contains($body,'booking-idle.js') && str_contains($body,'data-remaining="180"'),'Booking page enables the 180-second inactivity timer');
$allHeaders=strtolower(implode("\n",$headers));
securityCheck(str_contains($allHeaders,'httponly') && str_contains($allHeaders,'samesite=lax') && str_contains($allHeaders,'healthsessid'),'Session cookie uses app-specific name and security flags');
securityCheck(str_contains($allHeaders,"object-src 'none'") && str_contains($allHeaders,"frame-ancestors 'none'") && str_contains($allHeaders,'no-store'),'Browser protections and private-page caching policy');
[$status]=securityRequest('booking-reset'); securityCheck($status===405,'Reset cannot be triggered with GET');
[$status]=securityRequest('booking-reset',['csrf'=>'bad','booking_guard'=>$guard]); securityCheck($status===419,'Reset requires a valid CSRF token');
[$status]=securityRequest('booking-activity',['csrf'=>$csrf,'booking_guard'=>'wrong']); securityCheck($status===409,'Activity cannot revive an invalid booking token');
$details=['csrf'=>$csrf,'booking_guard'=>$guard,'full_name'=>'Juan Delacruz','gender'=>'Male','company'=>'Security Test','job'=>'Tester'];
[$status]=securityRequest('customer',array_replace($details,['full_name'=>['nested'=>'malformed']])); securityCheck($status===400,'Nested form fields are rejected');
[, $body]=securityRequest('login',['csrf'=>$csrf,'username'=>"' OR 1=1 --",'password'=>'any-password']); securityCheck(!str_contains($body,'All patient appointments'),'SQL injection cannot bypass login');
[$status,$body]=securityRequest('availability?date='.urlencode("2026-11-20' OR 1=1 --")); securityCheck($status===422,'SQL injection in calendar date is rejected');
[$status,$body]=securityRequest('availability?date='.urlencode("2026-11-20\0")); securityCheck($status===422 && !str_contains($body,'Fatal error'),'Null bytes in calendar dates are safely rejected');
$xss='<script>alert(document.cookie)</script>';
securityRequest('customer',array_replace($details,['company'=>$xss]));
[, $body]=securityRequest('schedule'); securityCheck(!str_contains($body,$xss) && str_contains($body,'&lt;script&gt;'),'Customer-provided scripts are escaped in scheduling summary');
$requestToken=securityField($body,'request_token');
[$status]=securityRequest('booking-activity',['csrf'=>$csrf,'booking_guard'=>$guard]); securityCheck($status===200,'Real user activity can refresh an active booking');
[$status]=securityRequest('booking-reset',['csrf'=>$csrf,'booking_guard'=>$guard]); securityCheck($status===200,'Authenticated reset clears unfinished booking');
[$status]=securityRequest('schedule'); securityCheck($status===303,'Reset removes the server-side customer draft');
[$status]=securityRequest('schedule',['csrf'=>$csrf,'request_token'=>$requestToken,'date'=>'2026-11-20','period'=>'AM']); securityCheck($status===303,'Old schedule cannot be submitted after reset');
[$status,$body]=securityRequest('customer'); $newGuard=securityField($body,'booking_guard');
securityCheck($newGuard!==$guard && !str_contains($body,'value="Juan Delacruz"'),'Reset returns a blank form with a fresh booking token');
[$status,$body]=securityRequest('customer',$details); securityCheck($status===419 && !str_contains($body,'value="Juan Delacruz"'),'Stale customer details cannot be replayed or redisplayed after reset');
// Age this test's own session through a CLI-only fixture, never a web backdoor.
securityRequest('customer',array_replace($details,['booking_guard'=>$newGuard]));
$process=proc_open([PHP_BINARY,__FILE__,'expire-fixture'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
if(!is_resource($process)) throw new RuntimeException('Unable to launch timeout fixture.');
fwrite($pipes[0],$cookies['HEALTHSESSID']); fclose($pipes[0]);
$fixtureOutput=stream_get_contents($pipes[1]); $fixtureErrors=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
if(proc_close($process)!==0) throw new RuntimeException('Timeout fixture failed: '.$fixtureOutput.$fixtureErrors);
[$status,$body]=securityRequest('availability?date=2026-11-20');
securityCheck($status===200,'Background slot polling remains available after booking expiry');
[$status]=securityRequest('booking-activity',['csrf'=>$csrf,'booking_guard'=>$newGuard]); securityCheck($status===409,'Polling cannot keep expired booking active or revive it');
[$status]=securityRequest('schedule'); securityCheck($status===303,'Server rejects expired draft even when browser timer is disabled');
[, $body]=securityRequest('customer'); securityCheck(!str_contains($body,'value="Juan Delacruz"'),'Server-side inactivity expiration removes private customer details');
foreach(['app/bootstrap.php','storage/health.sqlite','.git/config','scripts/create-super-admin.php'] as $private) {
    [$status,$body]=securityRequest($private); securityCheck($status===404 && !str_contains($body,'<?php'),"Private file is not served: $private");
}
echo "$checks security HTTP checks passed.\n";
