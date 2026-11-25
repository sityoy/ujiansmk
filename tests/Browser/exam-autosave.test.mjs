import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
import test from 'node:test';

// Exercise the actual inline exam script without requiring a browser or Laravel.
const coreTemplate = readFileSync(new URL('../../resources/views/student/exams/show.blade.php', import.meta.url), 'utf8');
const trialTemplate = readFileSync(new URL('../../resources/views/tryouts/show.blade.php', import.meta.url), 'utf8');
const submissionScript = readFileSync(new URL('../../public/js/exam-submission.js', import.meta.url), 'utf8');
const examScript = template => template.match(/<script>([\s\S]*?)<\/script>/)[1]
    .replace(/\{\{[^\n]+\}\}/g, '60000')
    .replace(/@json\(route\([^\n]+\)\)/g, '"/incident"');

function element(extra = {}) {
    return { listeners: {}, disabled: false, hidden: true, textContent: '',
        classList: { add() {}, remove() {} },
        focus() { this.focused = true; },
        addEventListener(name, listener) { this.listeners[name] = listener; }, ...extra };
}

function setup({trial = false, oldBrowser = false, written = false, grouped = false, essayOnlyAfterPg = false, resumed = false, multiple = false} = {}) {
    const button = element();
    const form = element({ hidden: false, submissions: 0, dataset: { questionCount: written ? '2' : '1' }, submit() { this.submissions++; }, querySelector: () => button });
    const inputs = ['A', 'B', 'C'].map(value => element({ type: 'radio', value, checked: value === 'A', dataset: { saveUrl: '/answer/1' } }));
    const selectionValue = { value: '' };
    if (multiple) {
        const group = {
            querySelector: () => trial ? selectionValue : null,
            querySelectorAll: () => inputs.filter(input => input.checked),
        };
        inputs.forEach(input => Object.assign(input, {type: 'checkbox', checked: false, closest: () => group}));
    }

    const sections = [];
    if (grouped) {
        inputs[0].checked = resumed;
        const short = element({ tagName: 'TEXTAREA', value: resumed ? 'Jawaban tersimpan' : '  ', dataset: { saveUrl: '/answer/2' } });
        const essay = element({ tagName: 'TEXTAREA', value: '', dataset: { saveUrl: '/answer/3' } });
        const groups = essayOnlyAfterPg ? [inputs.slice(), [essay]] : [inputs.slice(), [short], [essay]];
        form.dataset.questionCount = String(groups.length);
        sections.push(...groups.map((group, index) => {
            const controls = {
                '[data-exam-section-message]': element(),
                '[data-exam-section-back]': index > 0 ? element() : null,
                '[data-exam-section-next]': index < groups.length - 1 ? element() : null,
                '[data-exam-section-heading]': element({ getBoundingClientRect: () => ({ top: 150 }) }),
            };
            return element({
                dataset: { questionCount: '1' },
                querySelector: selector => controls[selector],
                querySelectorAll: () => group,
            });
        }));
        inputs.push(...groups.slice(1).flat());
    } else if (written) {
        inputs.push(element({ tagName: 'TEXTAREA', value: '  ', dataset: { saveUrl: '/answer/2' } }));
    }
    const cancel = element();
    const confirm = element();
    const trialSubmitPanel = element({ hidden: false });
    const dialog = element({ querySelector: selector => selector === '[data-submit-cancel]' ? cancel : confirm,
        setAttribute() { this.open = true; }, removeAttribute() { this.open = false; } });
    if (!oldBrowser) {
        dialog.showModal = () => { dialog.open = true; };
        dialog.close = () => { dialog.open = false; };
    }
    const nodes = { 'trial-form': form, 'timer': element(), 'submit-exam': form, 'save-status': element(), 'exam-timer': element(),
        'retry-save': element(), 'exam-submit-dialog': dialog, 'exam-submit-backdrop': element(), 'exam-submit-message': element(),
        'trial-submit-panel': trialSubmitPanel };
    const requests = [];
    const window = element({ scrollY: 0, scrollTo() {} });
    let blocked = false;
    window.examSecurity = { isBlocked: () => blocked };
    let elapsed = 0;
    let tick;
    vm.runInNewContext(submissionScript + '\n' + examScript(trial ? trialTemplate : coreTemplate), {
        document: { ...element(),
            querySelector: selector => selector === '.exam-toolbar' ? { getBoundingClientRect: () => ({ height: 80 }) } : { content: 'csrf' },
            querySelectorAll: selector => selector === '[data-exam-section]' ? sections : inputs, getElementById: id => nodes[id] },
        window, performance: { now: () => elapsed },
        setInterval: callback => { tick = callback; }, setTimeout, clearTimeout, AbortController: oldBrowser ? undefined : AbortController,
        fetch: (url, options) => new Promise(resolve => requests.push({ url, options, resolve })),
    });
    const click = { preventDefault() {} };
    return { inputs, selectionValue, nodes, form, requests, window, dialog, button, cancel, sections,
        open: () => form.listeners.submit(click), confirm: () => confirm.listeners.click(),
        block() { blocked = true; window.listeners['examsecurity:blocked'](); },
        expire() { elapsed = 60001; tick(); } };
}

