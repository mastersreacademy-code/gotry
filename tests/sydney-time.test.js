import test from 'node:test';
import assert from 'node:assert/strict';
import {sydneyToUtc,utcToSydney} from '../src/sydney-time.js';
test('Sydney winter UTC+10',()=>assert.equal(sydneyToUtc('2027-07-01T10:00'),'2027-07-01T00:00:00.000Z'));
test('Sydney summer UTC+11',()=>assert.equal(sydneyToUtc('2027-01-01T10:00'),'2026-12-31T23:00:00.000Z'));
test('reject DST fall-back ambiguous hour',()=>assert.throws(()=>sydneyToUtc('2027-04-04T02:30'),/Ambiguous/));
test('reject DST spring-forward nonexistent hour',()=>assert.throws(()=>sydneyToUtc('2027-10-03T02:30'),/Nonexistent/));
test('Sydney roundtrip',()=>assert.equal(utcToSydney(sydneyToUtc('2027-11-10T15:30')),'2027-11-10T15:30'));
test('reject invalid wall-clock time',()=>assert.throws(()=>sydneyToUtc('2027-02-30T09:00')));
