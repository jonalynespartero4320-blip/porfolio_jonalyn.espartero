<?php
declare(strict_types=1);
const BOOKING_IDLE_SECONDS=180;
function clear_booking(): void {
    foreach(['customer_draft','customer_request','customer_receipt','booking_last_activity','booking_guard'] as $key) unset($_SESSION[$key]);
}
function expire_booking(?int $now=null): bool {
    $now??=time();
    if(isset($_SESSION['booking_last_activity']) && $now-(int)$_SESSION['booking_last_activity']>=BOOKING_IDLE_SECONDS) {
        clear_booking(); return true;
    }
    return false;
}
function start_booking_activity(): void {
    $_SESSION['booking_guard']??=bin2hex(random_bytes(32));
    $_SESSION['booking_last_activity']=time();
}
function booking_guard_valid(mixed $guard): bool {
    return is_string($guard) && isset($_SESSION['booking_guard']) && hash_equals($_SESSION['booking_guard'],$guard);
}
function booking_guard_field(): string {
    return '<input type="hidden" name="booking_guard" value="'.e($_SESSION['booking_guard']??'').'">';
}
