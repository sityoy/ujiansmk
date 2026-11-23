@props([
    'filename',
    'landscape' => false,
    'paperWidth' => null,
    'paperHeight' => null,
    'margin' => 12,
    'margins' => null,
    'scale' => 100,
])

@once
    @vite('resources/js/question-bank-report.js')
    <style>
        .document-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
        .document-actions button { font: 600 13px/1.4 Arial, sans-serif; padding: 8px 12px; border-radius: 8px; border: 1px solid #475569; background: #fff; color: #0f172a; cursor: pointer; }
        .document-actions button[data-document-download] { background: #334155; color: #fff; }
        .document-actions button[hidden] { display: none; }
        .document-actions button:disabled { opacity: .65; cursor: wait; }
        .document-actions [role="status"] { flex-basis: 100%; font-size: 12px; overflow-wrap: anywhere; }
        .document-actions [role="status"]:empty { display: none; }
        .document-export { position: absolute; z-index: -1; top: 0; left: 0; background: #fff; pointer-events: none; }
        .document-export [data-print-content] { width: 100%; max-width: none; margin: 0; padding: 0; }
        .document-export .table-wrap { overflow: visible; }
        .document-export .sheet, .document-export .class-heading, .document-export .warning, .document-export .flyer { width: 100%; max-width: none; }
        .document-export .flyer { margin: 0; padding: 9mm 12mm 8mm; }
        .document-export .flyer h1 { font-size: 34px; }
        .document-export .flyer .school { font-size: 21px; }
        .document-export .flyer .details > div { width: 50%; }
        .document-export .flyer .rule-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .document-export .sheet:has(> .card) { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .report-sheet { color: #0f172a; background: #fff; }
        .report-sheet :is(th, td) { border-color: #94a3b8; overflow-wrap: anywhere; }
        .report-sheet th { background: #e2e8f0; }
        .report-sheet tbody tr:nth-child(even):not(.history-row) { background: #f8fafc; }
        .report-sheet thead { display: table-header-group; }
        @media print {
            .document-actions, .document-export { display: none !important; }
            [data-print-content] { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
@endonce
<div class="document-actions" data-document-actions data-filename="{{ $filename }}" data-paper-width="{{ $paperWidth ?? ($landscape ? 297 : 210) }}"
    data-paper-height="{{ $paperHeight ?? ($landscape ? 210 : 297) }}"
    data-paper-margins="{{ implode(',', $margins ?? array_fill(0, 4, $margin)) }}" data-paper-scale="{{ $scale }}">
    <button type="button" data-document-download="pdf" hidden>Unduh PDF</button>
    <button type="button" data-document-download="png" hidden>Unduh gambar PNG</button>
    <button type="button" onclick="window.print()">Cetak / Simpan PDF</button>
    <span role="status" aria-live="polite"></span>
</div>
