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

test('report renderer includes every grade, repeats headers and releases its canvas after exporting', async () => {
    const operations = [];
    const context = { fillRect(){}, drawImage(){}, strokeRect(){}, measureText: value=>({width:Array.from(String(value)).length*10}), fillText(value,x,y){operations.push({value:String(value),x,y});} };
    const canvas = { width:0,height:0,getContext:()=>context,toBlob:callback=>callback(new Blob(['image bytes'])) };
    globalThis.document = { createElement:()=>canvas,getElementById:()=>null };
    try {
        const data = {school:'Sekolah Contoh',address:'',scope:'Semua tingkat',filter:'',generatedAt:'16/11/2026',filename:'rekap',totals:{banks:62,filled:62,empty:0,questions:124},groups:[10,11].map(grade=>({title:`Kelas ${grade}`,subtitle:'ASAS 2026/2027',rows:Array.from({length:31},(_,i)=>[`Guru ${grade}-${i}`,'Mata pelajaran','X-1, X-2',2,0,0,2,'Sudah ada soal'])}))};
        const files = await renderPages(data,'png');
        assert.ok(files.length>2);
        for (const group of data.groups) for (const row of group.rows) assert.ok(operations.some(op=>op.value===row[0]));
        assert.equal(new Set(files.map(file=>file.name)).size,files.length);
        assert.ok(operations.filter(op=>op.value==='Guru penanggung jawab').length>=files.length);
        assert.equal(canvas.width,1); assert.equal(canvas.height,1);
    } finally { delete globalThis.document; }
});
