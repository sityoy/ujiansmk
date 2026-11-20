const encoder = new TextEncoder();
const bytes = value => typeof value === 'string' ? encoder.encode(value) : value;

function join(parts) {
    const result = new Uint8Array(parts.reduce((size, part) => size + part.length, 0));
    let offset = 0;
    for (const part of parts) { result.set(part, offset); offset += part.length; }
    return result;
}

// The PDF contains high resolution pages rendered locally. No report data is
// uploaded to a third-party converter and no server-side PDF package is needed.
export function buildPdf(images) {
    if (!images.length) throw new Error('Tidak ada halaman untuk diunduh.');
    const parts = [bytes('%PDF-1.4\n')];
    const offsets = [0];
    let length = parts[0].length;
    const add = (number, body) => {
        offsets[number] = length;
        const content = join([bytes(`${number} 0 obj\n`), ...body.map(bytes), bytes('\nendobj\n')]);
        parts.push(content); length += content.length;
    };
    add(1, ['<< /Type /Catalog /Pages 2 0 R >>']);
    add(2, [`<< /Type /Pages /Count ${images.length} /Kids [${images.map((_, i) => `${3 + i * 3} 0 R`).join(' ')}] >>`]);
    images.forEach((image, index) => {
        const page = 3 + index * 3;
        add(page, [`<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /XObject << /Image ${page + 2} 0 R >> >> /Contents ${page + 1} 0 R >>`]);
        const commands = 'q 842 0 0 595 0 0 cm /Image Do Q';
        add(page + 1, [`<< /Length ${bytes(commands).length} >>\nstream\n${commands}\nendstream`]);
        add(page + 2, [`<< /Type /XObject /Subtype /Image /Width ${image.width} /Height ${image.height} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ${image.bytes.length} >>\nstream\n`, image.bytes, '\nendstream']);
    });
    const start = length;
    parts.push(bytes(`xref\n0 ${offsets.length}\n0000000000 65535 f \n${offsets.slice(1).map(offset => `${String(offset).padStart(10, '0')} 00000 n \n`).join('')}trailer\n<< /Size ${offsets.length} /Root 1 0 R >>\nstartxref\n${start}\n%%EOF\n`));
    return join(parts);
}

export function crc32(data) {
    let crc = 0xffffffff;
    for (const value of data) {
        crc ^= value;
        for (let bit = 0; bit < 8; bit++) crc = (crc >>> 1) ^ ((crc & 1) ? 0xedb88320 : 0);
    }
    return (crc ^ 0xffffffff) >>> 0;
}

// PNG is already compressed. A stored ZIP avoids another dependency and lets
// the user download every page with one action (no blocked multiple downloads).
export function buildZip(files) {
    const local = [], central = [];
    let offset = 0;
    for (const file of files) {
        const name = bytes(file.name), content = file.bytes, crc = crc32(content);
        const header = new Uint8Array(30), h = new DataView(header.buffer);
        h.setUint32(0, 0x04034b50, true); h.setUint16(4, 20, true); h.setUint16(6, 0x0800, true);
        h.setUint16(12, 33, true); h.setUint32(14, crc, true); h.setUint32(18, content.length, true); h.setUint32(22, content.length, true); h.setUint16(26, name.length, true);
        local.push(header, name, content);
        const directory = new Uint8Array(46), d = new DataView(directory.buffer);
        d.setUint32(0, 0x02014b50, true); d.setUint16(4, 20, true); d.setUint16(6, 20, true); d.setUint16(8, 0x0800, true);
        d.setUint16(14, 33, true); d.setUint32(16, crc, true); d.setUint32(20, content.length, true); d.setUint32(24, content.length, true); d.setUint16(28, name.length, true); d.setUint32(42, offset, true);
        central.push(directory, name);
        offset += header.length + name.length + content.length;
    }
    const directory = join(central), end = new Uint8Array(22), e = new DataView(end.buffer);
    e.setUint32(0, 0x06054b50, true); e.setUint16(8, files.length, true); e.setUint16(10, files.length, true); e.setUint32(12, directory.length, true); e.setUint32(16, offset, true);
    return join([...local, directory, end]);
}

export function wrapText(context, text, width) {
    const lines = [];
    for (const paragraph of String(text ?? '').split(/\r?\n/)) {
        let line = '';
        for (const word of paragraph.trim().split(/\s+/)) {
            const candidate = line ? `${line} ${word}` : word;
            if (context.measureText(candidate).width <= width) { line = candidate; continue; }
            if (line) { lines.push(line); line = ''; }
            // Preserve full words where possible; split only an individual word
            // wider than its column, using code points instead of UTF-16 units.
            for (const character of Array.from(word)) {
                if (line && context.measureText(line + character).width > width) {
                    lines.push(line); line = character;
                } else line += character;
            }
        }
        lines.push(line);
    }
    return lines;
}

