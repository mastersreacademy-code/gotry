import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFileSync} from 'node:fs';
import {formatAbn, formatWebsite} from '../public/js/business-details.mjs';

test('business formatting accepts ABN spaces and preserves explicit website schemes', () => {
    assert.equal(formatAbn('51824753556'), '51 824 753 556');
    assert.equal(formatAbn('51 824 753 556'), '51 824 753 556');
    assert.equal(formatAbn('invalid'), 'invalid');
    assert.equal(formatWebsite('tennis.com.au'), 'https://www.tennis.com.au');
    assert.equal(formatWebsite('www.tennis.com.au'), 'https://www.tennis.com.au');
    assert.equal(formatWebsite('https://lessons.example.com/path'), 'https://lessons.example.com/path');
    assert.equal(formatWebsite('http://example.com'), 'http://example.com');
});

test('selected child registration blocks duplicates while leaving sibling signup available', () => {
    const handlers = {};
    const button = {dataset: {intent: 'register'}};
    const option = {dataset: {registrationStatus: 'interested'}};
    const child = {value: '1', selectedOptions: [option], addEventListener(name, fn) {handlers[name] = fn;}};
    const status = {};
    const link = {};
    const label = {};
    const form = {
        querySelector(selector) {return ({'#child_id': child, '[data-child-registration-status]': status})[selector];},
        querySelectorAll() {return [button];},
    };
    const cta = {querySelector(selector) {return selector === 'a' ? link : label;}};
    vm.runInNewContext(readFileSync(new URL('../public/js/trial-selection.mjs', import.meta.url), 'utf8'), {
        document: {querySelector(selector) {return selector === '[data-trial-form]' ? form : selector === '[data-registration-cta]' ? cta : null;}},
        window: {addEventListener() {}},
    });
    assert.equal(button.disabled, true);
    assert.equal(link.hidden, true);
    assert.equal(label.hidden, false);
    option.dataset.registrationStatus = '';
    child.value = '2';
    handlers.change();
    assert.equal(button.disabled, false);
    assert.equal(link.hidden, false);
    assert.equal(label.hidden, true);
});

test('All shows populated sections and Waitlist isolates its entries', () => {
    const hashes = ['#all-registrations', '#registered-interest', '#waitlist'];
    const tabs = hashes.map(hash => ({
        hash, events: {}, setAttribute() {},
        addEventListener(name, fn) {this.events[name] = fn;},
        click() {this.events.click({preventDefault() {}});}, focus() {},
    }));
    const panels = [
        {id: 'registered-interest', dataset: {entryCount: '2'}, setAttribute() {}},
        {id: 'waitlist', dataset: {entryCount: '1'}, setAttribute() {}},
        {id: 'confirmed', dataset: {entryCount: '0'}, setAttribute() {}},
    ];
    const navigation = {querySelectorAll() {return tabs;}, setAttribute() {}};
    const location = {hash: ''};
    vm.runInNewContext(readFileSync(new URL('../public/js/parent-tabs.js', import.meta.url), 'utf8'), {
        document: {querySelector() {return navigation;}, querySelectorAll() {return panels;}},
        location, history: {replaceState(a, b, hash) {location.hash = hash;}},
        window: {addEventListener() {}},
    });
    assert.equal(panels[0].hidden, false);
    assert.equal(panels[1].hidden, false);
    assert.equal(panels[2].hidden, true);
    tabs[2].click();
    assert.equal(panels[0].hidden, true);
    assert.equal(panels[1].hidden, false);
});
