'use strict';
const fs = require('fs'), vm = require('vm'), path = require('path'), assert = require('assert').strict;
const source = process.argv[2] || path.resolve(__dirname, '../../view/adminhtml/web/js/grid/export.js');
let component, currentForm, submissions = [], created = 0;
const window = {FORM_KEY: 'current-runtime-key'}, document = {body: {}};
const $ = tag => {
    const node = {attributes: {}, values: [], hidden: false};
    const wrapper = {
        attr(values) {Object.assign(node.attributes, values); return this;},
        prop(name, value) {node[name] = value; return this;},
        val(value) {node.value = value; return this;},
        appendTo(parent) {if (parent === document.body) currentForm = node; else parent[0].values.push(node); return this;},
        remove() {node.removed = true; return this;},
        0: node
    };
    if (tag === '<form>') {
        created++;
        node.submit = () => submissions.push({attributes: {...node.attributes}, fields: node.values.map(input => ({...input.attributes, value: input.value})), hidden: node.hidden, node});
    }
    return wrapper;
};
$.each = (object, callback) => Object.keys(object).forEach(key => callback(key, object[key]));
vm.runInNewContext(fs.readFileSync(source, 'utf8'), {define: (deps, factory) => {component = factory($, {extend: value => value});}, window, document, String});
const tests = [];
function test(name, callback) {callback(); tests.push(name);}
function submit(params) {component.postRequest.call({getParams: () => params}, {url: '/backend/coresh_quoterequest/request/exportcsv?query=1'});}
function fields() {return Object.fromEntries(submissions.at(-1).fields.map(field => [field.name, field.value]));}
test('missing runtime form key prevents export POST', () => {window.FORM_KEY = ''; submit({selected: [1]}); assert.equal(created, 0); window.FORM_KEY = 'current-runtime-key';});
test('missing grid params prevents export POST', () => {submit(null); assert.equal(created, 0);});
test('selected IDs are serialized as POST body with current key and forced module namespace', () => {
    submit({selected: [7, 12], namespace: 'injected_listing', form_key: 'stale-key'});
    assert.equal(submissions.at(-1).attributes.method, 'post');
    assert.equal(fields()['selected[0]'], '7'); assert.equal(fields()['selected[1]'], '12');
    assert.equal(fields().form_key, 'current-runtime-key'); assert.equal(fields().namespace, 'coresh_quote_request_listing');
});
test('native empty selection becomes select-all excluded literal false', () => {submit({selected: false}); assert.equal(fields().excluded, 'false'); assert.equal(fields().selected, undefined);});
test('excluded mode remains exclusive and runtime form key is reread', () => {window.FORM_KEY = 'changed-key'; submit({excluded: [8]}); assert.equal(fields()['excluded[0]'], '8'); assert.equal(fields().selected, undefined); assert.equal(fields().form_key, 'changed-key');});
test('nested grid filters and HTML-like text remain input values; temporary form is removed', () => {
    submit({selected: [9], filters: {message: '<img src=x onerror=alert(1)>'}});
    assert.equal(fields()['filters[message]'], '<img src=x onerror=alert(1)>');
    assert.equal(submissions.at(-1).hidden, true); assert.equal(submissions.at(-1).node.removed, true);
});
console.log(JSON.stringify({suite: 'Grid export AMD isolated DOM doubles', passed: tests.length, failed: 0, tests}, null, 2));