const settle = () => new Promise(resolve => setImmediate(resolve));
const ok = { ok: true, json: async () => ({ saved: true, saved_at: '10:00:00' }) };

test('core proceeds from multiple choice to short answers to essay after saving each section', async () => {
    const page = setup({grouped: true});
    const [pg, short, essay] = page.sections;
    const show = () => page.sections.map(section => !section.hidden);
    const pgNext = pg.querySelector('[data-exam-section-next]');
    assert.deepEqual(show(), [true, false, false]);
    assert.equal(page.form.hidden, true);

    await pgNext.listeners.click();
    assert.match(pg.querySelector('[data-exam-section-message]').textContent, /1 soal/);
    assert.deepEqual(show(), [true, false, false]);

    page.inputs[0].checked = true;
    page.inputs[0].listeners.change();
    const failedNext = pgNext.listeners.click();
    page.requests.at(-1).resolve({ ok: false, json: async () => ({ message: 'Koneksi gagal' }) });
    await failedNext;
    assert.deepEqual(show(), [true, false, false]);
    assert.match(pg.querySelector('[data-exam-section-message]').textContent, /belum tersimpan/);

    const pgSaved = pgNext.listeners.click();
    page.requests.at(-1).resolve(ok);
    await pgSaved;
    assert.deepEqual(show(), [false, true, false]);
    assert.equal(short.querySelector('[data-exam-section-heading]').focused, true);

    const next = short.querySelector('[data-exam-section-next]');
    await next.listeners.click();
    assert.deepEqual(show(), [false, true, false]);
    page.inputs[3].value = 'Jawaban singkat';
    page.inputs[3].listeners.change();
    const shortSaved = next.listeners.click();
    page.requests.at(-1).resolve(ok);
    await shortSaved;
    assert.deepEqual(show(), [false, false, true]);
    assert.equal(page.form.hidden, false);

    essay.querySelector('[data-exam-section-back]').listeners.click();
    assert.deepEqual(show(), [false, true, false]);
    assert.equal(page.inputs[3].value, 'Jawaban singkat');
    assert.equal(page.form.hidden, true);
});

test('a core exam can go directly from multiple choice to essay when there is no short-answer section', async () => {
    const page = setup({grouped: true, essayOnlyAfterPg: true});
    page.inputs[0].checked = true;
    await page.sections[0].querySelector('[data-exam-section-next]').listeners.click();
    assert.equal(page.sections[1].hidden, false);
    assert.equal(page.form.hidden, false);
});

test('a resumed exam opens the first section with unanswered questions', () => {
    const page = setup({grouped: true, resumed: true});
    assert.deepEqual(page.sections.map(section => !section.hidden), [false, false, true]);
    assert.equal(page.inputs[3].value, 'Jawaban tersimpan');
});

test('trial moves to essay while keeping the exam form and timer visible and saves the written answer', async () => {
    const page = setup({trial: true, grouped: true, essayOnlyAfterPg: true});
    const [pg, essay] = page.sections;
    assert.equal(page.nodes['trial-submit-panel'].hidden, true);
    assert.equal(page.form.hidden, false);
    page.inputs[0].checked = true;
    await pg.querySelector('[data-exam-section-next]').listeners.click();
    assert.equal(essay.hidden, false);
    assert.equal(page.nodes['trial-submit-panel'].hidden, false);

    const text = page.inputs[3];
    text.value = 'Jawaban Esai\nBaris Kedua';
    text.listeners.input();
    page.open();
    const submitted = page.confirm();
    assert.equal(page.form.submissions, 0);
    assert.deepEqual(JSON.parse(page.requests[0].options.body), { answer: text.value });
    page.requests[0].resolve(ok);
    await submitted;
    assert.equal(page.form.submissions, 1);
});

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
    page.open();
    const submission = page.confirm();
    assert.equal(page.form.submissions, 0);
    page.requests[0].resolve({ ok: false, json: async () => ({ message: 'Koneksi gagal' }) });
    await submission;
    assert.equal(page.form.submissions, 0);
    assert.equal(page.inputs[0].disabled, false);
    assert.equal(page.nodes['retry-save'].hidden, false);
    const retry = page.nodes['retry-save'].listeners.click();
    page.requests[1].resolve(ok);
    await retry;
    page.open();
    await page.confirm();
    assert.equal(page.form.submissions, 1);
});

