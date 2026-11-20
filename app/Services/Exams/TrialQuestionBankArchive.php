<?php

namespace App\Services\Exams;

use App\Models\TrialQuestionBank;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TrialQuestionBankArchive
{
    public function archive(TrialQuestionBank $bank): void
    {
        DB::transaction(function () use ($bank): void {
            // Starts and edits take this same parent lock before changing a bank.
            $bank = TrialQuestionBank::query()->lockForUpdate()->findOrFail($bank->id);
            $trials = $bank->trialExams()->orderBy('id')->lockForUpdate()->get();
            if ($trials->contains(fn ($trial) => $trial->attempts()->where('status', 'in_progress')->exists())) {
                throw ValidationException::withMessages([
                    'bank' => 'Bank belum dapat dihapus karena masih ada peserta yang sedang ujian. Selesaikan ujian peserta terlebih dahulu.',
                ]);
            }
            $bank->trialExams()->update(['is_active' => false]);
            $bank->forceFill(['archived_at' => $bank->archived_at ?? now()])->save();
        });
    }

    public function restore(TrialQuestionBank $bank): void
    {
        DB::transaction(function () use ($bank): void {
            $bank = TrialQuestionBank::query()->lockForUpdate()->findOrFail($bank->id);
            $bank->forceFill(['archived_at' => null])->save();
            // Trial links stay closed until the committee explicitly reactivates them.
        });
    }
}
