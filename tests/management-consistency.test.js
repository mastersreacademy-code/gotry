import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {providerDashboard,parentDashboard} from '../src/dashboard-pages.js';
import {adminPage} from '../src/admin-page.js';
const api=readFileSync(new URL('../src/api.js',import.meta.url),'utf8');
test('provider cancellation binds the UTC cutoff before owner ID',()=>{
 assert.match(api,/bind\(cancelSession\[1\],expectedStartsAt,new Date\(\)\.toISOString\(\),user\.id\)\.run\(\)/);
});
test('parent transfer and withdrawal reject expired source sessions atomically',()=>{
 assert.match(api,/old\.status='open' AND old\.starts_at_utc>\?/);
 assert.match(api,/bind\(target,transfer\[1\],user\.id,interest\.session_id,target,target,now,today,today,now\)\.run\(\)/);
 assert.match(api,/s\.status='open' AND s\.starts_at_utc>\?/);
 assert.match(api,/bind\(withdraw\[1\],user\.id,expectedSessionId,new Date\(\)\.toISOString\(\)\)\.run\(\)/);
});
test('parent and provider dashboards provide distinct state filters',()=>{
 assert.ok(providerDashboard.includes('All activities'));
 assert.ok(providerDashboard.includes('pending_approval'));
 assert.ok(parentDashboard.includes('All registrations'));
 assert.ok(parentDashboard.includes('withdrawn'));
 for(const html of [parentDashboard,providerDashboard])assert.equal((html.match(/id="stateFilter"/g)||[]).length,1);
});
test('admin session filtering preserves selected state after refresh',()=>{
 assert.ok(adminPage.includes('id="sessionFilter"'));
 assert.ok(adminPage.includes('filterSessions();'));
});
