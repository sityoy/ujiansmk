import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const script = readFileSync(new URL('../../public/js/exam-attendance.js', import.meta.url), 'utf8');

function element(properties = {}) {
    const listeners = {};
    return {
        addEventListener(name, handler) { listeners[name] = handler; },
        fire(name, event) { return listeners[name](event); },
        ...properties,
    };
}

function setup({permissionDenied = false, apiError = false, missingVerification = false} = {}) {
    const status = element();
    const feedback = { dataset: {}, querySelector: () => status };
    const check = element();
    const start = element({disabled: true});
    const credential = element({readOnly: false, reportValidity: () => true});
    const data = new Map([['_token', 'session-token']]);
    const requests = [];
    const form = {
        dataset: {trialAttendanceUrl: '/tryout/sejarah/attendance'},
        querySelector: selector => ({
            '[data-location-check]': check,
            '[data-attendance-feedback]': feedback,
            'button[type="submit"]': start,
        })[selector],
        querySelectorAll: () => [credential],
    };
    const geolocation = {
        getCurrentPosition: (success, failure, options) => {
            assert.equal(options.enableHighAccuracy, true);
            assert.equal(options.maximumAge, 0);
            if (permissionDenied) failure({code: 1});
            else success({coords: {latitude: -6.2, longitude: 106.8, accuracy: 12}});
        },
    };
    vm.runInNewContext(script, {
        document: {querySelector: () => form, querySelectorAll: () => []},
        navigator: {geolocation},
        FormData: function (source) {
            assert.equal(source, form);
            return data;
        },
        setTimeout,
        clearTimeout,
        fetch: async (url, options) => {
            requests.push({url, options});
            const result = apiError
                ? {errors: {location: ['Posisi di luar radius.']}}
                : {verified: !missingVerification, message: 'Absensi simulasi terverifikasi.'};
            return {ok: !apiError, json: async () => result};
        },
    });

    return {check, start, status, feedback, credential, requests, data};
}

test('verified GPS enables the start button and editing credentials requires a new check', async () => {
    const page = setup();
    const checking = page.check.fire('click');
    assert.equal(page.feedback.dataset.state, 'checking');
    assert.equal(page.start.disabled, true);
    await checking;
    assert.equal(page.requests[0].url, '/tryout/sejarah/attendance');
    assert.equal(page.requests[0].options.method, 'POST');
    assert.deepEqual([...page.data], [
        ['_token', 'session-token'], ['latitude', -6.2], ['longitude', 106.8], ['accuracy_meters', 12],
    ]);
    assert.equal(page.start.disabled, false);
    assert.equal(page.feedback.dataset.state, 'success');
    assert.equal(page.status.textContent, 'Absensi simulasi terverifikasi.');
    assert.equal(page.credential.readOnly, false);
    page.credential.fire('input');
    assert.equal(page.start.disabled, true);
    assert.equal(page.feedback.dataset.state, 'pending');
});

test('denied geolocation or a rejected check-in allows retry and keeps start disabled', async () => {
    const denied = setup({permissionDenied: true});
    await denied.check.fire('click');
    assert.equal(denied.start.disabled, true);
    assert.equal(denied.feedback.dataset.state, 'error');
    assert.equal(denied.check.disabled, false);
    assert.equal(denied.requests.length, 0);
    assert.match(denied.status.textContent, /Izin lokasi ditolak/);
    assert.equal(denied.check.textContent, 'Coba lagi');

    const rejected = setup({apiError: true});
    await rejected.check.fire('click');
    assert.equal(rejected.start.disabled, true);
    assert.equal(rejected.feedback.dataset.state, 'error');
    assert.equal(rejected.check.disabled, false);
    assert.equal(rejected.status.textContent, 'Posisi di luar radius.');
});

test('an HTTP success without confirmed attendance stays red and cannot start the trial', async () => {
    const page = setup({missingVerification: true});
    await page.check.fire('click');
    assert.equal(page.feedback.dataset.state, 'error');
    assert.equal(page.start.disabled, true);
});

test('core attendance reports GPS errors near the button and submits successful coordinates', async () => {
    const status = element();
    const check = element({ disabled: false });
    const feedback = { dataset: {}, querySelector: () => status };
    const form = element({
        elements: { latitude: {}, longitude: {}, accuracy_meters: {} },
        submissions: 0,
        submit() { this.submissions++; },
        querySelector: selector => selector === '[data-location-check]' ? check : feedback,
    });
    let deny = true;
    vm.runInNewContext(script, {
        document: {querySelector: () => null, querySelectorAll: () => [form]},
        navigator: {geolocation: {getCurrentPosition: (success, failure) => {
            if (deny) failure({code: 1});
            else success({coords: {latitude: -6.2, longitude: 106.8, accuracy: 12}});
        }}},
        setTimeout,
        clearTimeout,
    });
    let prevented = 0;
    const submit = {preventDefault: () => prevented++};
    await form.fire('submit', submit);
    assert.equal(check.disabled, false);
    assert.equal(feedback.dataset.state, 'error');
    assert.equal(form.submissions, 0);
    deny = false;
    await form.fire('submit', submit);
    assert.equal(prevented, 2);
    assert.equal(feedback.dataset.state, 'checking');
    assert.equal(form.submissions, 1);
    assert.deepEqual(Object.values(form.elements).map(input => input.value), [-6.2, 106.8, 12]);
});

test('a browser that never returns a position releases the GPS button for retry', async () => {
    const status = element();
    const feedback = {dataset: {}, querySelector: () => status};
    const button = element({disabled: false});
    const form = element({querySelector: selector => selector === '[data-location-check]' ? button : feedback});
    let expire;
    vm.runInNewContext(script, {
        document: {querySelector: () => null, querySelectorAll: () => [form]},
        navigator: {geolocation: {getCurrentPosition() {}}},
        setTimeout(callback, delay) {
            assert.equal(delay, 12000);
            expire = callback;
        },
    });
    const attempt = form.fire('submit', {preventDefault() {}});
    assert.equal(button.disabled, true);
    expire();
    await attempt;
    assert.equal(button.disabled, false);
    assert.equal(button.textContent, 'Coba lagi');
    assert.equal(feedback.dataset.state, 'error');
    assert.match(status.textContent, /Nyalakan GPS presisi/);
});
