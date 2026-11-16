<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
date_default_timezone_set('Asia/Singapore');
require dirname(__DIR__).'/app/customer.php';
$options=json_decode(stream_get_contents(STDIN),true);
if(!is_array($options)) { fwrite(STDERR,"Provide local MySQL admin connection JSON on stdin.\n"); exit(1); }
function mysqlTestConnection(array $options,?string $database=null): PDO {
    $port=(int)($options['port']??3308);
    if($database!==null && !preg_match('/^health_appointments_verify_[a-f0-9]{16}$/D',$database)) throw new RuntimeException('Invalid isolated test database.');
    return new PDO('mysql:host=127.0.0.1;port='.$port.';charset=utf8mb4'.($database?';dbname='.$database:''),$options['username']??'root',$options['password']??'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
}
$details=['full_name'=>'Test Patient','gender'=>'Other','company'=>'Isolated test','job'=>'Tester'];
if(($argv[1]??'')==='worker') {
    try { reserve_customer(mysqlTestConnection($options,$options['database']),$details,$options['date'],'AM',bin2hex(random_bytes(32))); exit(0); }
    catch(RuntimeException $e) { if(str_contains($e->getMessage(),'session is full')) exit(2); fwrite(STDERR,'Worker failed ('.$e->getCode().').'); exit(1); }
}
$checks=0;
function mysqlCheck(bool $ok,string $message): void { global $checks; if(!$ok) throw new RuntimeException($message); echo "PASS $message\n"; $checks++; }
$temporary='health_appointments_verify_'.bin2hex(random_bytes(8)); $admin=mysqlTestConnection($options); $created=false;
$date=date('Y-m-d',strtotime('+40 days')); while((int)date('N',strtotime($date))>5) $date=date('Y-m-d',strtotime($date.' +1 day'));
try {
    $admin->exec('CREATE DATABASE `'.$temporary.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'); $created=true;
    $sql=str_replace('health_appointments',$temporary,file_get_contents(dirname(__DIR__).'/database/health.sql'));
    $admin->exec($sql); $db=mysqlTestConnection($options,$temporary);
    mysqlCheck((int)$db->query('SELECT COUNT(*) FROM doctors')->fetchColumn()===3,'MySQL schema and seeds import successfully');
    $token=bin2hex(random_bytes(32)); $first=reserve_customer($db,$details,$date,'AM',$token);
    mysqlCheck($first['reference']==='001-'.date('mdy',strtotime($date)).'-A','MySQL reference includes queue number and appointment date');
    $again=reserve_customer($db,$details,$date,'AM',$token);
    mysqlCheck($again['id']===$first['id'] && customer_availability($db,$date)['AM']['remaining']===249,'MySQL retry is idempotent');
    for($i=1;$i<249;$i++) reserve_customer($db,$details,$date,'AM',bin2hex(random_bytes(32)));
    for($i=0;$i<250;$i++) reserve_customer($db,$details,$date,'PM',bin2hex(random_bytes(32)));
    mysqlCheck(customer_availability($db,$date)['PM']['remaining']===0,'MySQL limits PM capacity to 250');
    $workers=[];
    for($i=0;$i<8;$i++) {
        $p=proc_open([PHP_BINARY,'-d','extension=pdo_mysql',__FILE__,'worker'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if(!is_resource($p)) throw new RuntimeException('Cannot launch worker.');
        fwrite($pipes[0],json_encode($options+['database'=>$temporary,'date'=>$date])); fclose($pipes[0]); $workers[]=[$p,$pipes];
    }
    $won=0; $full=0;
    foreach($workers as [$p,$pipes]) {
        $out=stream_get_contents($pipes[1]); $err=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); $exit=proc_close($p);
        if($exit===0) $won++; elseif($exit===2) $full++; else throw new RuntimeException($out.$err);
    }
    mysqlCheck($won===1 && $full===7 && customer_availability($db,$date)['AM']['remaining']===0,'MySQL serializes eight concurrent requests for the final AM slot');
    mysqlCheck((int)$db->query('SELECT COUNT(*) FROM customer_bookings')->fetchColumn()===500,'MySQL never exceeds 500 reservations for the day');
    mysqlCheck(cancel_customer($db,$first['reference'],$details['full_name'],'Unable to attend the test appointment.'),'MySQL public cancellation succeeds');
    mysqlCheck(customer_availability($db,$date)['AM']['remaining']===1 && customer_availability($db,$date)['PM']['remaining']===0,'MySQL cancellation releases only the original session');
    mysqlCheck(!cancel_customer($db,$first['reference'],$details['full_name'],'Repeat cancellation must not free another slot.'),'MySQL duplicate cancellation cannot free another slot');
    $new=reserve_customer($db,$details,$date,'AM',bin2hex(random_bytes(32)));
    mysqlCheck($new['reference']==='251-'.date('mdy',strtotime($date)).'-A','MySQL reuses capacity without reusing references');
    $nextDate=date('Y-m-d',strtotime($date.' +1 day')); while((int)date('N',strtotime($nextDate))>5) $nextDate=date('Y-m-d',strtotime($nextDate.' +1 day'));
    $nextAM=reserve_customer($db,$details,$nextDate,'AM',bin2hex(random_bytes(32)));
    $nextPM=reserve_customer($db,$details,$nextDate,'PM',bin2hex(random_bytes(32)));
    mysqlCheck($nextAM['reference']==='001-'.date('mdy',strtotime($nextDate)).'-A','AM numbering restarts at 001 on the next appointment date');
    mysqlCheck($nextPM['reference']==='001-'.date('mdy',strtotime($nextDate)).'-P','PM has its own daily sequence starting at 001');
    $secondAM=reserve_customer($db,$details,$nextDate,'AM',bin2hex(random_bytes(32)));
    mysqlCheck($secondAM['reference']==='002-'.date('mdy',strtotime($nextDate)).'-A','Each daily time advances to 002 independently');
    $db->prepare('INSERT INTO customer_bookings(full_name,gender,company,job,date,period,reference,request_token) VALUES(?,?,?,?,?,?,?,?)')->execute([$details['full_name'],'Other','Legacy reference check','Tester',$nextDate,'AM','JD-900-A',bin2hex(random_bytes(32))]);
    mysqlCheck(cancel_customer($db,'JD-900-A',$details['full_name'],'Cannot attend the legacy appointment.'),'Legacy references remain valid for cancellation');
    $insert=$db->prepare('INSERT INTO appointments(user_id,doctor_id,date,time,reason) VALUES(1,1,?,?,?)');
    $insert->execute([$date,'09:00','Slot uniqueness test']); $id=(int)$db->lastInsertId();
    $duplicate=false; try { $insert->execute([$date,'09:00','Duplicate slot']); } catch(PDOException $e) { $duplicate=$e->getCode()==='23000'; }
    mysqlCheck($duplicate,'MySQL active doctor slots are unique');
    $db->prepare("UPDATE appointments SET status='cancelled' WHERE id=?")->execute([$id]); $insert->execute([$date,'09:00','Rebooked slot']);
    mysqlCheck(true,'MySQL permits rebooking a cancelled doctor slot');
    echo "$checks MySQL checks passed in an isolated database.\n";
} finally {
    // Delete only the random database created by this test, never the live database.
    if($created && preg_match('/^health_appointments_verify_[a-f0-9]{16}$/D',$temporary)) $admin->exec('DROP DATABASE `'.$temporary.'`');
}
