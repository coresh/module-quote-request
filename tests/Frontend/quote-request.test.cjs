'use strict';
const fs = require('fs');
const vm = require('vm');
const assert = require('assert').strict;
const modulePath = process.argv[2] || require('path').resolve(__dirname, '../../view/frontend/web/js/quote-request.js');
const events = new Map();
const fieldNames = ['product_id', 'form_key', 'request_token', 'customer_name', 'customer_email', 'customer_telephone', 'quantity', 'message', 'honeypot'];
const fields = Object.fromEntries(fieldNames.map(name => [name, {name, value: name === 'quantity' ? '1' : '', attrs: {}, props: {}, classes: new Set()}]));
const response = {value: '', attrs: {}, props: {}, classes: new Set()};
const submit = {value: '', attrs: {}, props: {}, classes: new Set()};
const productName = {value: '', attrs: {}, props: {}, classes: new Set()};
const form = {attrs: {}, props: {}, classes: new Set(), children: fields,
    reportValidity: () => true,
    reset: () => fieldNames.forEach(name => fields[name].value = name === 'quantity' ? '1' : '')};
const element = {attrs: {hidden: ''}, props: {}, classes: new Set(), privateData: {}, form};
const document = {};
let currentPrivateProfile = {name: '', email: ''};
let cookie = 'new-session-form-key';
let randomSequence = 0;
let moduleFactory;
let requestQueue = [];
let cachedPiiReads = 0;
let profileReloads = 0;
let deferProfiles = false;
const pendingProfiles = [];

class Wrapper {
    constructor(node) {this.node = node; this[0] = node;}
    find(selector) {
        if (this.node === element) {
            return new Wrapper({'[data-role="quote-form"]': form, '[data-role="response"]': response,
                '[data-role="submit"]': submit, '[data-role="product-name"]': productName}[selector]);
        }
        if (this.node === form) {
            const name = selector.match(/^\[name="([^"]+)"\]$/);
            return new Wrapper(name ? fields[name[1]] : undefined);
        }
        return new EmptyWrapper();
    }
    data(name, value) {
        if (!this.node.privateData) this.node.privateData = {};
        if (value === undefined) return this.node.privateData[name];
        this.node.privateData[name] = value; return this;
    }
    prop(name, value) {
        if (value === undefined) return this.node.props[name];
        this.node.props[name] = value; return this;
    }
    val(value) {
        if (value === undefined) return this.node ? this.node.value : undefined;
        this.node.value = value; return this;
    }
    text(value) {this.node.value = String(value); return this;}
    toggleClass(name, value) {if (value) this.node.classes.add(name); else this.node.classes.delete(name); return this;}
    addClass(name) {this.node.classes.add(name); return this;}
    attr(name) {return this.node.attrs[name];}
    removeAttr(name) {delete this.node.attrs[name]; return this;}
    modal(action) {this.node.modalAction = action; return this;}
    on(name, selector, callback) {
        if (typeof selector === 'function') {callback = selector; selector = null;}
        events.set(name + (selector || ''), callback); return this;
    }
    each(callback) {callback.call(this.node, 0, this.node); return this;}
    closest() {return new EmptyWrapper();}
}
class EmptyWrapper extends Wrapper {
    constructor() {super(undefined);}
    each() {return this;}
    addClass() {return this;}
    find() {return this;}
    val() {return undefined;}
}
function $(node) {
    if (typeof node === 'string') return new EmptyWrapper();
    return new Wrapper(node);
}
$.each = (object, callback) => Object.keys(object).forEach(key => callback(key, object[key]));
$.mage = {cookies: {get: () => cookie}};
$.ajax = options => {
    const entry = {options, handlers: {}};
    requestQueue.push(entry);
    const chain = {};
    ['done', 'fail', 'always'].forEach(name => chain[name] = callback => {entry.handlers[name] = callback; return chain;});
    return chain;
};
const customerData = {
    get: () => {cachedPiiReads++; return () => ({name: 'Stale prior account', email: 'stale@example.invalid'});},
    reload: () => {
        profileReloads++;
        return {done: callback => {
            if (deferProfiles) pendingProfiles.push(callback);
            else callback({'coresh-quote-request-customer': currentPrivateProfile});
        }};
    }
};
const sandbox = {define: (deps, factory) => {moduleFactory = factory($, () => {}, customerData, text => text);},
    window: {crypto: {getRandomValues: bytes => {randomSequence++; bytes.fill(randomSequence);}}},
    document, Uint8Array, JSON, Array, Boolean, String, Error};
