import test from 'node:test';
import assert from 'node:assert/strict';
import worker from '../src/index.js';
const base='https://gotry.example',id='00000000-0000-4000-8000-000000000001';
test('provider roster requires authentication',async()=>{
 const r=await worker.fetch(new Request(base+'/api/provider/sessions/'+id+'/roster'),{DB:{}});
 assert.equal(r.status,401);
});
test('provider reschedule requires authentication',async()=>{
 const r=await worker.fetch(new Request(base+'/api/provider/sessions/'+id+'/reschedule',{method:'POST',headers:{origin:base,'content-type':'application/json'},body:'{}'}),{DB:{}});
 assert.equal(r.status,401);
});
test('provider reschedule blocks cross-origin writes',async()=>{
 const r=await worker.fetch(new Request(base+'/api/provider/sessions/'+id+'/reschedule',{method:'POST',headers:{origin:'https://attacker.example','content-type':'application/json'},body:'{}'}),{DB:{}});
 assert.equal(r.status,403);
});
test('provider roster fails closed without D1',async()=>{
 const r=await worker.fetch(new Request(base+'/api/provider/sessions/'+id+'/roster'));
 assert.equal(r.status,503);
});
