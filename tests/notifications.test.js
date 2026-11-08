import test from 'node:test';
import assert from 'node:assert/strict';
import worker from '../src/index.js';
const base='https://gotry.example';
test('notifications page is noindexed and navigable',async()=>{
 const r=await worker.fetch(new Request(base+'/notifications'));
 assert.equal(r.status,200);
 assert.match(r.headers.get('x-robots-tag'),/noindex/);
 const html=await r.text();
 assert.match(html,/Mark as read/);
 assert.match(html,/\/api\/notifications/);
});
test('notification API fails closed when D1 is not bound',async()=>{
 const r=await worker.fetch(new Request(base+'/api/notifications'));
 assert.equal(r.status,503);
});
test('notification API rejects unauthenticated users',async()=>{
 const r=await worker.fetch(new Request(base+'/api/notifications'),{DB:{}});
 assert.equal(r.status,401);
});
test('notification write is protected from cross-origin requests',async()=>{
 const r=await worker.fetch(new Request(base+'/api/notifications/00000000-0000-4000-8000-000000000001/read',{method:'POST',headers:{origin:'https://other.example','content-type':'application/json'},body:'{}'}),{DB:{}});
 assert.equal(r.status,403);
});
test('notification write requires authentication',async()=>{
 const r=await worker.fetch(new Request(base+'/api/notifications/00000000-0000-4000-8000-000000000001/read',{method:'POST',headers:{origin:base,'content-type':'application/json'},body:'{}'}),{DB:{}});
 assert.equal(r.status,401);
});
