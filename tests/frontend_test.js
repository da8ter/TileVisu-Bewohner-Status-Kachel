'use strict';
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const raw = fs.readFileSync(path.join(__dirname, '../Bewohnerstatus/module.html'), 'utf8');
const script = raw.match(/<script>[\s\S]*?<\/script>/g)
    .map(block => block.slice('<script>'.length, -'</script>'.length))
    .find(block => block.includes('function handleMessage'));

// Minimales DOM-Double: die Kachel erzeugt ihre Bewohnerplaetze selbst, das
// Double muss deshalb createElement/appendChild/removeChild beherrschen.
class Element {
    constructor(tag = 'div') {
        this.tag = tag;
        this.classes = new Set();
        this.classList = { toggle: (name, on) => on ? this.classes.add(name) : this.classes.delete(name) };
        this.style = { setProperty: (key, value) => { this.style[key] = value; } };
        this.attributes = {};
        this.children = [];
        this.disabled = false;
        this.textContent = '';
    }
    set className(value) { this.classes = new Set(String(value).split(' ').filter(Boolean)); }
    get className() { return [...this.classes].join(' '); }
    setAttribute(key, value) { this.attributes[key] = value; }
    removeAttribute(key) { delete this[key]; delete this.attributes[key]; }
    appendChild(child) { this.children.push(child); return child; }
    removeChild(child) { this.children = this.children.filter(node => node !== child); return child; }
}

function fixture() {
    const container = new Element('div');
    const document = {
        documentElement: new Element('html'),
        body: new Element('body'),
        createElement: tag => new Element(tag),
        getElementById: id => (id === 'container' ? container : undefined),
    };
    const actions = [];
    const context = vm.createContext({
        document,
        console: { warn() {} },
        requestAction: (ident, value) => actions.push([ident, value]),
    });
    vm.runInContext(script, context);
    const slot = i => {
        const root = container.children[i - 1];
        if (root === undefined) return undefined;
        const [photo, name, info] = root.children;
        const [button, badge] = photo.children;
        return { root, photo, button, badge, image: button.children[0], name, info };
    };
    return {
        container, slot, actions, document,
        send: value => context.handleMessage(JSON.stringify(value)),
        raw: value => context.handleMessage(value),
    };
}

// Die Kachel liefert selbst keine Bewohnerbloecke mehr.
assert(!raw.includes('<button'));
assert(!/\{i\}|<!--BEWOHNER-->/.test(raw));
assert(raw.includes('id="container"'));

const f = fixture();
assert.equal(f.container.children.length, 0);
f.send({ residents: 3, fontsize: 21, nameswitch: false, Bewohner1: true, name1: '<b>Anna</b>', value1: true, operable1: true, info1: '0', bgimage: 'data:image/png;base64,AAAA' });
assert.equal(f.container.children.length, 3);
assert.equal(f.document.documentElement.style['--name-font-size'], '21px');
assert(f.slot(1).name.classes.has('name') && f.slot(1).name.classes.has('hidden'));
assert.equal(f.slot(1).button.attributes['aria-label'], '<b>Anna</b>');
assert.equal(f.slot(1).name.textContent, '<b>Anna</b>');
assert.equal(f.slot(1).button.attributes['aria-pressed'], 'true');
assert.equal(f.slot(1).button.disabled, false);
assert.equal(f.slot(1).button.attributes['aria-describedby'], 'info1');
assert(!f.slot(1).info.classes.has('hidden'));
assert(f.slot(2).root.classes.has('hidden') && f.slot(3).button.disabled);

// Der Klick meldet die Platznummer zurueck.
f.slot(2).button.onclick();
assert.deepEqual(f.actions, [['Bewohner2', 1]]);

