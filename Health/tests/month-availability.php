<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/customer.php';
date_default_timezone_set('Asia/Singapore');
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec('CREATE TABLE customer_bookings(date TEXT,period TEXT,status TEXT)');
$day=new DateTimeImmutable('tomorrow');
while((int)$day->format('N')>5) $day=$day->modify('+1 day');
$date=$day->format('Y-m-d');
$insert=$db->prepare('INSERT INTO customer_bookings VALUES(?,?,?)');
$insert->execute([$date,'AM','confirmed']);
$insert->execute([$date,'AM','cancelled']);
for($i=0;$i<150;$i++) $insert->execute([$date,'PM','confirmed']);
$result=customer_month_availability($db,$day->format('Y-m'));
if(count($result['days'])!==(int)$day->format('t')) throw new RuntimeException('Missing calendar days.');
foreach($result['days'] as $entry) {
    if($entry['date']===$date && ($entry['AM']['remaining']!==249 || $entry['PM']['remaining']!==100 || !$entry['AM']['open'])) throw new RuntimeException('Incorrect remaining slots.');
    if((int)(new DateTimeImmutable($entry['date']))->format('N')>5 && ($entry['valid'] || $entry['AM']['open'] || $entry['PM']['open'])) throw new RuntimeException('Weekend is bookable.');
    if(customer_date_valid($entry['date'])) {
        $single=customer_availability($db,$entry['date']);
        if($single['AM']!==$entry['AM'] || $single['PM']!==$entry['PM']) throw new RuntimeException('Calendar and daily counts disagree.');
    }
}
foreach(['2026-13','bad','2000-01',date('Y-m',strtotime('+1 year'))] as $invalid) {
    try { customer_month_availability($db,$invalid); } catch(RuntimeException $e) { continue; }
    throw new RuntimeException('Invalid month accepted.');
}
echo "Monthly availability passed: counts, cancellations, weekends, daily consistency, and invalid months.\n";
