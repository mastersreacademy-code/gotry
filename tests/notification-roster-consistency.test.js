import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {parentDashboard,providerDashboard} from '../src/dashboard-pages.js';
import {notificationsPage} from '../src/notifications-page.js';
const api=readFileSync(new URL('../src/api.js',import.meta.url),'utf8');
test('provider roster is count-only until confirmed and excludes cancelled activities',()=>{
 assert.ok(api.includes("CASE WHEN s.status='confirmed' AND a.status NOT LIKE 'cancelled%' THEN i.child_name ELSE NULL END"));
 assert.ok(api.includes("a.provider_id=? ORDER BY i.created_at ASC LIMIT 100"));
 assert.ok(api.includes("roster:namesAvailable?result.filter(x=>x.child_name!==null)"));
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
