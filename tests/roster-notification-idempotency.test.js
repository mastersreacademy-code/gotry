import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
const api=readFileSync(new URL('../src/api.js',import.meta.url),'utf8');
test('notification mark-as-read is idempotent but remains user scoped',()=>{
 assert.ok(api.includes("UPDATE notifications SET read_at=CURRENT_TIMESTAMP WHERE id=? AND user_id=? AND read_at IS NULL"));
 assert.ok(api.includes("SELECT id FROM notifications WHERE id=? AND user_id=?"));
 assert.ok(api.includes("if(!existing)return bad('Notification unavailable',404)"));
});
test('provider roster uses one SQL query to gate private names and count',()=>{
 const route=api.slice(api.indexOf('const roster=path.match'),api.indexOf('const cancelSession=path.match'));
 assert.ok(route.includes("CASE WHEN s.status='confirmed' AND a.status NOT LIKE 'cancelled%' THEN i.child_name ELSE NULL END"));
 assert.ok(route.includes('a.provider_id=?'));
 assert.ok(route.includes("SELECT COUNT(*) FROM interests all_i WHERE all_i.session_id=s.id AND all_i.status='interested'"));
 assert.ok(route.includes("roster:namesAvailable?result.filter(x=>x.child_name!==null)"));
 assert.equal((route.match(/\.all\(\)/g)||[]).length,1);
});
