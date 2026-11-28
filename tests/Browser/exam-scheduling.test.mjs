import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const script = readFileSync(new URL('../../public/js/exam-scheduling.js', import.meta.url), 'utf8');

function timeInput(value = '') {
    return {
        value,
        get valueAsNumber() { return this.value ? Date.parse(this.value + 'Z') : NaN; },
        addEventListener(event, listener) { this[event] = listener; },
        setCustomValidity(message) { this.message = message; },
        change(value) { this.value = value; this.input(); },
    };
}

function schedule(start, end) {
    const fields = {
        '[name="starts_at"]': timeInput(start),
        '[name="ends_at"]': timeInput(end),
        '[data-session-duration]': { textContent: '' },
    };
    return {
        start: fields['[name="starts_at"]'],
        end: fields['[name="ends_at"]'],
        duration: fields['[data-session-duration]'],
        dataset: {minDuration: '10', maxDuration: '600'},
        querySelector: selector => fields[selector],
    };
}

test('each schedule previews its own duration and rejects invalid end times', () => {
    const editable = schedule('2026-11-25T08:00', '2026-11-25T09:15');
    const other = schedule('2026-11-25T13:00', '2026-11-25T14:00');
    vm.runInNewContext(script, {document: {querySelectorAll: () => [editable, other]}});

    assert.equal(editable.duration.textContent, '75 menit');
    editable.start.change('2026-11-25T08:30');
    assert.equal(editable.duration.textContent, '45 menit');
    editable.end.change('2026-11-25T08:00');
    assert.match(editable.end.message, /setelah waktu mulai/);
    editable.end.change('2026-11-26T01:00');
    assert.match(editable.end.message, /sama dengan tanggal mulai/);
    editable.end.change('2026-11-25T08:40:30');
    assert.match(editable.end.message, /10–600 menit penuh/);
    editable.end.change('');
    assert.equal(editable.duration.textContent, '—');
    editable.end.change('2026-11-25T09:20');
    assert.equal(editable.duration.textContent, '50 menit');
    assert.equal(editable.end.message, '');
    assert.equal(other.duration.textContent, '60 menit');
});
