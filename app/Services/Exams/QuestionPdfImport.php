<?php

namespace App\Services\Exams;

use App\Enums\ExamQuestionType;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class QuestionPdfImport
{
    private const SESSION_KEY = 'pending_question_pdf_import';

    public function prepare(Request $request, string $bank, array $allowedTypes, bool $allowWrittenStatement = true): array
    {
        $request->validate([
            'pdf' => ['required', 'file', 'mimes:pdf', 'max:5120'],
            'key_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
        ]);
        $keys = $request->hasFile('key_pdf') ? $this->parseKey($this->extract($request->file('key_pdf'))) : [];
        $questions = $this->parse($this->extract($request->file('pdf')), $keys, $allowedTypes);
        if (! $allowWrittenStatement) {
            foreach ($questions as $number => $question) {
                if ($question['question_type'] !== ExamQuestionType::MultipleChoice->value && $question['statement'] !== null) {
                    $this->invalid($number, 'Untuk soal tertulis Ujian Inti, masukkan bacaan pendukung dalam bagian Pertanyaan.');
                }
            }
        }
        $token = Str::random(48);
        $request->session()->put(self::SESSION_KEY, [
            'bank' => $bank,
            'user' => $request->user()->getAuthIdentifier(),
            'token' => $token,
            'expires' => now()->addMinutes(20)->timestamp,
            'questions' => $questions,
        ]);

        return compact('questions', 'token');
    }

    public function confirmed(Request $request, string $bank): array
    {
        $input = $request->validate([
            'import_token' => ['required', 'string', 'size:48'],
            'confirm_import' => ['accepted'],
        ]);
        $pending = $request->session()->get(self::SESSION_KEY);
        if (! $pending || $pending['bank'] !== $bank || $pending['user'] !== $request->user()->getAuthIdentifier()
            || now()->timestamp >= $pending['expires'] || ! hash_equals($pending['token'], $input['import_token'])) {
            throw ValidationException::withMessages(['pdf' => 'Pratinjau sudah kedaluwarsa atau sudah disimpan. Unggah PDF kembali.']);
        }

        return $pending['questions'];
    }

    public function finish(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }

    private function extract(UploadedFile $file): string
    {
        try {
            $result = Process::timeout(8)->run(['pdftotext', '-enc', 'UTF-8', '-raw', '-l', '81', $file->getPathname(), '-']);
        } catch (ProcessTimedOutException) {
            throw ValidationException::withMessages(['pdf' => 'PDF terlalu lama dibaca. Bagi naskah menjadi beberapa PDF yang lebih kecil.']);
        } catch (\RuntimeException) {
            throw ValidationException::withMessages(['pdf' => 'Pembaca PDF belum tersedia di server. Hubungi pengelola aplikasi.']);
        }
        if ($result->exitCode() === 127) {
            throw ValidationException::withMessages(['pdf' => 'Pembaca PDF belum tersedia di server. Hubungi pengelola aplikasi.']);
        }
        $text = $result->output();
        if ($result->failed() || trim($text) === '') {
            throw ValidationException::withMessages(['pdf' => 'Teks PDF tidak dapat dibaca. Gunakan PDF dari Word atau dokumen yang teksnya dapat disalin.']);
        }
        if (substr_count($text, "\f") > 80 || strlen($text) > 150000) {
            throw ValidationException::withMessages(['pdf' => 'Naskah terlalu panjang. Bagi menjadi PDF kecil (maksimal 80 halaman per file).']);
        }

        return str_replace("\f", "\n", $text);
    }

    private function parseKey(string $text): array
    {
        preg_match_all('/^\s*(\d{1,4})\s*(?:\/|[.):])\s*([A-E])(?:$|\s*\/)/mi', $text, $matches, PREG_SET_ORDER);
        $keys = [];
        foreach ($matches as [, $number, $letter]) {
            $number = (int) $number;
            $letter = strtoupper($letter);
            if (isset($keys[$number]) && $keys[$number] !== $letter) {
                $this->invalid($number, 'Kunci pada PDF pendamping bertentangan.');
            }
            $keys[$number] = $letter;
        }
        if ($keys === []) {
            throw ValidationException::withMessages(['key_pdf' => 'Kunci tidak terbaca. Tulis tiap kunci pada baris tersendiri, misalnya 1. C.']);
        }

        return $keys;
    }

    private function parse(string $text, array $keys, array $allowedTypes): array
    {
        $pattern = '/^\h*(?:Soal\h+(\d{1,4})(?:\h*[·–:-]\h*(Pilihan Ganda|Isian Singkat|Esai))?|'
            .'(\d{1,4})\h*\/\h*[^\r\n]+)\h*$/miu';
        // Read the headers with byte offsets so optional capture groups can be empty.
        preg_match_all($pattern, $text, $headers, PREG_SET_ORDER | PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL);
        if ($headers === [] || count($headers) > 100) {
            throw ValidationException::withMessages(['pdf' => 'Gunakan judul “Soal 1 · Pilihan Ganda” untuk setiap soal. Maksimal 100 soal per impor.']);
        }

        $questions = [];
        foreach ($headers as $index => [$heading, $number, $type, $packageNumber]) {
            $sourceNumber = (int) ($number[0] ?? $packageNumber[0]);
            if (isset($questions[$sourceNumber])) {
                $this->invalid($sourceNumber, 'Nomor soal berulang.');
            }
            $kind = match (strtolower($type[0] ?? '')) {
                'isian singkat' => ExamQuestionType::ShortAnswer,
                'esai' => ExamQuestionType::Essay,
                default => ExamQuestionType::MultipleChoice,
            };
            if (! in_array($kind, $allowedTypes, true)) {
                $this->invalid($sourceNumber, $kind->label().' tidak tersedia di bank soal ini.');
            }
            $start = $heading[1] + strlen($heading[0]);
            $end = $headers[$index + 1][0][1] ?? strlen($text);
            $questions[$sourceNumber] = $this->parseQuestion(substr($text, $start, $end - $start), $sourceNumber, $kind, $keys[$sourceNumber] ?? null);
        }
        $unused = array_diff_key($keys, $questions);
        if ($unused !== []) {
            $this->invalid(array_key_first($unused), 'Nomor pada PDF kunci tidak ada pada naskah.');
        }

        return $questions;
    }

    private function parseQuestion(string $block, int $number, ExamQuestionType $type, ?string $fileKey): array
    {
        $fields = ['question_text' => []];
        $field = 'question_text';
        $inlineKey = null;
        $points = null;

        foreach (preg_split('/\R/u', $block) as $line) {
            $line = trim($line);
            if ($line === '' || preg_match('/^Kelas 10 · Soal Inti Percobaan\h+\d+$/u', $line)
                || preg_match('/^(Mudah|Sedang|Sulit) · Nomor \d/u', $line)) {
                continue;
            }
            if (preg_match('/^(Pernyataan|Pertanyaan)\s*:\s*(.*)$/iu', $line, $match)) {
                $field = strtolower($match[1]) === 'pernyataan' ? 'statement' : 'question_text';
                $fields[$field][] = $match[2];
            } elseif (preg_match('/^([A-E])[.)]\s+(.+)$/iu', $line, $match)) {
                $field = strtoupper($match[1]);
                if (isset($fields[$field])) {
                    $this->invalid($number, 'Pilihan '.$field.' berulang.');
                }
                $fields[$field] = [$match[2]];
            } elseif (preg_match('/^Kunci\s*:\s*([A-E])$/iu', $line, $match)) {
                if ($inlineKey !== null || ($fileKey !== null && $fileKey !== strtoupper($match[1]))) {
                    $this->invalid($number, 'Kunci ganda atau tidak cocok dengan PDF pendamping.');
                }
                $inlineKey = strtoupper($match[1]);
            } elseif (preg_match('/^Bobot\s*:\s*(\d+(?:[.,]\d{1,2})?)$/iu', $line, $match)) {
                $points = (float) str_replace(',', '.', $match[1]);
            } elseif (preg_match('/^(Kunci|Bobot)\h*:/iu', $line)) {
                $this->invalid($number, 'Format kunci atau bobot tidak sesuai.');
            } else {
                $fields[$field][] = $line;
            }
        }

        $fields = array_map(fn ($lines) => trim(implode("\n", $lines)), $fields);
        $question = $fields['question_text'];
        $statement = $fields['statement'] ?? null;
        $options = array_intersect_key($fields, array_flip(['A', 'B', 'C', 'D', 'E']));
        ksort($options);
        if ($question === '' || max(array_map(mb_strlen(...), $fields)) > 20000) {
            $this->invalid($number, 'Pertanyaan kosong atau bagian soal melebihi batas 20.000 karakter.');
        }

        $key = $inlineKey ?? $fileKey;
        if ($type === ExamQuestionType::MultipleChoice) {
            if (count($options) !== 5 || $key === null) {
                $this->invalid($number, 'Pilihan A–E dan satu kunci wajib diisi.');
            }
            $points = ExamScoreCalculator::MULTIPLE_CHOICE_POINTS;
        } elseif ($options !== [] || $key !== null || $points === null || $points < 0.01 || $points > 1000) {
            $this->invalid($number, 'Soal tertulis memerlukan bobot 0,01–1.000 dan dinilai manual.');
        }

        return [
            'question_type' => $type->value,
            'question_text' => $question,
            'text_format' => 'plain',
            'statement' => filled($statement) ? $statement : null,
            'options' => $options ?: null,
            'correct_answer' => $key,
            'points' => $points,
        ];
    }

    private function invalid(int $number, string $message): never
    {
        throw ValidationException::withMessages(['pdf' => 'Soal '.$number.'. '.$message]);
    }
}
