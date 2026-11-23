import { buildPdf, buildZip, download } from './question-bank-report.js';

const cssPerMm = 96 / 25.4;
const pointsPerMm = 72 / 25.4;

function lastBreakBefore(boundaries, top, limit) {
    for (let i = boundaries.length - 1; i >= 0; i--) {
        if (boundaries[i] <= top) break;
        if (boundaries[i] <= limit) return boundaries[i];
    }
    return null;
}

export function paginateDocument(height, pageHeight, { boundaries = [], breaks = [], tables = [] } = {}) {
    const pages = [];
    let top = 0;
    while (top < height) {
        const table = tables.find(item => top >= item.header.top + item.header.height && top < item.bottom);
        const header = table?.header.height < pageHeight / 3 ? table.header : null;
        const limit = Math.min(top + pageHeight - (header?.height ?? 0), height);
        const forced = breaks.find(y => y > top && y <= limit);
        const naturalBreak = lastBreakBefore(boundaries, top, limit);
        const end = forced ?? (limit === height ? height : naturalBreak ?? limit);
        pages.push({ top, height: end - top, header });
        top = end;
    }
    return pages;
}

function measureDocument(root, pageHeight) {
    const origin = root.getBoundingClientRect().top;
    const topOf = element => Math.floor(element.getBoundingClientRect().top - origin);
    const bottomOf = element => Math.ceil(element.getBoundingClientRect().bottom - origin);
    const tables = [];
    const boundaries = new Set();
    const breaks = [...root.querySelectorAll('[data-print-break]')].map(topOf);
    for (const table of root.querySelectorAll('table')) {
        const header = table.tHead;
        if (!header) continue;
        tables.push({
            bottom: bottomOf(table),
            header: { top: topOf(header), height: bottomOf(header) - topOf(header) },
        });
        if (topOf(table) > 0) boundaries.add(topOf(table));
        for (const body of table.tBodies) {
            for (const row of body.rows) {
                // Keep the score and its existing incident history together when they fit.
                if (!row.classList.contains('has-history')) boundaries.add(bottomOf(row));
                if (row.classList.contains('history-row') && row.getBoundingClientRect().height > pageHeight * .85) {
                    for (const line of row.querySelectorAll('li, p')) boundaries.add(bottomOf(line));
                }
            }
        }
    }

    const blockRows = new Map();
    for (const block of root.querySelectorAll('[data-print-block]')) {
        const top = topOf(block);
        blockRows.set(top, Math.max(blockRows.get(top) ?? 0, bottomOf(block)));
    }
    for (const bottom of blockRows.values()) boundaries.add(bottom);

    return {
        boundaries: [...boundaries].sort((a, b) => a - b),
        breaks: [...new Set(breaks)].sort((a, b) => a - b),
        tables,
    };
}

const imageBlob = (canvas, type) => new Promise((resolve, reject) => {
    canvas.toBlob(blob => blob ? resolve(blob) : reject(new Error('Gambar halaman gagal dibuat.')), type, .96);
});

