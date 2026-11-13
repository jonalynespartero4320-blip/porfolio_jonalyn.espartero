(() => {
  'use strict';
  const config = document.getElementById('booking-idle');
  if (!config) return;
  const timeout = 180000;
  let deadline = Date.now() + Number(config.dataset.remaining) * 1000;
  let lastSent = 0;
  let pendingActivity = false;
  let sending = false;
  let expired = false;
  const data = () => new URLSearchParams({csrf: config.dataset.csrf, booking_guard: config.dataset.guard});
  function hidePrivateData() {
    document.querySelectorAll('main input:not([type="hidden"]), main textarea').forEach(input => { input.value = ''; });
    document.querySelectorAll('main select').forEach(select => { select.selectedIndex = 0; });
    document.querySelectorAll('main form button, main form input, main form select, main form textarea').forEach(input => { input.disabled = true; });
    document.querySelectorAll('main .booking-layout, main .receipt, main .booking-steps').forEach(element => { element.hidden = true; });
    config.textContent = 'Your form has been cleared after 3 minutes without activity.';
  }
  async function reset() {
    if (expired) return;
    expired = true;
    hidePrivateData();
    const controller = new AbortController();
    const cancel = setTimeout(() => controller.abort(), 5000);
    try {
      await fetch(config.dataset.resetUrl, {method: 'POST', body: data(), cache: 'no-store', keepalive: true, signal: controller.signal});
    } catch (_) { /* The server independently rejects expired bookings on the next request. */ }
    finally { clearTimeout(cancel); }
    window.location.replace(config.dataset.startUrl);
  }
  async function sendActivity() {
    if (!pendingActivity || sending || expired || Date.now() - lastSent < 10000) return;
    sending = true;
    pendingActivity = false;
    lastSent = Date.now();
    try {
      const response = await fetch(config.dataset.activityUrl, {method: 'POST', body: data(), cache: 'no-store'});
      if (response.status === 409 || response.status === 419) {
        expired = true; hidePrivateData(); window.location.replace(config.dataset.startUrl);
      } else if (!response.ok) pendingActivity = true;
    } catch (_) { pendingActivity = true; }
    finally { sending = false; }
  }
  function activity(event) {
    if (!event.isTrusted || expired) return;
    if (Date.now() >= deadline) { reset(); return; }
    deadline = Date.now() + timeout;
    pendingActivity = true;
    sendActivity();
  }
  for (const name of ['pointerdown', 'pointermove', 'keydown', 'input', 'change', 'wheel', 'touchstart']) {
    document.addEventListener(name, activity, {passive: true, capture: true});
  }
  // Check expiry before any other submit listener can send an old form.
  document.addEventListener('submit', event => {
    if (expired || Date.now() >= deadline) { event.preventDefault(); event.stopImmediatePropagation(); reset(); }
  }, true);
  function tick() {
    if (expired) return;
    const remaining = Math.max(0, Math.ceil((deadline - Date.now()) / 1000));
    const display = document.getElementById('idle-countdown');
    if (display) display.textContent = `Clears in ${Math.floor(remaining / 60)}:${String(remaining % 60).padStart(2, '0')}.`;
    if (remaining === 0) reset(); else sendActivity();
  }
  setInterval(tick, 1000);
  window.addEventListener('pageshow', tick);
  document.addEventListener('visibilitychange', tick);
  tick();
})();
