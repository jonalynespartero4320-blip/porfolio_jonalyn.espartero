// Runs the real adapter with a minimal DOM to verify data behavior independently of browser/CDN access.
const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict'),path=require('node:path');
const source=fs.readFileSync(path.join(__dirname,'../browser-demo/bjmp/local.js'),'utf8');
const original=fs.readFileSync(path.join(__dirname,'../browser-demo/bjmp/queue.html'),'utf8');
function run(saved){
 const elements=new Map(),listeners={},store=new Map(saved?[['je-bjmp-original-v1',JSON.stringify(saved)]]:[]);let fail=false;
 function element(){return {textContent:'',innerHTML:'',value:'',style:{},dataset:{},className:'',classList:{add(){},remove(){},toggle(){},contains(){return false;}},setAttribute(){},querySelector(){return null;},querySelectorAll(){return[];},closest(){return null;},append(){},prepend(){},remove(){},reset(){},addEventListener(name,fn){this[name]=fn;}};}
 for(const match of original.matchAll(/id="([^"]+)"/g))elements.set('#'+match[1],element());elements.set('#localReset',element());elements.set('#tblRequests tbody',element());
 const document={body:{prepend(){},append(){}},createElement:element,querySelector(selector){return elements.get(selector)||null;},querySelectorAll(){return[];},addEventListener(name,fn){(listeners[name]??=[]).push(fn);}};
 const localStorage={getItem:name=>store.get(name)||null,setItem(name,value){if(fail)throw Error('Quota');store.set(name,value);}};
 const context={document,window:{addEventListener(){}},location:{pathname:'/browser-demo/bjmp/queue.html'},localStorage,console,Date,JSON,Set,String,Array,Object,Number,Math,confirm:()=>true,setTimeout:()=>1,clearTimeout(){},setInterval(){},URLSearchParams};
 vm.runInNewContext(source,context,{filename:'local.js'});
 return {elements,read:()=>JSON.parse(store.get('je-bjmp-original-v1')),setFailure:()=>{fail=true;},click(action,id){const control={dataset:{localAction:action,id}};const event={target:{closest(selector){return selector==='[data-local-action]'?control:null;}},preventDefault(){}};for(const listener of listeners.click||[])listener(event);}};
}
const first=run();assert.equal(first.read().visitors[0].id,'V001');console.log('PASS fictional data initializes in localStorage');
first.click('queue','V001');assert.equal(first.read().queue.length,1);console.log('PASS verified visitor can join queue');
first.click('queue','V001');assert.equal(first.read().queue.length,1);console.log('PASS duplicate active queue request rejected');
const id=first.read().queue[0].id;first.click('complete',id);assert.equal(first.read().queue[0].status,'pending');console.log('PASS time out blocked before admission');
first.click('accept',id);assert.equal(first.read().queue[0].status,'inside');assert.equal(first.elements.get('#statInside').textContent,'1 / 10');console.log('PASS admission saves status and updates original queue stats');
first.click('complete',id);assert.equal(first.read().queue[0].status,'completed');assert.ok(first.read().queue[0].timeOut);console.log('PASS time out persists');
const reload=run(first.read());assert.equal(reload.elements.get('#statCompleted').textContent,1);console.log('PASS saved state restores on reload');
const unsafe=first.read();unsafe.visitors[0].firstname='<img src=x onerror=alert(1)>';unsafe.queue[0].status='pending';const escaped=run(unsafe);assert.ok(escaped.elements.get('#tblRequests tbody').innerHTML.includes('&lt;img'));console.log('PASS record text is escaped before table rendering');
const failure=run(first.read());failure.setFailure();failure.click('queue','V001');assert.equal(failure.read().queue.length,1);console.log('PASS storage failure preserves previously saved records');
const blocked=first.read();blocked.visitors[0].banned=true;const banned=run(blocked);banned.click('queue','V001');assert.equal(banned.read().queue.length,1);console.log('PASS banned visitor cannot join queue');
