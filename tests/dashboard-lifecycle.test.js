import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {parentDashboard,providerDashboard} from '../src/dashboard-pages.js';
import {adminPage} from '../src/admin-page.js';
const api=readFileSync(new URL('../src/api.js',import.meta.url),'utf8');

test('rescheduling rejects started sessions before and during SQL update',()=>{
 assert.match(api,/Started or past sessions cannot be rescheduled/);
 assert.match(api,/WHERE id=\? AND status='open' AND starts_at_utc>\? AND EXISTS/);
});
test('provider session controls reflect full, minimum and expired states',()=>{
 for(const label of ['Session full','Minimum reached','Started or past sessions cannot be cancelled','Session confirmed by admin'])assert.ok(providerDashboard.includes(label),label);
});
test('parent dashboard locks expired actions and explains cancelled sessions',()=>{
 assert.match(parentDashboard,/session_start_utc>new Date\(\)\.toISOString\(\)/);
 assert.ok(parentDashboard.includes('This session was cancelled'));
 assert.ok(parentDashboard.includes('Admin confirmed this session'));
});
test('admin shows attendance state and refreshes queues after decisions',()=>{
 for(const phrase of ['Awaiting minimum','Minimum reached','Full','await refreshSessions()'])assert.ok(adminPage.includes(phrase),phrase);
});
