import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const script = readFileSync(new URL('../../public/js/trial-attendance.js', import.meta.url), 'utf8');

function element(properties = {}) {
    const listeners = {};
    return {
        addEventListener(name, handler) { listeners[name] = handler; },
        fire(name) { return listeners[name](); },
        ...properties,
    };
}

function setup({permissionDenied = false, apiError = false} = {}) {
    const status = element();
    const check = element();
    const start = element({disabled: true});
    const credential = element({readOnly: false, reportValidity: () => true});
    const data = new Map([['_token', 'session-token']]);
    const requests = [];
    const form = {
        dataset: {trialAttendanceUrl: '/tryout/sejarah/attendance'},
        querySelector: selector => ({
            '[data-location-check]': check,
            '[data-location-status]': status,
            'button[type="submit"]': start,
        })[selector],
        querySelectorAll: () => [credential],
    };
    const geolocation = {
        getCurrentPosition: (success, failure, options) => {
            assert.equal(options.enableHighAccuracy, true);
            if (permissionDenied) failure({code: 1});
            else success({coords: {latitude: -6.2, longitude: 106.8, accuracy: 12}});
        },
    };
    vm.runInNewContext(script, {
        document: {querySelector: () => form},
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
                : {message: 'Absensi simulasi terverifikasi.'};
            return {ok: !apiError, json: async () => result};
        },
    });

    return {check, start, status, credential, requests, data};
}

test('verified GPS enables the start button and editing credentials requires a new check', async () => {
    const page = setup();
    await page.check.fire('click');
    assert.equal(page.requests[0].url, '/tryout/sejarah/attendance');
    assert.equal(page.requests[0].options.method, 'POST');
    assert.deepEqual([...page.data], [
        ['_token', 'session-token'], ['latitude', -6.2], ['longitude', 106.8], ['accuracy_meters', 12],
    ]);
    assert.equal(page.start.disabled, false);
    assert.equal(page.status.textContent, 'Absensi simulasi terverifikasi.');
    assert.equal(page.credential.readOnly, false);
    page.credential.fire('input');
    assert.equal(page.start.disabled, true);
});

test('denied geolocation or a rejected check-in allows retry and keeps start disabled', async () => {
    const denied = setup({permissionDenied: true});
    await denied.check.fire('click');
    assert.equal(denied.start.disabled, true);
    assert.equal(denied.check.disabled, false);
    assert.equal(denied.requests.length, 0);
    assert.match(denied.status.textContent, /Izin lokasi ditolak/);

    const rejected = setup({apiError: true});
    await rejected.check.fire('click');
    assert.equal(rejected.start.disabled, true);
    assert.equal(rejected.check.disabled, false);
    assert.equal(rejected.status.textContent, 'Posisi di luar radius.');
});
