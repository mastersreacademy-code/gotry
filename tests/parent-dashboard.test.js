import test from 'node:test';
import assert from 'node:assert/strict';
import worker from '../src/index.js';
import {validateStep} from '../src/wizard.js';
const url=p=>'https://gotry.example'+p;
test('role dashboards and discovery pages exist',async()=>{
 for(const path of ['/parent','/provider','/activities','/admin','/provider/new','/account']){
  const r=await worker.fetch(new Request(url(path)),{});
  assert.equal(r.status,200,path);
  assert.match(r.headers.get('x-robots-tag'),/noindex/);
 }
});
test('parent interest API rejects unauthenticated users',async()=>{
 const r=await worker.fetch(new Request(url('/api/parent/interests')),{DB:{}});
 assert.equal(r.status,401);
});
test('parent interest API requires same-origin JSON mutation',async()=>{
 const r=await worker.fetch(new Request(url('/api/parent/interests'),{method:'POST',headers:{origin:'https://evil.example','content-type':'application/json'},body:'{}'}),{DB:{}});
 assert.equal(r.status,403);
});
test('provider validation rejects blank age and price',()=>{
 const activity={activityName:'Art',category:'Art',description:'Painting',location:'Sydney',minAge:'',maxAge:''};
 assert.ok(validateStep(1,activity).maxAge);
 assert.ok(validateStep(3,{regularDescription:'Weekly',regularPrice:''}).regularPrice);
});
test('discovery page includes interest action and parent dashboard',async()=>{
 const r=await worker.fetch(new Request(url('/activities')));
 const html=await r.text();
 assert.match(html,/Register interest/);
 assert.match(html,/\/parent/);
});
