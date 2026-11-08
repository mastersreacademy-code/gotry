import test from 'node:test';
import assert from 'node:assert/strict';
import worker from '../src/index.js';
const origin='https://gotry.example';
const interest='00000000-0000-4000-8000-000000000001';
const session='00000000-0000-4000-8000-000000000002';
for(const action of ['transfer','withdraw']){
 test(action+' rejects unauthenticated parent writes',async()=>{
  const res=await worker.fetch(new Request(origin+'/api/parent/interests/'+interest+'/'+action,{method:'POST',headers:{origin,'content-type':'application/json'},body:JSON.stringify({sessionId:session})}),{DB:{}});
  assert.equal(res.status,401);
 });
 test(action+' rejects cross-site parent writes',async()=>{
  const res=await worker.fetch(new Request(origin+'/api/parent/interests/'+interest+'/'+action,{method:'POST',headers:{origin:'https://attacker.example','content-type':'application/json'},body:JSON.stringify({sessionId:session})}),{DB:{}});
  assert.equal(res.status,403);
 });
}
test('provider roster rejects unauthenticated reads',async()=>{
 const res=await worker.fetch(new Request(origin+'/api/provider/sessions/'+session+'/roster'),{DB:{}});
 assert.equal(res.status,401);
});
test('admin sessions rejects unauthenticated reads',async()=>{
 const res=await worker.fetch(new Request(origin+'/api/admin/sessions'),{DB:{}});
 assert.equal(res.status,401);
});
