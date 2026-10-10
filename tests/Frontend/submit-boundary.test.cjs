'use strict';

// Execute the actual shipped AMD module, capture its $.ajax options, and consume
// responses captured by the PHP controller/service suite. DOM/jQuery/session,
// crypto and AJAX are explicit doubles; these are not live browser/HTTP results.
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const assert = require('assert').strict;
const {node, quoteHarness} = require('./support/quote-harness.cjs');
const moduleRoot = path.resolve(__dirname, '../..');
const fixturePath = path.join(moduleRoot, 'tests/Fixtures/frontend-submit-payloads.json');
const responsesPath = path.join(moduleRoot, 'tests/Fixtures/frontend-submit-responses.json');
const writeFixtures = process.argv.includes('--write-fixtures');
const results = [], captures = [];
const connectionMessage = 'The connection failed. Please try again; your request will not be saved twice.';

function test(name, callback) {callback(); results.push(name);}
function clone(value) {return JSON.parse(JSON.stringify(value));}
function prepared(configuration = {}, buttonAttributes = {}) {
    const h = quoteHarness(configuration, [], moduleRoot);
    h.env.cookie = 'boundary-runtime-form-key';
    if (!configuration.inline) h.click(h.quoteButton(42, buttonAttributes));
    h.fields.customer_name.value = 'Boundary Customer';
    h.fields.customer_email.value = 'boundary@example.invalid';
    h.fields.customer_telephone.value = '+1 202 555 0100';
    h.fields.quantity.value = '2.5';
    h.fields.message.value = 'Please quote this product.';
    return h;
}
function encodePost(post) {
    // This explicit form encoder provides parse_str-compatible captured vectors.
    // It is not represented as an executed jQuery transport implementation.
    const parameters = new URLSearchParams();
    Object.entries(post).forEach(([key, value]) => {
        if (value && typeof value === 'object') {
            Object.entries(value).forEach(([index, item]) => parameters.append(key + '[' + index + ']', item));
        } else {
            parameters.append(key, value);
        }
    });
    return parameters.toString();
}
function capture(id, mode, h) {
    const options = h.requests.at(-1).options, post = clone(options.data);
    captures.push({id, mode, ajax: {url: options.url, method: options.method,
        dataType: options.dataType, timeout: options.timeout}, post, encoded_post: encodePost(post)});
    return post;
}
function assertReady(h) {
    assert.equal(h.submit.props.disabled, false);
    assert.equal(h.response.props.hidden, false);
}

