<?php if($route==='customer'): $draft=$_SESSION['customer_draft']??[]; ?>
<ol class="booking-steps" aria-label="Booking progress">
    <li class="current">1. Customer details</li><li>2.Appointment Date</li>
    <li>3. Reference number</li>
</ol>
<div class="booking-layout">
    <section class="card booking">
        <div class="section-label">STEP 1 OF 2</div>
        <h2>Tell us about yourself</h2>
        <form method="post" action="<?=e(url('customer'))?>"><?=csrf()?>
            <label for="full_name">Full name</label>
            <input id="full_name" name="full_name" autocomplete="off" minlength="2" maxlength="100" placeholder="Juan Delacruz" value="<?=e($_POST['full_name']??$draft['full_name']??'')?>" required>
            <label for="gender">Gender</label>
            <select id="gender" name="gender" required>
                <option value="">Select gender</option>
                <?php foreach(['Male','Female','Other','Prefer not to say'] as $gender): ?>
                <option value="<?=e($gender)?>" <?=($_POST['gender']??$draft['gender']??'')===$gender?'selected':''?>><?=e($gender)?></option>
            <?php endforeach ?>
        </select>
<label for="company">Company name</label><input id="company" name="company" autocomplete="organization" minlength="2" maxlength="150" value="<?=e($_POST['company']??$draft['company']??'')?>" required>
<label for="job">Job applying for</label><input id="job" name="job" minlength="2" maxlength="150" value="<?=e($_POST['job']??$draft['job']??'')?>" placeholder="e.g. Administrative Assistant" required>
<button class="button wide">Next: choose a schedule →</button>
</form>
</section>
<aside class="card booking-info">
    <span class="large-heart">♡</span>
    <h2>Your health appointment, in a few steps.</h2>
    <p>Complete your details, choose a date in the calendar, then select an AM or PM time.</p>
    <hr><h3>500 slots each day</h3><p>250 slots in the morning and 250 in the afternoon. Availability updates as customers book.</p>
    <!-- <h3>A reference to keep</h3> -->
    <!-- <p>Your reference uses your queue number, appointment date (MMDDYY), and time: <strong>001-010126-A</strong> for January 1, 2026 in the morning, or <strong>001-010126-P</strong> in the afternoon. Numbers start at 001 for each date and AM/PM time.</p> -->