export function paginateRows(rows, height, lineHeight = 27, padding = 14) {
    const pages = [[]];
    let remaining = height;
    for (const row of rows) {
        const count = Math.max(...row.cells.map(cell => cell.length), 1);
        let offset = 0;
        // Keep ordinary rows together, split only a row taller than a full page.
        const rowHeight = count * lineHeight + padding * 2;
        if (rowHeight <= height && rowHeight > remaining) { pages.push([]); remaining = height; }
        while (offset < count) {
            let capacity = Math.floor((remaining - padding * 2) / lineHeight);
            if (capacity < 1) { pages.push([]); remaining = height; capacity = Math.floor((height - padding * 2) / lineHeight); }
            if (capacity < 1) throw new Error('Judul laporan terlalu panjang. Gunakan filter periode untuk mempersempit laporan.');
            const take = Math.min(count - offset, capacity);
            const part = { ...row, cells: row.cells.map(cell => cell.slice(offset, offset + take)), height: take * lineHeight + padding * 2 };
            part.cells[0] = [String(row.number)];
            pages[pages.length - 1].push(part);
            remaining -= part.height; offset += take;
        }
    }
    return pages;
}

const WIDTH = 1684, HEIGHT = 1190, MARGIN = 56, LINE = 27;
const columns = [0.045, 0.17, 0.25, 0.17, 0.047, 0.047, 0.075, 0.06, 0.136].map(ratio => ratio * (WIDTH - MARGIN * 2));
const labels = ['No.', 'Guru penanggung jawab', 'Mata pelajaran', 'Rombel', 'PG', 'Esai', 'Isian singkat', 'Total', 'Status'];

function font(context, size = 20, bold = false) { context.font = `${bold ? 'bold ' : ''}${size}px Arial, sans-serif`; }
function paragraph(context, text, x, y, width, size, bold = false) {
    font(context, size, bold);
    const lines = wrapText(context, text, width);
    lines.forEach((line, i) => context.fillText(line, x, y + i * (size + 7)));
    return y + lines.length * (size + 7);
}
function header(context, data, group, logo) {
    context.fillStyle = '#fff'; context.fillRect(0, 0, WIDTH, HEIGHT); context.textBaseline = 'top';
    if (logo?.complete && logo.naturalWidth) context.drawImage(logo, MARGIN, 45, 76, 76);
    context.fillStyle = '#0f172a';
    let y = paragraph(context, data.school, MARGIN + 100, 45, WIDTH - MARGIN * 2 - 100, 27, true);
    y = paragraph(context, 'REKAP PEMBUATAN SOAL · BANK SOAL INTI', MARGIN + 100, y + 3, WIDTH - MARGIN * 2 - 100, 21, true);
    if (data.address) y = paragraph(context, data.address, MARGIN + 100, y + 2, WIDTH - MARGIN * 2 - 100, 17);
    y = Math.max(135, y + 14);
    context.fillStyle = '#334155'; context.fillRect(MARGIN, y, WIDTH - MARGIN * 2, 3); y += 20;
    context.fillStyle = '#0f172a';
    y = paragraph(context, group.title, MARGIN, y, WIDTH - MARGIN * 2, 24, true);
    y = paragraph(context, group.subtitle, MARGIN, y + 4, WIDTH - MARGIN * 2, 19);
    if (data.filter) y = paragraph(context, data.filter, MARGIN, y + 4, WIDTH - MARGIN * 2, 18);
    const t = data.totals;
    y = paragraph(context, `Total hasil filter: ${t.banks} bank · ${t.filled} sudah ada soal · ${t.empty} belum ada soal · ${t.questions} soal`, MARGIN, y + 7, WIDTH - MARGIN * 2, 18);
    return y + 18;
}
function drawRow(context, cells, y, height, background, bold = false) {
    context.fillStyle = background; context.fillRect(MARGIN, y, WIDTH - MARGIN * 2, height);
    let x = MARGIN;
    context.strokeStyle = '#64748b'; context.lineWidth = 1.5;
    font(context, 20, bold); context.fillStyle = '#0f172a';
    cells.forEach((lines, i) => {
        context.strokeRect(x, y, columns[i], height);
        lines.forEach((line, lineIndex) => {
            const centered = i === 0 || (i >= 4 && i <= 7);
            const left = centered ? x + (columns[i] - context.measureText(line).width) / 2 : x + 12;
            context.fillText(line, left, y + 14 + lineIndex * LINE);
        });
        x += columns[i];
    });
}
function canvasBlob(canvas, format) {
    return new Promise((resolve, reject) => canvas.toBlob(blob => blob ? resolve(blob) : reject(new Error('Gambar tidak dapat dibuat. Silakan gunakan tombol Cetak.')), format, 0.94));
}

