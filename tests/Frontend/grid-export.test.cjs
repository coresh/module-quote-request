'use strict';
const fs = require('fs'), vm = require('vm'), path = require('path'), crypto = require('crypto'), assert = require('assert').strict;
const source = process.argv[2] || path.resolve(__dirname, '../../view/adminhtml/web/js/grid/export.js');
// Optional actual Magento AMD parent; jQuery, underscore and uiElement remain explicit doubles.
const coreSource = process.argv[3];
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
let coreComponent;
if (coreSource) {
    const underscore = {
        each: (object, callback, context) => Object.keys(object).forEach(key => callback.call(context, object[key], key)),
        findWhere: (items, match) => items.find(item => Object.keys(match).every(key => item[key] === match[key]))
    };
    vm.runInNewContext(fs.readFileSync(coreSource, 'utf8'), {
        define: (deps, factory) => {coreComponent = factory($, underscore, {extend: value => value});}, window, document
    });
    $.ajax = () => {throw new Error('The module POST form must replace the native AJAX export transport.');};
}
vm.runInNewContext(fs.readFileSync(source, 'utf8'), {define: (deps, factory) => {
    component = factory($, {extend: value => Object.assign({}, coreComponent || {}, value)});
}, window, document, String});
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
if (coreComponent) {
    function clickExport(data) {
        const context = Object.assign({}, component, {
            selections: () => ({getSelections: () => data}),
            checked: () => 'csv',
            options: [{value: 'csv', url: '/backend/coresh_quoterequest/request/exportcsv?key=secret-key'}],
            additionalParams: {}
        });
        context.applyOption();
        assert.equal(submissions.at(-1).attributes.action, context.options[0].url);
        assert.equal(submissions.at(-1).attributes.method, 'post');
        assert.equal(fields().form_key, window.FORM_KEY);
        assert.equal(fields().namespace, 'coresh_quote_request_listing');
    }
    test('actual native Export button click dispatches module POST for selected requests and search filters', () => {
        clickExport({excludeMode: false, selected: [21, 22], excluded: [],
            params: {namespace: 'coresh_quote_request_listing', search: 'ring', filters: {request_status: 'new'}}});
        assert.equal(fields()['selected[0]'], '21');
        assert.equal(fields()['selected[1]'], '22');
        assert.equal(fields().search, 'ring');
        assert.equal(fields()['filters[request_status]'], 'new');
        assert.equal(fields().excluded, undefined);
    });
    test('actual native empty selected export uses filtered select-all protocol', () => {
        clickExport({excludeMode: false, selected: [], excluded: [],
            params: {namespace: 'coresh_quote_request_listing', filters: {store_id: '3'}}});
        assert.equal(fields().excluded, 'false');
        assert.equal(fields().selected, undefined);
        assert.equal(fields()['filters[store_id]'], '3');
    });
    test('actual native excluded mode exports all filtered requests except explicit exclusions', () => {
        clickExport({excludeMode: true, selected: [], excluded: [23],
            params: {namespace: 'coresh_quote_request_listing', filters: {}}});
        assert.equal(fields()['excluded[0]'], '23');
        assert.equal(fields().selected, undefined);
    });
    test('actual native select-all exclusion mode with no excluded rows remains literal false', () => {
        clickExport({excludeMode: true, selected: [], excluded: [],
            params: {namespace: 'coresh_quote_request_listing', filters: {}}});
        assert.equal(fields().excluded, 'false');
        assert.equal(fields().selected, undefined);
    });
}
console.log(JSON.stringify({suite: 'Grid export AMD isolated DOM doubles', passed: tests.length, failed: 0, tests,
    native_parent_button_methods: coreSource ? {
        status: 'PASS', path: coreSource, sha256: crypto.createHash('sha256').update(fs.readFileSync(coreSource)).digest('hex'),
        scope: 'Actual upstream applyOption/getActiveOption/getParams; DOM, jQuery, underscore and uiElement doubles. No browser or Magento UI bootstrap.'
    } : {status: 'NOT RUN', reason: 'Pass the installed or upstream Magento_Ui grid/export.js as the third CLI argument.'}
}, null, 2));
