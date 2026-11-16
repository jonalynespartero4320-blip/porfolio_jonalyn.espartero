(function(root){
 'use strict';
 const escape=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 const date=()=>new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Singapore',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date());
 function validDate(value,today=date()) { if(!/^\d{4}-\d{2}-\d{2}$/.test(value))return false;const parsed=new Date(value+'T00:00:00Z'),limit=new Date(today+'T00:00:00Z');limit.setUTCDate(limit.getUTCDate()+90);return Number.isFinite(+parsed)&&parsed.toISOString().slice(0,10)===value&&value>=today&&+parsed<=+limit&&![0,6].includes(parsed.getUTCDay()); }
 function reserve(state,input,today=date()) {
  if(!validDate(input.date,today)||!['AM','PM'].includes(input.period))throw Error('Choose a weekday within the next 90 days and an AM or PM session.');
  for(const name of ['full_name','gender','company','job'])if(typeof input[name]!=='string'||input[name].trim().length<2||input[name].length>150)throw Error('Complete your customer details.');
  if(state.bookings.filter(row=>row.date===input.date&&row.period===input.period&&row.status!=='cancelled').length>=250)throw Error('This session is full. Choose another time.');
  const sequenceKey=input.date+'-'+input.period;const sequence=(state.sequences[sequenceKey]||0)+1;state.sequences[sequenceKey]=sequence;
  const reference=String(sequence).padStart(3,'0')+'-'+input.date.slice(5,7)+input.date.slice(8,10)+input.date.slice(2,4)+'-'+input.period[0];
  const row={...input,id:reference,reference,status:'confirmed',attendance:'Not arrived',created:new Date().toISOString()};state.bookings.push(row);state.receipt=row.id;delete state.draft;return row;
 }
 function cancel(state,reference,name,reason) {const row=state.bookings.find(item=>item.reference.toLowerCase()===reference.trim().toLowerCase()&&item.full_name.toLowerCase()===name.trim().toLowerCase());if(!row)throw Error('No matching booking in this browser.');if(row.status!=='confirmed'||row.attendance==='Arrived')throw Error('This booking cannot be cancelled.');if(reason.trim().length<10)throw Error('Enter a cancellation reason of at least 10 characters.');row.status='cancelled';row.reason=reason.trim();return row;}
 function lookup(rows,kind,number,barangay) {const normalized=number.trim().replace(/^(PRO-ID-|FOOD-ID-)/i,'');const row=rows.find(item=>item.kind===kind&&item.number===normalized);if(!row)return{status:'missing'};if(kind==='professional'&&row.barangay.toLowerCase()!==barangay.trim().toLowerCase())return{status:'mismatch'};return{status:'verified',row};}
 class Store {
  constructor(name,seed){this.key='je-demo-v2-'+name;this.seed=seed;this.warning='';try{const raw=localStorage.getItem(this.key);this.data=raw?JSON.parse(raw):seed();if(!this.data||typeof this.data!=='object'||Array.isArray(this.data)||Object.keys(seed()).some(key=>Array.isArray(seed()[key])&&!Array.isArray(this.data[key])))throw Error();localStorage.setItem(this.key,JSON.stringify(this.data));}catch(_){this.data=seed();this.warning='Saved data could not be loaded. Browser storage may be blocked; sample records are shown.';}}
  change(callback){const next=JSON.parse(JSON.stringify(this.data));const result=callback(next);try{localStorage.setItem(this.key,JSON.stringify(next));}catch(_){throw Error('Unable to save: browser storage is full or disabled. Previous records are unchanged.');}this.data=next;return result;}
  reset(){const next=this.seed();localStorage.setItem(this.key,JSON.stringify(next));this.data=next;}
 }
 function notice(message){let el=document.querySelector('.demo-message');if(!el){el=document.createElement('div');el.className='demo-message';el.setAttribute('role','status');document.body.append(el);}el.textContent=message;}
 function banner(store){const element=document.createElement('div');element.className='demo-notice';element.innerHTML='<span>Browser demo · Fictional records · Changes stay on this device · No secure authentication</span><span><a href="../../index.html#projects">Portfolio</a> · <button type="button">Reset this demo</button></span>';document.body.prepend(element);element.querySelector('button').onclick=()=>{if(confirm('Reset this demo and discard its browser records?')){try{store.reset();location.reload();}catch(_){notice('Unable to reset browser storage.');}}};if(store.warning)notice(store.warning);}
 function modal(selector){const element=document.querySelector(selector);if(!element)return;if(root.bootstrap)bootstrap.Modal.getOrCreateInstance(element).show();else{element.classList.add('show');element.style.display='block';element.style.background='#0008';}}
 const api={Store,escape,date,validDate,reserve,cancel,lookup,notice,banner,modal};if(typeof module==='object')module.exports=api;else root.PortfolioDemo=api;
})(typeof window==='undefined'?globalThis:window);