export async function renderPages(data, format, progress = () => {}) {
    const canvas = document.createElement('canvas'); canvas.width = WIDTH; canvas.height = HEIGHT;
    const context = canvas.getContext('2d');
    if (!context) throw new Error('Browser belum mendukung unduhan gambar. Gunakan tombol Cetak.');
    const logo = document.getElementById('report-logo');
    if (logo?.decode) await logo.decode().catch(() => {});
    const groups = data.groups.length ? data.groups : [{ title: data.scope, subtitle: 'Tidak ada bank soal sesuai filter.', rows: [] }];
    const files = [];
    let number = 0;
    try {
        for (const group of groups) {
            const top = header(context, data, group, logo);
            font(context, 20, true);
            const heading = labels.map((label, i) => wrapText(context, label, columns[i] - 24));
            const headingHeight = Math.max(...heading.map(cell => cell.length)) * LINE + 28;
            font(context);
            const rows = group.rows.map((row, index) => ({ number: index + 1, cells: [index + 1, ...row].map((value, i) => wrapText(context, value, columns[i] - 24)) }));
            // Append a complete group total as part of the table flow.
            rows.push({ number: '', total: true, cells: ['', 'JUMLAH', `${group.rows.length} bank soal`, '', ...[3, 4, 5, 6].map(i => String(group.rows.reduce((sum, row) => sum + row[i], 0))), ''].map(value => [String(value)]) });
            const pages = paginateRows(rows, HEIGHT - 88 - top - headingHeight);
            for (const [index, page] of pages.entries()) {
                header(context, data, group, logo);
                drawRow(context, heading, top, headingHeight, '#e2e8f0', true);
                let y = top + headingHeight;
                for (const row of page) {
                    drawRow(context, row.cells, y, row.height, row.total ? '#e2e8f0' : (Number(row.number) % 2 === 0 ? '#f1f5f9' : '#fff'), row.total);
                    y += row.height;
                }
                font(context, 15); context.fillStyle = '#475569';
                context.fillText(`Dibuat ${data.generatedAt} · Bank bersama antarrombel dihitung satu kali. Guru mengikuti penugasan.`, MARGIN, HEIGHT - 55);
                context.fillText(`Halaman kelompok ${index + 1}/${pages.length}`, MARGIN, HEIGHT - 32);
                const blob = await canvasBlob(canvas, format === 'pdf' ? 'image/jpeg' : 'image/png');
                files.push({ name: `${data.filename}-halaman-${String(++number).padStart(2, '0')}.png`, bytes: new Uint8Array(await blob.arrayBuffer()), width: WIDTH, height: HEIGHT });
                progress(number);
                await new Promise(resolve => setTimeout(resolve, 0));
            }
        }
    } finally { canvas.width = 1; canvas.height = 1; }
    return files;
}

function download(content, type, name) {
    const url = URL.createObjectURL(new Blob([content], { type }));
    const link = document.createElement('a'); link.href = url; link.download = name;
    document.body.appendChild(link); link.click(); link.remove();
    setTimeout(() => URL.revokeObjectURL(url), 60000);
}
function initializeReport() {
    const source = document.getElementById('question-bank-report-data');
    if (!source) return;
    const data = JSON.parse(source.textContent), buttons = document.querySelectorAll('[data-download-report]');
    const status = document.getElementById('report-status');
    buttons.forEach(button => button.addEventListener('click', async () => {
        buttons.forEach(item => { item.disabled = true; });
        status.textContent = 'Menyiapkan seluruh hasil rekap…';
        try {
            if (document.fonts?.ready) await document.fonts.ready;
            const format = button.dataset.downloadReport;
            const files = await renderPages(data, format, count => { status.textContent = `Menyiapkan halaman ${count}…`; });
            if (format === 'pdf') download(buildPdf(files), 'application/pdf', `${data.filename}.pdf`);
            else if (files.length === 1) download(files[0].bytes, 'image/png', `${data.filename}.png`);
            else download(buildZip(files), 'application/zip', `${data.filename}-png.zip`);
            status.textContent = `Selesai. ${files.length} halaman rekap telah disiapkan untuk diunduh.`;
        } catch (error) { status.textContent = `${error.message} Anda juga dapat memakai tombol Cetak, lalu Simpan sebagai PDF.`; }
        finally { buttons.forEach(item => { item.disabled = false; }); }
    }));
}
if (typeof document !== 'undefined') initializeReport();