test('deadline triggers a single automatic submission using elapsed time', () => {
    const page = setup({grouped: true});
    assert.equal(page.form.hidden, true);
    page.expire();
    page.expire();
    assert.equal(page.form.submissions, 1);
    assert.equal(page.nodes['exam-timer'].textContent, '00:00:00');
});

for (const trial of [false, true]) {
    test(`${trial ? 'trial' : 'core'} counts unanswered radio groups and can cancel without changing exam state`, () => {
        const page = setup({trial, oldBrowser: trial});
        page.inputs[0].checked = false;
        page.open();
        assert.match(page.nodes['exam-submit-message'].textContent, /1 soal yang belum terjawab/);
        assert.equal(page.dialog.hidden, true);
        page.inputs[1].checked = true;
        page.open();
        assert.equal(page.dialog.hidden, false);
        assert.equal(page.cancel.focused, true);
        page.cancel.listeners.click({ preventDefault() {} });
        assert.equal(page.dialog.hidden, true);
        assert.equal(page.button.focused, true);
        assert.equal(page.inputs[1].checked, true);
        assert.equal(page.window.examAutoSubmit, false);
        assert.equal(page.form.submissions, 0);
        assert.equal(page.requests.length, 0);
    });

    test(`${trial ? 'trial' : 'core'} saves the full checkbox selection and clears an unanswered question`, async () => {
        const page = setup({trial, multiple: true});
        page.open();
        assert.equal(page.dialog.hidden, true);
        assert.match(page.nodes['exam-submit-message'].textContent, /1 soal yang belum terjawab/);
        const [first, , third] = page.inputs;
        third.checked = true;
        third.listeners.change();
        first.checked = true;
        first.listeners.change();
        assert.deepEqual(JSON.parse(page.requests[0].options.body), {answer: 'C'});
        page.requests[0].resolve(ok);
        await settle();
        assert.deepEqual(JSON.parse(page.requests[1].options.body), {answer: 'AC'});
        if (trial) assert.equal(page.selectionValue.value, 'AC');
        page.requests[1].resolve(ok);
        await settle();
        page.open();
        assert.equal(page.dialog.hidden, false);
        page.cancel.listeners.click({preventDefault() {}});

        third.checked = false;
        third.listeners.change();
        first.checked = false;
        first.listeners.change();
        page.requests[2].resolve(ok);
        await settle();
        assert.deepEqual(JSON.parse(page.requests[3].options.body), {answer: ''});
        if (trial) assert.equal(page.selectionValue.value, '');
        page.requests[3].resolve(ok);
        await settle();
        page.open();
        assert.equal(page.dialog.hidden, true);
        assert.match(page.nodes['exam-submit-message'].textContent, /1 soal yang belum terjawab/);
    });

    test(`${trial ? 'trial' : 'core'} saves answers when AbortController is unavailable`, async () => {
        const page = setup({trial, oldBrowser: true});
        page.inputs[0].listeners.change();
        assert.equal(page.requests.length, 1);
        page.requests[0].resolve(ok);
        await settle();
        assert.equal(page.nodes['retry-save'].hidden, true);
        assert.ok(page.nodes['save-status'].textContent.includes('Tersimpan pukul'));
    });

    test(`${trial ? 'trial' : 'core'} does not submit twice if the deadline passes during manual saving`, async () => {
        const page = setup({trial});
        page.inputs[0].listeners.change();
        page.open();
        const submission = page.confirm();
        page.expire();
        assert.equal(page.form.submissions, 1);
        page.requests[0].resolve(ok);
        await submission;
        assert.equal(page.form.submissions, 1);
    });
}

test('trial keeps pending answers after a failed save and permits retry before submission', async () => {
    const page = setup({trial: true});
    page.inputs[0].listeners.change();
    page.open();
    const submission = page.confirm();
    page.requests[0].resolve({ok:false, json: async () => ({message:'Jaringan terputus'})});
    await submission;
    assert.equal(page.form.submissions, 0);
    assert.equal(page.nodes['retry-save'].hidden, false);
    assert.equal(page.inputs[0].disabled, false);
    const retry = page.nodes['retry-save'].listeners.click();
    page.requests[1].resolve(ok);
    await retry;
    page.open();
    await page.confirm();
    assert.equal(page.form.submissions, 1);
});

test('a blank written answer blocks manual submission and a security warning dismisses the confirmation', async () => {
    const page = setup({written: true});
    page.open();
    assert.match(page.nodes['exam-submit-message'].textContent, /1 soal yang belum terjawab/);
    assert.equal(page.dialog.hidden, true);
    page.inputs[3].value = '0';
    page.open();
    assert.equal(page.dialog.hidden, false);
    page.block();
    assert.equal(page.dialog.hidden, true);
    await page.confirm();
    assert.equal(page.form.submissions, 0);
});