<!-- <small>No account is required. Customer details are visible only to the care team and in this browser’s booking confirmation.</small> -->
</aside></div>
<?php elseif($route==='schedule'):
$draft=$_SESSION['customer_draft']??[];
$selectedDate=is_string($_POST['date']??null)?$_POST['date']:(is_string($_GET['date']??null)?$_GET['date']:'');
$available=customer_date_valid($selectedDate)?customer_availability($db,$selectedDate):null;
$selectedPeriod=is_string($_POST['period']??null)?$_POST['period']:'';
?>
<ol class="booking-steps" aria-label="Booking progress"><li>1. Customer details</li><li class="current">2. Appointment Date</li><li>3. Reference number</li></ol>
<div class="booking-layout"><section class="card booking"><div class="section-label">STEP 2 OF 2</div><h2>Choose appointment date</h2>
<form method="get" action="<?=e(url('schedule'))?>" id="availability-form"><label for="schedule-date">Appointment date</label><input id="schedule-date" type="date" name="date" min="<?=date('Y-m-d')?>" max="<?=date('Y-m-d',strtotime('+90 days'))?>" value="<?=e($selectedDate)?>" required><small>Monday–Friday only · Up to 90 days ahead · Singapore time (UTC+8)</small><button class="text-button" type="submit">Check available slots</button></form>
<form id="customer-schedule" method="post" action="<?=e(url('schedule'))?>" data-availability-url="<?=e(url('availability'))?>"><?=csrf()?><input type="hidden" name="request_token" value="<?=e($_SESSION['customer_request']??'')?>"><input type="hidden" id="booking-date" name="date" value="<?=e($selectedDate)?>">
<button type="button" id="calendar-toggle" class="calendar-toggle" aria-expanded="false" aria-controls="slot-calendar" hidden>Choose appointment date &#128197;</button>
<section id="slot-calendar" class="slot-calendar" aria-label="Monthly slot availability" hidden>
<div class="calendar-toolbar"><button type="button" id="calendar-prev" aria-label="Previous month">&#8592;</button><strong id="calendar-month"></strong><button type="button" id="calendar-next" aria-label="Next month">&#8594;</button></div>
<p class="muted">Remaining slots per day. Select a date, then choose AM or PM below.</p>
<div class="calendar-weekdays" aria-hidden="true"><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span></div>
<div id="calendar-days" class="calendar-days"></div><p id="calendar-status" class="muted" role="status" aria-live="polite"></p>
<noscript><p>Use the appointment date field above to check available slots.</p></noscript>
</section>
<fieldset class="session-fieldset"><legend>Choose your time</legend><div class="session-grid">
<?php foreach(['AM'=>['Morning','8:00 AM – 12:00 PM'],'PM'=>['Afternoon','1:00 PM – 5:00 PM']] as $period=>$description): $open=$available && $available[$period]['open'] && $available[$period]['remaining']>0; ?>
<label class="session-option" for="period-<?=$period?>"><input type="radio" name="period" id="period-<?=$period?>" value="<?=$period?>" <?=$selectedPeriod===$period?'checked':''?> <?=$open?'':'disabled'?> required><span><strong><?=$period?> · <?=$description[0]?></strong><small><?=$description[1]?></small><span class="slot-number" id="remaining-<?=$period?>"><?=$available?(int)$available[$period]['remaining']:'—'?></span><span class="slot-caption"> / 250 slots available</span><small id="session-state-<?=$period?>"><?=$available?(!$available[$period]['open']?'Time closed':($available[$period]['remaining']===0?'Time fully booked':'Available')):'Select a date first'?></small></span></label>
<?php endforeach ?></div></fieldset>
<p id="availability-message" class="muted" role="status" aria-live="polite"><?=$available?'Availability checked. Updates every 5 seconds while this page is open.':'Choose a date in the calendar to view live availability.'?></p>
<p class="muted">A slot is reserved only after you confirm. Keep your reference number for your visit.</p>
<button class="button wide" id="confirm-booking" <?=($available && (($available['AM']['open'] && $available['AM']['remaining']>0)||($available['PM']['open'] && $available['PM']['remaining']>0)))?'':'disabled'?>>Confirm appointment →</button><a class="back-link" href="<?=e(url('customer'))?>">← Edit customer details</a></form></section>
<aside class="card booking-info"><div class="section-label">YOUR DETAILS</div><h2><?=e($draft['full_name']??'')?></h2><dl class="booking-details"><dt>Gender</dt><dd><?=e($draft['gender']??'')?></dd><dt>Company</dt><dd><?=e($draft['company']??'')?></dd><dt>Job applying for</dt><dd><?=e($draft['job']??'')?></dd></dl><hr><h3>Daily time slot limits</h3><p>AM: 250 customers<br>PM: 250 customers<br>Total: 500 customers per day</p>
<!-- <small>Counts show remaining slots, not a timer. Cancelled bookings release a slot. Completed bookings still count toward the daily limit.</small> -->
</aside></div>
<script src="<?=e(url('assets/customer.js'))?>" defer></script>
<?php else:
$q=$db->prepare('SELECT * FROM customer_bookings WHERE id=?'); $q->execute([$_SESSION['customer_receipt']??0]); $receipt=$q->fetch();
if($receipt): ?>
<section class="card booking receipt"><span class="pill">BOOKING <?=e(strtoupper($receipt['status']))?></span><h2>Keep your reference number</h2><div class="reference-number"><?=e($receipt['reference'])?></div><p class="print-hide">Save or print this confirmation and present it when you arrive.</p><dl class="booking-details"><dt>Full name</dt><dd><span class="receipt-screen-name"><?=e($receipt['full_name'])?></span><span class="receipt-print-name"><?=e(customer_print_name($receipt['full_name']))?></span></dd><dt>Gender</dt><dd><?=e($receipt['gender'])?></dd><dt>Company</dt><dd><?=e($receipt['company'])?></dd><dt>Job applying for</dt><dd><?=e($receipt['job'])?></dd><dt>Appointment date</dt><dd><?=e(date('l, F j, Y',strtotime($receipt['date'])))?></dd><dt>Time</dt><dd><?=$receipt['period']==='AM'?'AM · 8:00 – 12:00':'PM - 1:00 – 5:00'?></dd></dl><p class="print-only">Save or print this confirmation and present it when you arrive.</p><?php if($receipt['period']==='AM'): ?><div class="arrival-reminder"><strong>Important reminder — Morning appointments</strong><p>Please arrive within your scheduled morning time, from 8:00 AM to 12:00 PM on your appointment date. Arrivals after 12:00 PM will invalidate your appointment. A new booking will be required, subject to availability.</p></div><?php endif ?><p class="muted print-hide">Unable to attend? <a href="<?=e(url('cancel-appointment'))?>">Cancel your appointment</a> using your reference number and full name.</p><button class="button" id="print-receipt" type="button">Print confirmation</button> <a class="back-link" href="<?=e(url('customer'))?>">Book another appointment →</a></section><script src="<?=e(url('assets/customer.js'))?>" defer></script>
<?php else: ?><div class="alert error">This confirmation is unavailable. <a href="<?=e(url('customer'))?>">Start a booking</a>.</div><?php endif; endif ?>
