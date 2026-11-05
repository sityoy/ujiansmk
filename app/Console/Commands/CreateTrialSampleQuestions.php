<?php

namespace App\Console\Commands;

use App\Models\Subject;
use App\Models\TrialQuestionBank;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreateTrialSampleQuestions extends Command
{
    protected $signature = 'trial-questions:sample {--grade=10 : Tingkat kelas 10, 11, atau 12}';

    protected $description = 'Membuat masing-masing 10 soal PG uji coba Bahasa Indonesia, Bahasa Inggris, dan Sejarah';

    public function handle(): int
    {
        $grade = (int) $this->option('grade');

        if (! in_array($grade, [10, 11, 12], true)) {
            $this->error('Tingkat kelas harus 10, 11, atau 12.');

            return self::FAILURE;
        }

        $created = [];
        $skipped = [];

        foreach ($this->questionSets() as $set) {
            $subject = Subject::query()
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($set['subject'])])
                ->first()
                ?? Subject::query()->where('name', 'like', '%'.$set['subject'].'%')->first()
                ?? Subject::query()->firstOrCreate(
                    ['code' => $set['code']],
                    ['name' => $set['subject'], 'is_active' => true],
                );

            $bankName = 'Sample Uji Coba '.$set['subject'].' Kelas '.$grade;
            $bank = TrialQuestionBank::query()->firstOrCreate(
                ['subject_id' => $subject->id, 'grade_level' => $grade, 'name' => $bankName],
                ['teacher_user_id' => null],
            );

            if ($bank->trialExams()->whereHas('attempts')->exists()) {
                $skipped[] = $bankName.' (sudah memiliki peserta)';

                continue;
            }

            DB::transaction(function () use ($bank, $set): void {
                $bank->questions()->delete();

                foreach ($set['questions'] as $index => $question) {
                    $bank->questions()->create([
                        'question_text' => $question['question'],
                        'options' => $question['options'],
                        'correct_answer' => $question['answer'],
                        'points' => 7,
                        'position' => $index + 1,
                    ]);
                }
            });

            $created[] = [$bank->id, $set['subject'], 'Kelas '.$grade, 10, '7,00 poin'];
        }

        if ($created !== []) {
            $this->table(['ID Bank', 'Mata Pelajaran', 'Tingkat', 'Jumlah PG', 'Bobot/soal'], $created);
            $this->info(count($created).' bank uji coba berhasil disiapkan. Total nilai PG setiap bank: 70 poin.');
        }

        foreach ($skipped as $message) {
            $this->warn('Dilewati: '.$message);
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{
     *     code: string,
     *     subject: string,
     *     questions: array<int, array{question: string, options: array<string, string>, answer: string}>
     * }>
     */
    private function questionSets(): array
    {
        return [
            [
                'code' => 'BIN',
                'subject' => 'Bahasa Indonesia',
                'questions' => [
                    ['question' => 'Kalimat yang menggunakan kata baku adalah ...', 'options' => ['A' => 'Adik membeli obat di apotik.', 'B' => 'Ibu meminta kuitansi pembayaran.', 'C' => 'Ayah sedang mengurus ijin usaha.', 'D' => 'Kami harus merubah jadwal.', 'E' => 'Dia memberikan nasehat kepada saya.'], 'answer' => 'B'],
                    ['question' => 'Gagasan utama yang menjadi dasar pengembangan sebuah paragraf disebut ...', 'options' => ['A' => 'kalimat penjelas', 'B' => 'ide pokok', 'C' => 'kata rujukan', 'D' => 'konjungsi', 'E' => 'simpulan'], 'answer' => 'B'],
                    ['question' => 'Tujuan utama teks eksposisi adalah ...', 'options' => ['A' => 'menghibur pembaca dengan cerita', 'B' => 'menggambarkan suatu objek secara rinci', 'C' => 'menjelaskan informasi disertai argumen yang logis', 'D' => 'menceritakan pengalaman pribadi', 'E' => 'memberikan petunjuk melakukan sesuatu'], 'answer' => 'C'],
                    ['question' => 'Kalimat efektif terdapat pada pilihan ...', 'options' => ['A' => 'Para siswa-siswa sedang belajar di kelas.', 'B' => 'Bagi peserta diharapkan hadir tepat waktu.', 'C' => 'Kami membahas masalah itu dalam rapat.', 'D' => 'Ia naik ke atas panggung.', 'E' => 'Mereka saling tolong-menolong satu sama lain.'], 'answer' => 'C'],
                    ['question' => 'Majas yang membandingkan dua hal secara langsung dengan kata seperti, bagai, atau laksana disebut ...', 'options' => ['A' => 'metafora', 'B' => 'personifikasi', 'C' => 'simile', 'D' => 'hiperbola', 'E' => 'ironi'], 'answer' => 'C'],
                    ['question' => 'Struktur teks anekdot yang berisi munculnya masalah atau peristiwa unik disebut ...', 'options' => ['A' => 'abstraksi', 'B' => 'orientasi', 'C' => 'krisis', 'D' => 'reaksi', 'E' => 'koda'], 'answer' => 'C'],
                    ['question' => 'Konjungsi yang menyatakan hubungan sebab adalah ...', 'options' => ['A' => 'tetapi', 'B' => 'kemudian', 'C' => 'karena', 'D' => 'atau', 'E' => 'meskipun'], 'answer' => 'C'],
                    ['question' => 'Kalimat “Angin malam menyapa lembut wajahku” menggunakan majas ...', 'options' => ['A' => 'personifikasi', 'B' => 'litotes', 'C' => 'metonimia', 'D' => 'repetisi', 'E' => 'paradoks'], 'answer' => 'A'],
                    ['question' => 'Pola rima pantun yang tepat adalah ...', 'options' => ['A' => 'a-a-a-a', 'B' => 'a-b-a-b', 'C' => 'a-a-b-b', 'D' => 'a-b-b-a', 'E' => 'bebas'], 'answer' => 'B'],
                    ['question' => 'Bagian teks laporan hasil observasi yang menjelaskan gambaran umum objek disebut ...', 'options' => ['A' => 'orientasi', 'B' => 'deskripsi manfaat', 'C' => 'pernyataan umum', 'D' => 'argumentasi', 'E' => 'penegasan ulang'], 'answer' => 'C'],
                ],
            ],
            [
                'code' => 'BIG',
                'subject' => 'Bahasa Inggris',
                'questions' => [
                    ['question' => 'She ... to school by bus every day.', 'options' => ['A' => 'go', 'B' => 'goes', 'C' => 'went', 'D' => 'going', 'E' => 'gone'], 'answer' => 'B'],
                    ['question' => 'The correct past form of the verb “go” is ...', 'options' => ['A' => 'goed', 'B' => 'gone', 'C' => 'went', 'D' => 'goes', 'E' => 'going'], 'answer' => 'C'],
                    ['question' => 'The word “joyful” has the closest meaning to ...', 'options' => ['A' => 'angry', 'B' => 'happy', 'C' => 'tired', 'D' => 'afraid', 'E' => 'lonely'], 'answer' => 'B'],
                    ['question' => 'Rina and I are classmates. ... study in the same classroom.', 'options' => ['A' => 'He', 'B' => 'She', 'C' => 'They', 'D' => 'We', 'E' => 'It'], 'answer' => 'D'],
                    ['question' => 'My brother is ... than I am.', 'options' => ['A' => 'tall', 'B' => 'taller', 'C' => 'tallest', 'D' => 'more tall', 'E' => 'most tall'], 'answer' => 'B'],
                    ['question' => 'The passive form of “The students clean the classroom every day” is ...', 'options' => ['A' => 'The classroom cleans the students every day.', 'B' => 'The classroom is cleaned by the students every day.', 'C' => 'The classroom was cleaned by the students every day.', 'D' => 'The students are cleaned by the classroom every day.', 'E' => 'The classroom has cleaned every day.'], 'answer' => 'B'],
                    ['question' => 'If it rains this afternoon, we ... at home.', 'options' => ['A' => 'stay', 'B' => 'stayed', 'C' => 'will stay', 'D' => 'staying', 'E' => 'have stayed'], 'answer' => 'C'],
                    ['question' => 'An announcement is generally written to ...', 'options' => ['A' => 'tell a fictional story', 'B' => 'describe a person in detail', 'C' => 'give information to the public', 'D' => 'argue about an issue', 'E' => 'entertain readers with jokes'], 'answer' => 'C'],
                    ['question' => 'The Komodo dragon is a large reptile found in Indonesia. The sentence is suitable for the opening of a ... text.', 'options' => ['A' => 'procedure', 'B' => 'descriptive', 'C' => 'recount', 'D' => 'narrative', 'E' => 'spoof'], 'answer' => 'B'],
                    ['question' => 'Choose the correct preposition: “Dina is interested ... learning English.”', 'options' => ['A' => 'at', 'B' => 'on', 'C' => 'in', 'D' => 'with', 'E' => 'for'], 'answer' => 'C'],
                ],
            ],
            [
                'code' => 'SJR',
                'subject' => 'Sejarah',
                'questions' => [
                    ['question' => 'Penyusunan peristiwa sejarah berdasarkan urutan waktu disebut ...', 'options' => ['A' => 'periodisasi', 'B' => 'kronologi', 'C' => 'interpretasi', 'D' => 'historiografi', 'E' => 'verifikasi'], 'answer' => 'B'],
                    ['question' => 'Badan yang dibentuk untuk menyelidiki usaha-usaha persiapan kemerdekaan Indonesia adalah ...', 'options' => ['A' => 'VOC', 'B' => 'BPUPKI', 'C' => 'KNIP', 'D' => 'BKR', 'E' => 'RIS'], 'answer' => 'B'],
                    ['question' => 'PPKI mengesahkan UUD 1945 pada tanggal ...', 'options' => ['A' => '1 Juni 1945', 'B' => '22 Juni 1945', 'C' => '16 Agustus 1945', 'D' => '17 Agustus 1945', 'E' => '18 Agustus 1945'], 'answer' => 'E'],
                    ['question' => 'Ikrar Sumpah Pemuda dibacakan pada tanggal ...', 'options' => ['A' => '20 Mei 1908', 'B' => '28 Oktober 1928', 'C' => '1 Juni 1945', 'D' => '17 Agustus 1945', 'E' => '10 November 1945'], 'answer' => 'B'],
                    ['question' => 'Teks Proklamasi Kemerdekaan Indonesia dibacakan oleh ...', 'options' => ['A' => 'Mohammad Hatta', 'B' => 'Sutan Sjahrir', 'C' => 'Soekarno', 'D' => 'Ahmad Soebardjo', 'E' => 'Ki Hajar Dewantara'], 'answer' => 'C'],
                    ['question' => 'Peristiwa Rengasdengklok terjadi karena golongan muda menginginkan agar ...', 'options' => ['A' => 'proklamasi ditunda', 'B' => 'kemerdekaan dibahas bersama Jepang', 'C' => 'proklamasi segera dilakukan tanpa campur tangan Jepang', 'D' => 'Indonesia tetap menjadi bagian Jepang', 'E' => 'ibu kota dipindahkan'], 'answer' => 'C'],
                    ['question' => 'Organisasi pergerakan nasional yang berdiri pada 20 Mei 1908 adalah ...', 'options' => ['A' => 'Sarekat Islam', 'B' => 'Indische Partij', 'C' => 'Budi Utomo', 'D' => 'Perhimpunan Indonesia', 'E' => 'Partai Nasional Indonesia'], 'answer' => 'C'],
                    ['question' => 'Tujuan utama pembentukan VOC pada tahun 1602 adalah ...', 'options' => ['A' => 'mengembangkan pendidikan pribumi', 'B' => 'menghindari persaingan antarpedagang Belanda', 'C' => 'membantu kerajaan lokal', 'D' => 'menghapus monopoli perdagangan', 'E' => 'membangun demokrasi'], 'answer' => 'B'],
                    ['question' => 'Kerajaan maritim besar yang berkembang di Sumatra dan menguasai jalur perdagangan adalah ...', 'options' => ['A' => 'Kutai', 'B' => 'Tarumanegara', 'C' => 'Sriwijaya', 'D' => 'Kediri', 'E' => 'Singasari'], 'answer' => 'C'],
                    ['question' => 'Sumpah Palapa diucapkan oleh Gajah Mada pada masa Kerajaan ...', 'options' => ['A' => 'Sriwijaya', 'B' => 'Majapahit', 'C' => 'Mataram Kuno', 'D' => 'Samudra Pasai', 'E' => 'Demak'], 'answer' => 'B'],
                ],
            ],
        ];
    }
}
