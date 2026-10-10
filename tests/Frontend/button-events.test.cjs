'use strict';

// Execute the shipped AMD modules with explicit DOM/jQuery/customer-data doubles.
// This verifies event and response behavior, not a real Magento browser session.
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const assert = require('assert').strict;
const moduleRoot = process.argv[2] || path.resolve(__dirname, '../..');
const adminSource = fs.readFileSync(path.join(moduleRoot, 'view/adminhtml/web/js/submit-once.js'), 'utf8');
const results = [];

const {node, quoteHarness: isolatedQuoteHarness} = require('./support/quote-harness.cjs');
const quoteHarness = (configuration, initialButtons) => isolatedQuoteHarness(configuration, initialButtons, moduleRoot);

function test(name, callback) {callback(); results.push(name);}

test('quote button opens the modal and writes product names as plain text', () => {
    const h = quoteHarness();
    assert.equal(h.click().defaultPrevented, true);
    assert.equal(h.element.modalAction, 'openModal');
    assert.equal(h.element.attrs.hidden, undefined);
    assert.equal(h.fields.product_id.value, 42);
    assert.equal(h.title.value, '<b>Product 42</b>');
    assert.equal(h.form.props.hidden, false);
    assert.equal(h.submit.props.disabled, false);
});

test('malformed or non-object quote context leaves the dialog closed without throwing', () => {
    ['{broken', 'null', '[]', 'false', '17', '"value"', '{}'].forEach(value => {
        const h = quoteHarness();
        assert.doesNotThrow(() => h.click(node({attrs: {'data-coresh-quote': value}})));
        assert.equal(h.element.modalAction, undefined);
        assert.equal(h.form.resets, 0);
        assert.equal(h.requests.length, 0);
    });
});

test('PDP quote button copies quantity and submits only well-formed selected attributes', () => {
    const h = quoteHarness();
    h.pdpOptions.push(node({name: 'super_attribute[12]', value: '5'}),
        node({name: 'super_attribute[13]', value: ''}), node({name: 'super_attribute[bad]', value: '7'}));
    h.click(h.quoteButton(42, {'data-coresh-pdp': '1'})); h.fill(); h.send();
    const data = h.requests[0].options.data;
    assert.equal(h.fields.quantity.value, '2.5');
    assert.equal(data.quantity, '2.5');
    assert.deepEqual(Object.assign({}, data.super_attribute), {'12': '5'});
    assert.equal(h.requests[0].options.method, 'POST');
    assert.equal(h.requests[0].options.dataType, 'json');
    assert.equal(h.requests[0].options.timeout, 30000);
});

test('listing quote button excludes unrelated PDP quantity and configurable selections', () => {
    const h = quoteHarness();
    h.pdpOptions.push(node({name: 'super_attribute[12]', value: '5'}));
    h.click(); h.fill(); h.send();
    assert.equal(h.requests[0].options.data.quantity, '1');
    assert.deepEqual(Object.assign({}, h.requests[0].options.data.super_attribute), {});
});

test('browser-invalid quote form cancels submission without an HTTP request or locked button', () => {
    const h = quoteHarness(); h.click(); h.form.valid = false;
    assert.equal(h.send().defaultPrevented, true);
    assert.equal(h.requests.length, 0);
    assert.equal(h.submit.props.disabled, false);
});

test('product clicks while submitting cannot change the in-flight product or reset entered data', () => {
    const h = quoteHarness(); h.click(); h.fill(); h.send();
    const resets = h.form.resets;
    h.click(h.quoteButton(43)); h.send();
    assert.equal(h.requests.length, 1);
    assert.equal(h.requests[0].options.data.product_id, '42');
    assert.equal(h.fields.product_id.value, 42);
    assert.equal(h.form.resets, resets);
    assert.equal(h.submit.props.disabled, true);
});

test('accepted quote produces a success message, hides and resets modal form, and unlocks submit', () => {
    const h = quoteHarness(); h.click(); h.fill(); h.send(); h.complete('done', {success: true});
    assert.equal(h.response.value, 'Your quote request has been accepted.');
    assert.equal(h.response.props.hidden, false);
    assert.equal(h.response.classes.has('success'), true);
    assert.equal(h.form.props.hidden, true);
    assert.equal(h.fields.message.value, '');
    assert.equal(h.submit.props.disabled, false);
});

test('application rejection preserves input, displays an error as plain text, and unlocks retry', () => {
    const h = quoteHarness(); h.click(); h.fill(); h.send();
    h.complete('done', {success: false, message: '<img src=x onerror=alert(1)>'});
    assert.equal(h.response.value, '<img src=x onerror=alert(1)>');
    assert.equal(h.response.classes.has('error'), true);
    assert.equal(h.form.props.hidden, false);
    assert.equal(h.fields.message.value, 'Please send a quote.');
    assert.equal(h.submit.props.disabled, false);
});

test('empty JSON response produces the documented rejection message and leaves retry available', () => {
    const h = quoteHarness(); h.click(); h.fill(); h.send(); h.complete('done', null);
    assert.equal(h.response.value, 'The request could not be accepted.');
    assert.equal(h.response.classes.has('error'), true);
    assert.equal(h.submit.props.disabled, false);
});

