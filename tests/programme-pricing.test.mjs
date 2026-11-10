import test from 'node:test';
import assert from 'node:assert/strict';
import {programmePreview} from '../public/js/programme-pricing.mjs';

const group = {regular_format: 'group', lessons_per_term: '10', standard_price: '20', small_group_price: '25', discount_type: 'percent', discount_value: '10', discount_applies: 'term'};
const totals = data => programmePreview(data).map(row => row.total);
test('parent price preview matches reference percentage and fixed term amounts', () => {
    assert.deepEqual(totals(group), [18000, 22500]);
    assert.deepEqual(totals({...group, discount_type: 'fixed'}), [19000, 24000]);
});
test('discount applies once to the first lesson, not every lesson', () => {
    assert.deepEqual(totals({...group, discount_applies: 'lesson'}), [19800, 24750]);
});
test('individual pricing is independent of stale group prices and term count', () => {
    assert.deepEqual(totals({...group, regular_format: 'individual', individual_price: '60', discount_type: 'fixed', discount_applies: 'lesson'}), [5000]);
});
test('no discount and fractional cents remain consistent', () => {
    assert.deepEqual(totals({...group, discount_type: 'none'}), [20000, 25000]);
    assert.deepEqual(totals({...group, standard_price: '0.03', small_group_price: '0.03', discount_value: '0.01'}), [30, 30]);
});
test('invalid or incomplete pricing never shows a misleading price', () => {
    for (const bad of [{discount_value: '101'}, {discount_type: 'fixed', discount_value: '201'}, {standard_price: '20.001'}, {lessons_per_term: ''}, {small_group_price: '-1'}]) {
        assert.deepEqual(programmePreview({...group, ...bad}), []);
    }
});
