import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const script = readFileSync(new URL('../../public/js/exam-text-size.js', import.meta.url), 'utf8');

function setup(stored = null, { blocked = false, storageUnavailable = false } = {}) {
    const properties = new Map();
    const button = () => ({
        disabled: false,
        addEventListener(_name, onClick) { this.onClick = onClick; },
        click() { if (!this.disabled) this.onClick(); },
    });
    const decrease = button(), increase = button(), reset = button(), percentage = {};
    const controls = { querySelector: selector => ({
        '[data-exam-text-decrease]': decrease,
        '[data-exam-text-increase]': increase,
        '[data-exam-text-reset]': reset,
        '[data-exam-text-scale]': percentage,
    })[selector] };
    const content = {
        hidden: blocked, disabled: blocked,
        style: { setProperty: (name, value) => properties.set(name, value) },
    };
    const document = {
        getElementById: id => id === 'exam-content' ? content : null,
        querySelector: selector => selector === '[data-exam-text-controls]' ? controls : null,
    };
    const localStorage = {
        getItem() {
            if (storageUnavailable) throw new Error('Storage blocked');
            return stored;
        },
        setItem(_key, value) {
            if (storageUnavailable) throw new Error('Storage blocked');
            stored = value;
        },
    };
    vm.runInNewContext(script, { document, localStorage });
    return { decrease, increase, reset, percentage, properties, content, saved: () => stored };
}

test('the controls scale only exam content and allow reducing, increasing and resetting', () => {
    const page = setup();
    assert.equal(page.percentage.textContent, '100%');
    page.decrease.click();
    assert.equal(page.percentage.textContent, '90%');
    assert.equal(page.decrease.disabled, true);
    assert.equal(page.properties.get('--exam-text-scale'), '0.9');

    for (let step = 0; step < 10; step++) page.increase.click();
    assert.equal(page.percentage.textContent, '200%');
    assert.equal(page.increase.disabled, true);
    assert.deepEqual([...page.properties], [['--exam-text-scale', '2']]);
    assert.equal(setup(page.saved()).percentage.textContent, '200%');

    page.reset.click();
    assert.equal(page.percentage.textContent, '100%');
    assert.equal(page.reset.disabled, true);
    assert.equal(page.saved(), '100');
});

test('invalid or unavailable storage keeps sizing available once the exam starts', () => {
    const page = setup('999', { blocked: true, storageUnavailable: true });
    page.increase.click();
    assert.equal(page.percentage.textContent, '100%');

    page.content.hidden = false;
    page.content.disabled = false;
    page.increase.click();
    assert.equal(page.percentage.textContent, '115%');
    assert.equal(page.properties.get('--exam-text-scale'), '1.15');
    assert.equal(setup('999').percentage.textContent, '100%');
});
