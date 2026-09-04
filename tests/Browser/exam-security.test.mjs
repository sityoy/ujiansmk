import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
import test from 'node:test';

const script = readFileSync(new URL('../../public/js/exam-security.js', import.meta.url), 'utf8');
function element(extra = {}) {
    return { listeners: {}, style: {}, hidden: false, disabled: false, textContent: '',
        addEventListener(name, listener) { this.listeners[name] = listener; }, ...extra };
}
const settle = () => new Promise(resolve => setImmediate(resolve));
function setup({ supported = true, denied = false, initiallyLocked = false, storage = new Map() } = {}) {
    const root = element({ dataset: { stateUrl: '/state', eventUrl: '/events', indexUrl: '/exams', attemptId: '1', locked: initiallyLocked ? '1' : '0' } });
    const nodes = Object.fromEntries(['security-panel', 'security-title', 'security-message', 'security-network', 'security-continue', 'security-count'].map(id => [id, element()]));
    nodes['exam-security'] = root;
    nodes['exam-content'] = initiallyLocked ? null : element({ hidden: true, disabled: true, contains: () => true });
    const doc = { ...element(), hidden: false, fullscreenEnabled: supported, fullscreenElement: null,
        getElementById: id => nodes[id], querySelector: () => ({ content: 'csrf' }) };
    const win = { ...element(), dispatched: [], location: { reloads: 0, reload() { this.reloads++; }, replace(url) { this.replaced = url; } },
        dispatchEvent(event) { this.dispatched.push(event.type); } };
    doc.documentElement = { requestFullscreen: async () => {
        if (denied) throw Error('denied');
        doc.fullscreenElement = doc.documentElement;
        doc.listeners.fullscreenchange();
    } };
    const page = { nodes, doc, win, posts: [], fail: false, storage, state: {
        status: 'in_progress', locked: initiallyLocked, enabled: true, violations: initiallyLocked ? 2 : 0,
        deadline: '2026-09-04T11:00:00Z', server_time: '2026-09-04T10:00:00Z',
    } };
    let sequence = 0;
    const processed = new Set();
    vm.runInNewContext(script, { document: doc, window: win, performance: { now: () => 0 },
        localStorage: { getItem: key => storage.get(key), setItem: (key, value) => storage.set(key, value) },
        crypto: { randomUUID: () => `00000000-0000-4000-8000-${String(++sequence).padStart(12, '0')}` },
        Event: class { constructor(type) { this.type = type; } },
        setInterval: () => {}, setTimeout, clearTimeout, AbortController,
        fetch: async (url, options) => {
            if (url === '/events') page.posts.push(JSON.parse(options.body));
            if (page.fail) throw Error('offline');
            if (url === '/events') {
                const event = JSON.parse(options.body);
                if (!processed.has(event.event_id)) {
                    processed.add(event.event_id);
                    page.state.violations = Math.min(2, page.state.violations + 1);
                    page.state.locked = page.state.violations >= 2;
                }
            }
            return { ok: true, json: async () => ({ ...page.state }) };
        },
    });
    page.enter = () => nodes['security-continue'].listeners.click();
    return page;
}

test('fullscreen and hidden signals from one departure are only sent once', async () => {
    const page = setup();
    await page.enter();
    assert.equal(page.win.examSecurity.isBlocked(), false);
    page.doc.hidden = true;
    page.doc.listeners.visibilitychange();
    page.doc.fullscreenElement = null;
    page.doc.listeners.fullscreenchange();
    await settle();
    assert.equal(page.posts.length, 1);
    assert.equal(page.state.violations, 1);
    assert.equal(page.win.examSecurity.isBlocked(), true);
});

test('second departure locks and a confirmation cannot unlock the server state', async () => {
    const page = setup();
    await page.enter();
    page.doc.fullscreenElement = null;
    page.doc.listeners.fullscreenchange();
    await settle();
    await page.enter();
    page.doc.fullscreenElement = null;
    page.doc.listeners.fullscreenchange();
    await settle();
    assert.equal(page.state.locked, true);
    await page.enter();
    assert.equal(page.nodes['exam-content'].hidden, true);
    assert.equal(page.nodes['exam-content'].disabled, true);
    assert.equal(page.win.examSecurity.isBlocked(), true);
});

test('offline event retains its ID for retry without adding connection violations', async () => {
    const page = setup();
    await page.enter();
    page.fail = true;
    page.doc.hidden = true;
    page.doc.listeners.visibilitychange();
    await settle();
    const eventId = page.posts[0].event_id;
    assert.equal(page.state.violations, 0);
    assert.equal(page.win.examSecurity.isBlocked(), true);
    assert.equal(JSON.parse(page.storage.get('exam-security:1'))[0].event_id, eventId);
    page.fail = false;
    page.doc.hidden = false;
    await page.enter();
    assert.equal(page.posts[1].event_id, eventId);
    assert.equal(page.state.violations, 1);
    assert.equal(page.win.examSecurity.isBlocked(), false);
});

test('reloading restores an undelivered event with the same ID', async () => {
    const id = '00000000-0000-4000-8000-000000000009';
    const page = setup({ storage: new Map([['exam-security:1', JSON.stringify([{ event_id: id, category: 'tab_hidden' }])]]) });
    await settle();
    assert.equal(page.posts[0].event_id, id);
    assert.equal(page.state.violations, 1);
});

test('unsupported fullscreen falls back to visibility; rejected permission is not a violation', async () => {
    const unsupported = setup({ supported: false });
    await unsupported.enter();
    assert.equal(unsupported.win.examSecurity.isBlocked(), false);
    assert.equal(unsupported.state.violations, 0);
    const denied = setup({ denied: true });
    await denied.enter();
    assert.equal(denied.win.examSecurity.isBlocked(), true);
    assert.equal(denied.posts.length, 0);
});

test('intentional submission confirmation does not count a fullscreen exit', async () => {
    const page = setup();
    await page.enter();
    page.win.examSecurity.pause();
    page.doc.fullscreenElement = null;
    page.doc.listeners.fullscreenchange();
    page.win.examSecurity.returnToExam();
    assert.equal(page.posts.length, 0);
    assert.equal(page.win.examSecurity.isBlocked(), true);
});

test('locked page reloads only when a supervisor releases it and redirects when finalized', async () => {
    const page = setup({ initiallyLocked: true });
    await settle();
    page.state.locked = false;
    await page.win.examSecurity.check();
    assert.equal(page.win.location.reloads, 1);
    page.state.status = 'submitted';
    await page.win.examSecurity.check();
    assert.equal(page.win.location.replaced, '/exams');
});