test('HTTP 429 produces the server error message as text and preserves the retry token', () => {
    const h = quoteHarness(); h.click(); h.fill(); h.send();
    const token = h.requests[0].options.data.request_token;
    h.complete('fail', {status: 429, responseJSON: {message: 'Too many requests. Please try again later.'}});
    assert.equal(h.response.value, 'Too many requests. Please try again later.');
    assert.equal(h.submit.props.disabled, false);
    h.send(); assert.equal(h.requests[1].options.data.request_token, token);
});

test('server transport failure uses the connection message and restores submit availability', () => {
    const h = quoteHarness(); h.click(); h.fill(); h.send();
    h.complete('fail', {status: 500, responseJSON: {message: 'Internal database details'}});
    assert.equal(h.response.value, 'The connection failed. Please try again; your request will not be saved twice.');
    assert.equal(h.submit.props.disabled, false);
    assert.equal(h.fields.message.value, 'Please send a quote.');
});

test('unavailable secure randomness prevents a request and displays an actionable message', () => {
    const h = quoteHarness(); h.click(); h.fill(); h.env.cryptoAvailable = false; h.send();
    assert.equal(h.requests.length, 0);
    assert.equal(h.response.value, 'A secure connection is required. Please reload this page using HTTPS.');
    assert.equal(h.submit.props.disabled, false);
});

test('repeated widget initialization does not attach another click or submit handler', () => {
    const h = quoteHarness(); h.initializeAgain(); h.click(); h.fill(); h.send();
    assert.equal(h.document.events.length, 2);
    assert.equal(h.form.events.length, 1);
    assert.equal(h.requests.length, 1);
});

test('purchase suppression skips invalid display context and processes subsequent valid buttons', () => {
    const pdp = node({attrs: {'data-coresh-display': JSON.stringify({suppressPurchase: true}), 'data-coresh-pdp': '1'}});
    const listingPurchase = node();
    const listing = node({attrs: {'data-coresh-quote': JSON.stringify({productId: 42, suppressPurchase: true})},
        productItem: node({find: {'.action.tocart, .actions-primary': [listingPurchase]}})});
    const h = quoteHarness({}, [node({attrs: {'data-coresh-display': 'null'}}), pdp, listing]);
    assert.equal(h.pdpCart.classes.has('coresh-quote-purchase-suppressed'), true);
    assert.equal(listingPurchase.classes.has('coresh-quote-purchase-suppressed'), true);
});

test('contentUpdated reapplies suppression for dynamically inserted quote products', () => {
    const h = quoteHarness({disablePurchasing: true});
    assert.equal(h.globalPurchase.classes.has('coresh-quote-purchase-suppressed'), true);
    const listingPurchase = node();
    h.buttons.push(node({attrs: {'data-coresh-quote': JSON.stringify({productId: 43, suppressPurchase: true})},
        productItem: node({find: {'.action.tocart, .actions-primary': [listingPurchase]}})}));
    h.update();
    assert.equal(listingPurchase.classes.has('coresh-quote-purchase-suppressed'), true);
});

function adminHarness() {
    let factory;
    const $ = item => ({
        on: (event, callback) => {assert.equal(event, 'submit.coreshQuoteAdmin'); item.handler = callback;},
        find: selector => ({prop: (name, value) => {
            assert.equal(selector, 'button[type="submit"]'); assert.equal(name, 'disabled'); item.disabled = value;
        }})
    });
    vm.runInNewContext(adminSource, {define: (dependencies, create) => {factory = create($);}}, {filename: 'submit-once.js'});
    const element = {disabled: false}; factory({}, element);
    const submit = (prevented = false, nativePrevented = false) => {
        const event = {defaultPrevented: nativePrevented, isDefaultPrevented: () => prevented,
            preventDefault() {this.defaultPrevented = true;}};
        element.handler(event); return event;
    };
    return {element, submit};
}

test('admin submission previously cancelled by jQuery validation leaves buttons and retry available', () => {
    const h = adminHarness(); h.submit(true);
    assert.equal(h.element.disabled, false);
    assert.equal(h.submit().defaultPrevented, false);
    assert.equal(h.element.disabled, true);
});

test('admin submission previously cancelled by a native listener leaves retry available', () => {
    const h = adminHarness(); h.submit(false, true);
    assert.equal(h.element.disabled, false);
    assert.equal(h.submit().defaultPrevented, false);
    assert.equal(h.element.disabled, true);
});

test('admin accepted first submit leaves native POST enabled and suppresses repeated button submits', () => {
    const h = adminHarness();
    assert.equal(h.submit().defaultPrevented, false);
    assert.equal(h.element.disabled, true);
    assert.equal(h.submit().defaultPrevented, true);
    assert.equal(h.submit().defaultPrevented, true);
});

test('admin status-save and resend forms maintain separate submission guards', () => {
    const status = adminHarness(), resend = adminHarness();
    status.submit();
    assert.equal(resend.element.disabled, false);
    assert.equal(resend.submit().defaultPrevented, false);
    assert.equal(status.submit().defaultPrevented, true);
    assert.equal(resend.submit().defaultPrevented, true);
});

console.log(JSON.stringify({suite: 'Quote and admin button event/output behavior with isolated DOM doubles',
    passed: results.length, failed: 0, tests: results}, null, 2));
