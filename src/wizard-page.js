import { steps } from './wizard.js';
export const wizardPage = String.raw`<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>GoTry · Provider activity draft</title>
<style>
:root{font-family:system-ui,-apple-system,sans-serif;color:#172748;background:#f6f8fc}*{box-sizing:border-box}body{margin:0}header{background:white;border-bottom:1px solid #e5e9f1;padding:18px max(24px,calc((100vw - 1050px)/2));display:flex;justify-content:space-between;align-items:center}.brand{font-weight:900;font-size:27px;color:#1971d4}.brand span{color:#ec2d84}a{color:#1769c8;text-decoration:none}main{max-width:1050px;margin:40px auto;padding:0 24px}h1{font-size:clamp(28px,5vw,40px);margin-bottom:6px}p{color:#63718a;line-height:1.55}.steps{display:flex;gap:7px;margin:30px 0;flex-wrap:wrap}.step{flex:1;min-width:110px;border-radius:12px;padding:12px 10px;background:#e7ebf3;color:#586780;font-size:13px;font-weight:700;text-align:center}.step.active{background:#176dd0;color:white}.step.done{background:#dff4e9;color:#14643f}.panel{background:white;border:1px solid #e3e8f0;border-radius:20px;padding:clamp(20px,4vw,38px);box-shadow:0 10px 28px #142b5208}h2{margin-top:0}.fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.field{display:flex;flex-direction:column;gap:7px;font-size:14px;font-weight:650}.wide{grid-column:1/-1}input,select,textarea{width:100%;padding:13px;border:1px solid #cbd5e3;border-radius:9px;font:inherit;background:white;color:#172748}textarea{min-height:110px;resize:vertical}.field small{font-weight:400;color:#67758b}.error{font-size:12px;color:#b82046;min-height:15px}.actions{display:flex;justify-content:space-between;gap:10px;margin-top:26px}button{padding:12px 20px;border:0;border-radius:10px;font:inherit;font-weight:700;cursor:pointer;background:#176dd0;color:white}button.secondary{background:#e9eef6;color:#263a57}button:disabled{opacity:.5;cursor:not-allowed}.note{background:#fff5d8;border-radius:12px;padding:15px;color:#765414;font-size:13px;margin:16px 0}.review{white-space:pre-wrap;background:#f6f8fc;border-radius:12px;padding:18px;line-height:1.8;overflow-wrap:anywhere}.saved{color:#287650;font-size:13px}footer{margin:30px 0;color:#78859b;font-size:12px}@media(max-width:620px){.fields{grid-template-columns:1fr}.step{min-width:85px;font-size:11px}.panel{padding:20px}main{margin-top:24px}}
</style></head><body><header><div class="brand">Go<span>Try</span></div><nav aria-label="Main navigation" style="display:flex;gap:14px;flex-wrap:wrap"><a href="/">Home</a><a href="/activities">Explore</a><a href="/account">Account</a><a href="/admin">Admin</a><a href="/provider">Dashboard</a></nav></header><main><h1>Create an activity</h1><p>Provider draft · 5 steps · Saved on this device only</p><div class="steps" id="steps" aria-label="Wizard progress"></div><section class="panel"><h2 id="heading"></h2><p id="help"></p><div class="note">Prototype only. Do not enter real personal information. Sign in to submit to a test database when configured.</div><form id="form" novalidate><div class="fields" id="fields"></div><div class="actions"><button type="button" class="secondary" id="back">Back</button><button type="submit" id="next">Continue</button></div></form><p class="saved" id="saved" role="status" aria-live="polite"></p></section><footer>No live bookings, payment processing or provider approval is available yet.</footer></main>
<script type="module">
const names=${JSON.stringify(steps)};
const key='gotry-provider-draft-v1';
const spec=[
 [['businessName','Business name','text',true],['contactName','Contact name','text',true],['email','Email','email',true],['phone','Phone','tel'],['website','Website','url']],
 [['activityName','Activity name','text',true],['category','Category','select',true,['Sports','Music','Art','STEM','Dance','Other']],['description','Activity description','textarea',true],['location','Venue or suburb','text',true],['minAge','Minimum age','number',true],['maxAge','Maximum age','number',true]],
 [['trialType','Trial format','select',true,['group','individual']],['trialDate','Proposed trial date','date',true],['trialTime','Proposed start time','time'],['minParticipants','Minimum participants (group)','number'],['maxParticipants','Maximum participants (group)','number'],['deadline','Interest registration deadline','date']],
 [['regularDescription','Regular lesson details','textarea',true],['regularPrice','Regular lesson price (AUD)','number',true],['discountType','GoTry discount type','select',false,['none','percent','fixed']],['discountValue','Discount value','number']],
 []];
let data={};try{const saved=JSON.parse(localStorage.getItem(key)||'{}');if(saved&&typeof saved==='object'&&!Array.isArray(saved))data=saved;}catch{}
let step=0;
const el=id=>document.getElementById(id);
const clean=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
function save(){try{localStorage.setItem(key,JSON.stringify(data));el('saved').textContent='Draft saved locally on this device.';}catch{el('saved').textContent='Local saving unavailable in this browser.';}}
function render(){
 el('steps').innerHTML=names.map((n,i)=>'<div class="step '+(i===step?'active':i<step?'done':'')+'">'+(i+1)+'. '+n+'</div>').join('');
 el('heading').textContent=names[step];
 el('help').textContent=['Tell families about your organisation.','Describe the activity and who it is for.','Set up an introductory trial and its minimum group size.','Explain the ongoing programme and optional discount.','Review your draft before a future submission.'][step];
 el('back').disabled=step===0;el('next').textContent=step===4?'Finish review':'Continue';
 if(step===4){el('fields').innerHTML='<div class="wide"><div class="review" id="review"></div></div>';el('review').textContent=Object.entries(data).filter(([k,v])=>v!==''&&v!=null).map(([k,v])=>k.replace(/([A-Z])/g,' $1')+': '+v).join('\n')||'No information entered.';return;}
 el('fields').innerHTML=spec[step].map(([key,label,type,required,opts])=>'<label class="field '+(['description','regularDescription'].includes(key)?'wide':'')+'">'+clean(label)+(required?' *':'')+(type==='textarea'?'<textarea name="'+key+'" '+(required?'required':'')+'>'+clean(data[key])+'</textarea>':type==='select'?'<select name="'+key+'" '+(required?'required':'')+'><option value="">Select…</option>'+opts.map(o=>'<option value="'+clean(o)+'" '+(data[key]===o?'selected':'')+'>'+clean(o)+'</option>').join('')+'</select>':'<input name="'+key+'" type="'+type+'" value="'+clean(data[key])+'" '+(required?'required':'')+(type==='number'?' min="0" step="any"':'')+'>')+'<span class="error" data-error="'+key+'"></span></label>').join('');
}
el('form').addEventListener('input',e=>{if(e.target.name){data[e.target.name]=e.target.value;save();}});
el('form').addEventListener('change',e=>{if(e.target.name){data[e.target.name]=e.target.value;save();}});
el('back').addEventListener('click',()=>{if(step>0){step--;render();}});
async function submitActivity(){
 const errors={};for(let i=0;i<4;i++){for(const [k,label,,required] of spec[i])if(required&&!String(data[k]??'').trim())errors[k]=label+' is required';}
 if(Object.keys(errors).length){el('saved').textContent='Missing required information: '+Object.values(errors).join('; ');return;}
 el('next').disabled=true;el('saved').textContent='Submitting activity…';
 try{const r=await fetch('/api/provider/activities',{method:'POST',headers:{'content-type':'application/json'},body:JSON.stringify(data)});const result=await r.json();if(!r.ok)throw Error(result.error||JSON.stringify(result.errors)||'Submission failed');localStorage.removeItem(key);el('saved').textContent='Submitted for admin approval. Reference: '+result.id;el('next').textContent='Submitted';}
 catch(e){el('next').disabled=false;el('saved').textContent=e.message+(e.message==='Unauthenticated'?' — Sign in at /account first.':'');}
}
el('form').addEventListener('submit',e=>{
 e.preventDefault();if(step===4){submitActivity();return;}
 const errors={};const required=spec[step].filter(f=>f[3]);for(const [k,label] of required)if(!String(data[k]??'').trim())errors[k]=label+' is required';
 if(step===0&&data.email&&!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.email))errors.email='Enter a valid email';
 if(step===1&&(!String(data.minAge??'').trim()||!String(data.maxAge??'').trim()||Number(data.maxAge)<Number(data.minAge)||Number(data.maxAge)>18))errors.maxAge='Maximum age must be at least minimum age';
 if(step===2&&data.trialType==='group'&&(!(Number(data.minParticipants)>0)||Number(data.maxParticipants)<Number(data.minParticipants)))errors.maxParticipants='Enter a valid group size range';
 if(step===3&&(!String(data.regularPrice??'').trim()||Number(data.regularPrice)<0))errors.regularPrice='Price must not be negative';
 for(const node of document.querySelectorAll('[data-error]'))node.textContent=errors[node.dataset.error]||'';
 if(Object.keys(errors).length){const first=el('fields').querySelector('[name="'+Object.keys(errors)[0]+'"]');first?.focus();return;}
 step++;render();
});
render();
</script></body></html>`;