vm.runInNewContext(fs.readFileSync(modulePath, 'utf8'), sandbox, {filename: modulePath});
moduleFactory({submitUrl: '/quoterequest/request/submit', disablePurchasing: false}, element);
const click = events.get('click.coreshQuote[data-coresh-quote]');
const send = events.get('submit');
const clickProduct = id => click.call({attrs: {'data-coresh-quote': JSON.stringify({productId: id, productName: 'Product ' + id})}}, {preventDefault() {}});
const fill = () => {fields.customer_name.value = 'Requester'; fields.customer_email.value = 'requester@example.invalid'; fields.message.value = 'A quote, please.';};
const finish = (kind, result) => {const entry = requestQueue.at(-1); entry.handlers[kind](result); entry.handlers.always();};
const testResults = [];
function test(name, fn) {fn(); testResults.push(name);}

test('current private profile is fetched without reading stale cached PII', () => {
    currentPrivateProfile = {name: 'Current customer', email: 'current@example.invalid'};
    clickProduct(42);
    assert.equal(fields.customer_email.value, 'current@example.invalid');
    assert.equal(cachedPiiReads, 0);
    assert.equal(profileReloads, 1);
});
test('logout clears previous untouched auto-fill; user-entered fields preserved', () => {
    fields.customer_name.value = 'Manual name';
    currentPrivateProfile = {name: '', email: ''};
    clickProduct(42);
    assert.equal(fields.customer_email.value, '');
    assert.equal(fields.customer_name.value, 'Manual name');
});
test('submission contains runtime form key, product ID, quantity, and secure 64hex token', () => {
    fill(); send({preventDefault() {}});
    const data = requestQueue.at(-1).options.data;
    assert.equal(data.product_id, '42');
    assert.equal(data.quantity, '1');
    assert.equal(data.form_key, cookie);
    assert.match(data.request_token, /^[0-9a-f]{64}$/);
    assert.equal(fields.request_token.value, data.request_token);
    assert.equal(submit.props.disabled, true);
});
test('double click while in-flight does not start another HTTP request', () => {
    const count = requestQueue.length;
    send({preventDefault() {}});
    assert.equal(requestQueue.length, count);
});
let retryToken = requestQueue.at(-1).options.data.request_token;
test('network failure keeps same idempotency token for unchanged retry', () => {
    finish('fail', {status: 0});
    send({preventDefault() {}});
    assert.equal(requestQueue.at(-1).options.data.request_token, retryToken);
    finish('fail', {status: 0});
});
test('CSRF cookie change does not change the payload fingerprint token', () => {
    cookie = 'refreshed-form-key';
    send({preventDefault() {}});
    assert.equal(requestQueue.at(-1).options.data.request_token, retryToken);
    assert.equal(requestQueue.at(-1).options.data.form_key, cookie);
    finish('fail', {status: 0});
});
test('edited message generates new token, keeping retry independent', () => {
    fields.message.value = 'A revised quote request.';
    send({preventDefault() {}});
    assert.notEqual(requestQueue.at(-1).options.data.request_token, retryToken);
    retryToken = requestQueue.at(-1).options.data.request_token;
    finish('fail', {status: 0});
});
test('HTTP 400 application failure renders safe text, not HTML', () => {
    send({preventDefault() {}});
    finish('fail', {status: 400, responseJSON: {message: '<img src=x onerror=alert(1)>'}});
    assert.equal(response.value, '<img src=x onerror=alert(1)>');
    assert.equal(response.classes.has('error'), true);
});
test('product switch resets quantity and rotates idempotency token', () => {
    fields.quantity.value = '7';
    clickProduct(43);
    assert.equal(fields.quantity.value, '1');
    fill(); send({preventDefault() {}});
    assert.equal(requestQueue.at(-1).options.data.product_id, '43');
    assert.notEqual(requestQueue.at(-1).options.data.request_token, retryToken);
});
test('accepted request shows response as text, resets token, and hides form', () => {
    finish('done', {success: true, message: '<b>Accepted</b>'});
    assert.equal(response.value, '<b>Accepted</b>');
    assert.equal(form.props.hidden, true);
    assert.equal(fields.request_token.value, '');
});
test('new request for same product after success receives fresh token', () => {
    const previous = requestQueue.at(-1).options.data.request_token;
    clickProduct(43); fill(); send({preventDefault() {}});
    assert.notEqual(requestQueue.at(-1).options.data.request_token, previous);
    finish('fail', {status: 0});
});
test('missing runtime form key prevents HTTP submission', () => {
    cookie = null;
    const count = requestQueue.length;
    send({preventDefault() {}});
    assert.equal(requestQueue.length, count);
    assert.equal(response.value, 'Your session has expired. Please reload this page and try again.');
});
test('out-of-order profile reload cannot replace the latest product profile', () => {
    deferProfiles = true;
    clickProduct(44);
    clickProduct(45);
    const older = pendingProfiles.shift(), latest = pendingProfiles.shift();
    latest({'coresh-quote-request-customer': {name: 'Current profile', email: 'current@example.invalid'}});
    older({'coresh-quote-request-customer': {name: 'Earlier profile', email: 'earlier@example.invalid'}});
    assert.equal(fields.product_id.value, 45);
    assert.equal(fields.customer_name.value, 'Current profile');
    assert.equal(fields.customer_email.value, 'current@example.invalid');
});
test('a stale product reload arriving before the latest response cannot prefill the new form', () => {
    clickProduct(46);
    clickProduct(47);
    const older = pendingProfiles.shift(), latest = pendingProfiles.shift();
    older({'coresh-quote-request-customer': {name: 'Earlier profile', email: 'earlier@example.invalid'}});
    assert.equal(fields.customer_name.value, '');
    assert.equal(fields.customer_email.value, '');
    latest({'coresh-quote-request-customer': {name: 'Current profile', email: 'current@example.invalid'}});
    assert.equal(fields.customer_email.value, 'current@example.invalid');
});
test('a delayed current profile response preserves manually entered fields', () => {
    clickProduct(47);
    fields.customer_name.value = 'Manual name';
    fields.customer_email.value = 'manual@example.invalid';
    pendingProfiles.shift()({'coresh-quote-request-customer': {name: 'Current profile', email: 'current@example.invalid'}});
    assert.equal(fields.customer_name.value, 'Manual name');
    assert.equal(fields.customer_email.value, 'manual@example.invalid');
    deferProfiles = false;
});
let adminFactory, adminSubmit, adminDisabled = false;
const adminJquery = () => ({
    on: (event, callback) => {adminSubmit = callback;},
    find: () => ({prop: (name, value) => {assert.equal(name, 'disabled'); adminDisabled = value;}})
});
vm.runInNewContext(fs.readFileSync(modulePath.replace('/frontend/web/js/quote-request.js', '/adminhtml/web/js/submit-once.js'), 'utf8'), {
    define: (deps, factory) => {adminFactory = factory(adminJquery);}
});
adminFactory({}, {});
test('admin first submit disables resend/save button without cancelling POST', () => {
    let cancelled = false;
    adminSubmit({preventDefault() {cancelled = true;}});
    assert.equal(adminDisabled, true);
    assert.equal(cancelled, false);
});
test('admin accidental second submit is cancelled', () => {
    let cancelled = false;
    adminSubmit({preventDefault() {cancelled = true;}});
    assert.equal(cancelled, true);
});
console.log(JSON.stringify({suite: 'Frontend AMD isolated DOM doubles', passed: testResults.length, failed: 0, tests: testResults}, null, 2));
