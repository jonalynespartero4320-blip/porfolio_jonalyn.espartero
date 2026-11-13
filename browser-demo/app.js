(() => {
  'use strict';
  const configurations = {
    queue: {title:'ID & Queuing System', description:'Register a fictional visitor, issue a queue number, and move visits through the service desk.', form:'Register a visitor', fields:[['name','Visitor name'],['detail','Purpose of visit']], columns:['Visitor','Purpose'], statuses:['Waiting','Serving','Completed'], seeds:[['Alex Sample','ID application','Waiting'],['Jamie Demo','Record update','Serving']]},
    inventory: {title:'Inventory Management System', description:'Create equipment records, track assignments, and update the condition of your sample inventory.', form:'Add equipment', fields:[['name','Equipment name'],['detail','Assigned department']], columns:['Equipment','Department'], statuses:['Available','Assigned','Maintenance'], seeds:[['Demo Laptop','Administration','Available'],['Sample Printer','Service desk','Assigned']]},
    appointments: {title:'Online Appointment System', description:'Book a sample appointment and manage its status in the same browser workspace.', form:'Book an appointment', fields:[['name','Customer name'],['detail','Service'],['date','Appointment date','date'],['slot','Time slot','slot']], columns:['Customer','Service','Date / time'], statuses:['Booked','Arrived','Completed','Cancelled'], seeds:[]},
    verification: {title:'Verification Portal', description:'Look up fictional IDs and try matching, missing, and barangay mismatch results.', form:'Verify a sample ID', fields:[['kind','ID type','kind'],['reference','ID number'],['barangay','Barangay']], columns:['Sample person','ID type','ID number'], statuses:['Active'], seeds:[]}
  };
  const key = new URLSearchParams(location.search).get('project') || 'queue';
  const type = configurations[key] ? key : 'queue';
  const config = configurations[type];
  const storageKey = 'jonalyn-portfolio-demo-v1-' + type;
  const $ = selector => document.querySelector(selector);
  const escape = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
  const seed = () => type === 'verification' ? [
    {id:'PRO-001',name:'A*** S*****',detail:'Professional',reference:'0000001',barangay:'Demo Barangay',status:'Active'},
    {id:'HOME-001',name:'J**** D***',detail:'Home',reference:'000001',barangay:'Demo Barangay',status:'Active'},
    {id:'FOOD-001',name:'R**** S*****',detail:'Food',reference:'00001',barangay:'Demo Barangay',status:'Active'}
  ] : config.seeds.map(([name,detail,status],index) => ({id:'DEMO-'+String(index+1).padStart(3,'0'),name,detail,status}));
  let records;
  try {
    const saved = localStorage.getItem(storageKey);
    records = saved === null ? seed() : JSON.parse(saved);
    if (!Array.isArray(records) || !records.every(row => row && typeof row.id === 'string' && typeof row.name === 'string' && config.statuses.includes(row.status))) throw new Error('Invalid records');
    localStorage.setItem(storageKey,JSON.stringify(records));
  } catch (_) {
    records = seed();
    $('#message').textContent = 'Saved data could not be loaded. Sample records are shown. Storage may be blocked or damaged; changes may not persist.';
  }
  function save(next) {
    try { localStorage.setItem(storageKey,JSON.stringify(next)); records = next; render(); return true; }
    catch (_) { $('#message').textContent = 'Unable to save. Browser storage may be disabled or full. Your previous records have been kept.'; return false; }
  }
  document.title = config.title + ' | Browser demo';
  $('#title').textContent = $('#sideTitle').textContent = config.title;
  $('#description').textContent = config.description;
  $('#formTitle').textContent = config.form;
  const today = () => { const now = new Date(); return [now.getFullYear(),String(now.getMonth()+1).padStart(2,'0'),String(now.getDate()).padStart(2,'0')].join('-'); };
  $('#recordForm').innerHTML = config.fields.map(([name,label,inputType]) => {
    let control;
    const options = inputType === 'slot' ? ['AM','PM'] : inputType === 'kind' ? ['Professional','Home','Food'] : null;
    if (options) control = `<select name="${name}">${options.map(option => `<option>${option}</option>`).join('')}</select>`;
    else control = `<input name="${name}" type="${inputType || 'text'}" required maxlength="100" ${inputType === 'date' ? `min="${today()}"` : ''} ${name === 'barangay' ? 'value="Demo Barangay"' : ''}>`;
    return `<label>${label}${control}</label>`;
  }).join('') + `<button type="submit">${type === 'verification' ? 'Verify ID' : 'Save record'}</button>`;
  if (type === 'verification') {
    $('#recordsTitle').textContent = 'Fictional IDs to try';
    $('#search').placeholder = 'Search sample IDs';
  }
  function render() {
    const active = records.filter(row => row.status === config.statuses[0]).length;
    $('#stats').innerHTML = [['Total records',records.length],[config.statuses[0],active],['Storage','This browser']].map(([label,value]) => `<div class="stat"><span>${escape(label)}</span><strong>${escape(value)}</strong></div>`).join('');
    $('#head').innerHTML = '<tr>' + ['Reference',...config.columns,'Status',...(type === 'verification' ? [] : ['Actions'])].map(label => `<th scope="col">${label}</th>`).join('') + '</tr>';
    const search = $('#search').value.toLowerCase();
    const filtered = records.filter(row => Object.values(row).join(' ').toLowerCase().includes(search));
    $('#rows').innerHTML = filtered.map(row => `<tr><td>${escape(row.id)}</td><td>${escape(row.name)}</td><td>${escape(row.detail)}</td>${type === 'appointments' ? `<td>${escape(row.date)} / ${escape(row.slot)}</td>` : type === 'verification' ? `<td>${escape(row.reference)}</td>` : ''}<td>${escape(row.status)}</td>${type === 'verification' ? '' : `<td><button data-action="advance" data-id="${escape(row.id)}">Change status</button><button class="secondary" data-action="delete" data-id="${escape(row.id)}">Delete</button></td>`}</tr>`).join('') || `<tr><td colspan="6">No matching records.</td></tr>`;
  }
  $('#recordForm').addEventListener('submit', event => {
    event.preventDefault();
    const values = Object.fromEntries(new FormData(event.target));
    Object.keys(values).forEach(name => { values[name] = values[name].trim(); });
    if (Object.values(values).some(value => !value)) { $('#result').textContent = 'Please complete every field.'; return; }
    if (type === 'verification') {
      const match = records.find(row => row.reference === values.reference && row.detail === values.kind);
      $('#result').textContent = !match ? 'No matching demo ID found.' : match.barangay.toLowerCase() !== values.barangay.toLowerCase() ? 'Barangay does not match this demo ID.' : `Verified fictional record: ${match.name}. Status: ${match.status}. This is a demonstration, not a real identity check.`;
      return;
    }
    if (type === 'appointments') {
      const date = new Date(values.date + 'T12:00:00');
      if (values.date < today() || !Number.isFinite(date.getTime()) || [0,6].includes(date.getDay())) { $('#result').textContent = 'Choose a weekday today or later.'; return; }
      if (records.some(row => row.date === values.date && row.slot === values.slot && row.status !== 'Cancelled')) { $('#result').textContent = 'This demo slot is already booked. Choose another date or time.'; return; }
    }
    const nextNumber = records.reduce((max,row) => Math.max(max,Number(row.id.replace('DEMO-','')) || 0),0)+1;
    const row = {...values,id:'DEMO-'+String(nextNumber).padStart(3,'0'),status:config.statuses[0]};
    if (save([...records,row])) { event.target.reset(); $('#result').textContent = `Saved ${row.id} in this browser.`; }
  });
  $('#search').addEventListener('input',render);
  $('#rows').addEventListener('click', event => {
    const button = event.target.closest('button[data-action]');
    if (!button) return;
    const row = records.find(item => item.id === button.dataset.id);
    if (!row) return;
    if (button.dataset.action === 'delete') {
      if (confirm('Delete this demo record?')) save(records.filter(item => item.id !== row.id));
    } else {
      const status = config.statuses[(config.statuses.indexOf(row.status)+1)%config.statuses.length];
      if (type === 'appointments' && status !== 'Cancelled' && records.some(item => item.id !== row.id && item.date === row.date && item.slot === row.slot && item.status !== 'Cancelled')) { $('#message').textContent = 'Cannot reopen this appointment: its slot is already booked.'; return; }
      save(records.map(item => item.id === row.id ? {...item,status} : item));
    }
  });
  $('#reset').addEventListener('click',() => { if (confirm('Remove your changes and restore fictional samples for this demo?')) { if (save(seed())) $('#result').textContent = 'Demo reset.'; } });
  window.addEventListener('storage', event => { if (event.key === storageKey) location.reload(); });
  render();
})();
