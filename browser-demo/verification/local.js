(()=>{
 'use strict';const D=PortfolioDemo,$=s=>document.querySelector(s),store=new D.Store('verification',()=>({records:[{kind:'professional',number:'0000001',barangay:'Demo Barangay',first:'A***',middle:'D***',last:'S*****'},{kind:'home',number:'000001',barangay:'Demo Barangay',first:'J****',middle:'D***',last:'E******'},{kind:'food',number:'00001',barangay:'Demo Barangay',first:'R****',middle:'D***',last:'S*****'}],history:[]}));D.banner(store);
 const kind=location.pathname.split('/').pop().replace('.html','');const text=(id,value)=>{if($(id))$(id).textContent=value;};
 if($('#sessionExpiredAlert'))$('#sessionExpiredAlert').style.display='none';text('#top-header','Fictional ID verification · Browser demo');
 if($('#brgySelect'))$('#brgySelect').innerHTML='<option value="">Select barangay</option><option>Demo Barangay</option><option>Sample Barangay</option>';
 const field=kind==='home'?$('#senior_citizen_id'):$('#unique_id');
 if($('#verifyForm')){
  const info=document.createElement('p');info.className='field-help';info.textContent='Try '+({professional:'0000001 with Demo Barangay',home:'000001',food:'00001'}[kind])+'. Other numbers show a missing record.';$('#verifyForm').append(info);
  $('#verifyForm').addEventListener('submit',event=>{event.preventDefault();const number=field.value.trim(),barangay=$('#brgySelect')?.value||'Demo Barangay';if(!number||kind==='professional'&&!barangay){text('#error-message','Complete the ID number and barangay.');return;}
   const result=D.lookup(store.data.records,kind,number,barangay);try{store.change(state=>{state.history.push({kind,result:result.status,at:new Date().toISOString()});state.history=state.history.slice(-50);});}catch(error){D.notice(error.message);return;}
   text('#error-message','');text('#success-message','');
   if(result.status==='missing'){text('#error-message','No matching fictional record.');D.modal('#notVerifiedModal');return;}
   if(result.status==='mismatch'){text('#error-message','Barangay does not match this fictional ID.');D.modal('#wrongBrgyCodeModal');return;}
   text('#v_firstname',result.row.first);text('#v_middlename',result.row.middle);text('#v_lastname',result.row.last);text('#v_pwd_id','PRO-ID-'+result.row.number);text('#v_senior_citizen_id',result.row.number);text('#v_rtc_code','FOOD-ID-'+result.row.number);text('#v_acc_status','Active demo');text('#valid_status_badge','Fictional demo');text('#v_valid_until','Sample record only');text('#success-message','Fictional sample matched. This is not real identity verification.');D.modal('#verifiedModal');
  });
 }
 // The existing protected scanner panel becomes an explicit demo lookup, with no secret gate.
 if($('#scanQrButton')){$('#scanQrButton').disabled=false;$('#scanQrButton').textContent='Use sample Food ID';$('#scanQrButton').onclick=()=>{if(field)field.value='00001';D.notice('Submit the sample number above. Camera scanning is not included in this browser demo.');};}
 document.querySelectorAll('form').forEach(form=>{if(form.id!=='verifyForm')form.addEventListener('submit',event=>{event.preventDefault();D.notice('Use the fictional ID lookup form. Protected server scanning is not part of this demo.');});});
 document.addEventListener('click',event=>{const close=event.target.closest('[data-bs-dismiss="modal"]');if(close&&!window.bootstrap){const modal=close.closest('.modal');modal.style.display='none';modal.classList.remove('show');}});
})();
