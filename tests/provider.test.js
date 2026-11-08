import test from 'node:test';
import assert from 'node:assert/strict';
import worker from '../src/index.js';
import {steps,validateStep,safeDraft} from '../src/wizard.js';
test('provider wizard has five steps',()=>assert.deepEqual(steps,['Business','Activity','Trial Lesson','Regular Lessons','Review']));
test('business requires identity and valid email',()=>{assert.ok(validateStep(0,{}).email);assert.ok(validateStep(0,{businessName:'Studio',contactName:'Alex',email:'invalid'}).email);assert.deepEqual(validateStep(0,{businessName:'Studio',contactName:'Alex',email:'alex@example.com'}),{});});
test('group trial validates min and max',()=>{assert.ok(validateStep(2,{trialType:'group',trialDate:'2026-12-01',minParticipants:'6',maxParticipants:'4'}).maxParticipants);assert.deepEqual(validateStep(2,{trialType:'group',trialDate:'2026-12-01',minParticipants:'6',maxParticipants:'10'}),{});});
test('only known fields retained in local draft',()=>{const d=safeDraft({businessName:'Studio',admin:true,description:'a'.repeat(4000)});assert.equal(d.admin,undefined);assert.equal(d.description.length,3000);});
test('wizard page is accessible and not indexed',async()=>{const r=await worker.fetch(new Request('https://gotry.example/provider/new'));const html=await r.text();assert.equal(r.status,200);assert.match(html,/Create an activity/);assert.match(html,/Trial Lesson/);assert.equal(r.headers.get('x-robots-tag'),'noindex, nofollow');});
