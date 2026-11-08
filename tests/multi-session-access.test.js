import test from 'node:test';
import assert from 'node:assert/strict';
import worker from '../src/index.js';
const base='https://gotry.example';
const id='00000000-0000-4000-8000-000000000001';
test('session transfer requires authentication',async()=>{
 const r=await worker.fetch(new Request(base+'/api/parent/interests/'+id+'/transfer',{method:'POST',headers:{origin:base,'content-type':'application/json'},body:JSON.stringify({sessionId:id})}),{DB:{}});
 assert.equal(r.status,401);
});
test('session transfer rejects cross-origin requests',async()=>{
 const r=await worker.fetch(new Request(base+'/api/parent/interests/'+id+'/transfer',{method:'POST',headers:{origin:'https://evil.example','content-type':'application/json'},body:JSON.stringify({sessionId:id})}),{DB:{}});
 assert.equal(r.status,403);
});
test('session transfer fails closed without database',async()=>{
 const r=await worker.fetch(new Request(base+'/api/parent/interests/'+id+'/transfer',{method:'POST',headers:{origin:base,'content-type':'application/json'},body:JSON.stringify({sessionId:id})}));
 assert.equal(r.status,503);
});
