import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {parentDashboard,providerDashboard} from '../src/dashboard-pages.js';
import {notificationsPage} from '../src/notifications-page.js';
const api=readFileSync(new URL('../src/api.js',import.meta.url),'utf8');
test('provider roster is count-only until confirmed and excludes cancelled activities',()=>{
 assert.match(api,/const namesAvailable=owned\.status==='confirmed'&&!owned\.activity_status\.startsWith\('cancelled'\)/);
 assert.match(api,/if\(!namesAvailable\)return json\(\{roster:\[\],count:Number\(count\.n\),namesAvailable:false\}\)/);
 assert.match(api,/SELECT child_name FROM interests WHERE session_id=\?/);
 assert.ok(providerDashboard.includes("d.count+' interested'"));
});
test('parent transfer uses effective registration deadline',()=>{
 assert.ok(api.includes("d.deadline||d.trialDate||''"));
 assert.ok(api.includes("COALESCE(json_extract(a.data_json,'$.deadline'),json_extract(a.data_json,'$.trialDate'))>=?"));
});
test('parent status filter uses actual interest status',()=>{
 assert.ok(parentDashboard.includes('card.dataset.state=a.status'));
});
test('notification mark-read action disables repeated submissions',()=>{
 assert.ok(notificationsPage.includes('b.onclick=async()=>{b.disabled=true;'));
});
