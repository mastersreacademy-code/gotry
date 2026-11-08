import test from 'node:test';
import assert from 'node:assert/strict';
import worker from '../src/index.js';

const base='https://gotry.example';
const id='00000000-0000-4000-8000-000000000001';
const endpoints=[
 '/api/parent/interests',
 '/api/parent/interests/'+id+'/transfer',
 '/api/parent/interests/'+id+'/withdraw',
 '/api/provider/sessions/'+id+'/cancel',
 '/api/provider/sessions/'+id+'/reschedule',
 '/api/admin/sessions/'+id+'/decision'
];

for(const path of endpoints){
 test('unauthenticated write denied: '+path,async()=>{
  const response=await worker.fetch(new Request(base+path,{method:'POST',headers:{origin:base,'content-type':'application/json'},body:'{}'}),{DB:{}});
  assert.equal(response.status,401);
 });
 test('cross-origin write denied: '+path,async()=>{
  const response=await worker.fetch(new Request(base+path,{method:'POST',headers:{origin:'https://attacker.example','content-type':'application/json'},body:'{}'}),{DB:{}});
  assert.equal(response.status,403);
 });
}
test('provider roster denies anonymous access',async()=>{
 const response=await worker.fetch(new Request(base+'/api/provider/sessions/'+id+'/roster'),{DB:{}});
 assert.equal(response.status,401);
});
