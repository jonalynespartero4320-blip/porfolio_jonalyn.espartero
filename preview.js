const showcases = {
  visitorflow: {
    title: 'VisitorFlow', image: 'showcase-assets/visitorflow.png', type: 'Operations & Queuing',
    lead: 'A visitor journey designed for clear registration, organized queues, and efficient front-desk work.', accent: '#245ca6', soft: '#cfe1ff',
    features: [['01','Guided registration','A focused step-by-step intake experience.'],['02','Live queue view','Clear status information for staff and visitors.'],['03','Useful reporting','Operational summaries without sensitive records.']]
  },
  assetpilot: {
    title: 'AssetPilot', image: 'showcase-assets/assetpilot.png', type: 'Inventory Operations',
    lead: 'An asset workspace that brings equipment, service work, and operational requests into one view.', accent: '#147f82', soft: '#bde5e4',
    features: [['01','Asset records','Structured equipment and supply organization.'],['02','Service workflow','Track requests from submission to completion.'],['03','Management views','Readable summaries for better decisions.']]
  },
  verifyhub: {
    title: 'VerifyHub', image: 'showcase-assets/verifyhub.png', type: 'Identity Services Concept',
    lead: 'A privacy-conscious multi-service verification experience.', accent: '#155bc4', soft: '#cfe0ff',
    features: [['01','Service selection','A clear starting point for every user.'],['02','Protected results','Minimal information and privacy-aware feedback.'],['03','Responsive access','Designed to work across common devices.']]
  },
  recordbridge: {
    title: 'RecordBridge', image: 'showcase-assets/recordbridge.png', type: 'Data Administration',
    lead: 'An administrative interface for importing, reviewing, and maintaining structured records.', accent: '#17655c', soft: '#bde8e2',
    features: [['01','Import workflow','Guided file processing and validation.'],['02','Duplicate review','Catch possible record conflicts early.'],['03','Activity history','Readable logs for accountable work.']]
  },
  stockwise: {
    title: 'StockWise', image: 'showcase-assets/stockwise.png', type: 'Small Business Tools',
    lead: 'A flexible workspace for inventory, costs, sales, expenses, and everyday business insight.', accent: '#08784f', soft: '#bdebd6',
    features: [['01','Stock overview','Understand quantities and reorder needs.'],['02','Costing tools','Compare capital, price, and expected margin.'],['03','Business reports','Turn everyday entries into useful summaries.']]
  }
};

const key = new URLSearchParams(location.search).get('project') || 'visitorflow';
const project = showcases[key] || showcases.visitorflow;

document.documentElement.style.setProperty('--accent', project.accent);
document.documentElement.style.setProperty('--soft', project.soft);
document.title = `${project.title} — Portfolio Showcase`;
document.querySelector('#projectType').textContent = `${project.type} / Anonymized design`;
document.querySelector('#projectTitle').textContent = project.title;
document.querySelector('#projectLead').textContent = project.lead;
document.querySelector('#designImage').src = project.image;
document.querySelector('#designImage').alt = `${project.title} anonymized interface design`;
document.querySelector('#featureGrid').innerHTML = project.features
  .map(([number,title,text]) => `<article class="feature-card"><span>${number}</span><h3>${title}</h3><p>${text}</p></article>`)
  .join('');
