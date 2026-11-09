import test from 'node:test';
import assert from 'node:assert/strict';
import worker from '../src/index.js';
test('health endpoint provides status with no-store', async () => {
 const r = await worker.fetch(new Request('https://gotry.example/health'));
 assert.equal(r.status,200); assert.equal(r.headers.get('cache-control'),'no-store');
 assert.deepEqual(await r.json(),{app:'gotry',status:'ok',version:'0.1.0'});
});
test('homepage explains trial flow without collecting payments', async () => {
 const r = await worker.fetch(new Request('https://gotry.example/'));
 const html=await r.text();
 assert.equal(r.status,200); assert.match(r.headers.get('content-type'),/text\/html/);
 assert.equal(r.headers.get('x-robots-tag'),'noindex, nofollow');
 assert.match(html,/Try before you enrol/i);
 assert.match(html,/Register interest/i);
 assert.match(html,/no live bookings or payments/i);
});
test('unknown route returns 404', async () => {
 const r = await worker.fetch(new Request('https://gotry.example/unknown'));
 assert.equal(r.status,404);
});
