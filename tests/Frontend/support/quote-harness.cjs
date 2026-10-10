'use strict';

// Explicit isolated DOM/jQuery/customer-data doubles for the shipped AMD source.
// The harness does not create a browser, perform HTTP, or bootstrap Magento.
const fs = require('fs');
const path = require('path');
const vm = require('vm');

function node(extra = {}) {
    return Object.assign({attrs: {}, props: {}, classes: new Set(), data: {}, value: '', events: []}, extra);
}

function quoteHarness(configuration = {}, initialButtons = [], moduleRoot = path.resolve(__dirname, '../../..')) {
    const quoteSource = fs.readFileSync(path.join(moduleRoot, 'view/frontend/web/js/quote-request.js'), 'utf8');
    const fields = Object.fromEntries(['product_id', 'form_key', 'request_token', 'quantity',
        'customer_name', 'customer_email', 'customer_telephone', 'message', 'honeypot']
        .map(name => [name, node({name, value: name === 'quantity' ? '1' : ''})]));
    const form = node({valid: true, resets: 0});
    form.reportValidity = () => form.valid;
    form.reset = () => {
        form.resets++;
        Object.values(fields).forEach(field => {field.value = field.name === 'quantity' ? '1' : '';});
    };
    const response = node(), submit = node(), title = node(), element = node({attrs: {hidden: ''}});
    const document = node(), pdpQuantity = node({value: '2.5'}), pdpCart = node(), globalPurchase = node();
    const buttons = initialButtons, pdpOptions = [], requests = [], reloads = [];
    const env = {cookie: 'current-cookie', cryptoAvailable: true, randomCounter: 0, profile: {name: '', email: ''}};
    const selectors = {
        '[data-coresh-quote], [data-coresh-display]': buttons,
        '.action.tocart, #product-addtocart-button, .box-tocart': [globalPurchase],
        '.product-info-main .box-tocart': [pdpCart],
        '#product_addtocart_form [name="qty"]': [pdpQuantity],
        '#product_addtocart_form [name^="super_attribute["]': pdpOptions
    };

    class Wrapper {
        constructor(nodes) {this.nodes = nodes.filter(Boolean); this.length = this.nodes.length; this.nodes.forEach((item, index) => {this[index] = item;});}
        first() {return this.nodes[0];}
        find(selector) {
            const item = this.first();
            if (item === element) return $({'[data-role="quote-form"]': form, '[data-role="response"]': response,
                '[data-role="submit"]': submit, '[data-role="product-name"]': title}[selector]);
            if (item === form) {const match = selector.match(/^\[name="([^"]+)"\]$/); return $(match && fields[match[1]]);}
            return $(item && item.find ? item.find[selector] : []);
        }
        data(name, value) {if (value === undefined) return this.first().data[name]; this.nodes.forEach(item => {item.data[name] = value;}); return this;}
        prop(name, value) {if (value === undefined) return this.first().props[name]; this.nodes.forEach(item => {item.props[name] = value;}); return this;}
        val(value) {if (value === undefined) return this.first() && this.first().value; this.nodes.forEach(item => {item.value = value;}); return this;}
        text(value) {this.nodes.forEach(item => {item.value = String(value);}); return this;}
        toggleClass(name, enabled) {this.nodes.forEach(item => {if (enabled) item.classes.add(name); else item.classes.delete(name);}); return this;}
        addClass(name) {return this.toggleClass(name, true);}
        attr(name) {return this.first() && this.first().attrs[name];}
        removeAttr(name) {this.nodes.forEach(item => {delete item.attrs[name];}); return this;}
        modal(action) {this.nodes.forEach(item => {item.modalAction = action;}); return this;}
        closest() {return $(this.first() && this.first().productItem);}
        each(callback) {this.nodes.forEach((item, index) => callback.call(item, index, item)); return this;}
        on(event, selector, callback) {
            if (typeof selector === 'function') {callback = selector; selector = null;}
            this.nodes.forEach(item => item.events.push({event, selector, callback})); return this;
        }
    }
    function $(input) {
        if (typeof input === 'string') return new Wrapper(selectors[input] || []);
        return new Wrapper(Array.isArray(input) ? input : [input]);
    }
    $.each = (object, callback) => Object.keys(object).forEach(key => callback(key, object[key]));
    $.mage = {cookies: {get: () => env.cookie}};
    $.ajax = options => {
        const request = {options, callbacks: {}};
        requests.push(request);
        const chain = {};
        ['done', 'fail', 'always'].forEach(kind => {chain[kind] = callback => {request.callbacks[kind] = callback; return chain;};});
        return chain;
    };
    let factory;
    const sandbox = {document, Uint8Array, JSON, Array, Boolean, String, Error,
        window: {crypto: {getRandomValues: bytes => {
            if (!env.cryptoAvailable) throw new Error('Crypto unavailable');
            bytes.fill(++env.randomCounter);
        }}},
        define: (dependencies, create) => {
            factory = create($, (options, wrapper) => {wrapper.first().modalOptions = options;}, {
                reload: () => ({done: callback => {reloads.push(callback); callback({'coresh-quote-request-customer': env.profile});}})
            }, value => value);
        }};
    vm.runInNewContext(quoteSource, sandbox, {filename: 'view/frontend/web/js/quote-request.js'});
    const config = Object.assign({submitUrl: '/quoterequest/request/submit', disablePurchasing: false}, configuration);
    factory(config, element);
    function fire(item, event, target = item, selector = null) {
        const eventObject = {defaultPrevented: false, preventDefault() {this.defaultPrevented = true;}};
        item.events.filter(handler => handler.event === event && handler.selector === selector)
            .forEach(handler => handler.callback.call(target, eventObject));
        return eventObject;
    }
    function quoteButton(id, extra = {}) {
        return node({attrs: Object.assign({'data-coresh-quote': JSON.stringify({productId: id, productName: '<b>Product ' + id + '</b>'})}, extra)});
    }
    function click(button = quoteButton(42)) {return fire(document, 'click.coreshQuote', button, '[data-coresh-quote]');}
    function send() {return fire(form, 'submit');}
    function complete(kind, value) {
        const request = requests.at(-1);
        request.callbacks[kind](value);
        request.callbacks.always();
    }
    function fill() {fields.customer_name.value = 'Requester'; fields.customer_email.value = 'customer@example.invalid'; fields.message.value = 'Please send a quote.';}
    return {fields, form, response, submit, title, element, document, buttons, pdpOptions, pdpQuantity,
        pdpCart, globalPurchase, requests, env, click, send, complete, fill, quoteButton,
        update: () => fire(document, 'contentUpdated.coreshQuote'),
        initializeAgain: () => factory(config, element)};
}


module.exports = {node, quoteHarness};
