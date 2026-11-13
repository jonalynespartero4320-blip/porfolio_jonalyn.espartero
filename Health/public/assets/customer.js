(() => {
  'use strict';
  if (document.getElementById('customer-details')) {
    window.addEventListener('pageshow', event => {
      // Reload restored pages so old textbox values cannot outlive the server draft.
      if (event.persisted) window.location.reload();
    });
  }
  document.getElementById('print-receipt')?.addEventListener('click', () => window.print());
  const form = document.getElementById('customer-schedule');
  if (!form) return;
  const date = document.getElementById('schedule-date');
  const hiddenDate = document.getElementById('booking-date');
  const message = document.getElementById('availability-message');
  const confirm = document.getElementById('confirm-booking');
  let activeRequest = null;
  let submitting = false;
  const calendarDays = document.getElementById('calendar-days');
  const calendarStatus = document.getElementById('calendar-status');
  const previousMonth = document.getElementById('calendar-prev');
  const nextMonth = document.getElementById('calendar-next');
  let calendarMonth = (date.value || date.min).slice(0, 7);
  let calendarRequest = null;
  const calendar = document.getElementById('slot-calendar');
  const calendarToggle = document.getElementById('calendar-toggle');
  document.getElementById('availability-form').hidden = true;
  calendarToggle.hidden = false;
  function updateDateLabel() {
    calendarToggle.textContent = date.value
      ? new Date(`${date.value}T12:00:00`).toLocaleDateString('en', {weekday: 'short', month: 'long', day: 'numeric', year: 'numeric'}) + ' — Change date'
      : 'Choose appointment date 📅';
  }
  updateDateLabel();
  calendarToggle.addEventListener('click', () => {
    calendar.hidden = !calendar.hidden;
    calendarToggle.setAttribute('aria-expanded', String(!calendar.hidden));
    if (!calendar.hidden) refreshCalendar();
  });
  calendar.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
      calendar.hidden = true;
      calendarToggle.setAttribute('aria-expanded', 'false');
      calendarToggle.focus();
    }
  });
  function shiftMonth(month, offset) {
    const [year, number] = month.split('-').map(Number);
    const shifted = new Date(year, number - 1 + offset, 1);
    return `${shifted.getFullYear()}-${String(shifted.getMonth() + 1).padStart(2, '0')}`;
  }
  async function refreshCalendar() {
    if (submitting) return;
    calendarRequest?.abort();
    const controller = new AbortController();
    calendarRequest = controller;
    const requestedMonth = calendarMonth;
    document.getElementById('calendar-month').textContent = new Date(`${calendarMonth}-01T12:00:00`).toLocaleDateString('en', {month: 'long', year: 'numeric'});
    previousMonth.disabled = calendarMonth <= date.min.slice(0, 7);
    nextMonth.disabled = calendarMonth >= date.max.slice(0, 7);
    calendarStatus.textContent = 'Updating monthly availability...';
    try {
      const response = await fetch(`${form.dataset.availabilityUrl}?month=${encodeURIComponent(requestedMonth)}`, {cache: 'no-store', signal: controller.signal, headers: {Accept: 'application/json'}});
      const result = await response.json();
      if (controller.signal.aborted || calendarMonth !== requestedMonth) return;
      if (!response.ok) throw new Error(result.error || 'Unable to load calendar.');
      const cells = document.createDocumentFragment();
      const firstWeekday = result.days.find(day => ![0, 6].includes(new Date(`${day.date}T12:00:00`).getDay()));
      const offset = firstWeekday ? new Date(`${firstWeekday.date}T12:00:00`).getDay() - 1 : 0;
      for (let i = 0; i < offset; i++) cells.append(document.createElement('span'));
      for (const day of result.days) {
        if ([0, 6].includes(new Date(`${day.date}T12:00:00`).getDay())) continue;
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'calendar-day';
        button.disabled = !day.valid || !['AM', 'PM'].some(period => day[period].open && day[period].remaining > 0);
        button.setAttribute('aria-pressed', String(day.date === date.value));
        const number = document.createElement('strong');
        number.textContent = String(Number(day.date.slice(-2)));
        button.append(number);
        const labels = [];
        if (day.valid) {
          for (const period of ['AM', 'PM']) {
            const count = document.createElement('span');
            count.textContent = `${period}: ${day[period].open ? day[period].remaining : 'Closed'}`;
            button.append(count);
            labels.push(count.textContent);
          }
        } else {
          const closed = document.createElement('span');
          closed.textContent = [0, 6].includes(new Date(`${day.date}T12:00:00`).getDay()) ? 'Weekend' : 'Unavailable';
          button.append(closed);
          labels.push(closed.textContent);
        }
        button.setAttribute('aria-label', `${day.date}, ${labels.join(', ')}`);
        button.addEventListener('click', () => {
          date.value = day.date;
          updateDateLabel();
          calendar.hidden = true;
          calendarToggle.setAttribute('aria-expanded', 'false');
          calendarToggle.focus();
          calendarDays.querySelectorAll('button').forEach(cell => cell.setAttribute('aria-pressed', String(cell === button)));
          refresh(true);
        });
        cells.append(button);
      }
      calendarDays.replaceChildren(cells);
      calendarStatus.textContent = 'Live availability refreshes every 5 seconds. Slots are reserved after confirmation.';
    } catch (error) {
      if (error.name === 'AbortError') return;
      calendarDays.replaceChildren();
      calendarStatus.textContent = `${error.message} Use the date field above or wait for the next refresh.`;
    }
  }
  previousMonth.addEventListener('click', () => { calendarMonth = shiftMonth(calendarMonth, -1); calendarDays.replaceChildren(); refreshCalendar(); });
  nextMonth.addEventListener('click', () => { calendarMonth = shiftMonth(calendarMonth, 1); calendarDays.replaceChildren(); refreshCalendar(); });
  function disableSessions() {
    confirm.disabled = true;
    for (const period of ['AM', 'PM']) {
      const input = document.getElementById(`period-${period}`);
      input.disabled = true;
      input.checked = false;
      document.getElementById(`remaining-${period}`).textContent = '—';
      document.getElementById(`session-state-${period}`).textContent = 'Availability unverified';
    }
  }
  async function refresh(changed = false) {
    if (submitting) return;
    activeRequest?.abort();
    const controller = new AbortController();
    activeRequest = controller;
    const requestedDate = date.value;
    const weekday = requestedDate ? new Date(`${requestedDate}T12:00:00`).getDay() : null;
    const weekend = weekday === 0 || weekday === 6;
    date.setCustomValidity(weekend ? 'Appointments are available Monday to Friday only.' : '');
    if (weekend) {
      hiddenDate.value = '';
      disableSessions();
      message.textContent = 'Weekends are unavailable. Please choose Monday to Friday.';
      return;
    }
    hiddenDate.value = requestedDate;
    if (changed) disableSessions();
    if (!requestedDate) { disableSessions(); message.textContent = 'Choose a date first.'; return; }
    if (changed) message.textContent = 'Checking available slots…';
    try {
      const response = await fetch(`${form.dataset.availabilityUrl}?date=${encodeURIComponent(requestedDate)}`, {cache: 'no-store', signal: controller.signal, headers: {Accept: 'application/json'}});
      const result = await response.json();
      if (date.value !== requestedDate || controller.signal.aborted) return;
      if (!response.ok) throw new Error(result.error || 'Unable to check availability.');
      let hasAvailability = false;
      for (const period of ['AM', 'PM']) {
        const available = result[period];
        const input = document.getElementById(`period-${period}`);
        const canBook = available.open && available.remaining > 0;
        input.disabled = !canBook;
        if (!canBook) input.checked = false;
        hasAvailability ||= canBook;
        document.getElementById(`remaining-${period}`).textContent = String(available.remaining);
        document.getElementById(`session-state-${period}`).textContent = !available.open ? 'Time closed' : available.remaining === 0 ? 'Time fully booked' : 'Available';
      }
      confirm.disabled = !hasAvailability;
      message.textContent = `Live slots · Updated ${new Date().toLocaleTimeString()} · Refreshes every 5 seconds.`;
    } catch (error) {
      if (error.name === 'AbortError') return;
      disableSessions();
      message.textContent = `${error.message} Availability will retry automatically.`;
    }
  }
  date.addEventListener('change', () => {
    refresh(true);
    if (date.value && date.value >= date.min && date.value <= date.max) calendarMonth = date.value.slice(0, 7);
    refreshCalendar();
  });
  document.getElementById('availability-form').addEventListener('submit', event => { event.preventDefault(); refresh(true); });
  form.addEventListener('submit', event => {
    if (!date.value || date.value !== hiddenDate.value || !form.querySelector('input[name="period"]:checked:not(:disabled)')) {
      event.preventDefault(); message.textContent = 'Choose a date and an available time first.'; return;
    }
    submitting = true;
    calendarRequest?.abort();
    activeRequest?.abort();
    confirm.disabled = true;
    confirm.textContent = 'Reserving your slot…';
  });
  window.addEventListener('pageshow', () => { submitting = false; confirm.textContent = 'Confirm appointment →'; refresh(); });
  setInterval(() => { if (!document.hidden) refresh(); }, 5000);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
  refreshCalendar();
  setInterval(() => { if (!document.hidden) refreshCalendar(); }, 5000);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) refreshCalendar(); });
})();
