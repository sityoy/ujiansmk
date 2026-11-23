import assert from 'node:assert/strict';
import test from 'node:test';
import { buildPdf, buildZip, crc32, paginateRows, wrapText, renderPages } from '../../resources/js/question-bank-report.js';

const text = value => new TextEncoder().encode(value);

test('PDF contains separate landscape pages and accurate binary offsets', () => {
    const pdf = buildPdf([{ bytes: new Uint8Array([255,216,255,217]), width: 1684, height: 1190 }, { bytes: new Uint8Array([255,216,0,255,217]), width: 1684, height: 1190 }]);
    const source = Buffer.from(pdf).toString('latin1');
    assert.ok(source.startsWith('%PDF-1.4'));
    assert.ok(source.includes('/Count 2'));
    assert.equal(source.match(/\/Type \/Page /g).length, 2);
    assert.equal(source.match(/\/MediaBox \[0 0 842 595\]/g).length, 2);
    const xref = Number(source.match(/startxref\n(\d+)/)[1]);
    assert.equal(source.slice(xref, xref + 4), 'xref');
    const entries = source.slice(xref).split('\n').slice(3, 11);
    entries.forEach((entry, i) => {
        const offset = Number(entry.slice(0, 10));
        assert.ok(source.slice(offset).startsWith(`${i + 1} 0 obj`));
    });
    assert.throws(() => buildPdf([]));
});

test('PNG ZIP keeps every file with correct CRC, sizes and central-directory offsets', () => {
    assert.equal(crc32(text('123456789')), 0xcbf43926);
    const files = [{name:'rekap-01.png',bytes:text('first image')},{name:'rekap-02.png',bytes:text('second image')}];
    const zip = buildZip(files), view = new DataView(zip.buffer);
    let offset = 0;
    for (const file of files) {
        assert.equal(view.getUint32(offset,true),0x04034b50);
        assert.equal(view.getUint32(offset+14,true),crc32(file.bytes));
        const nameLength = view.getUint16(offset+26,true);
        const start = offset+30+nameLength;
        assert.deepEqual(zip.slice(start,start+file.bytes.length),file.bytes);
        offset=start+file.bytes.length;
    }
    assert.equal(view.getUint32(offset,true),0x02014b50);
    assert.equal(view.getUint16(zip.length-22+10,true),2);
    assert.equal(view.getUint32(zip.length-22+16,true),offset);
});

test('wrapping supports Unicode and long unbroken text without losing content', () => {
    const context = {measureText: value => ({width: Array.from(value).length * 10})};
    const original = 'Pelajaran🧑éABCDEFGHIJKLMNOPQRSTUVWXYZ';
    const lines = wrapText(context,original,80);
    assert.equal(lines.join(''),original);
    assert.ok(lines.every(line=>context.measureText(line).width<=80));
    assert.deepEqual(wrapText(context,'A\nB',80),['A','B']);
    assert.deepEqual(wrapText(context,'Guru penanggung jawab',160),['Guru penanggung','jawab']);
});

test('table pagination keeps ordinary rows intact and splits an over-height row without dropping lines', () => {
    const rows = [1,2,3].map(number=>({number,cells:[[String(number)],['A','B']]}));
    const pages = paginateRows(rows,150,20,10);
    assert.deepEqual(pages.map(page=>page.map(row=>row.number)),[[1,2],[3]]);
    const huge = {number:4,cells:[['4'],Array.from({length:53},(_,i)=>String(i))]};
    const parts = paginateRows([huge],150,20,10);
    assert.deepEqual(parts.flatMap(page=>page.flatMap(row=>row.cells[1])),huge.cells[1]);
    assert.ok(parts.every(page=>page.reduce((sum,row)=>sum+row.height,0)<=150));
    assert.throws(()=>paginateRows([huge],10,20,10));
});

test('PDF and PNG pages repeat the correct period deadline and include every row', async () => {
    const renderedPages = [];
    let pageText = [];
    const context = {
        fillRect(x, y) { if (x === 0 && y === 0) pageText = []; },
        drawImage() {},
        strokeRect() {},
        measureText: value => ({ width: Array.from(String(value)).length * 10 }),
        fillText(value) { pageText.push(String(value)); },
    };
    const canvas = {
        width: 0, height: 0, getContext: () => context,
        toBlob(callback, mime) {
            renderedPages.push({ mime, text: pageText });
            callback(new Blob(['image bytes']));
        },
    };
    globalThis.document = { createElement:()=>canvas,getElementById:()=>null };
    try {
        const periods = [
            { title: 'Kelas 10 · ASAS Ganjil', deadline: '28/11/2026 17:30 WIB' },
            { title: 'Kelas 10 · UUB Ganjil', deadline: 'Belum ditetapkan' },
        ];
        const data = {
            school: 'Sekolah Contoh', address: '', scope: 'Semua tingkat', filter: '', generatedAt: '16/11/2026', filename: 'rekap',
            totals: { banks: 62, filled: 62, empty: 0, questions: 124 },
            groups: periods.map((period, index) => ({
                ...period, subtitle: 'Tahun ajaran 2026/2027',
                rows: Array.from({ length: 31 }, (_, row) => [`Guru ${index}-${row}`, 'Mata pelajaran', 'X-1, X-2', 2, 0, 0, 2, 'Sudah ada soal']),
            })),
        };
        for (const [format, mime] of [['pdf', 'image/jpeg'], ['png', 'image/png']]) {
            renderedPages.length = 0;
            const files = await renderPages(data, format);
            assert.ok(files.length > 2);
            assert.equal(new Set(files.map(file => file.name)).size, files.length);
            for (const group of data.groups) {
                const pages = renderedPages.filter(page => page.text.includes(group.title));
                assert.ok(pages.length > 1);
                for (const page of pages) {
                    assert.equal(page.mime, mime);
                    assert.deepEqual(page.text.filter(line => line.startsWith('Batas pembuatan soal:')), [`Batas pembuatan soal: ${group.deadline}`]);
                    assert.ok(page.text.includes('Guru penanggung jawab'));
                }
                for (const row of group.rows) assert.ok(pages.some(page => page.text.includes(row[0])));
            }
            assert.equal(canvas.width, 1);
            assert.equal(canvas.height, 1);
        }
        renderedPages.length = 0;
        await renderPages({
            ...data,
            reportKind: 'Bank Soal Uji Coba',
            detailColumn: 'Bank',
            totals: { banks: 1, filled: 1, empty: 0, questions: 3 },
            groups: [{
                title: 'Kelas 10', subtitle: 'Bank aktif',
                rows: [['Bu Nia', 'Sejarah', 'Bank simulasi\nBatas: 28/11/2026 17:30 WIB', 1, 1, 1, 3, 'Sudah ada soal']],
            }],
        }, 'png');
        const trialReport = renderedPages[0].text;
        assert.ok(trialReport.includes('REKAP PEMBUATAN SOAL · BANK SOAL UJI COBA'));
        assert.ok(trialReport.includes('Bank'));
        assert.ok(trialReport.includes('Bank simulasi'));
        assert.ok(trialReport.join(' ').includes('Batas: 28/11/2026 17:30 WIB'));
        assert.ok(!trialReport.includes('Rombel'));
    } finally { delete globalThis.document; }
});
