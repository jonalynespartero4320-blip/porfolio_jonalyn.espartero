const http=require('node:http'),fs=require('node:fs'),path=require('node:path'),{spawn}=require('node:child_process');
const root=path.resolve(__dirname,'..');
const server=http.createServer((req,res)=>{const file=path.resolve(root,'.'+decodeURIComponent(req.url.split('?')[0]));if(!file.startsWith(root+path.sep)){res.writeHead(403).end();return;}try{res.setHeader('Content-Type',({'.html':'text/html','.js':'text/javascript','.css':'text/css','.svg':'image/svg+xml'})[path.extname(file)]||'text/plain');res.end(fs.readFileSync(file));}catch{res.writeHead(404).end();}});
const sleep=ms=>new Promise(resolve=>setTimeout(resolve,ms));
let browser,ws,sequence=0;const pending=new Map(),errors=[];
const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++sequence;pending.set(id,{resolve,reject});ws.send(JSON.stringify({id,method,params}));});
const evaluate=async expression=>{const result=await send('Runtime.evaluate',{expression,awaitPromise:true,returnByValue:true});if(result.exceptionDetails)throw Error(JSON.stringify(result.exceptionDetails));return result.result.value;};
const go=async page=>{await send('Page.navigate',{url:'http://127.0.0.1:8787/browser-demo/bjmp/'+page+'.html'});await sleep(1800);};
function assert(value,label){if(!value)throw Error(label);console.log('PASS '+label);}
(async()=>{
 await new Promise(resolve=>server.listen(8787,'127.0.0.1',resolve));
 browser=spawn('C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=9323','--user-data-dir='+path.join(root,'.static-export-bjmp','qa-browser'),'about:blank'],{windowsHide:true,stdio:'ignore'});
 let tabs;for(let tries=0;tries<10;tries++){try{tabs=await(await fetch('http://127.0.0.1:9323/json',{signal:AbortSignal.timeout(1000)})).json();break;}catch{await sleep(200);}}
 if(!tabs)throw Error('Browser debugging unavailable');
 ws=new WebSocket(tabs.find(tab=>tab.type==='page').webSocketDebuggerUrl);await new Promise(resolve=>ws.addEventListener('open',resolve));
 ws.addEventListener('message',event=>{const data=JSON.parse(event.data);if(data.id){const request=pending.get(data.id);pending.delete(data.id);if(data.error)request.reject(Error(JSON.stringify(data.error)));else request.resolve(data.result);}else if(data.method==='Runtime.exceptionThrown')errors.push(data.params.exceptionDetails);});
 await send('Runtime.enable');await send('Page.enable');await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
 await go('index');assert(await evaluate("!!document.querySelector('.auth-stage') && !!document.querySelector('.auth-brand-panel')"),'original two-panel login markup');
 await evaluate("document.querySelector('[name=username]').value='demo';document.querySelector('[name=password]').value='Demo2026!';document.querySelector('form').requestSubmit()");await sleep(1500);
 assert(await evaluate("location.pathname.endsWith('dashboard.html')"),'demo login opens dashboard');
 assert(await evaluate("!!document.querySelector('.dashboard-hero') && !!document.querySelector('.sidebar')"),'original dashboard and sidebar');
 let screenshot=await send('Page.captureScreenshot',{format:'png'});fs.writeFileSync(path.join(root,'.static-export-bjmp/dashboard.png'),Buffer.from(screenshot.data,'base64'));
 await go('visitor-registration');await evaluate("document.querySelector('#pdlSelect').value='R001';document.querySelector('#pdlSelect').dispatchEvent(new Event('change'));document.querySelector('#btnSelectPDL').click()");
 assert(await evaluate("document.querySelector('#visitorTable tbody').textContent.includes('Robin')"),'authorized visitors load from localStorage');
 await evaluate("document.querySelector('[data-local-action=register-visitor]').click()");assert(await evaluate("document.querySelector('#tab2').classList.contains('active')"),'original registration steps advance');
 await evaluate("document.querySelector('#visitorDetailsForm').requestSubmit()");await evaluate("document.querySelector('#btnSubmitRegistration').click()");
 assert(await evaluate("JSON.parse(localStorage.getItem('je-bjmp-original-v1')).visitors[0].status==='Verified'"),'registration saved');
 await go('visitor-management');assert(await evaluate("document.querySelector('#visitorManagementTable tbody').textContent.includes('Robin')"),'registered visitor appears in original management screen');
 await evaluate("document.querySelector('[data-local-action=queue]').click()");assert(await evaluate("JSON.parse(localStorage.getItem('je-bjmp-original-v1')).queue.length===1"),'queue request persists');
 await go('queue');assert(await evaluate("document.querySelector('#tblRequests tbody').textContent.includes('Robin')"),'original queue displays saved request');
 await evaluate("document.querySelector('[data-local-action=accept]').click()");assert(await evaluate("document.querySelector('#statInside').textContent==='1 / 10'"),'admission updates capacity');
 await evaluate("document.querySelector('[data-local-action=complete]').click()");assert(await evaluate("document.querySelector('#statCompleted').textContent==='1'"),'time out completes admission');
 await go('queue');assert(await evaluate("document.querySelector('#statCompleted').textContent==='1'"),'queue state survives navigation');
 await go('pdl-form');assert(await evaluate("document.querySelector('[name=dorm_no]').options.length>1"),'original resident form has demo masterlists');
 await go('reports');assert(await evaluate("document.querySelector('#reportsTable tbody').textContent.includes('Robin')"),'reports use browser records');
 await send('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});await go('dashboard');assert(await evaluate("document.documentElement.scrollWidth<=window.innerWidth+1"),'mobile dashboard has no horizontal overflow');
 assert(errors.length===0,'no JavaScript runtime exceptions');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{try{if(ws)await send('Browser.close');}catch{}ws?.close();browser?.kill();server.close();});
