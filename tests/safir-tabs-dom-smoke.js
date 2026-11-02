#!/usr/bin/env node
// Native Node-only DOM contract. No browser, network, packages or sending.
'use strict';
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public_html/resources/views/admin/partials/bale-safir-campaign.php', 'utf8');
const blocks = Array.from(source.matchAll(/<script>([\s\S]*?)<\/script>/g), match => match[1]);
if (blocks.length !== 1) throw Error('SAFIR_JS_BLOCK_COUNT');
const callbacks = {};
const tabKeys = ['identity', 'variables', 'message'];
function makeTab(key) {
    const attributes = {};
    const classes = new Set(key === 'identity' ? ['is-active'] : []);
    return {
        dataset: { safirTab: key },
        classList: { toggle: (name, active) => active ? classes.add(name) : classes.delete(name),
                     contains: name => classes.has(name) },
        setAttribute(name, value) { attributes[name] = value; },
        getAttribute(name) { return attributes[name]; },
        addEventListener(name, listener) { callbacks[key + ':' + name] = listener; }
    };
}
const tabs = tabKeys.map(makeTab);
const panels = tabKeys.map((key, index) => ({ dataset: { safirPanel: key }, hidden: index !== 0 }));
const noNodes = { querySelectorAll: () => [], addEventListener: () => {}, append: () => {} };
const body = { value: '', addEventListener: () => {} };
const count = { textContent: '' };
const add = { disabled: false, addEventListener: () => {} };
const preview = { textContent: '' };
const form = {
    querySelectorAll(selector) {
        return selector === '[data-safir-tab]' ? tabs
            : selector === '[data-safir-panel]' ? panels : [];
    },
    querySelector(selector) {
        return ({
            '[data-safir-variables]': noNodes,
            '[data-safir-body]': body,
            '[data-safir-count]': count,
            '[data-safir-add]': add,
            '[data-safir-sample]': preview,
        })[selector] ?? null;
    },
    addEventListener() {}
};
const document = {
    querySelectorAll: () => [],
    querySelector(selector) {
        if (selector === '[data-safir-editor]') return form;
        if (selector === '[data-safir-manual-test]') return null;
        return null;
    },
    getElementById() { return null; }
};
vm.runInNewContext(blocks[0], { document, window: {}, console }, { timeout: 3000 });
if (typeof callbacks['variables:click'] !== 'function' ||
    typeof callbacks['message:click'] !== 'function') throw Error('SAFIR_TAB_HANDLERS_NOT_REGISTERED');
for (const key of tabKeys) {
    callbacks[key + ':click']();
    for (const panel of panels) {
        if (panel.hidden !== (panel.dataset.safirPanel !== key)) {
            throw Error('SAFIR_TAB_PANEL_NOT_TOGGLED:' + key);
        }
    }
    const selected = tabs.filter(tab => tab.getAttribute('aria-selected') === 'true');
    if (selected.length !== 1 || selected[0].dataset.safirTab !== key) {
        throw Error('SAFIR_TAB_ARIA_STATE_INVALID:' + key);
    }
}
console.log('SAFIR_TAB_CLICK_RUNTIME=PASS');
console.log('SAFIR_TAB_NAVIGATION_REQUIRES_NO_FIELD_VALUES=PASS');
