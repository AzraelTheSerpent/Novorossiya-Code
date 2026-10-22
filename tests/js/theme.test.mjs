import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const script = readFileSync(new URL('../../public/theme.js', import.meta.url), 'utf8');
function boot({ stored = null, dark = false, blocked = false } = {}) {
    const events = {}, systemEvents = {}, documentEvents = {}, buttons = [];
    const values = new Map(stored === null ? [] : [['kod:theme', stored]]);
    const root = { dataset: {}, style: {} }, meta = {};
    const system = { matches: dark, addEventListener: (name, fn) => systemEvents[name] = fn };
    const window = {
        matchMedia: () => system,
        addEventListener: (name, fn) => (events[name] ??= []).push(fn),
        dispatchEvent: event => (events[event.type] ?? []).forEach(fn => fn(event)),
    };
    const document = {
        documentElement: root,
        querySelector: () => meta,
        querySelectorAll: () => buttons,
        addEventListener: (name, fn) => documentEvents[name] = fn,
        createElement: () => ({ dataset: {}, attributes: {}, setAttribute(name, value) { this.attributes[name] = value; }, addEventListener(name, fn) { this[name] = fn; } }),
        body: { appendChild: button => buttons.push(button) },
    };
    const localStorage = {
        getItem(key) { if (blocked) throw Error('blocked'); return values.get(key) ?? null; },
        setItem(key, value) { if (blocked) throw Error('blocked'); values.set(key, value); },
    };
    vm.runInNewContext(script, { window, document, localStorage, CustomEvent: class { constructor(type, options) { this.type = type; this.detail = options.detail; } } });
    return { window, root, meta, values, buttons, ready: documentEvents.DOMContentLoaded, system(value) { system.matches = value; systemEvents.change({ matches: value }); } };
}
test('first visit follows system and changes with it without saving a preference', () => {
    const app = boot({ dark: true });
    assert.equal(app.root.dataset.theme, 'dark');
    app.system(false);
    assert.equal(app.root.dataset.theme, 'light');
    assert.equal(app.values.has('kod:theme'), false);
});
test('manual choice survives reload and takes precedence over system changes', () => {
    const app = boot({ dark: true });
    app.window.KodTheme.set('light');
    app.system(true);
    assert.equal(app.root.dataset.theme, 'light');
    assert.equal(boot({ stored: app.values.get('kod:theme'), dark: true }).root.dataset.theme, 'light');
});
test('blocked or corrupt storage still allows switching and updates accessible control', () => {
    for (const options of [{ blocked: true }, { stored: '{broken' }, { stored: '"unknown"' }]) {
        const app = boot(options);
        app.ready();
        app.buttons[0].click();
        assert.equal(app.root.dataset.theme, 'dark');
        assert.equal(app.root.style.colorScheme, 'dark');
        assert.equal(app.meta.content, '#101722');
        assert.equal(app.buttons[0].attributes['aria-pressed'], 'true');
        assert.equal(app.buttons[0].attributes['aria-label'], 'Включить светлую тему');
    }
});
test('storage changes sync tabs and removing preference restores system behavior', () => {
    const app = boot({ stored: '"light"', dark: true });
    app.window.dispatchEvent({ type: 'storage', key: 'kod:theme', newValue: '"dark"' });
    assert.equal(app.root.dataset.theme, 'dark');
    app.window.dispatchEvent({ type: 'storage', key: 'kod:theme', newValue: null });
    app.system(false);
    assert.equal(app.root.dataset.theme, 'light');
});
