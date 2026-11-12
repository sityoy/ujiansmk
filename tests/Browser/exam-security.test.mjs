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
function setup({ supported = true, denied = false, initiallyLocked = false, storage = new Map(), prepared = true, oldBrowser = false, memory = 8, fakeTimeouts = false } = {}) {
    let focused = true;
    const root = element({ dataset: { stateUrl: '/state', eventUrl: '/events', indexUrl: '/exams', attemptId: '1', locked: initiallyLocked ? '1' : '0' } });
    const nodes = Object.fromEntries(['security-panel', 'security-title', 'security-message', 'security-network', 'security-continue', 'security-count'].map(id => [id, element()]));
    nodes['exam-security'] = root;
    nodes['exam-device-ready'] = element({checked: prepared, focus() { this.focused = true; }});
    nodes['exam-lite-mode'] = element({checked: false});
    nodes['exam-content'] = initiallyLocked ? null : element({ hidden: true, disabled: true, contains: () => true });
    const doc = { ...element(), hidden: false, fullscreenEnabled: supported, fullscreenElement: null,
        activeElement: null, hasFocus: () => focused,
        getElementById: id => nodes[id], querySelector: () => ({ content: 'csrf' }) };
    const win = { ...element(), dispatched: [], innerWidth: 1080, innerHeight: 1920, devicePixelRatio: 2,
        screen: { width: 1080, height: 1920, availWidth: 1080, availHeight: 1920 },
        history: { pushes: 0, pushState() { this.pushes++; } },
        location: { href: '/attempts/1', reloads: 0, reload() { this.reloads++; }, replace(url) { this.replaced = url; } },
        dispatchEvent(event) { this.dispatched.push(event.type); } };
    doc.documentElement = { requestFullscreen: async () => {
        if (denied) throw Error('denied');
        doc.fullscreenElement = doc.documentElement;
        doc.listeners.fullscreenchange();
    }, style: {}, dataset: {} };
    const page = { nodes, doc, win, posts: [], fail: false, hang: false, storage, state: {
        status: 'in_progress', locked: initiallyLocked, enabled: true, violations: initiallyLocked ? 2 : 0,
        deadline: '2026-09-04T11:00:00Z', server_time: '2026-09-04T10:00:00Z',
    } };
    let sequence = 0;
    const processed = new Set();
    const timeouts = new Map();
    win.crypto = oldBrowser ? {getRandomValues(bytes) { bytes.fill(1); bytes[0] = ++sequence; return bytes; }}
        : {randomUUID: () => `00000000-0000-4000-8000-${String(++sequence).padStart(12, '0')}`};
    vm.runInNewContext(script, { document: doc, window: win, navigator: {deviceMemory: memory, hardwareConcurrency: 8}, Uint8Array, performance: { now: () => 0 },
        localStorage: { getItem: key => storage.get(key), setItem: (key, value) => storage.set(key, value) },
        crypto: { randomUUID: () => `00000000-0000-4000-8000-${String(++sequence).padStart(12, '0')}` },
        Event: class { constructor(type) { this.type = type; } },
        setInterval: () => {},
        setTimeout: fakeTimeouts ? (callback, ms) => { const id = Symbol(); timeouts.set(id, {callback, ms}); return id; } : setTimeout,
        clearTimeout: fakeTimeouts ? id => timeouts.delete(id) : clearTimeout,
        AbortController: oldBrowser ? undefined : AbortController,
        fetch: async (url, options) => {
            if (page.hang) return new Promise(() => {});
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
    page.fireTimeout = ms => { for (const [id, timeout] of timeouts) { if (timeout.ms === ms) { timeouts.delete(id); timeout.callback(); } } };
    page.enter = () => nodes['security-continue'].listeners.click();
    page.setFocus = value => { focused = value; };
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

test('window blur and a top-edge system gesture are strict violations', async () => {
    const page = setup();
    await page.enter();
    page.setFocus(false);
    page.win.listeners.blur();
    await new Promise(resolve => setTimeout(resolve, 650));
    await settle();
    assert.equal(page.posts[0].category, 'window_blur');
    assert.equal(page.state.violations, 1);

    page.setFocus(true);
    page.win.listeners.focus();
    await page.enter();
    page.doc.listeners.touchstart({ touches: [{ clientY: 8 }], cancelable: true, preventDefault() {} });
    page.doc.listeners.touchmove({ touches: [{ clientY: 110 }] });
    await settle();
    assert.equal(page.posts[1].category, 'system_gesture');
    assert.equal(page.state.locked, true);
});

test('a large viewport reduction is reported as possible split screen', async () => {
    const page = setup();
    await page.enter();
    page.win.innerWidth = 600;
    page.win.listeners.resize();
    await new Promise(resolve => setTimeout(resolve, 700));
    await settle();
    assert.equal(page.posts[0].category, 'viewport_change');
    assert.equal(page.posts[0].context.viewport_width, 600);
    assert.equal(page.state.violations, 1);
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

test('device preparation must be confirmed before entering exam mode', async () => {
    const page = setup({prepared: false});
    await page.enter();
    assert.equal(page.win.examSecurity.isBlocked(), true);
    assert.equal(page.doc.fullscreenElement, null);
    assert.equal(page.posts.length, 0);
    assert.ok(page.nodes['security-network'].textContent.includes('Centang kesiapan'));
    page.nodes['exam-device-ready'].checked = true;
    await page.enter();
    assert.equal(page.win.examSecurity.isBlocked(), false);
});

test('older browsers send valid persistent event IDs without randomUUID or AbortController', async () => {
    const page = setup({oldBrowser: true});
    await page.enter();
    page.fail = true;
    page.doc.hidden = true;
    page.doc.listeners.visibilitychange();
    await settle();
    const id = page.posts[0].event_id;
    assert.match(id, /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/);
    page.fail = false;
    page.doc.hidden = false;
    await page.enter();
    assert.equal(page.posts[1].event_id, id);
    assert.equal(page.state.violations, 1);
    assert.equal(page.win.examSecurity.isBlocked(), false);
});

test('light mode reduces effects automatically and can be selected manually', async () => {
    const page = setup({memory: 2});
    assert.equal(page.doc.documentElement.dataset.examLite, '1');
    assert.equal(page.nodes['exam-lite-mode'].checked, true);
    page.nodes['exam-lite-mode'].checked = false;
    page.nodes['exam-lite-mode'].listeners.change();
    assert.equal(page.doc.documentElement.dataset.examLite, '0');
    await page.enter();
    page.doc.hidden = true;
    page.doc.listeners.visibilitychange();
    await settle();
    assert.equal(page.state.violations, 1);
});

test('typing and keyboard closure do not count as split screen but width reduction still does', async () => {
    const page = setup();
    await page.enter();
    page.doc.activeElement = {tagName: 'TEXTAREA'};
    page.doc.listeners.focusin();
    page.win.innerHeight = 950;
    page.win.listeners.resize();
    await new Promise(resolve => setTimeout(resolve, 700));
    assert.equal(page.posts.length, 0);
    page.doc.activeElement = null;
    page.doc.listeners.focusout();
    page.win.listeners.resize();
    await new Promise(resolve => setTimeout(resolve, 700));
    assert.equal(page.posts.length, 0);
    page.doc.activeElement = {tagName: 'TEXTAREA'};
    page.win.innerWidth = 600;
    page.win.listeners.resize();
    await new Promise(resolve => setTimeout(resolve, 700));
    await settle();
    assert.equal(page.posts[0].category, 'viewport_change');
});

test('brief focus loss and a cancelled tap at the top edge are not automatically violations', async () => {
    const page = setup();
    await page.enter();
    page.setFocus(false);
    page.win.listeners.blur();
    page.setFocus(true);
    page.win.listeners.focus();
    page.doc.listeners.touchstart({touches: [{clientY: 8}], cancelable: true, preventDefault(){}});
    page.doc.listeners.touchcancel();
    await new Promise(resolve => setTimeout(resolve, 650));
    assert.equal(page.posts.length, 0);
    page.doc.listeners.touchstart({touches: [{clientY: 8}], cancelable: true, preventDefault(){}});
    page.doc.listeners.touchmove({touches: [{clientY: 70}]});
    page.doc.listeners.touchcancel();
    await settle();
    assert.equal(page.posts[0].category, 'system_gesture');
});

test('page departure is retained and a bfcache restore gates questions until confirmed', async () => {
    const page = setup();
    await page.enter();
    page.win.listeners.pagehide();
    await settle();
    assert.equal(page.posts[0].category, 'navigation_attempt');
    page.win.listeners.pageshow({persisted: true});
    await settle();
    assert.equal(page.nodes['exam-content'].hidden, true);
    assert.equal(page.win.examSecurity.isBlocked(), true);
    await page.enter();
    assert.equal(page.win.examSecurity.isBlocked(), false);
});

test('network timeouts on older browsers can be retried without counting a violation', async () => {
    const page = setup({oldBrowser: true, fakeTimeouts: true});
    page.hang = true;
    const entering = page.enter();
    await settle();
    page.fireTimeout(15000);
    await entering;
    assert.equal(page.win.examSecurity.isBlocked(), true);
    assert.equal(page.state.violations, 0);
    page.hang = false;
    await page.enter();
    assert.equal(page.win.examSecurity.isBlocked(), false);
});