const modal = prepared();
let original, changed;
test('modal submit captures the actual AMD POST fields with current CSRF and generated token', () => {
    const event = modal.send();
    original = capture('modal_first', 'popup_listing', modal);
    assert.equal(event.defaultPrevented, true);
    assert.deepEqual(Object.keys(original), ['product_id', 'quantity', 'customer_name', 'customer_email',
        'customer_telephone', 'message', 'honeypot', 'super_attribute', 'form_key', 'request_token']);
    assert.equal(original.product_id, '42');
    assert.equal(original.quantity, '2.5');
    assert.equal(original.form_key, 'boundary-runtime-form-key');
    assert.match(original.request_token, /^[a-f0-9]{64}$/);
    assert.deepEqual(original.super_attribute, {});
    assert.equal(modal.requests.at(-1).options.url, '/quoterequest/request/submit');
    assert.equal(modal.requests.at(-1).options.method, 'POST');
    assert.equal(modal.requests.at(-1).options.dataType, 'json');
    assert.equal(modal.requests.at(-1).options.timeout, 30000);
    assert.equal(modal.submit.props.disabled, true);
});
test('in-flight modal double click neither sends a second request nor changes the selected product', () => {
    modal.send(); modal.click(modal.quoteButton(43));
    assert.equal(modal.requests.length, 1);
    assert.equal(modal.fields.product_id.value, 42);
    assert.equal(modal.submit.props.disabled, true);
});
test('HTTP 500 hides internal error details, retains input, and releases the submit button', () => {
    modal.complete('fail', {status: 500, responseJSON: {message: 'SQL secret and customer data'}});
    assertReady(modal);
    assert.equal(modal.response.value, connectionMessage);
    assert.equal(modal.response.classes.has('error'), true);
    assert.equal(modal.form.props.hidden, false);
    assert.equal(modal.fields.message.value, original.message);
    assert.equal(modal.fields.customer_email.value, original.customer_email);
    assert.equal(modal.fields.request_token.value, original.request_token);
});
test('unchanged modal retry after HTTP 500 sends exactly the same captured business payload and token', () => {
    modal.send();
    assert.deepEqual(capture('modal_retry_500', 'popup_listing', modal), original);
    modal.complete('fail', {status: 0});
});
test('refreshed session CSRF changes only form_key while an uncertain retry preserves the request token', () => {
    modal.env.cookie = 'boundary-refreshed-form-key'; modal.send();
    const post = capture('modal_retry_session_refreshed', 'popup_listing', modal);
    assert.deepEqual(post, Object.assign({}, original, {form_key: 'boundary-refreshed-form-key'}));
    modal.complete('fail', {status: 0});
});
test('editing the message creates a new request token without losing the product or contact fields', () => {
    modal.fields.message.value = 'Please quote the revised requirement.'; modal.send();
    changed = capture('modal_message_changed', 'popup_listing', modal);
    assert.notEqual(changed.request_token, original.request_token);
    assert.equal(changed.message, 'Please quote the revised requirement.');
    assert.equal(changed.product_id, original.product_id);
    assert.equal(changed.customer_email, original.customer_email);
    modal.complete('fail', {status: 500});
});
test('editing quantity creates a new captured token and sends the exact entered decimal value', () => {
    modal.fields.quantity.value = '4.5'; modal.send();
    const post = capture('modal_quantity_changed', 'popup_listing', modal);
    assert.equal(post.quantity, '4.5'); assert.notEqual(post.request_token, changed.request_token);
    changed = post; modal.complete('fail', {status: 0});
});
test('editing customer email rotates the request token while preserving safe input for retry', () => {
    modal.fields.customer_email.value = 'revised@example.invalid'; modal.send();
    const post = capture('modal_email_changed', 'popup_listing', modal);
    assert.equal(post.customer_email, 'revised@example.invalid');
    assert.notEqual(post.request_token, changed.request_token);
    modal.complete('fail', {status: 0});
});
test('PDP popup capture contains selected nested attribute fields and excludes malformed selectors', () => {
    const h = prepared({}, {'data-coresh-pdp': '1'});
    h.env.randomCounter = 32;
    h.pdpOptions.push(node({name: 'super_attribute[12]', value: '5'}),
        node({name: 'super_attribute[13]', value: '7'}), node({name: 'super_attribute[bad]', value: '9'}));
    h.send();
    const post = capture('popup_pdp_configurable', 'popup_pdp', h);
    assert.deepEqual(post.super_attribute, {'12': '5', '13': '7'});
    assert.equal(post.quantity, '2.5');
    const parameters = new URLSearchParams(captures.at(-1).encoded_post);
    assert.equal(parameters.get('super_attribute[12]'), '5');
    assert.equal(parameters.get('super_attribute[13]'), '7');
    assert.equal(parameters.has('super_attribute[bad]'), false);
});
test('inline capture uses the same scalar POST contract and sends its fixed product context', () => {
    const h = prepared({inline: true, productContext: {productId: 42, productName: 'Boundary Product'}});
    h.env.randomCounter = 64;
    h.pdpOptions.push(node({name: 'super_attribute[12]', value: '5'}));
    h.send(); const post = capture('inline_first', 'inline_pdp', h);
    assert.equal(post.product_id, '42'); assert.deepEqual(post.super_attribute, {'12': '5'});
    assert.equal(post.form_key, original.form_key);
    assert.equal(h.document.events.length, 0);
    assert.equal(h.element.modalOptions, undefined);
});
test('tampering with the hidden product field cannot replace the context-selected product ID', () => {
    const h = prepared(); h.fields.product_id.value = '999'; h.send();
    assert.equal(h.requests[0].options.data.product_id, '42');
});
test('modal success acknowledges plain text and allows a separately tokenized request after reopening', () => {
    const h = prepared(); h.send(); const token = h.requests[0].options.data.request_token;
    h.complete('done', {success: true, status: 'accepted', message: '<b>Received</b>'});
    assertReady(h); assert.equal(h.response.value, '<b>Received</b>');
    assert.equal(h.response.classes.has('success'), true); assert.equal(h.form.props.hidden, true);
    assert.equal(h.fields.request_token.value, ''); assert.equal(h.fields.message.value, '');
    h.click(); h.fill(); h.send();
    assert.notEqual(h.requests[1].options.data.request_token, token);
});
test('an application rejection keeps all entered values and the unchanged retry token', () => {
    const h = prepared(); h.send(); const post = clone(h.requests[0].options.data);
    h.complete('done', {success: false, message: 'The request could not be accepted.'});
    assertReady(h); assert.equal(h.response.classes.has('error'), true);
    assert.equal(h.form.props.hidden, false); assert.equal(h.fields.message.value, post.message);
    h.send(); assert.deepEqual(clone(h.requests[1].options.data), post);
});
test('HTTP 400 application response is inserted as text and preserves the request token', () => {
    const h = prepared(); h.send(); const token = h.requests[0].options.data.request_token;
    h.complete('fail', {status: 400, responseJSON: {message: '<img src=x onerror=alert(1)>'}});
    assertReady(h); assert.equal(h.response.value, '<img src=x onerror=alert(1)>');
    h.send(); assert.equal(h.requests[1].options.data.request_token, token);
});
test('HTTP 429 validation response is actionable and leaves retry input intact', () => {
    const h = prepared(); h.send();
    h.complete('fail', {status: 429, responseJSON: {message: 'Too many requests. Please try again later.'}});
    assertReady(h); assert.equal(h.response.value, 'Too many requests. Please try again later.');
    assert.equal(h.fields.message.value, 'Please quote this product.');
});
test('invalid JSON on an HTTP 200 transport failure uses the safe retry message', () => {
    const h = prepared(); h.send(); const post = clone(h.requests[0].options.data);
    h.complete('fail', {status: 200, responseText: '<html>Unexpected response</html>'});
    assertReady(h); assert.equal(h.response.value, connectionMessage);
    h.send(); assert.deepEqual(clone(h.requests[1].options.data), post);
});
test('a timeout releases retry and resends the same token and values', () => {
    const h = prepared(); h.send(); const post = clone(h.requests[0].options.data);
    h.complete('fail', {status: 0, statusText: 'timeout'});
    assertReady(h); assert.equal(h.response.value, connectionMessage);
    h.send(); assert.deepEqual(clone(h.requests[1].options.data), post);
});
test('missing current CSRF prevents all AJAX traffic and does not lock the submit button', () => {
    const h = prepared(); h.env.cookie = null; h.send();
    assert.equal(h.requests.length, 0); assert.equal(h.submit.props.disabled, false);
    assert.equal(h.response.value, 'Your session has expired. Please reload this page and try again.');
});
test('native form validation cancellation prevents traffic and retains a usable submit button', () => {
    const h = prepared(); h.form.valid = false; h.send();
    assert.equal(h.requests.length, 0); assert.equal(h.submit.props.disabled, false);
});
test('changing a selected configurable option rotates the token before the next retry', () => {
    const h = prepared({}, {'data-coresh-pdp': '1'});
    const option = node({name: 'super_attribute[12]', value: '5'}); h.pdpOptions.push(option); h.send();
    const token = h.requests[0].options.data.request_token;
    h.complete('fail', {status: 500}); option.value = '6'; h.send();
    assert.notEqual(h.requests[1].options.data.request_token, token);
    assert.equal(h.requests[1].options.data.super_attribute['12'], '6');
});
test('inline HTTP 500 retry preserves its fixed product, contact values, options and token', () => {
    const h = prepared({inline: true, productContext: {productId: 42, productName: 'Boundary Product'}});
    h.pdpOptions.push(node({name: 'super_attribute[12]', value: '5'})); h.send();
    const post = clone(h.requests[0].options.data);
    h.complete('fail', {status: 500, responseJSON: {message: 'Do not disclose'}});
    assertReady(h); assert.equal(h.response.value, connectionMessage);
    h.send(); assert.deepEqual(clone(h.requests[1].options.data), post);
});
test('inline accepted response resets inquiry fields while leaving the fixed-product form visible', () => {
    const h = prepared({inline: true, productContext: {productId: 42, productName: 'Boundary Product'}});
    h.send(); h.complete('done', {success: true, message: 'Your quote request has been received.'});
    assertReady(h); assert.equal(h.response.classes.has('success'), true);
    assert.equal(h.form.props.hidden, false); assert.equal(h.fields.message.value, '');
    assert.equal(h.fields.request_token.value, '');
    h.fill(); h.send(); assert.equal(h.requests[1].options.data.product_id, '42');
});

