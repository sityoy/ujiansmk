import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const script = readFileSync(new URL('../../public/js/exam-image-preview.js', import.meta.url), 'utf8');

function element(properties = {}) {
    const attributes = new Set();
    const listeners = {};
    return {
        hidden: false,
        addEventListener(name, handler) { listeners[name] = handler; },
        fire(name, event) { listeners[name](event); },
        focus() { this.focused = true; },
        hasAttribute(name) { return attributes.has(name); },
        setAttribute(name) { attributes.add(name); },
        removeAttribute(name) { attributes.delete(name); delete this[name]; },
        ...properties,
    };
}

function setup(nativeDialog = true) {
    const content = element();
    const close = element();
    const preview = element({clientWidth: 240, style: {removeProperty(name) { delete this[name]; }}});
    const viewport = element();
    const zoomOut = element({disabled: true});
    const zoomIn = element({disabled: true});
    const zoomReset = element({disabled: true});
    const scale = element();
    const error = element();
    const title = element();
    const backdrop = element({ hidden: true });
    const image = { alt: 'Lampiran pilihan A', currentSrc: '/storage/a.png' };
    const trigger = element({ querySelector: () => image });
    const dialog = element({
        hidden: true,
        querySelector: selector => ({
            '[data-exam-image-close]': close,
            '[data-exam-image-preview]': preview,
            '[data-exam-image-error]': error,
            '[data-exam-image-viewport]': viewport,
            '[data-exam-image-zoom-out]': zoomOut,
            '[data-exam-image-zoom-in]': zoomIn,
            '[data-exam-image-zoom-reset]': zoomReset,
            '[data-exam-image-scale]': scale,
        })[selector],
        querySelectorAll: () => [close, zoomOut, zoomIn, zoomReset, viewport].filter(control => !control.disabled),
    });
    if (nativeDialog) {
        dialog.showModal = () => dialog.setAttribute('open', '');
        dialog.close = () => dialog.removeAttribute('open');
    }
    const win = element({ examSecurity: { isBlocked: () => content.hidden } });
    const doc = { getElementById: id => ({
        'exam-image-dialog': dialog,
        'exam-image-backdrop': backdrop,
        'exam-content': content,
        'exam-image-title': title,
    })[id] };
    vm.runInNewContext(script, { document: doc, window: win });

    const open = () => content.fire('click', { target: { closest: () => trigger } });
    return { open, content, dialog, close, preview, backdrop, error, title, trigger, win, doc,
        viewport, zoomIn, zoomOut, zoomReset, scale };
}

test('preview uses the displayed attachment and closes inside the exam', () => {
    const page = setup();
    page.open();
    assert.equal(page.dialog.hasAttribute('open'), true);
    assert.equal(page.preview.src, '/storage/a.png');
    assert.equal(page.title.textContent, 'Lampiran pilihan A');
    assert.equal(page.backdrop.hidden, true);
    page.close.fire('click');
    assert.equal(page.dialog.hidden, true);
    assert.equal(page.preview.src, undefined);
    assert.equal(page.trigger.focused, true);
});

test('the exam guard dismisses the preview and prevents opening while blocked', () => {
    const page = setup();
    page.open();
    page.preview.fire('load');
    page.zoomIn.fire('click');
    page.content.hidden = true;
    page.win.fire('examsecurity:blocked');
    page.open();
    assert.equal(page.dialog.hidden, true);
    assert.equal(page.preview.hasAttribute('data-enlarged'), false);
    assert.equal(page.trigger.focused, undefined);
});

test('without native dialog, the close button clears the preview and its backdrop', () => {
    const page = setup(false);
    page.open();
    assert.equal(page.backdrop.hidden, false);
    assert.equal(page.close.focused, true);
    page.preview.fire('error');
    assert.equal(page.error.hidden, false);
    assert.equal(page.zoomIn.disabled, true);
    page.close.fire('click');
    assert.equal(page.backdrop.hidden, true);
    assert.equal(page.dialog.hidden, true);
});

test('zoom scales only the loaded image, keeps the view scrollable, and resets between previews', () => {
    const page = setup();
    page.open();
    assert.equal(page.zoomIn.disabled, true);
    page.preview.fire('load');
    assert.equal(page.scale.textContent, '100%');

    page.zoomIn.fire('click');
    assert.equal(page.preview.style.width, '360px');
    assert.equal(page.preview.hasAttribute('data-enlarged'), true);
    assert.equal(page.scale.textContent, '150%');
    assert.equal(page.dialog.hasAttribute('open'), true);
    page.viewport.scrollTop = 90;
    page.zoomIn.fire('click');
    assert.equal(page.viewport.scrollTop, 120);
    assert.equal(page.preview.style.width, '480px');
    page.zoomOut.fire('click');
    assert.equal(page.scale.textContent, '150%');

    for (let index = 0; index < 5; index++) page.zoomIn.fire('click');
    assert.equal(page.scale.textContent, '300%');
    assert.equal(page.preview.style.width, '720px');
    assert.equal(page.zoomIn.disabled, true);
    page.zoomReset.fire('click');
    assert.equal(page.scale.textContent, '100%');
    assert.equal(page.preview.style.width, undefined);
    assert.equal(page.viewport.scrollTop, 0);
    assert.equal(page.zoomReset.disabled, true);

    page.zoomIn.fire('click');
    page.close.fire('click');
    page.open();
    assert.equal(page.scale.textContent, '100%');
    assert.equal(page.preview.style.width, undefined);
    assert.equal(page.zoomIn.disabled, true);
});

test('the fallback viewer allows keyboard access to zoom and retains Escape to close', () => {
    const page = setup(false);
    page.open();
    page.preview.fire('load');
    page.doc.activeElement = page.close;
    page.dialog.fire('keydown', {key: 'Tab', preventDefault() {}});
    assert.equal(page.zoomIn.focused, true);
    page.dialog.fire('keydown', {key: 'Escape', preventDefault() {}});
    assert.equal(page.dialog.hidden, true);
});
