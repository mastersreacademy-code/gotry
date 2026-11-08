import test from 'node:test';
import assert from 'node:assert/strict';
import worker from '../src/index.js';
const base='https://gotry.example';
test('public session endpoint requires D1 binding',async()=>{
 const r=await worker.fetch(new Request(base+'/api/trial-sessions?activityId=00000000-0000-4000-8000-000000000001'));
 assert.equal(r.status,503);
});
test('public session lookup validates activity id',async()=>{
 const r=await worker.fetch(new Request(base+'/api/trial-sessions?activityId=bad'),{DB:{}});
 assert.equal(r.status,400);
});
test('provider session creation requires authentication',async()=>{
 const r=await worker.fetch(new Request(base+'/api/provider/activities/00000000-0000-4000-8000-000000000001/sessions',{method:'POST',headers:{origin:base,'content-type':'application/json'},body:'{}'}),{DB:{}});
 assert.equal(r.status,401);
});
test('admin session decisions reject cross-origin writes',async()=>{
 const r=await worker.fetch(new Request(base+'/api/admin/sessions/00000000-0000-4000-8000-000000000001/decision',{method:'POST',headers:{origin:'https://attacker.example','content-type':'application/json'},body:'{}'}),{DB:{}});
 assert.equal(r.status,403);
});
test('admin session decisions reject unauthenticated writes',async()=>{
 const r=await worker.fetch(new Request(base+'/api/admin/sessions/00000000-0000-4000-8000-000000000001/decision',{method:'POST',headers:{origin:base,'content-type':'application/json'},body:'{}'}),{DB:{}});
 assert.equal(r.status,401);
});
