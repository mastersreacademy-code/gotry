import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {parentDashboard,providerDashboard} from '../src/dashboard-pages.js';
const api=readFileSync(new URL('../src/api.js',import.meta.url),'utf8');
const route=(start,end)=>api.slice(api.indexOf(start),api.indexOf(end,api.indexOf(start)));
test('transfer only succeeds from the session shown to the parent',()=>{
 const code=route('const transfer=path.match','const withdraw=path.match');
 assert.match(code,/WHERE id=\? AND parent_id=\? AND status='interested' AND session_id=\?/);
 assert.match(code,/bind\(target,transfer\[1\],user\.id,interest\.session_id,target,target,now,today,today,now\)/);
});
test('withdrawal is conditional on the expected session',()=>{
 const code=route('const withdraw=path.match','const providerSessions=path.match');
 assert.match(code,/session_id IS \?/);
 assert.match(code,/bind\(withdraw\[1\],user\.id,expectedSessionId,new Date\(\)\.toISOString\(\)\)/);
 assert.ok(parentDashboard.includes('expectedSessionId:a.session_id||null'));
});
test('provider cancel and reschedule reject stale schedules',()=>{
 const cancel=route('const cancelSession=path.match','const reschedule=path.match');
 const reschedule=route('const reschedule=path.match',"if(path==='/api/provider/activities'&&method==='POST')");
 for(const code of [cancel,reschedule])assert.match(code,/status='open' AND starts_at=\? AND starts_at_utc>\?/);
 assert.match(cancel,/bind\(cancelSession\[1\],expectedStartsAt,new Date\(\)\.toISOString\(\),user\.id\)/);
 assert.match(reschedule,/bind\(start,end,startUtc,endUtc,venue,reschedule\[1\],expectedStartsAt,new Date\(\)\.toISOString\(\),user\.id\)/);
 assert.ok(providerDashboard.includes('expectedStartsAt:s.starts_at,expectedEndsAt:s.ends_at,expectedVenue:s.venue'));
});
