const showcases = {
  visitorflow: { title: 'VisitorFlow', type: 'Operations & Queuing', lead: 'A sample visitor journey designed for clear registration, organized queues, and efficient front-desk work.', accent: '#245ca6', soft: '#cfe1ff', features: [['01','Guided registration','A focused step-by-step intake experience.'],['02','Live queue view','Clear status information for staff and visitors.'],['03','Useful reporting','Operational summaries without sensitive records.']] },
  assetpilot: { title: 'AssetPilot', type: 'Inventory Operations', lead: 'A fictional asset workspace that brings equipment, service work, and operational requests into one view.', accent: '#654bd8', soft: '#ded8ff', features: [['01','Asset records','Structured equipment and supply organization.'],['02','Service workflow','Track requests from submission to completion.'],['03','Management views','Readable summaries for better decisions.']] },
  verifyhub: { title: 'VerifyHub', type: 'Identity Services Concept', lead: 'A privacy-conscious demonstration of a simple multi-service verification experience.', accent: '#526900', soft: '#e5ff7a', features: [['01','Service selection','A clear starting point for every user.'],['02','Protected results','Minimal information and privacy-aware feedback.'],['03','Responsive access','Designed to work across common devices.']] },
  recordbridge: { title: 'RecordBridge', type: 'Data Administration', lead: 'A sample administrative tool for importing, reviewing, and maintaining structured records.', accent: '#17655c', soft: '#bde8e2', features: [['01','Import workflow','Guided file processing and validation.'],['02','Duplicate review','Catch possible record conflicts early.'],['03','Activity history','Readable logs for accountable work.']] },
  stockwise: { title: 'StockWise', type: 'Small Business Tools', lead: 'A flexible sample dashboard for inventory, costs, sales, expenses, and everyday business insight.', accent: '#8a2b5d', soft: '#f7cfe5', features: [['01','Stock overview','Understand quantities and reorder needs.'],['02','Costing tools','Compare capital, price, and expected margin.'],['03','Business reports','Turn everyday entries into useful summaries.']] }
};
const key = new URLSearchParams(location.search).get('project') || 'visitorflow';
const project = showcases[key] || showcases.visitorflow;
document.documentElement.style.setProperty('--accent', project.accent);
document.documentElement.style.setProperty('--soft', project.soft);
document.title = `${project.title} — Portfolio Showcase`;
document.querySelector('#projectType').textContent = `${project.type} / Showcase`;
document.querySelector('#projectTitle').textContent = project.title;
document.querySelector('#projectLead').textContent = project.lead;
document.querySelector('#sideBrand').textContent = project.title;
document.querySelector('#featureGrid').innerHTML = project.features.map(([number,title,text]) => `<article class="feature-card"><span>${number}</span><h3>${title}</h3><p>${text}</p></article>`).join('');
