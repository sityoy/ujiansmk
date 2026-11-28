import assert from 'node:assert/strict';
import test from 'node:test';
import { paginateDocument, setPngDensity } from '../../resources/js/print-document.js';
import { buildPdf, crc32 } from '../../resources/js/question-bank-report.js';

test('named sections and repeated table headers retain every point of the print document', () => {
    const pages = paginateDocument(250, 100, {
        breaks: [150],
        boundaries: [50, 90, 125, 150, 190, 210],
        tables: [{ bottom: 125, header: { top: 15, height: 15 } }],
    });
    assert.deepEqual(pages, [
        { top: 0, height: 90, header: null },
        { top: 90, height: 60, header: { top: 15, height: 15 } },
        { top: 150, height: 100, header: null },
    ]);
});

test('score rows stay with their history when the pair fits and exceptionally long histories carry across pages', () => {
    const pages = paginateDocument(375, 120, {
        boundaries: [50, 145, 185, 265, 335, 368],
        tables: [{ bottom: 368, header: { top: 10, height: 20 } }],
    });
    assert.equal(pages[0].height, 50);
    assert.equal(pages[1].top, 50);
    assert.equal(pages[1].height, 95);
    assert.ok(pages.every(page => page.height + (page.header?.height ?? 0) <= 120));
    assert.equal(pages.reduce((sum, page) => sum + page.height, 0), 375);
    for (let i = 1; i < pages.length; i++) {
        assert.equal(pages[i].top, pages[i - 1].top + pages[i - 1].height);
    }
});

test('the PDF can keep the original print paper size in portrait, landscape and F4', () => {
    const images = [[595, 842], [842, 595], [609, 935]].map(([paperWidth, paperHeight]) => ({
        paperWidth, paperHeight, width: 1500, height: 2000, bytes: new Uint8Array([255, 216, 255, 217]),
    }));
    const pdf = Buffer.from(buildPdf(images)).toString('latin1');
    for (const { paperWidth: width, paperHeight: height } of images) {
        assert.ok(pdf.includes(`/MediaBox [0 0 ${width} ${height}]`));
        assert.ok(pdf.includes(`q ${width} 0 0 ${height} 0 0 cm /Image Do Q`));
    }
});

test('PNG replaces browser density with the resolution of the selected paper', () => {
    const plainPng = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jNukAAAAASUVORK5CYII=', 'base64');
    const browserPng = setPngDensity(plainPng, 3780);
    const printPng = Buffer.from(setPngDensity(browserPng, 7559));
    assert.equal(printPng.length, plainPng.length + 21);
    assert.equal(printPng.toString('ascii', 37, 41), 'pHYs');
    assert.equal(printPng.readUInt32BE(41), 7559);
    assert.equal(printPng.readUInt32BE(45), 7559);
    assert.equal(printPng[49], 1);
    assert.equal(printPng.readUInt32BE(50), crc32(printPng.subarray(37, 50)));
    assert.deepEqual(printPng.subarray(54), plainPng.subarray(33));
});
