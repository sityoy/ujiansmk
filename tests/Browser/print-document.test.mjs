import assert from 'node:assert/strict';
import test from 'node:test';
import { paginateDocument, paginateTableDocument, setPngDensity } from '../../resources/js/print-document.js';
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

function printNode(tag, name, height = 0, children = []) {
    const node = {
        tag, name, children: [],
        hasAttribute(attribute) { return attribute === 'data-print-break' && this.pageBreak === true; },
        get tHead() { return this.children.find(child => child.tag === 'thead'); },
        get tBodies() { return this.children.filter(child => child.tag === 'tbody'); },
        get rows() { return this.children; },
        append(child) {
            child.remove();
            child.parent = this;
            this.children.push(child);
        },
        remove() {
            if (!this.parent) return;
            this.parent.children.splice(this.parent.children.indexOf(this), 1);
            this.parent = null;
        },
        replaceChildren() { [...this.children].forEach(child => child.remove()); },
        querySelector(selector) {
            for (const child of this.children) {
                const match = child.tag === selector ? child : child.querySelector(selector);
                if (match) return match;
            }
            return null;
        },
        cloneNode() { return printNode(tag, name, height, this.children.map(child => child.cloneNode())); },
        getBoundingClientRect() {
            return { height: height + this.children.reduce((total, child) => total + child.getBoundingClientRect().height, 0) };
        },
    };
    children.forEach(child => node.append(child));
    return node;
}

const tableBlock = (name, rowHeights) => printNode('div', name, 0, [
    printNode('table', name, 0, [
        printNode('thead', name, 10),
        printNode('tbody', name, 0, rowHeights.map((height, i) => printNode('tr', i + 1, height))),
    ]),
]);

test('the schedule export carries full rows and their period header to each page', () => {
    const root = printNode('main', 'recap', 0, [
        printNode('header', 'school', 23),
        tableBlock('ASAS', [24, 32, 34, 31]),
        tableBlock('Susulan', [26, 23]),
        printNode('p', 'note', 10),
    ]);
    const pages = paginateTableDocument(root, 90);
    assert.deepEqual(pages.map(page => page.map(block => {
        const table = block.querySelector('table');
        return table ? [table.tHead.name, table.tBodies[0].rows.map(row => row.name)] : block.name;
    })), [
        ['school', ['ASAS', [1, 2]]],
        [['ASAS', [3, 4]]],
        [['Susulan', [1, 2]], 'note'],
    ]);
    assert.ok(pages.every(page => page.reduce((height, block) => height + block.getBoundingClientRect().height, 0) <= 90));
});

test('a flyer and each class appendix start on separate pages', () => {
    const flyer = printNode('article', 'flyer', 65);
    const firstClass = tableBlock('Kelas 10', [30, 35, 35]);
    const nextClass = tableBlock('Kelas 11', [20, 18]);
    firstClass.pageBreak = nextClass.pageBreak = true;
    const root = printNode('main', 'flyer', 0, [flyer, firstClass, nextClass]);
    const pages = paginateTableDocument(root, 100);
    assert.deepEqual(pages.map(page => page.map(block => {
        const table = block.querySelector('table');
        return table ? [table.tHead.name, table.tBodies[0].rows.map(row => row.name)] : block.name;
    })), [['flyer'], [['Kelas 10', [1, 2]]], [['Kelas 10', [3]]], [['Kelas 11', [1, 2]]]]);
});

test('an oversized schedule row is kept whole on a separate page for fitting', () => {
    const root = printNode('main', 'recap', 0, [
        printNode('header', 'school', 23),
        tableBlock('ASAS', [145, 20]),
    ]);
    const pages = paginateTableDocument(root, 90);
    assert.equal(pages.length, 3);
    assert.equal(pages[0][0].name, 'school');
    assert.deepEqual(pages.slice(1).map(page => page[0].querySelector('tbody').rows.map(row => row.name)), [[1], [2]]);
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
