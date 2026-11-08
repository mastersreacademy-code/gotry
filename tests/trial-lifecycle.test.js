import test from 'node:test';
import assert from 'node:assert/strict';
import {validateStep} from '../src/wizard.js';
import worker from '../src/index.js';
const base='https://gotry.example';
test('provider and parent dashboards render without a DB',async()=>{
 for(const path of ['/provider','/parent','/admin','/activities']){
  const response=await worker.fetch(new Request(base+path),{});
  assert.equal(response.status,200,path);
  assert.match(response.headers.get('x-robots-tag'),/noindex/);
 }
});
test('public activity endpoint fails closed without D1',async()=>{
 const r=await worker.fetch(new Request(base+'/api/activities'),{});
 assert.equal(r.status,503);
});
test('trial lifecycle actions reject cross-origin requests',async()=>{
 const r=await worker.fetch(new Request(base+'/api/admin/activities/00000000-0000-4000-8000-000000000001/lifecycle',{method:'POST',headers:{origin:'https://attacker.example','content-type':'application/json'},body:'{"action":"confirm_minimum"}'}),{DB:{}});
 assert.equal(r.status,403);
});
test('unauthenticated trial lifecycle actions are rejected',async()=>{
 const r=await worker.fetch(new Request(base+'/api/admin/activities/00000000-0000-4000-8000-000000000001/lifecycle',{method:'POST',headers:{origin:base,'content-type':'application/json'},body:'{"action":"confirm_minimum"}'}),{DB:{}});
 assert.equal(r.status,401);
});
test('provider wizard enforces required age, price, and group size',()=>{
 assert.ok(validateStep(1,{activityName:'Art',category:'Art',description:'Painting',location:'Sydney',minAge:'',maxAge:'8'}).maxAge);
 assert.ok(validateStep(2,{trialType:'group',trialDate:'2027-01-10',minParticipants:'5',maxParticipants:'3'}).maxParticipants);
 assert.ok(validateStep(3,{regularDescription:'Weekly',regularPrice:''}).regularPrice);
});
test('discovery and dashboards display trial status indicators',async()=>{
 const discovery=await(await worker.fetch(new Request(base+'/activities'))).text();
 const provider=await(await worker.fetch(new Request(base+'/provider'))).text();
 assert.match(discovery,/Trial full/);
 assert.match(provider,/Cancel activity/);
});