// Entfernung als Kennzeichen oben rechts am Foto.
assert(f.slot(1).badge.classes.has('badge') && f.slot(1).badge.classes.has('hidden'));
assert.equal(f.slot(1).badge.textContent, '');
f.send({ distance1: '2,4 km' });
assert.equal(f.slot(1).badge.textContent, '2,4 km');
assert(!f.slot(1).badge.classes.has('hidden'));
f.send({ distance1: '' });
assert(f.slot(1).badge.classes.has('hidden'));
assert(raw.includes('.photo {') && /\.badge\s*\{[^}]*position:\s*absolute/.test(raw));
// Das Kennzeichen darf den Fotorahmen nicht verlassen; html schneidet ab.
assert(!/\.badge\s*\{[^}]*transform:/.test(raw));
assert(/\.badge\s*\{[^}]*pointer-events:\s*none/.test(raw));

f.send({ nameswitch: true });
assert(!f.slot(1).name.classes.has('hidden'));
f.send({ bgimage: '', info1: '', value1: false, operable1: false });
assert.equal(f.document.body.style['--background-image'], 'none');
assert(f.slot(1).info.classes.has('info') && f.slot(1).info.classes.has('hidden'));
assert(f.slot(1).image.classes.has('grey') && !f.slot(1).image.classes.has('color'));
assert.equal(f.slot(1).button.attributes['aria-pressed'], 'false');
assert.equal(f.slot(1).button.disabled, true);
f.send({ image1: 'data:image/webp;base64,AAAA' });
f.send({ value1: true });
assert.equal(f.slot(1).image.src, 'data:image/webp;base64,AAAA');
f.send({ image1: '', Bewohner1: false });
assert.equal(f.slot(1).image.src, undefined);
assert(f.slot(1).root.classes.has('hidden'));

// Die Liste waechst und schrumpft ohne Neuladen der Kachel.
f.send({ residents: 7, Bewohner7: true, name7: 'Nummer sieben', value7: true, operable7: true });
assert.equal(f.container.children.length, 7);
assert.equal(f.slot(7).name.textContent, 'Nummer sieben');
f.slot(7).button.onclick();
assert.deepEqual(f.actions.at(-1), ['Bewohner7', 1]);
f.send({ residents: 2 });
assert.equal(f.container.children.length, 2);
assert.equal(f.slot(3), undefined);
f.send({ residents: 0 });
assert.equal(f.container.children.length, 0);

// Unsinnige Bewohnerzahlen duerfen die Kachel nicht sprengen.
for (const residents of ['viele', -3, null, NaN, 1e9]) {
    assert.doesNotThrow(() => f.send({ residents }));
    assert(f.container.children.length <= 200);
}

// Graustufen und Deckkraft bei Abwesenheit kommen aus der Konfiguration.
f.send({ graustufen: 0, abwesenheitstransparenz: 0.25 });
assert.equal(f.document.documentElement.style['--absent-grayscale'], '0%');
assert.equal(f.document.documentElement.style['--absent-opacity'], '0.25');
assert(raw.includes('grayscale(var(--absent-grayscale') && raw.includes('opacity: var(--absent-opacity'));

// Die Kachel setzt ihre Raender selbst und liest keine Systemwerte aus der Adresse.
assert(!/--sym-|URLSearchParams|location\.search/.test(raw));
assert(/\.container_anwesenheit\s*\{[^}]*padding:\s*0;/.test(raw));
assert(raw.includes('.image-wrapper:focus-visible'));
assert(!/DebugOutline|debug-outline/.test(raw));

for (const input of ['', 'not json', 'null', '[]']) assert.doesNotThrow(() => f.raw(input));

// Reihenfolge der Schluessel darf das Ergebnis nicht aendern.
const message = { residents: 1, fontsize: 17, nameswitch: true, Bewohner1: true, name1: 'Anna', info1: 'Arbeit', distance1: '12 km', value1: true, operable1: true };
const left = fixture(), right = fixture();
left.send(message); right.send(Object.fromEntries(Object.entries(message).reverse()));
const describe = node => [node.tag, [...node.classes].sort(), node.attributes, node.disabled, node.textContent, node.src,
    node.children.map(describe)];
const state = f => JSON.stringify(f.container.children.map(describe));
assert.equal(state(left), state(right));
assert.equal(left.document.documentElement.style['--name-font-size'], right.document.documentElement.style['--name-font-size']);

console.log('PASS: Frontend slot building, deltas, ordering, escaping, visibility, operation, ARIA state, distance badge, absent styling and malformed messages');
