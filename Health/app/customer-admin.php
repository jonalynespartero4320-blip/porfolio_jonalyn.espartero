<?php
$customerSearch=is_string($_GET['q']??null)?trim($_GET['q']):'';
$searchError='';
$filterDate=is_string($_GET['date']??null)?$_GET['date']:date('Y-m-d');
$filterPeriod=is_string($_GET['period']??null)?$_GET['period']:'';
$filterScope=is_string($_GET['scope']??null)?$_GET['scope']:'date';
$parsedDate=DateTimeImmutable::createFromFormat('!Y-m-d',$filterDate);
if(!$parsedDate || $parsedDate->format('Y-m-d')!==$filterDate || !in_array($filterPeriod,['','AM','PM'],true) || !in_array($filterScope,['date','upcoming'],true)) $searchError='Choose a valid date, time, and date range.';
if(isset($_GET['q']) && (!is_string($_GET['q']) || strlen($customerSearch)>150 || !preg_match('//u',$customerSearch))) $searchError='Enter a reference number or full name of up to 150 characters.';
$referenceOrder=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'
    ? "CASE WHEN reference REGEXP '^[0-9]+-' THEN CAST(SUBSTRING_INDEX(reference,'-',1) AS UNSIGNED) ELSE CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(reference,'-',-2),'-',1) AS UNSIGNED) END"
    : "CASE WHEN reference GLOB '[0-9]*-*' THEN CAST(reference AS INTEGER) ELSE CAST(substr(reference,instr(reference,'-')+1) AS INTEGER) END";
$customers=[];
if(!$searchError) {
    $sql='SELECT * FROM customer_bookings WHERE date'.($filterScope==='upcoming'?'>=':'=').'?'; $parameters=[$filterDate];
    if($filterPeriod!=='') { $sql.=' AND period=?'; $parameters[]=$filterPeriod; }
    if($customerSearch!=='') {
        $pattern='%'.strtr($customerSearch,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';
        $sql.=" AND (LOWER(reference) LIKE LOWER(?) ESCAPE '!' OR LOWER(full_name) LIKE LOWER(?) ESCAPE '!')";
        array_push($parameters,$pattern,$pattern);
    }
    $sql.=' ORDER BY date ASC,'.$referenceOrder.' ASC,period ASC,id ASC';
    $query=$db->prepare($sql); $query->execute($parameters); $customers=$query->fetchAll();
}
?>
<section class="card appointment-card customer-admin"><div class="section-heading"><div><h2>Customer health schedules</h2><p>250 AM + 250 PM slots per day. Cancelling a booking releases its slot.</p></div></div>
<form class="appointment-search" method="get" action="<?=e(url('admin'))?>">
<div><label for="customer-search">Reference or full name</label><input id="customer-search" name="q" type="search" maxlength="150" value="<?=e($customerSearch)?>" placeholder="Search appointments"></div>
<div><label for="filter-date">Appointment date</label><input id="filter-date" name="date" type="date" value="<?=e($filterDate)?>" required></div>
<div><label for="filter-scope">Show appointments</label><select id="filter-scope" name="scope"><option value="date" <?=$filterScope==='date'?'selected':''?>>On selected date</option><option value="upcoming" <?=$filterScope==='upcoming'?'selected':''?>>From selected date onward</option></select></div>
<div><label for="filter-period">Time</label><select id="filter-period" name="period"><option value="">AM and PM</option><option value="AM" <?=$filterPeriod==='AM'?'selected':''?>>AM</option><option value="PM" <?=$filterPeriod==='PM'?'selected':''?>>PM</option></select></div>
<button class="button" type="submit">Search</button><a href="<?=e(url('admin'))?>">Today / reset</a></form>
<p class="search-summary"><?=e($filterScope==='upcoming'?'Appointments from ':'Appointments on ')?><?=e($filterDate)?><?=e($filterPeriod!==''?' - '.$filterPeriod:' - AM and PM')?></p>
<?php if($searchError): ?><div class="search-summary error" role="alert"><?=e($searchError)?></div><?php else: ?><p class="search-summary" role="status"><?=count($customers)?> appointment<?=count($customers)===1?'':'s'?><?=$customerSearch!==''?' found':''?></p><?php endif ?>
<?php if(!$customers): ?><div class="empty"><p><?=$customerSearch!==''?'No matching appointments. Try another reference number or name.':'No customer bookings to display.'?></p></div><?php else: ?><div class="table-wrap"><table><thead><tr><th>Reference / customer</th><th>Company / job</th><th>Schedule</th><th>Booking status</th><th>Attendance status</th><th>Actions</th></tr></thead><tbody>
<?php foreach($customers as $customer): ?><tr><td><strong><?=e($customer['reference'])?></strong><small><?=e($customer['full_name'])?> · <?=e($customer['gender'])?></small></td><td><strong><?=e($customer['company'])?></strong><small><?=e($customer['job'])?></small></td><td><strong><?=e(date('M j, Y',strtotime($customer['date'])))?></strong><small><?=e($customer['period'])?> time</small></td><td><span class="status <?=e($customer['status'])?>"><?=e(ucfirst($customer['status']))?></span></td><td>
<?php $arrivalRecord=$customer; $arrivalType='customer'; require __DIR__.'/arrival-control.php'; ?>
</td><td>
<?php if(!empty($customer['cancellation_reason'])): ?><details><summary>Cancellation reason</summary><p class="reason"><?=e($customer['cancellation_reason'])?></p><small><?=e($customer['cancelled_at'])?> SGT · <?=e($customer['cancellation_source'])?></small></details><?php endif ?>
<?php if($customer['status']==='confirmed'): ?><form class="inline-form" method="post" action="<?=e(url('customer-status'))?>"><?=csrf()?><input type="hidden" name="id" value="<?=e($customer['id'])?>"><select name="status" aria-label="Customer booking status"><option value="cancelled">Cancel</option><?php if(strtotime($customer['date'].' '.($customer['period']==='AM'?'12:00':'17:00'))<=time()): ?><option value="completed">Complete</option><?php endif ?></select><button class="text-button">Update</button></form><?php endif ?></td></tr><?php endforeach ?></tbody></table></div><?php endif ?></section>