const sourceSha256 = crypto.createHash('sha256').update(fs.readFileSync(
    path.join(moduleRoot, 'view/frontend/web/js/quote-request.js'))).digest('hex');
const fixture = {schema_version: 1, source: 'view/frontend/web/js/quote-request.js', source_sha256: sourceSha256,
    scope: 'Actual quote-request.js AMD AJAX payloads captured using explicit DOM/jQuery/customer-data/crypto doubles. No browser, HTTP, Magento bootstrap or email transport. encoded_post is derived with an explicit form encoder for PHP parse_str tests; it is not captured network traffic. Contact values are synthetic example.invalid fixtures.',
    captures};
if (writeFixtures) fs.writeFileSync(fixturePath, JSON.stringify(fixture, null, 2) + '\n');
else assert.deepEqual(JSON.parse(fs.readFileSync(fixturePath, 'utf8')), fixture,
    'Payload fixture is stale: regenerate with node tests/Frontend/submit-boundary.test.cjs --write-fixtures');

let responseCases = 0;
if (fs.existsSync(responsesPath)) {
    const fixtureResponses = JSON.parse(fs.readFileSync(responsesPath, 'utf8')).responses;
    ['accepted', 'duplicate', 'validation_error', 'csrf_error', 'server_error', 'token_error'].forEach(id => {
        const captured = fixtureResponses.find(response => response.id === id);
        assert.ok(captured, 'Missing actual PHP controller response capture: ' + id);
        ['popup', 'inline'].forEach(mode => {
            test('captured PHP ' + id + ' response completes the actual AMD ' + mode + ' UI safely', () => {
                const h = prepared(mode === 'inline' ? {inline: true,
                    productContext: {productId: 42, productName: 'Boundary Product'}} : {});
                h.send(); const post = clone(h.requests[0].options.data);
                if (captured.http_status >= 400) h.complete('fail', {status: captured.http_status, responseJSON: captured.body});
                else h.complete('done', captured.body);
                assertReady(h);
                if (captured.body.success === true) {
                    assert.equal(h.response.value, captured.body.message);
                    assert.equal(h.response.classes.has('success'), true);
                    assert.equal(h.fields.message.value, '');
                    assert.equal(h.fields.request_token.value, '');
                    assert.equal(h.form.props.hidden, mode === 'popup');
                } else {
                    const expected = captured.http_status >= 500 ? connectionMessage : captured.body.message;
                    assert.equal(h.response.value, expected);
                    if (id === 'token_error') {
                        assert.equal(captured.http_status, 400);
                        assert.equal(h.response.value, 'This request token is no longer valid. Please refresh the page and try again.');
                    }
                    assert.equal(h.response.classes.has('error'), true);
                    assert.equal(h.fields.message.value, post.message);
                    assert.equal(h.fields.request_token.value, post.request_token);
                    h.send(); assert.deepEqual(clone(h.requests[1].options.data), post);
                }
            });
            responseCases++;
        });
    });
} else {
    assert.ok(writeFixtures, 'PHP response fixture is absent; run FrontendSubmitPipelineTest with CORESH_CAPTURE_SUBMIT_RESPONSES first.');
}
console.log(JSON.stringify({suite: 'Actual AMD submission payload and captured PHP response boundary with explicit doubles',
    source_sha256: sourceSha256, passed: results.length, failed: 0, payload_captures: captures.length, response_cases: responseCases,
    response_cases_status: responseCases ? 'PASS' : 'NOTRUN: generate PHP controller response captures before final verification',
    tests: results}, null, 2));
