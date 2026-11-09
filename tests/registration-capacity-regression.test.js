import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';

const api=readFileSync(new URL('../src/api.js',import.meta.url),'utf8');
const registration=api.slice(api.indexOf("if(path==='/api/parent/interests'&&method==='POST')"),api.indexOf('const transfer=path.match'));

test('registration and reactivation both use effective deadline in atomic SQL',()=>{
 assert.equal((registration.match(/COALESCE\(json_extract\(data_json,'\$\.deadline'\),json_extract\(data_json,'\$\.trialDate'\)\)>=\?/g)||[]).length,2);
 assert.ok(registration.includes("const deadline=details.deadline||details.trialDate||"));
});
test('withdrawn interest reactivation matches normalized child names',()=>{
 assert.match(registration,/SELECT id,status,session_id FROM interests WHERE activity_id=\? AND parent_id=\? AND lower\(trim\(child_name\)\)=lower\(trim\(\?\)\)/);
});
test('database capacity and active-child guards remain in migrations',()=>{
 const capacity=readFileSync(new URL('../migrations/0009_session_capacity_guards.sql',import.meta.url),'utf8');
 const uniqueness=readFileSync(new URL('../migrations/0010_active_child_uniqueness.sql',import.meta.url),'utf8');
 for(const label of ['interest_session_capacity_insert','interest_session_capacity_update'])assert.ok(capacity.includes(label));
 for(const label of ['interest_active_child_insert','interest_active_child_update'])assert.ok(uniqueness.includes(label));
});

test('dashboards expose a manual refresh after stale writes',async()=>{
 const {parentDashboard,providerDashboard}=await import('../src/dashboard-pages.js');
 for(const page of [parentDashboard,providerDashboard]){
  assert.ok(page.includes('id="refreshDashboard"'));
  assert.ok(page.includes("addEventListener('click',load)"));
  assert.ok(page.includes('noindex,nofollow'));
 }
});
