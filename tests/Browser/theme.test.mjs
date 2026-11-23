import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const script = readFileSync(new URL('../../public/js/theme.js', import.meta.url), 'utf8');

function setup(stored = null, matches = true) {
    const documentEvents = {}, windowEvents = {};
    let schemeChange;
    const system = { matches, addEventListener(_event, callback) { schemeChange = callback; } };
    const labels = [{}, {}];
    const summary = { focus() {} };
    const picker = { open: true, querySelector: () => summary, contains: () => true };
    const choices = ['system', 'light', 'dark'].map(value => ({
        dataset: { themeChoice: value },
        closest(selector) { return selector === '[data-theme-choice]' ? this : picker; },
        setAttribute(name, attribute) { this[name] = attribute; },
    }));
    const root = { dataset: { theme: 'light' } };
    const document = {
        documentElement: root,
        querySelectorAll: selector => ({ '[data-theme-label]': labels, '[data-theme-choice]': choices })[selector] ?? [],
        addEventListener: (name, handler) => { documentEvents[name] = handler; },
    };
    const window = {
        matchMedia: () => system,
        addEventListener: (name, handler) => { windowEvents[name] = handler; },
    };
    vm.runInNewContext(script, { document, window, localStorage: {
        getItem: () => stored,
        setItem: (_key, value) => { stored = value; },
    } });
    return {
        root, labels, choices,
        select: index => documentEvents.click({ target: choices[index] }),
        changeSystem: value => { system.matches = value; schemeChange(); },
        changeStorage: value => { stored = value; windowEvents.storage({ key: 'ujiansmk-theme' }); },
        stored: () => stored,
    };
}

test('first visit uses Light even when the phone is dark and only System follows phone changes', () => {
    const page = setup();
    assert.equal(page.root.dataset.theme, 'light');
    assert.equal(page.labels[0].textContent, 'Terang');
    assert.equal(page.choices[1]['aria-pressed'], 'true');
    assert.equal(page.stored(), null);
    page.changeSystem(false);
    page.changeSystem(true);
    assert.equal(page.root.dataset.theme, 'light');
    page.select(1);
    assert.equal(page.root.dataset.theme, 'light');
    assert.equal(page.stored(), 'light');
    assert.equal(page.choices[1]['aria-pressed'], 'true');
    assert.deepEqual(page.labels.map(label => label.textContent), ['Terang', 'Terang']);
    page.changeSystem(false);
    page.changeSystem(true);
    assert.equal(page.root.dataset.theme, 'light');
    page.select(2);
    page.changeSystem(false);
    assert.equal(page.root.dataset.theme, 'dark');
    page.select(0);
    assert.equal(page.root.dataset.theme, 'light');
    page.changeSystem(true);
    assert.equal(page.root.dataset.theme, 'dark');
    assert.equal(page.stored(), 'system');
});

test('existing theme settings are retained and changes from another tab are applied', () => {
    const page = setup('light');
    assert.equal(page.root.dataset.theme, 'light');
    page.changeStorage('dark');
    assert.equal(page.labels[0].textContent, 'Gelap');
    page.changeSystem(false);
    assert.equal(page.root.dataset.theme, 'dark');
    page.changeStorage(null);
    assert.equal(page.root.dataset.theme, 'light');
    assert.equal(page.labels[0].textContent, 'Terang');
});

test('System remains the selected preference after navigation and can be overridden', () => {
    const page = setup('system');
    assert.equal(page.root.dataset.theme, 'dark');
    assert.equal(page.labels[1].textContent, 'Sistem');
    page.select(1);
    assert.equal(setup(page.stored()).root.dataset.theme, 'light');
});
