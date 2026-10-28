import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
import test from 'node:test';

// Exercise the actual inline exam script without requiring a browser or Laravel.
const template = readFileSync(new URL('../../resources/views/student/exams/show.blade.php', import.meta.url), 'utf8');
const script = template.match(/<script>([\s\S]*?)<\/script>/)[1]
    .replace(/\{\{[^\n]+\}\}/g, '60000')
    .replace(/@json\(route\([^\n]+\)\)/g, '"/incident"');

function element(extra = {}) {
    return { listeners: {}, disabled: false, hidden: true, textContent: '',
        classList: { add() {}, remove() {} },
        addEventListener(name, listener) { this.listeners[name] = listener; }, ...extra };
}

function setup() {
    const button = element();
    const form = element({ submissions: 0, submit() { this.submissions++; }, querySelector: () => button });
    const inputs = ['A', 'B', 'C'].map(value => element({ value, dataset: { saveUrl: '/answer/1' } }));
    const nodes = { 'submit-exam': form, 'save-status': element(), 'exam-timer': element(), 'retry-save': element() };
    const requests = [];
    const window = element();
    let elapsed = 0;
    let tick;
    vm.runInNewContext(script, {
        document: { ...element(), querySelector: () => ({ content: 'csrf' }),
            querySelectorAll: () => inputs, getElementById: id => nodes[id] },
        window, performance: { now: () => elapsed }, confirm: () => true,
        setInterval: callback => { tick = callback; }, setTimeout, clearTimeout, AbortController,
        fetch: (url, options) => new Promise(resolve => requests.push({ url, options, resolve })),
    });
    return { inputs, nodes, form, requests, window, expire() { elapsed = 60001; tick(); } };
}

const settle = () => new Promise(resolve => setImmediate(resolve));
const ok = { ok: true, json: async () => ({ saved: true, saved_at: '10:00:00' }) };

test('rapid changes serialize writes and retain the latest choice', async () => {
    const page = setup();
    page.inputs[0].listeners.change();
    page.inputs[1].listeners.change();
    page.inputs[2].listeners.change();
    assert.equal(page.requests.length, 1);
    page.requests[0].resolve(ok);
    await settle();
    assert.equal(page.requests.length, 2);
    assert.deepEqual(JSON.parse(page.requests[1].options.body), { answer: 'C' });
    page.requests[1].resolve(ok);
    await settle();
    assert.equal(page.nodes['retry-save'].hidden, true);
});

test('manual submission waits for saves and a failed save can be retried', async () => {
    const page = setup();
    page.inputs[0].listeners.change();
    const submission = page.form.listeners.submit({ preventDefault() {} });
    assert.equal(page.form.submissions, 0);
    page.requests[0].resolve({ ok: false, json: async () => ({ message: 'Koneksi gagal' }) });
    await submission;
    assert.equal(page.form.submissions, 0);
    assert.equal(page.inputs[0].disabled, false);
    assert.equal(page.nodes['retry-save'].hidden, false);
    const retry = page.nodes['retry-save'].listeners.click();
    page.requests[1].resolve(ok);
    await retry;
    await page.form.listeners.submit({ preventDefault() {} });
    assert.equal(page.form.submissions, 1);
});

test('deadline triggers a single automatic submission using elapsed time', () => {
    const page = setup();
    page.expire();
    page.expire();
    assert.equal(page.form.submissions, 1);
    assert.equal(page.nodes['exam-timer'].textContent, '00:00:00');
});
