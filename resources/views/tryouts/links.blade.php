<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tautan Ujian Uji Coba</title>
    <style>
        @page { size: A4; margin: 14mm; }
        * { box-sizing: border-box; }
        body { max-width: 900px; margin: 0 auto; padding: 18px; color: #172033; font: 1rem/1.5 Arial, sans-serif; }
        a { color: #075985; }
        .toolbar, .toolbar form, .copy-actions { display: flex; align-items: center; flex-wrap: wrap; gap: 10px; }
        .toolbar { justify-content: space-between; padding-bottom: 16px; border-bottom: 1px solid #cbd5e1; }
        button, select { font: inherit; border: 1px solid #64748b; border-radius: 6px; padding: 5px 10px; }
        button { color: white; background: #164e63; cursor: pointer; }
        select { color: inherit; background: white; }
        .copy-box { margin: 18px 0; }
        textarea { width: 100%; margin-top: 8px; padding: 8px; font: inherit; color: inherit; border: 1px solid #94a3b8; border-radius: 6px; }
        h1 { margin: 0; font-size: 1.5rem; }
        h2 { margin: 1em 0 .35em; font-size: 1.2rem; break-after: avoid; }
        header p { margin: 4px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 6px 8px; border: 1px solid #64748b; text-align: left; vertical-align: top; overflow-wrap: anywhere; }
        th { background: #e8eff2; }
        td small { display: block; }
        thead { display: table-header-group; }
        tr { break-inside: avoid; }
        @media (max-width: 580px) {
            body { padding: 12px; }
            table { font-size: .85rem; }
        }
        @media print {
            .toolbar, .copy-box { display: none; }
            body { max-width: none; padding: 0; font-size: 10pt; }
            a { color: inherit; text-decoration: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ route('operations.index') }}#pemantauan-uji-coba">← Pemantauan peserta</a>
        <form method="GET" action="{{ route('tryouts.links') }}">
            <label for="grade">Kelas</label>
            <select id="grade" name="grade">
                <option value="">Semua kelas</option>
                @foreach ($grades as $level)
                    <option value="{{ $level }}" @selected($grade === $level)>Kelas {{ $level }}</option>
                @endforeach
            </select>
            <button type="submit">Tampilkan</button>
        </form>
        @if ($groups->isNotEmpty())
            <x-print-actions filename="tautan-uji-coba" :margin="14" />
        @endif
    </div>

    @if ($groups->isNotEmpty())
        <div class="copy-box">
            <div class="copy-actions">
                <button type="button" id="copy-links">Salin Teks</button>
                <span id="copy-result" role="status"></span>
            </div>
            <textarea aria-label="Teks tautan siap disalin" id="share-text" rows="5" readonly>{{ $shareText }}</textarea>
        </div>
    @endif

    <main class="report-sheet" data-print-content>
        <header>
            <x-school-logo :school="$school" custom-only width="42" height="42" />
            <p><strong>{{ $school?->name ?? config('app.name') }}</strong></p>
            <h1>Daftar Tautan Ujian Uji Coba</h1>
            <p>Masuk menggunakan NIS atau NISN dan kode akses sesuai ujian.</p>
        </header>
        @forelse ($groups as $level => $rows)
            <section @unless ($loop->first) data-print-break @endunless>
                <h2>Kelas {{ $level }}</h2>
                <table aria-label="Tautan Ujian Uji Coba Kelas {{ $level }}">
                    <thead><tr>
                        <th scope="col">Mata pelajaran</th>
                        <th scope="col">Tautan peserta</th>
                        <th scope="col">Kode akses</th>
                        <th scope="col">Status</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td><strong>{{ $row['subject'] }}</strong><small>{{ $row['title'] }}</small></td>
                                <td><a href="{{ $row['url'] }}" target="_blank" rel="noopener">{{ $row['url'] }}</a></td>
                                <td>{{ $row['code'] }}</td>
                                <td>{{ $row['status'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        @empty
            <p>Belum ada tautan Ujian Uji Coba{{ $grade ? ' untuk Kelas '.$grade : '' }}.</p>
        @endforelse
    </main>
    @if ($groups->isNotEmpty())
        <script>
            document.getElementById('copy-links').addEventListener('click', async () => {
                const text = document.getElementById('share-text');
                const result = document.getElementById('copy-result');

                try {
                    await navigator.clipboard.writeText(text.value);
                    result.textContent = 'Teks berhasil disalin.';
                } catch {
                    text.focus();
                    text.select();
                    result.textContent = 'Teks dipilih. Tekan Ctrl+C atau gunakan menu Salin.';
                }
            });
        </script>
    @endif
</body>
</html>
