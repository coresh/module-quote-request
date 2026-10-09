'use strict';
const fs = require('fs'), vm = require('vm'), assert = require('assert').strict, path = require('path');
const source = process.argv[2] || require('path').resolve(__dirname, '../../view/frontend/web/js/quote-request.js');
const names = ['product_id','quantity','customer_name','customer_email','customer_telephone','message','honeypot','form_key','request_token'];
const fields = Object.fromEntries(names.map(name => [name, {value: name === 'quantity' ? '1' : ''}]));
let formSubmit, factory, requests = [], randomCounter = 0, privateReloads = 0, options = [{name: 'super_attribute[15]',value: '3'}];
let deferProfiles = false;
const pendingProfiles = [];
const document = {}, response = {}, title = {}, submit = {}, element = {privateData: {}}, form = {
    reportValidity: () => true, reset: () => names.forEach(name => fields[name].value = name === 'quantity' ? '1' : '')
};
class W {
    constructor(node) {this.n = node; this[0] = node;}
    find(selector) {
        if (this.n === element) return new W({'[data-role="quote-form"]':form,'[data-role="response"]':response,'[data-role="submit"]':submit,'[data-role="product-name"]':title}[selector]);
        const match = selector.match(/^\[name="([^"]+)"\]$/); return new W(match ? fields[match[1]] : null);
    }
    data(name,value) {if(value === undefined)return this.n.privateData[name];this.n.privateData[name]=value;return this;}
    prop(name,value) {this.n[name]=value;return this;}
    val(value) {if(value === undefined)return this.n.value;this.n.value=value;return this;}
    text(value) {this.n.text=String(value);return this;}
    toggleClass() {return this;}
    on(event,callback) {if(this.n===document)throw new Error('Inline widget attached global listeners');assert.equal(this.n,form);assert.equal(event,'submit');formSubmit=callback;return this;}
}
const $ = node => {
    if(typeof node === 'string') return {each: callback => options.forEach(option => callback.call(option)), val: () => undefined};
    return new W(node);
};
$.each=(obj,callback)=>Object.keys(obj).forEach(key=>callback(key,obj[key]));
$.mage={cookies:{get:()=> 'runtime-inline-form-key'}};
$.ajax=options=> {const entry={options,handlers:{}};requests.push(entry);const chain={};['done','fail','always'].forEach(name=>chain[name]=fn=>{entry.handlers[name]=fn;return chain;});return chain;};
vm.runInNewContext(fs.readFileSync(source,'utf8'),{
    define:(deps,fn)=>factory=fn($,()=>{throw new Error('Inline widget initialized modal');},{
        get:()=> {throw new Error('Inline widget read cached PII');},
        reload:()=> {privateReloads++;return{done:fn=>{
            if (deferProfiles) pendingProfiles.push(fn);
            else fn({'coresh-quote-request-customer':{name:'Current Customer',email:'current@example.invalid'}});
        }};}
    },text=>text),
    window:{crypto:{getRandomValues:bytes=>bytes.fill(++randomCounter)}},document,Uint8Array,JSON,Array,Boolean,String,Error
});
factory({inline:true,productContext:{productId:19,productName:'<img src=x onerror=alert(1)>'},submitUrl:'/quoterequest/request/submit'},element);
const results=[];
function test(name,fn){fn();results.push(name);}
function send(){formSubmit({preventDefault(){}});}
function finish(kind,result){const entry=requests.at(-1);entry.handlers[kind](result);entry.handlers.always();}
test('inline form initializes fixed public product context without modal/global handlers',()=>{
    assert.equal(fields.product_id.value,19);assert.equal(title.text,'<img src=x onerror=alert(1)>');
});
test('inline form populates profile only through current private-session reload',()=>{
    assert.equal(fields.customer_name.value,'Current Customer');assert.equal(fields.customer_email.value,'current@example.invalid');assert.equal(privateReloads,1);
});
test('inline POST uses runtime CSRF, entered quantity and selected configurable options',()=>{
    fields.message.value='Please quote';fields.quantity.value='3';send();const data=requests.at(-1).options.data;
    assert.equal(data.product_id,'19');assert.equal(data.quantity,'3');assert.equal(data.form_key,'runtime-inline-form-key');
    assert.equal(data.super_attribute['15'],'3');assert.match(data.request_token,/^[0-9a-f]{64}$/);
});
test('inline in-flight duplicate is suppressed',()=>{const count=requests.length;send();assert.equal(requests.length,count);});
let original=requests.at(-1).options.data.request_token;
test('inline uncertain transport retry keeps identical token',()=>{finish('fail',{status:0});send();assert.equal(requests.at(-1).options.data.request_token,original);finish('fail',{status:0});});
test('inline edited quantity rotates token',()=>{fields.quantity.value='4';send();assert.notEqual(requests.at(-1).options.data.request_token,original);});
test('inline accepted response remains text and form is available for a new inquiry',()=>{
    finish('done',{success:true,message:'<b>Accepted</b>'});assert.equal(response.text,'<b>Accepted</b>');assert.equal(form.hidden,false);
    assert.equal(fields.request_token.value,'');assert.equal(fields.message.value,'');assert.equal(fields.customer_email.value,'current@example.invalid');
});
test('new inline inquiry after success uses a new token and same fixed product',()=>{
    const old=requests.at(-1).options.data.request_token;fields.message.value='A separate inquiry';send();assert.notEqual(requests.at(-1).options.data.request_token,old);assert.equal(requests.at(-1).options.data.product_id,'19');finish('fail',{status:0});
});
test('out-of-order inline reload after successive submissions retains only the latest profile',()=>{
    deferProfiles=true;
    send();finish('done',{success:true});
    fields.customer_name.value='Second requester';fields.customer_email.value='second@example.invalid';fields.message.value='Another inquiry';
    send();finish('done',{success:true});
    assert.equal(pendingProfiles.length,2);
    const older=pendingProfiles.shift(), latest=pendingProfiles.shift();
    latest({'coresh-quote-request-customer':{name:'Current profile',email:'current@example.invalid'}});
    older({'coresh-quote-request-customer':{name:'Earlier profile',email:'earlier@example.invalid'}});
    assert.equal(fields.customer_name.value,'Current profile');assert.equal(fields.customer_email.value,'current@example.invalid');
});
test('a delayed inline profile response preserves manually entered fields',()=>{
    fields.message.value='Next inquiry';send();finish('done',{success:true});
    fields.customer_name.value='Manual name';fields.customer_email.value='manual@example.invalid';
    pendingProfiles.shift()({'coresh-quote-request-customer':{name:'Current profile',email:'current@example.invalid'}});
    assert.equal(fields.customer_name.value,'Manual name');assert.equal(fields.customer_email.value,'manual@example.invalid');
});
console.log(JSON.stringify({suite:'Inline inquiry form isolated DOM doubles',passed:results.length,failed:0,tests:results},null,2));
