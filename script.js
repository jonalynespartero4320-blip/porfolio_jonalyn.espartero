const roles={coordinator:{number:'AUG 2024–PRESENT / LGU OF MANDALUYONG',title:'Web Programmer',text:'Deploy, configure, and maintain government information systems. Enhance back-end code, troubleshoot issues, provide technical support, and create user manuals to help staff work effectively.',tags:['System deployment','Back-end programming','Technical support','User documentation']},creative:{number:'MAR 2024–AUG 2024 / MICROGENESIS BUSINESS SYSTEM',title:'Sales Associate',text:'Managed client accounts, sales reports, and account mapping using Salesforce CRM. Conducted telemarketing, prepared quotations, presented products, monitored service delivery, and resolved customer concerns.',tags:['Salesforce CRM','Client relations','Sales reporting','Product presentations']},client:{number:'MAR 2023–JAN 2024 / LEXSYS TECHNOLOGIES INCORPORATED',title:'Technical Intern / Technical Support / Programmer',text:'Started by creating user manuals and system documentation as an intern. Progressed to technical support for LGU system deployment and troubleshooting, then took on programming work to enhance back-end code and maintain system functionality.',tags:['LGU user support','System documentation','Troubleshooting','Code maintenance']}};
const projects=[
 {title:'ID & Queuing System',type:'systems',label:'Visitor Registration & Queuing',theme:'#c5e5cf',accent:'#205c3b',copy:'Follow a visitor from registration to the live queue.',description:'Explore the original visitor management application: records, registration, ID issuance, queue monitoring, and reports, connected to a disposable demo database.',stack:['PHP','MySQL','Visitor management','Live queue'],url:'bjmp_vaqs/public/',demoLogin:true},
 {title:'Inventory Management System',type:'systems',label:'Inventory Management System',theme:'#b9d9ff',accent:'#164b85',copy:'Manage equipment, assignments, and service requests.',description:'Use the original inventory application to browse and manage equipment, assignments, maintenance, and reports. Demo changes are saved in a separate sandbox database.',stack:['PHP','MySQL','Asset management','Reporting'],url:'ims/public/',demoLogin:true},
 {title:'Verification Portal',type:'systems',label:'PWD & Home Verification',theme:'#a9bfff',accent:'#203b7a',artColor:'#203b7a',copy:'Try a real ID lookup with fictional sample records.',description:'Open the original verification portal and test Professional ID, Home ID, and Food ID lookups against sample records. Try matching, missing, and invalid IDs and see privacy-masked results.',stack:['PHP','MySQL','Verification API','Privacy-aware results'],url:'verification_system/',sampleRecords:true},
 {title:'Online Appointment System',type:'systems',label:'Public Booking & Admin Management',theme:'#f7cccc',accent:'#7a2525',copy:'Online booking with a dedicated admin dashboard.',description:'Customers can book appointments, check weekday AM/PM availability, view confirmations, and cancel bookings. The administrator dashboard supports reviewing appointments, tracking arrivals, and managing booking statuses. Super admins can also manage administrator accounts.',stack:['PHP','MySQL / SQLite','Appointment booking','Live availability'],url:'Health/public/customer',adminUrl:'Health/public/login',launchNote:'Public booking requires no sign-in. The admin portal requires an authorized staff account. Submitted appointments are saved in the Health database.'}
];
const roleDetail=document.querySelector('#roleDetail');
const photoPreview = document.querySelector('#photoPreview');
const openPhoto = document.querySelector('#openPhoto');
openPhoto.addEventListener('click', () => photoPreview.showModal());
document.querySelector('#closePhoto').addEventListener('click', () => photoPreview.close());
photoPreview.addEventListener('click', (event) => {
  if (event.target === photoPreview) {
    const box = photoPreview.getBoundingClientRect();
    if (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom) photoPreview.close();
  }
});
photoPreview.addEventListener('close', () => openPhoto.focus());
function showRole(key) {
  const role = roles[key];

  roleDetail.innerHTML = `
    <span class="role-number">${role.number}</span>
    <h3>${role.title}</h3>
    <p>${role.text}</p>
    <ul>${role.tags.map((tag) => `<li>${tag}</li>`).join('')}</ul>
  `;
}
showRole('coordinator');
document.querySelectorAll('.role-tab').forEach(tab=>tab.addEventListener('click',()=>{document.querySelectorAll('.role-tab').forEach(t=>t.classList.remove('active'));tab.classList.add('active');showRole(tab.dataset.role)}));
const grid=document.querySelector('#projectGrid');
function renderProjects(filter='all'){grid.innerHTML=projects.map((p,i)=>({p,i})).filter(x=>filter==='all'||x.p.type===filter).map(({p,i})=>`<article class="project-card reveal" tabindex="0" data-project="${i}" aria-label="View ${p.title} project details"><div class="project-art" style="background:${p.theme};color:${p.artColor || 'inherit'}"><span class="project-index">0${i+1}</span><div class="site-mini"><h4>${p.title}</h4><p>${p.copy}</p><button>View project</button></div></div><div class="project-info"><div><h3>${p.title}</h3><p>${p.label}</p></div><b>↗</b></div></article>`).join('');observe();document.querySelectorAll('.project-card').forEach(card=>{card.addEventListener('click',()=>openProject(+card.dataset.project));card.addEventListener('keydown',e=>{if(e.key==='Enter')openProject(+card.dataset.project)})})}
renderProjects();
document.querySelectorAll('.filters button').forEach(btn=>btn.addEventListener('click',()=>{document.querySelectorAll('.filters button').forEach(b=>b.classList.remove('active'));btn.classList.add('active');renderProjects(btn.dataset.filter)}));
const modal=document.querySelector('#projectModal'),modalContent=document.querySelector('#modalContent');
function openProject(i) {
  const p = projects[i];
  document.querySelector('#modalUrl').textContent = p.url;
  const instructions = p.demoLogin
    ? `<p>Open the demo and sign in.</p><p>Username: <code>demo</code><br>Password: <code>Demo2026!</code></p>`
    : p.sampleRecords
      ? `<p>Choose an ID type and enter a sample number.</p><ul><li>Professional: <code>0000001</code> (Demo Barangay)</li><li>Home: <code>000001</code></li><li>Food: <code>00001</code></li></ul>`
      : `<p>Choose a date and complete the booking form. No login needed.</p>`;
  modalContent.innerHTML = `<div class="demo-hero demo-compact" style="background:${p.theme};color:${p.accent}"><span class="demo-type">Live demo</span><h2>${p.title}</h2>${instructions}<a class="demo-pill" href="${p.url}" target="_blank" rel="noopener">Open demo &#8599;</a>${p.adminUrl ? `<div class="demo-instructions"><h3>Admin dashboard</h3><p>Manage bookings and arrivals. Staff login required.</p><a class="demo-pill" href="${p.adminUrl}" target="_blank" rel="noopener">Open admin &#8599;</a></div>` : ''}<small class="launch-note">Demo data and branding changed for privacy.</small></div>`;
  modal.classList.add('open');
  modal.setAttribute('aria-hidden', 'false');
  document.body.classList.add('modal-open');
  document.querySelector('#closeModal').focus();
}
function closeProject() {
  modal.classList.remove('open');
  modal.setAttribute('aria-hidden', 'true');
  document.body.classList.remove('modal-open');
}
document.querySelector('#closeModal').addEventListener('click',closeProject);modal.addEventListener('click',e=>{if(e.target===modal)closeProject()});document.addEventListener('keydown',e=>{if(e.key==='Escape')closeProject()});
const menu=document.querySelector('.menu-toggle'),links=document.querySelector('#navLinks');menu.addEventListener('click',()=>{const open=links.classList.toggle('open');menu.setAttribute('aria-expanded',open)});document.querySelectorAll('.nav-links a').forEach(a=>a.addEventListener('click',()=>{links.classList.remove('open');menu.setAttribute('aria-expanded','false')}));
function observe(){const io=new IntersectionObserver(entries=>entries.forEach(e=>{if(e.isIntersecting){e.target.classList.add('visible');io.unobserve(e.target)}}),{threshold:.12});document.querySelectorAll('.reveal:not(.visible)').forEach(el=>io.observe(el))}observe();
const sections=[...document.querySelectorAll('main section[id]')];window.addEventListener('scroll',()=>{let current='home';sections.forEach(s=>{if(scrollY>=s.offsetTop-160)current=s.id});document.querySelectorAll('.nav-links a').forEach(a=>a.classList.toggle('active',a.getAttribute('href')===`#${current}`))},{passive:true});
