<?php

namespace App\Console\Commands;

use App\Services\Exams\TrialQuestionPack;
use Illuminate\Console\Command;

class CreateTrialSampleQuestions extends Command
{
    protected $signature = 'trial-questions:sample
        {--grade=10 : Tingkat kelas 10, 11, atau 12}
        {--all : Impor seluruh pilihan siap pakai secara eksplisit}
        {--pack= : Impor satu pilihan, misalnya bin-10 atau mtk-12}
        {--archive-history-12 : Hapus bank Sejarah XII dari daftar aktif, simpan riwayatnya}';

    protected $description = 'Menyiapkan paket PG per tingkat, termasuk Matematika XII dari mudah ke sulit, tanpa menimpa soal';

    public function handle(TrialQuestionPack $pack): int
    {
        if ($key = $this->option('pack')) {
            if ($this->option('all') || $this->option('archive-history-12')) {
                $this->error('Gunakan --pack sendiri untuk menambahkan satu pilihan tanpa mengubah bank lain.');

                return self::FAILURE;
            }
            if (! in_array($key, array_column($pack->sets(), 'id'), true)) {
                $this->error('Pilihan tidak tersedia. Gunakan salah satu kode: '.implode(', ', array_column($pack->sets(), 'id')));

                return self::FAILURE;
            }
            $result = $pack->importSelected($key);
            $this->info($result['created'] ? $result['bank']->name.' ditambahkan (20 soal).' : 'Pilihan sudah tersedia atau dihapus; soal tidak ditimpa.');

            return self::SUCCESS;
        }
        $grade = filter_var($this->option('grade'), FILTER_VALIDATE_INT);
        if (! $this->option('all') && ! in_array($grade, [10, 11, 12], true)) {
            $this->error('Tingkat kelas harus 10, 11, atau 12.');

            return self::FAILURE;
        }
        $result = $pack->import($this->option('all') ? null : $grade);
        $this->info("{$result['created']} bank / {$result['questions']} soal ditambahkan; {$result['skipped']} bank yang sudah terisi atau dihapus dilewati. Bobot PG tetap 2 poin per jawaban benar.");
        if ($this->option('archive-history-12')) {
            $history = $pack->archiveHistoryGrade12();
            $this->info("{$history['archived']} bank Sejarah XII dihapus dari daftar aktif; riwayat peserta tetap tersimpan.");
            if ($history['blocked']) {
                $this->warn("{$history['blocked']} bank belum dihapus karena peserta masih mengerjakan ujian.");

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
