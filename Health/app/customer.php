<?php
declare(strict_types=1);
function customer_details(array $input): array {
    $details=[];
    foreach (['full_name'=>100,'gender'=>30,'company'=>150,'job'=>150] as $key=>$limit) {
        if (!is_string($input[$key]??null)) throw new RuntimeException('Please complete every customer detail.');
        $details[$key]=trim($input[$key]);
        if (strlen($details[$key])<2 || strlen($details[$key])>$limit) throw new RuntimeException('Please complete all fields within their character limits.');
    }
    if (!preg_match('/^\p{L}[\p{L}\p{M}\s.\x{2019}\x{0027}-]*\p{L}\p{M}*$/u',$details['full_name'])) throw new RuntimeException('Enter your full name using letters, spaces, apostrophes, or hyphens.');
    if (!in_array($details['gender'],['Male','Female','Other','Prefer not to say'],true)) throw new RuntimeException('Choose a listed gender option.');
    return $details;
}
function customer_date_valid(string $date): bool {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date)) return false;
    $d=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
    return $d && $d->format('Y-m-d')===$date && $date>=date('Y-m-d') && $date<=date('Y-m-d',strtotime('+90 days')) && (int)$d->format('N')<=5;
}
function customer_open(string $date,string $period): bool {
    return customer_date_valid($date) && in_array($period,['AM','PM'],true) && strtotime($date.' '.($period==='AM'?'12:00':'17:00'))>time();
}
function customer_availability(PDO $db,string $date): array {
    if (!customer_date_valid($date)) throw new RuntimeException('Choose a Monday–Friday date within the next 90 days. Weekends are unavailable.');
    $q=$db->prepare("SELECT period,COUNT(*) AS booked FROM customer_bookings WHERE date=? AND status!='cancelled' GROUP BY period"); $q->execute([$date]);
    $counts=array_column($q->fetchAll(),'booked','period');
    $result=['date'=>$date,'capacity'=>250,'timezone'=>'Asia/Singapore'];
    foreach(['AM','PM'] as $period) $result[$period]=['remaining'=>max(0,250-(int)($counts[$period]??0)),'open'=>customer_open($date,$period)];
    return $result;
}
function customer_month_availability(PDO $db,string $month): array {
    if (!preg_match('/^\d{4}-\d{2}$/D',$month)) throw new RuntimeException('Choose a valid month.');
    $start=DateTimeImmutable::createFromFormat('!Y-m-d',$month.'-01');
    if (!$start || $start->format('Y-m')!==$month || $month<date('Y-m') || $month>date('Y-m',strtotime('+90 days'))) throw new RuntimeException('Choose a month within the next 90 days.');
    $end=$start->modify('last day of this month')->format('Y-m-d');
    $q=$db->prepare("SELECT date,period,COUNT(*) AS booked FROM customer_bookings WHERE date>=? AND date<=? AND status!='cancelled' GROUP BY date,period");
    $q->execute([$start->format('Y-m-d'),$end]);
    $counts=[];
    foreach($q->fetchAll() as $row) $counts[$row['date']][$row['period']]=(int)$row['booked'];
    $days=[];
    for($day=$start;$day->format('Y-m-d')<=$end;$day=$day->modify('+1 day')) {
        $date=$day->format('Y-m-d');
        $entry=['date'=>$date,'valid'=>customer_date_valid($date)];
        foreach(['AM','PM'] as $period) $entry[$period]=['remaining'=>max(0,250-($counts[$date][$period]??0)),'open'=>customer_open($date,$period)];
        $days[]=$entry;
    }
    return ['month'=>$month,'days'=>$days];
}
function customer_print_name(string $name): string {
    return preg_replace_callback('/\S+/u',static function(array $match): string {
        preg_match_all('/\X/u',$match[0],$letters);
        $characters=$letters[0];
        $length=count($characters);
        if($length<=1) return $match[0];
        if($length===2) return $characters[0].'*';
        return $characters[0].str_repeat('*',$length-2).$characters[$length-1];
    },$name) ?? '';
}
function customer_initials(string $name): string {
    $letters=[];
    foreach(preg_split('/\s+/u',trim($name)) as $word) { preg_match('/\p{L}\p{M}*/u',$word,$match); if(isset($match[0])) $letters[]=$match[0]; }
    $initials=$letters ? $letters[0].(count($letters)>1?$letters[count($letters)-1]:'') : '';
    return function_exists('mb_strtoupper') ? mb_strtoupper($initials,'UTF-8') : strtoupper($initials);
}
function reserve_customer(PDO $db,array $details,string $date,string $period,string $token): array {
    $details=customer_details($details);
    if (!preg_match('/^[a-f0-9]{64}$/',$token)) throw new RuntimeException('Restart your booking to continue.');
    if(!customer_date_valid($date) || !in_array($period,['AM','PM'],true)) throw new RuntimeException('Choose a Monday–Friday appointment date within 90 days and an AM or PM time.');
    $mysql=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
    if ($mysql) $db->beginTransaction(); else $db->exec('BEGIN IMMEDIATE');
    try {
        if ($mysql) {
            if (!in_array($period,['AM','PM'],true)) throw new RuntimeException('Choose AM or PM.');
            // Lock a seeded session counter before any snapshot reads. This serializes
            // capacity checks and reference allocation for all writers in that session.
            $db->prepare('INSERT INTO daily_reference_sequences(date,period,last_number) VALUES(?,?,0) ON DUPLICATE KEY UPDATE last_number=last_number')->execute([$date,$period]);
            $lock=$db->prepare('SELECT last_number FROM daily_reference_sequences WHERE date=? AND period=? FOR UPDATE'); $lock->execute([$date,$period]); $lastNumber=$lock->fetchColumn(); $lock->closeCursor();
            if ($lastNumber===false) throw new RuntimeException('The booking counters are missing. Import the database schema.');
        }
        $q=$db->prepare('SELECT * FROM customer_bookings WHERE request_token=?'); $q->execute([$token]); $existing=$q->fetch(); $q->closeCursor();
        if ($existing) { if($mysql) $db->commit(); else $db->exec('COMMIT'); return $existing; }
        if (!customer_open($date,$period)) throw new RuntimeException('Choose an available future AM or PM time on a Monday–Friday date within 90 days.');
        $availability=customer_availability($db,$date);
        if ($availability[$period]['remaining']<=0) throw new RuntimeException('The '.$period.' session is full. Choose the other session or another date.');
        if ($mysql) $db->prepare('UPDATE daily_reference_sequences SET last_number=last_number+1 WHERE date=? AND period=?')->execute([$date,$period]);
        else $db->prepare('INSERT INTO daily_reference_sequences(date,period,last_number) VALUES(?,?,1) ON CONFLICT(date,period) DO UPDATE SET last_number=last_number+1')->execute([$date,$period]);
        $q=$db->prepare('SELECT last_number FROM daily_reference_sequences WHERE date=? AND period=?'); $q->execute([$date,$period]); $number=(int)$q->fetchColumn(); $q->closeCursor();
        $reference=str_pad((string)$number,3,'0',STR_PAD_LEFT).'-'.date('mdy',strtotime($date)).'-'.($period==='AM'?'A':'P');
        $db->prepare('INSERT INTO customer_bookings(full_name,gender,company,job,date,period,reference,request_token) VALUES(?,?,?,?,?,?,?,?)')->execute([$details['full_name'],$details['gender'],$details['company'],$details['job'],$date,$period,$reference,$token]);
        $id=(int)$db->lastInsertId();
        if($mysql) $db->commit(); else $db->exec('COMMIT');
        return ['id'=>$id,'reference'=>$reference];
    } catch(Throwable $e) { if($mysql) { if($db->inTransaction()) $db->rollBack(); } else $db->exec('ROLLBACK'); throw $e; }
}
function cancel_customer(PDO $db,string $reference,string $fullName,string $reason): bool {
    $reference=function_exists('mb_strtoupper')?mb_strtoupper(trim($reference),'UTF-8'):strtoupper(trim($reference)); $fullName=trim($fullName); $reason=trim($reason);
    if (!preg_match('/^(?:[0-9]{3,20}-(?:[0-9]{6}|[0-9]{8})|\p{L}[\p{L}\p{M}]*-[0-9]{3,20})-[AP]$/uD',$reference) || strlen($reference)>128 || strlen($fullName)<2 || strlen($fullName)>100 || strlen($reason)<10 || strlen($reason)>500) throw new RuntimeException('Enter your reference number, full name as booked, and a reason of 10–500 characters.');
    $q=$db->prepare('SELECT id,full_name FROM customer_bookings WHERE reference=?'); $q->execute([$reference]); $booking=$q->fetch(); $q->closeCursor();
    $normalize=static function(string $name): string { $name=preg_replace('/\s+/u',' ',trim($name)); return function_exists('mb_strtolower')?mb_strtolower($name,'UTF-8'):strtolower($name); };
    if(!$booking || !hash_equals($normalize($booking['full_name']),$normalize($fullName))) return false;
    // Conditional update is atomic: repeated requests and staff arrival cannot
    // cancel a booking twice, or cancel one already checked in/completed.
    $q=$db->prepare("UPDATE customer_bookings SET status='cancelled', cancellation_reason=?,cancelled_at=?,cancellation_source='customer'
        WHERE id=? AND status='confirmed' AND arrived_at IS NULL
        AND (date>? OR (date=? AND ((period='AM' AND ?<'12:00') OR (period='PM' AND ?<'17:00'))))");
    $q->execute([$reason,date('Y-m-d H:i:s'),$booking['id'],date('Y-m-d'),date('Y-m-d'),date('H:i'),date('H:i')]);
    return $q->rowCount()===1;
}
