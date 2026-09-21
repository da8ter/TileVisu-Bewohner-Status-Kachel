'use strict';
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const html = fs.readFileSync(path.join(__dirname, '../Bewohnerstatus/module.html'), 'utf8');
const script = html.match(/<script>([\s\S]*?)<\/script>/)[1];
class Element {
    constructor(classes = '') {
        this.classes = new Set(classes.split(' ').filter(Boolean));
        this.classList = { toggle: (name, on) => on ? this.classes.add(name) : this.classes.delete(name) };
        this.style = { setProperty: (key, value) => { this.style[key] = value; } };
        this.attributes = {}; this.disabled = true; this.textContent = '';
    }
    setAttribute(key, value) { this.attributes[key] = value; }
    removeAttribute(key) { delete this[key]; }
}
function fixture() {
    const elements = {};
    for (let i = 1; i <= 5; i++) {
        for (const prefix of ['Bewohner', 'image', 'button', 'name', 'info']) {
            const tag = html.match(new RegExp('<[^>]+id="' + prefix + i + '"[^>]*>'))[0];
            elements[prefix + i] = new Element(tag.match(/class="([^"]+)"/)?.[1]);
        }
    }
    const document = { documentElement: new Element(), body: new Element(), getElementById: id => elements[id] };
    const context = vm.createContext({ document, console: { warn() {} } });
    vm.runInContext(script, context);
    return { elements, document, send: value => context.handleMessage(JSON.stringify(value)), raw: value => context.handleMessage(value) };
}
const f = fixture();
f.send({ fontsize: 21, nameswitch: false, Bewohner1: true, name1: '<b>Anna</b>', value1: true, operable1: true, info1: '0', bgimage: 'data:image/png;base64,AAAA' });
assert(f.elements.name1.classes.has('name') && f.elements.name1.classes.has('hidden'));
assert.equal(f.elements.button1.attributes['aria-label'], '<b>Anna</b>');
assert.equal(f.elements.name1.textContent, '<b>Anna</b>');
assert.equal(f.elements.button1.attributes['aria-pressed'], 'true');
assert.equal(f.elements.button1.disabled, false);
assert(!f.elements.info1.classes.has('hidden'));
f.send({ nameswitch: true });
assert.equal(f.document.documentElement.style['--name-font-size'], '21px');
assert(!f.elements.name1.classes.has('hidden'));
f.send({ bgimage: '', info1: '', value1: false, operable1: false, DebugOutline: true });
assert.equal(f.document.body.style['--background-image'], 'none');
assert(f.elements.info1.classes.has('info') && f.elements.info1.classes.has('hidden'));
assert(f.elements.image1.classes.has('grey') && !f.elements.image1.classes.has('color'));
assert.equal(f.elements.button1.attributes['aria-pressed'], 'false');
assert.equal(f.elements.button1.disabled, true);
f.send({ DebugOutline: false, image1: 'data:image/webp;base64,AAAA' });
assert(!f.document.documentElement.classes.has('debug-outline'));
f.send({ value1: true });
assert.equal(f.elements.image1.src, 'data:image/webp;base64,AAAA');
f.send({ image1: '', Bewohner1: false });
assert.equal(f.elements.image1.src, undefined);
assert(f.elements.Bewohner1.classes.has('hidden'));
for (const input of ['', 'not json', 'null', '[]']) assert.doesNotThrow(() => f.raw(input));
const message = { fontsize: 17, nameswitch: true, Bewohner1: true, name1: 'Anna', info1: 'Arbeit', value1: true, operable1: true };
const left = fixture(), right = fixture();
left.send(message); right.send(Object.fromEntries(Object.entries(message).reverse()));
const state = f => JSON.stringify(Object.entries(f.elements).map(([id,e]) => [id, [...e.classes].sort(), e.attributes, e.disabled, e.textContent, e.src]));
assert.equal(state(left), state(right));
assert.equal(left.document.documentElement.style['--name-font-size'], right.document.documentElement.style['--name-font-size']);
assert.equal((html.match(/<button type="button"/g) || []).length, 5);
assert.equal((html.match(/<img[^>]*alt=""/g) || []).length, 5);
assert(html.includes('.image-wrapper:focus-visible'));
console.log('PASS: Frontend deltas, ordering, escaping, visibility, operation, ARIA state and malformed messages');
