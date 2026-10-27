<?php

namespace App\Console\Commands;

use App\Enums\AssessmentType;
use App\Enums\ExamQuestionType;
use App\Models\AssessmentSubject;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreateHistorySampleQuestions extends Command
{
    protected $signature = 'questions:sample-history {assessment_subject_id : ID komponen mapel Sejarah}';

    protected $description = 'Memasukkan 30 contoh soal pilihan ganda Sejarah Indonesia dengan opsi A-E';

    public function handle(): int
    {
        $component = AssessmentSubject::query()
            ->with(['assessmentPeriod', 'subject', 'schoolClass'])
            ->find($this->argument('assessment_subject_id'));

        if (! $component) {
            $this->error('Komponen asesmen tidak ditemukan.');

            return self::FAILURE;
        }

        if (! str_contains(mb_strtolower($component->subject->name), 'sejarah')) {
            $this->error('Komponen yang dipilih bukan mata pelajaran Sejarah.');

            return self::FAILURE;
        }

        if ($component->assessmentPeriod->type === AssessmentType::ATS) {
            $this->error('Periode ATS hanya menerima isian singkat dan esai. Gunakan komponen AAS, AAT, UUB, atau lainnya.');

            return self::FAILURE;
        }

        if ($component->assignments()->whereHas('attempt')->exists()) {
            $this->error('Bank soal terkunci karena sudah ada peserta yang memulai ujian.');

            return self::FAILURE;
        }

        if ($component->questions()->exists()) {
            $this->error('Komponen sudah memiliki soal. Kosongkan bank soal terlebih dahulu agar contoh tidak bercampur atau ganda.');

            return self::FAILURE;
        }

        $questions = $this->questions();

        DB::transaction(function () use ($component, $questions): void {
            foreach ($questions as $index => $question) {
                $component->questions()->create([
                    'question_type' => ExamQuestionType::MultipleChoice,
                    'question_text' => $question['question'],
                    'options' => $question['options'],
                    'correct_answer' => $question['answer'],
                    'points' => $index < 10 ? 3.34 : 3.33,
                    'position' => $index + 1,
                ]);
            }
        });

        $this->info('30 soal PG Sejarah berhasil dibuat pada '.$component->subject->name.' · '.$component->schoolClass->name.'.');
        $this->line('Total bobot: 100 poin. Silakan periksa dan edit soal sebelum periode diterbitkan.');

        return self::SUCCESS;
    }

    /** @return array<int, array{question: string, options: array<string, string>, answer: string}> */
    private function questions(): array
    {
        return [
            ['question' => 'Kata “sejarah” dalam bahasa Arab berasal dari kata syajaratun yang berarti ...', 'options' => ['A' => 'tulisan', 'B' => 'pohon', 'C' => 'masa lampau', 'D' => 'peristiwa', 'E' => 'pengetahuan'], 'answer' => 'B'],
            ['question' => 'Unsur utama dalam kajian sejarah yang menunjukkan tempat berlangsungnya suatu peristiwa adalah ...', 'options' => ['A' => 'manusia', 'B' => 'waktu', 'C' => 'ruang', 'D' => 'sumber', 'E' => 'kronologi'], 'answer' => 'C'],
            ['question' => 'Penyusunan peristiwa sejarah berdasarkan urutan waktu disebut ...', 'options' => ['A' => 'periodisasi', 'B' => 'kronologi', 'C' => 'interpretasi', 'D' => 'historiografi', 'E' => 'verifikasi'], 'answer' => 'B'],
            ['question' => 'Pembabakan waktu dalam sejarah untuk memudahkan pemahaman suatu masa disebut ...', 'options' => ['A' => 'periodisasi', 'B' => 'anakronisme', 'C' => 'diakronik', 'D' => 'sinkronik', 'E' => 'heuristik'], 'answer' => 'A'],
            ['question' => 'Tahap penelitian sejarah yang dilakukan dengan mencari dan mengumpulkan sumber disebut ...', 'options' => ['A' => 'verifikasi', 'B' => 'interpretasi', 'C' => 'historiografi', 'D' => 'heuristik', 'E' => 'periodisasi'], 'answer' => 'D'],
            ['question' => 'Prasasti, naskah, dan surat kabar termasuk jenis sumber sejarah ...', 'options' => ['A' => 'lisan', 'B' => 'tertulis', 'C' => 'benda', 'D' => 'sekunder', 'E' => 'audio visual'], 'answer' => 'B'],
            ['question' => 'Masa praaksara adalah masa ketika manusia ...', 'options' => ['A' => 'belum mengenal tulisan', 'B' => 'belum mengenal api', 'C' => 'hidup menetap', 'D' => 'sudah berdagang', 'E' => 'mendirikan kerajaan'], 'answer' => 'A'],
            ['question' => 'Jenis manusia purba yang ditemukan Eugene Dubois di Trinil adalah ...', 'options' => ['A' => 'Meganthropus paleojavanicus', 'B' => 'Homo wajakensis', 'C' => 'Pithecanthropus erectus', 'D' => 'Homo floresiensis', 'E' => 'Homo soloensis'], 'answer' => 'C'],
            ['question' => 'Kehidupan manusia praaksara yang berpindah-pindah dari satu tempat ke tempat lain disebut ...', 'options' => ['A' => 'sedenter', 'B' => 'nomaden', 'C' => 'maritim', 'D' => 'agraris', 'E' => 'urbanisasi'], 'answer' => 'B'],
            ['question' => 'Bangunan batu besar berbentuk meja yang digunakan untuk meletakkan sesaji disebut ...', 'options' => ['A' => 'menhir', 'B' => 'sarkofagus', 'C' => 'dolmen', 'D' => 'waruga', 'E' => 'punden berundak'], 'answer' => 'C'],
            ['question' => 'Kerajaan Hindu tertua di Indonesia berdasarkan bukti prasasti Yupa adalah ...', 'options' => ['A' => 'Tarumanegara', 'B' => 'Kutai', 'C' => 'Mataram Kuno', 'D' => 'Kediri', 'E' => 'Singasari'], 'answer' => 'B'],
            ['question' => 'Raja Tarumanegara yang namanya tercantum dalam Prasasti Ciaruteun adalah ...', 'options' => ['A' => 'Mulawarman', 'B' => 'Purnawarman', 'C' => 'Balaputradewa', 'D' => 'Airlangga', 'E' => 'Kertanegara'], 'answer' => 'B'],
            ['question' => 'Kerajaan Sriwijaya berkembang menjadi kerajaan maritim besar karena ...', 'options' => ['A' => 'memiliki lahan pertanian yang luas', 'B' => 'berada di jalur perdagangan internasional', 'C' => 'menguasai seluruh Pulau Jawa', 'D' => 'menutup pelabuhan bagi bangsa asing', 'E' => 'berpusat di daerah pegunungan'], 'answer' => 'B'],
            ['question' => 'Sumpah Palapa diucapkan oleh Mahapatih Gajah Mada pada masa Kerajaan ...', 'options' => ['A' => 'Sriwijaya', 'B' => 'Singasari', 'C' => 'Majapahit', 'D' => 'Kediri', 'E' => 'Mataram Kuno'], 'answer' => 'C'],
            ['question' => 'Kitab Negarakertagama yang menceritakan keadaan Majapahit ditulis oleh ...', 'options' => ['A' => 'Mpu Tantular', 'B' => 'Mpu Prapanca', 'C' => 'Mpu Sedah', 'D' => 'Mpu Panuluh', 'E' => 'Ken Arok'], 'answer' => 'B'],
            ['question' => 'Salah satu saluran utama penyebaran Islam di Nusantara adalah ...', 'options' => ['A' => 'penjajahan', 'B' => 'perdagangan', 'C' => 'pemaksaan', 'D' => 'perlombaan senjata', 'E' => 'isolasi wilayah'], 'answer' => 'B'],
            ['question' => 'Kerajaan Islam yang dikenal sebagai salah satu kerajaan Islam awal di Nusantara adalah ...', 'options' => ['A' => 'Samudra Pasai', 'B' => 'Tarumanegara', 'C' => 'Kutai', 'D' => 'Kalingga', 'E' => 'Pajajaran'], 'answer' => 'A'],
            ['question' => 'Tokoh Wali Songo yang dikenal menggunakan wayang sebagai media dakwah adalah ...', 'options' => ['A' => 'Sunan Ampel', 'B' => 'Sunan Kalijaga', 'C' => 'Sunan Gresik', 'D' => 'Sunan Kudus', 'E' => 'Sunan Gunung Jati'], 'answer' => 'B'],
            ['question' => 'Bangsa Eropa yang pertama kali berhasil mencapai Kepulauan Maluku adalah ...', 'options' => ['A' => 'Belanda', 'B' => 'Inggris', 'C' => 'Prancis', 'D' => 'Portugis', 'E' => 'Jerman'], 'answer' => 'D'],
            ['question' => 'Tujuan utama pembentukan VOC pada tahun 1602 adalah ...', 'options' => ['A' => 'mengembangkan pendidikan pribumi', 'B' => 'menghindari persaingan antarpedagang Belanda', 'C' => 'membantu kerajaan lokal', 'D' => 'menghapus monopoli perdagangan', 'E' => 'membangun pemerintahan demokratis'], 'answer' => 'B'],
            ['question' => 'Sistem Tanam Paksa pada tahun 1830 diperkenalkan oleh ...', 'options' => ['A' => 'Herman Willem Daendels', 'B' => 'Thomas Stamford Raffles', 'C' => 'Johannes van den Bosch', 'D' => 'Jan Pieterszoon Coen', 'E' => 'Pieter Both'], 'answer' => 'C'],
            ['question' => 'Perang Diponegoro berlangsung pada tahun ...', 'options' => ['A' => '1811–1816', 'B' => '1825–1830', 'C' => '1830–1835', 'D' => '1873–1904', 'E' => '1908–1912'], 'answer' => 'B'],
            ['question' => 'Organisasi pergerakan nasional yang berdiri pada 20 Mei 1908 adalah ...', 'options' => ['A' => 'Sarekat Islam', 'B' => 'Indische Partij', 'C' => 'Budi Utomo', 'D' => 'Perhimpunan Indonesia', 'E' => 'Partai Nasional Indonesia'], 'answer' => 'C'],
            ['question' => 'Tokoh yang dikenal sebagai pendiri Taman Siswa adalah ...', 'options' => ['A' => 'Ki Hajar Dewantara', 'B' => 'Dr. Sutomo', 'C' => 'H.O.S. Tjokroaminoto', 'D' => 'Mohammad Hatta', 'E' => 'Sutan Sjahrir'], 'answer' => 'A'],
            ['question' => 'Ikrar Sumpah Pemuda dibacakan pada tanggal ...', 'options' => ['A' => '20 Mei 1908', 'B' => '28 Oktober 1928', 'C' => '1 Juni 1945', 'D' => '17 Agustus 1945', 'E' => '10 November 1945'], 'answer' => 'B'],
            ['question' => 'Badan yang dibentuk Jepang untuk mempersiapkan hal-hal berkaitan dengan kemerdekaan Indonesia adalah ...', 'options' => ['A' => 'VOC', 'B' => 'BPUPKI', 'C' => 'KNIP', 'D' => 'BKR', 'E' => 'RIS'], 'answer' => 'B'],
            ['question' => 'Peristiwa Rengasdengklok terjadi karena golongan muda menginginkan agar ...', 'options' => ['A' => 'proklamasi ditunda', 'B' => 'kemerdekaan dibahas bersama Jepang', 'C' => 'proklamasi segera dilaksanakan tanpa campur tangan Jepang', 'D' => 'Indonesia tetap menjadi bagian Jepang', 'E' => 'ibu kota dipindahkan ke Rengasdengklok'], 'answer' => 'C'],
            ['question' => 'Teks Proklamasi Kemerdekaan Indonesia dibacakan oleh Soekarno pada ...', 'options' => ['A' => '1 Juni 1945', 'B' => '22 Juni 1945', 'C' => '16 Agustus 1945', 'D' => '17 Agustus 1945', 'E' => '18 Agustus 1945'], 'answer' => 'D'],
            ['question' => 'Pertempuran Surabaya yang kemudian diperingati sebagai Hari Pahlawan terjadi pada ...', 'options' => ['A' => '10 November 1945', 'B' => '15 Desember 1945', 'C' => '23 Maret 1946', 'D' => '1 Maret 1949', 'E' => '27 Desember 1949'], 'answer' => 'A'],
            ['question' => 'Pengakuan kedaulatan Indonesia oleh Belanda sebagai hasil Konferensi Meja Bundar berlangsung pada ...', 'options' => ['A' => '17 Agustus 1945', 'B' => '10 November 1945', 'C' => '19 Desember 1948', 'D' => '27 Desember 1949', 'E' => '17 Agustus 1950'], 'answer' => 'D'],
        ];
    }
}
