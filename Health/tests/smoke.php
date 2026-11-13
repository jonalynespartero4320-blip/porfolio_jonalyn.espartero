<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require __DIR__.'/require-sqlite.php';
$base=rtrim(getenv('HEALTH_TEST_URL')?:'http://127.0.0.1:8080','/'); $cookies=[]; $checks=0;
function request(string $path,?array $data=null): array {
    global $base,$cookies;
    $headers='Cookie: '.implode('; ',array_map(fn($k,$v)=>"$k=$v",array_keys($cookies),$cookies))."\r\nContent-Type: application/x-www-form-urlencoded\r\n";
    $body=file_get_contents($base.'/'.$path,false,stream_context_create(['http'=>['method'=>$data===null?'GET':'POST','header'=>$headers,'content'=>$data===null?'':http_build_query($data),'ignore_errors'=>true,'follow_location'=>0,'timeout'=>10]]));
    if($body===false) throw new RuntimeException('Start the local test server.');
    foreach($http_response_header as $h) if(preg_match('/^Set-Cookie: ([^=]+)=([^;]*)/i',$h,$m)) $cookies[$m[1]]=$m[2];
    preg_match('/\s(\d{3})\s/',$http_response_header[0],$m); return [(int)$m[1],$body,$http_response_header];
}
function check(bool $ok,string $message): void { global $checks; if(!$ok) throw new RuntimeException($message); echo "PASS $message\n"; $checks++; }
[$status,, $headers]=request(''); check($status===303 && str_contains(implode(' ',$headers),'/customer'),'Guest root opens customer booking');
[$status,$body]=request('customer'); check($status===200 && str_contains($body,'Job applying for'),'Public customer form opens without authentication');
check(!str_contains($body,'Create account') && !str_contains($body,'name="password"') && !str_contains($body,'name="email"'),'Customer form has no account, password, or email fields');
check(!str_contains($body,'class="sidebar"') && !str_contains($body,'Administrator sign in') && str_contains($body,'government-masthead') && str_contains($body,'health-department-logo.jpg'),'Customer page shows government identity without sidebar or staff controls');
preg_match('/name="csrf" value="([^"]+)"/',$body,$match); $csrf=$match[1];
[$status]=request('register'); check($status===404,'Registration page is disabled');
[$status]=request('register',['csrf'=>$csrf,'name'=>'No Account','email'=>'disabled@example.com','password'=>'NoAccountCreated!123']); check($status===404,'Registration POST is disabled');
[$status,$body]=request('login'); check($status===200 && str_contains($body,'Admin sign in') && !str_contains($body,'Create account'),'Login is reserved for staff');
[$status]=request('admin'); check($status===303,'Guest cannot access appointments dashboard');
[$status]=request('manage-admins'); check($status===303,'Guest cannot create Admin accounts');
[$status]=request('book'); check($status===404,'Legacy account-based booking route is disabled');
[$status]=request('does-not-exist'); check($status===404,'Unknown route returns 404');
[$status]=request('assets/style.css'); check($status===200,'Stylesheet is available');
echo "$checks smoke checks passed.\n";
