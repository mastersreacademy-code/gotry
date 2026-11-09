import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {activitiesPage} from '../src/activities-page.js';
import {adminPage} from '../src/admin-page.js';
const api=readFileSync(new URL('../src/api.js',import.meta.url),'utf8');
test('trial discovery enforces effective Sydney registration deadline',()=>{
 assert.match(api,/COALESCE\(json_extract\(a\.data_json,'\$\.deadline'\),json_extract\(a\.data_json,'\$\.trialDate'\)\)>=\?/);
 assert.match(api,/registrationClosed:/);
 assert.match(api,/utcToSydney\(new Date\(\)\.toISOString\(\)\)\.slice\(0,10\)/);
});
test('Explore distinguishes closed, full and unavailable sessions',()=>{
 for(const phrase of ['Registration closed','No upcoming sessions','All sessions full','View registration in Parent dashboard'])assert.ok(activitiesPage.includes(phrase),phrase);
});
test('Admin cancellation uses registration deadline',()=>{
 assert.match(adminPage,/a\.trial\.deadline>=sydneyToday\(\)/);
});
