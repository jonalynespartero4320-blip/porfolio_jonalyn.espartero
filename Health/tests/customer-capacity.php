<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require __DIR__.'/require-sqlite.php';
require dirname(__DIR__).'/app/bootstrap.php';
require dirname(__DIR__).'/app/customer.php';
function connection(string $path): PDO {
    $db=new PDO('sqlite:'.$path,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $db->exec('PRAGMA busy_timeout=10000'); return $db;
}
$details=['full_name'=>'Juan Delacruz','gender'=>'Male','company'=>'Example Company','job'=>'Office Assistant'];
if(($argv[1]??'')==='worker') {
    try { reserve_customer(connection($argv[2]),$details,$argv[3],'AM',bin2hex(random_bytes(32))); exit(0); }
    catch(RuntimeException $e) { if(str_contains($e->getMessage(),'session is full')) exit(2); fwrite(STDERR,$e->getMessage()); exit(1); }
}
$checks=0;
function verify(bool $condition,string $message): void { global $checks; if(!$condition) throw new RuntimeException($message); echo "PASS $message\n"; $checks++; }
$schema=db()->query("SELECT sql FROM sqlite_master WHERE type='table' AND name IN ('customer_bookings','reference_sequences','daily_reference_sequences')")->fetchAll(PDO::FETCH_COLUMN);
$db=connection(':memory:'); foreach($schema as $sql) $db->exec($sql);
$date=date('Y-m-d',strtotime('+60 days')); while((int)date('N',strtotime($date))>5) $date=date('Y-m-d',strtotime($date.' +1 day'));
$token=bin2hex(random_bytes(32));
$first=reserve_customer($db,$details,$date,'AM',$token);
verify($first['reference']==='001-'.date('mdy',strtotime($date)).'-A','AM reference uses daily queue number and appointment date');
$again=reserve_customer($db,$details,$date,'AM',$token);
verify($again['id']===$first['id'] && customer_availability($db,$date)['AM']['remaining']===249,'Repeated submission reserves only one slot');
$pm=reserve_customer($db,$details,$date,'PM',bin2hex(random_bytes(32)));
verify($pm['reference']==='001-'.date('mdy',strtotime($date)).'-P','PM reference uses independent daily sequence');
for($i=1;$i<250;$i++) foreach(['AM','PM'] as $period) reserve_customer($db,$details,$date,$period,bin2hex(random_bytes(32)));
$availability=customer_availability($db,$date);
verify($availability['AM']['remaining']===0 && $availability['PM']['remaining']===0,'Exactly 250 AM and 250 PM reservations fill the day');
foreach(['AM','PM'] as $period) {
    $blocked=false; try { reserve_customer($db,$details,$date,$period,bin2hex(random_bytes(32))); } catch(RuntimeException $e) { $blocked=str_contains($e->getMessage(),'session is full'); }
    verify($blocked,"251st $period reservation is rejected");
}
$db->exec("UPDATE customer_bookings SET status='completed' WHERE id=".(int)$first['id']);
verify(customer_availability($db,$date)['AM']['remaining']===0,'Completed bookings still consume daily capacity');
$db->exec("UPDATE customer_bookings SET status='cancelled' WHERE id=".(int)$first['id']);
verify(customer_availability($db,$date)['AM']['remaining']===1,'Cancellation releases one slot');
$replacement=reserve_customer($db,$details,$date,'AM',bin2hex(random_bytes(32)));
verify($replacement['reference']==='251-'.date('mdy',strtotime($date)).'-A','Cancelled reference numbers are never reused');
$nextDate=date('Y-m-d',strtotime($date.' +1 day')); while((int)date('N',strtotime($nextDate))>5) $nextDate=date('Y-m-d',strtotime($nextDate.' +1 day'));
verify(customer_availability($db,$nextDate)['AM']['remaining']===250,'Each date has its own capacity');
$next=reserve_customer($db,$details,$nextDate,'AM',bin2hex(random_bytes(32)));
verify($next['reference']==='001-'.date('mdy',strtotime($nextDate)).'-A','A new appointment date restarts numbering at 001');
verify(!customer_date_valid('2026-02-30') && !customer_open($date,'XX'),'Invalid dates and sessions are rejected');
$invalid=false; try { customer_details($details+['unused'=>'x']); customer_details(array_replace($details,['company'=>''])); } catch(RuntimeException) { $invalid=true; }
verify($invalid,'Missing customer details are rejected');
verify(customer_initials('Juan Miguel Delacruz')==='JD','Reference initials exclude middle names');
verify(customer_initials('Juan Miguel Jose Delacruz')==='JD','Multiple middle names are excluded');
verify(customer_initials('Juan Delacruz')==='JD','Two-part names keep first and last initials');
verify(customer_initials('Juan')==='J','Single-part names use one initial');
// Separate database and independent PHP processes exercise competing writers.
$temporary=tempnam(sys_get_temp_dir(),'wellnest-capacity-'); $workers=[];
try {
    $shared=connection($temporary); foreach($schema as $sql) $shared->exec($sql);
    for($i=0;$i<249;$i++) reserve_customer($shared,$details,$date,'AM',bin2hex(random_bytes(32)));
    for($i=0;$i<8;$i++) {
        $process=proc_open([PHP_BINARY,'-d','extension=pdo_sqlite',__FILE__,'worker',$temporary,$date],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if(!is_resource($process)) throw new RuntimeException('Could not launch concurrency test worker.');
        fclose($pipes[0]); $workers[]=[$process,$pipes];
    }
    $succeeded=0; $rejected=0;
    foreach($workers as [$process,$pipes]) {
        $output=stream_get_contents($pipes[1]); $errors=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); $code=proc_close($process);
        if($code===0) $succeeded++; elseif($code===2) $rejected++; else throw new RuntimeException('Worker failed: '.$output.$errors);
    }
    verify($succeeded===1 && $rejected===7,'Eight concurrent customers compete safely for the last slot');
    verify((int)$shared->query('SELECT COUNT(*) FROM customer_bookings')->fetchColumn()===250,'Concurrent requests never exceed session capacity');
    $shared=null;
} finally { if(is_file($temporary)) unlink($temporary); }
echo "$checks customer capacity checks passed.\n";
