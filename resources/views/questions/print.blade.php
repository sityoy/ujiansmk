<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $withKey ? 'Naskah & Kunci' : 'Naskah Peserta' }} · {{ $title }}</title>
    <style>
        @page { size: A4; margin: 14mm; }
        * { box-sizing: border-box; }
        body { max-width: 210mm; margin: auto; padding: 12px 16px 24px; font: 11pt/1.5 Arial, sans-serif; color: #172033; background: #fff; }
        a { color: #164e63; text-decoration: underline; overflow-wrap: anywhere; }
        .toolbar, .navigation, .identity { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 16px; }
        .toolbar { padding-bottom: 14px; justify-content: space-between; }
        .navigation { font-size: 10pt; }
        main { overflow-wrap: anywhere; }
        .cover { border-bottom: 2px solid #164e63; padding: 0 0 10px; margin-bottom: 16px; break-inside: avoid; }
        .cover p { margin: 0 0 4px; }
        .school { font-size: 10pt; font-weight: 700; color: #164e63; }
        h1 { font-size: 20pt; line-height: 1.15; margin: 4px 0 8px; }
        .mode { font-size: 10pt; font-weight: 700; }
        .identity { margin: 14px 0 8px; }
        .identity span { flex: 1 1 135px; border-bottom: 1px solid #64748b; min-height: 24px; }
        .instructions { font-size: 10pt; }
        .question { padding: 0 0 13px; margin: 0 0 12px; border-bottom: 1px solid #cbd5e1; break-inside: avoid; }
        .question h2 { margin: 0 0 6px; font-size: 11pt; color: #164e63; break-after: avoid; }
        .question-rich-text { white-space: pre-wrap; overflow-wrap: anywhere; }
        .question-rich-text :is(p, ol, ul) { margin: 0 0 5px; }
        .question-rich-text :is(ul, ol) { padding-inline-start: 22px; }
        .question-prompt { font-weight: 700; }
        .statement { margin-bottom: 6px; }
        .options { display: grid; gap: 4px; margin-top: 6px; }
        .option { display: grid; grid-template-columns: 20px minmax(0, 1fr); }
        .question-image { display: block; max-width: 100%; width: auto; height: auto; max-height: 72mm; margin: 7px 0; }
        .attachment-reference { font-size: 9pt; margin: 4px 0 8px; }
        .answer-line { height: 8mm; border-bottom: 1px solid #94a3b8; }
        .key { break-before: page; }
        .key h2 { margin: 0 0 6px; font-size: 15pt; break-after: avoid; }
        .key p { margin: 0 0 8px; font-size: 10pt; }
        .key table { width: 100%; border-collapse: collapse; }
        .key th, .key td { padding: 5px 8px; border: 1px solid #94a3b8; text-align: left; }
        .key tr { break-inside: avoid; }
        @media print {
            body { padding: 0; max-width: none; margin: 0; }
            .toolbar { display: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <nav class="navigation" aria-label="Pilihan ekspor">
            <a href="{{ $backUrl }}">← Kembali ke bank soal</a>
            @if ($questions->isNotEmpty())
                <a href="{{ $modeUrl }}">{{ $withKey ? 'Lihat naskah peserta' : 'Lihat versi dengan kunci' }}</a>
            @endif
        </nav>
        @if ($questions->isNotEmpty())
            <x-print-actions :filename="$filename" :margin="14" pdf-only />
        @endif
    </div>
    <main data-print-content class="report-sheet">
        <header class="cover" data-print-block>
            <p class="school">{{ $school?->name ?? config('app.name') }} · {{ $bankType }}</p>
            <h1>{{ $title }}</h1>
            <p>{{ implode(' · ', $details) }}</p>
            <p class="mode">{{ $withKey ? 'Pegangan Guru · Memuat Kunci Jawaban' : 'Naskah Peserta' }} · {{ $questions->count() }} soal</p>
            @if ($questions->isNotEmpty())
                @unless ($withKey)
                    <div class="identity"><span>Nama</span><span>Kelas</span><span>Tanggal</span></div>
                @endunless
                <p class="instructions">
                    Baca setiap soal dengan teliti.
                    @if ($questions->contains(fn ($question) => ! $question->question_type->requiresManualGrading()))
                        Pilih satu jawaban yang paling tepat untuk pilihan ganda.
                    @endif
                    @if ($questions->contains(fn ($question) => $question->question_type->requiresManualGrading()))
                        Tulis jawaban pada ruang yang disediakan atau lembar jawaban.
                    @endif
                </p>
            @endif
        </header>

        @forelse ($questions as $question)
            <article class="question" data-print-block>
                <h2>Soal {{ $question->position }} · {{ $question->question_type->label() }}</h2>
                @if (filled($question->statement) && ($trial || ! $question->question_type->requiresManualGrading()))
                    <x-question-text :text="$question->statement" :format="$question->text_format" class="statement" />
                @endif
                @if ($question->question_image_path)
                    <x-print-question-attachment :path="$question->question_image_path" :label="'Lampiran soal '.$question->position" />
                @endif
                <x-question-text :text="$question->question_text" :format="$question->text_format" class="question-prompt" />

                @if ($question->question_type->requiresManualGrading())
                    @unless ($withKey)
                        @for ($line = 0; $line < ($question->question_type === \App\Enums\ExamQuestionType::Essay ? 3 : 1); $line++)
                            <div class="answer-line" aria-hidden="true"></div>
                        @endfor
                    @endunless
                @else
                    @php($limit = \App\Services\Exams\MultipleChoiceAnswers::selectionLimit($question->correct_answer))
                    <p>{{ $limit > 1 ? 'Pilih 1 sampai '.$limit.' jawaban yang menurut Anda benar.' : 'Pilih satu jawaban.' }}</p>
                    <div class="options">
                        @foreach (collect($question->options)->sortKeys() as $letter => $answer)
                            <div class="option">
                                <strong>{{ $letter }}.</strong>
                                <div>
                                    <x-question-text :text="$answer" :format="$question->text_format" />
                                    @if ($question->option_file_paths[$letter] ?? null)
                                        <x-print-question-attachment :path="$question->option_file_paths[$letter]" :label="'Lampiran pilihan '.$letter" />
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </article>
        @empty
            <p>Bank ini masih kosong. Tambahkan soal sebelum mengekspor PDF.</p>
        @endforelse

        @if ($withKey && $questions->isNotEmpty())
            <section class="key" data-print-break>
                <h2>Kunci Jawaban · Pegangan Guru</h2>
                <p>Kunci mengikuti urutan pilihan pada naskah ini. Isian singkat dan esai dinilai manual.</p>
                <table>
                    <thead>
                        <tr><th scope="col">Soal</th><th scope="col">Jenis</th><th scope="col">Kunci</th><th scope="col">Bobot maks.</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($questions as $question)
                            <tr>
                                <td>{{ $question->position }}</td>
                                <td>{{ $question->question_type->label() }}</td>
                                <td>{{ $question->question_type->requiresManualGrading() ? 'Koreksi manual' : \App\Services\Exams\MultipleChoiceAnswers::label($question->correct_answer) }}</td>
                                <td>{{ $question->question_type->requiresManualGrading() ? (float) $question->points : \App\Services\Exams\ExamScoreCalculator::MULTIPLE_CHOICE_POINTS }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        @endif
    </main>
</body>
</html>