async function renderDocument(source, settings, format, progress) {
    const { default: capture } = await import('html2canvas');
    const paperWidth = Number(settings.paperWidth), paperHeight = Number(settings.paperHeight);
    const [top, right, bottom, left] = settings.paperMargins.split(',').map(Number);
    const zoom = Number(settings.paperScale) / 100;
    const width = Math.floor((paperWidth - left - right) * cssPerMm / zoom);
    const pageHeight = Math.floor((paperHeight - top - bottom) * cssPerMm / zoom);
    const pixelScale = 2;
    const exportRoot = document.createElement('div');
    exportRoot.className = 'document-export';
    exportRoot.setAttribute('aria-hidden', 'true');
    exportRoot.inert = true;
    exportRoot.style.width = `${width}px`;
    const root = source.cloneNode(true);
    exportRoot.appendChild(root);
    document.body.appendChild(exportRoot);

    try {
        if (document.fonts?.ready) await document.fonts.ready;
        await Promise.all([...root.querySelectorAll('img')].map(image => {
            image.loading = 'eager';
            if (image.decode) return image.decode();
            if (image.complete && image.naturalWidth) return;
            return new Promise((resolve, reject) => {
                image.addEventListener('load', resolve, { once: true });
                image.addEventListener('error', reject, { once: true });
            });
        }));

        if (root.scrollWidth > width + 2) throw new Error('Konten lebih lebar daripada ukuran kertas.');
        const pages = paginateDocument(Math.ceil(root.getBoundingClientRect().height), pageHeight, measureDocument(root, pageHeight));
        const cache = new Map();
        const captureSlice = (y, height) => capture(root, {
            x: 0, y, width, height, scale: pixelScale, backgroundColor: '#fff',
            windowWidth: window.innerWidth, logging: false, useCORS: true,
            ignoreElements: element => element === source,
        });
        const output = [];
        for (const [index, page] of pages.entries()) {
            const canvas = document.createElement('canvas');
            canvas.width = Math.round(paperWidth * cssPerMm * pixelScale);
            canvas.height = Math.round(paperHeight * cssPerMm * pixelScale);
            const context = canvas.getContext('2d');
            if (!context) throw new Error('Browser belum mendukung ekspor gambar.');
            context.fillStyle = '#fff';
            context.fillRect(0, 0, canvas.width, canvas.height);
            const x = left * cssPerMm * pixelScale;
            let y = top * cssPerMm * pixelScale;
            if (page.header) {
                if (!cache.has(page.header.top)) {
                    cache.clear();
                    cache.set(page.header.top, await captureSlice(page.header.top, page.header.height));
                }
                const header = cache.get(page.header.top);
                context.drawImage(header, x, y, header.width * zoom, header.height * zoom);
                y += header.height * zoom;
            }
            const body = await captureSlice(page.top, page.height);
            context.drawImage(body, x, y, body.width * zoom, body.height * zoom);
            body.width = 1;
            body.height = 1;
            const blob = await imageBlob(canvas, format === 'pdf' ? 'image/jpeg' : 'image/png');
            output.push({
                name: `${settings.filename}-halaman-${String(index + 1).padStart(2, '0')}.png`,
                bytes: new Uint8Array(await blob.arrayBuffer()),
                width: canvas.width,
                height: canvas.height,
                paperWidth: Math.round(paperWidth * pointsPerMm),
                paperHeight: Math.round(paperHeight * pointsPerMm),
            });
            canvas.width = 1;
            canvas.height = 1;
            progress(index + 1, pages.length);
            await new Promise(resolve => setTimeout(resolve, 0));
        }
        return output;
    } finally {
        exportRoot.remove();
    }
}

export function initializeDownloads() {
    const source = document.querySelector('[data-print-content]');
    const toolbar = document.querySelector('[data-document-actions]');
    if (!source || !toolbar) return;
    const status = toolbar.querySelector('[role="status"]');
    const buttons = toolbar.querySelectorAll('[data-document-download]');
    buttons.forEach(button => {
        button.hidden = false;
        button.addEventListener('click', async () => {
            buttons.forEach(item => { item.disabled = true; });
            status.textContent = 'Menyiapkan dokumen…';
            try {
                const format = button.dataset.documentDownload;
                const stem = toolbar.dataset.filename;
                const files = await renderDocument(source, toolbar.dataset, format, (index, total) => {
                    status.textContent = `Menyiapkan halaman ${index} dari ${total}…`;
                });
                if (format === 'pdf') download(buildPdf(files), 'application/pdf', `${stem}.pdf`);
                else if (files.length === 1) download(files[0].bytes, 'image/png', `${stem}.png`);
                else download(buildZip(files), 'application/zip', `${stem}-png.zip`);
                status.textContent = `${files.length} halaman siap diunduh.`;
            } catch {
                status.textContent = 'Unduhan gagal dibuat. Pilih Cetak / Simpan PDF untuk membuka opsi cetak browser.';
            } finally {
                buttons.forEach(item => { item.disabled = false; });
            }
        });
    });
}
