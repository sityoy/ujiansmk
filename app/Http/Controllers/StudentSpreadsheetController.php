<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\Students\StudentSpreadsheetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StudentSpreadsheetController extends Controller
{
    public function template(StudentSpreadsheetService $spreadsheet): BinaryFileResponse
    {
        return response()
            ->download(
                $spreadsheet->createTemplate(),
                'template-import-siswa.xlsx',
                ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            )
            ->deleteFileAfterSend(true);
    }

    public function export(StudentSpreadsheetService $spreadsheet): BinaryFileResponse
    {
        $students = Student::query()
            ->with(['schoolClass.academicYear', 'user'])
            ->orderBy('full_name')
            ->get();

        return response()
            ->download(
                $spreadsheet->createExport($students),
                'data-siswa-'.now()->format('Y-m-d-His').'.xlsx',
                ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            )
            ->deleteFileAfterSend(true);
    }

    public function import(Request $request, StudentSpreadsheetService $spreadsheet): RedirectResponse
    {
        // Creating many student accounts requires one secure password hash per new user.
        // Keep the normal PHP limit for other pages, but allow this bulk operation to finish.
        if (function_exists('set_time_limit')) {
            set_time_limit(600);
        }

        $validated = $request->validate([
            'school_class_id' => ['nullable', 'exists:school_classes,id'],
            'spreadsheet' => ['required', 'file', 'max:10240'],
        ]);

        $extension = strtolower($request->file('spreadsheet')->getClientOriginalExtension());

        if (! in_array($extension, ['xlsx', 'csv'], true)) {
            return back()->withErrors(['spreadsheet' => 'File harus berformat .xlsx atau .csv.']);
        }

        try {
            $rows = $spreadsheet->read($request->file('spreadsheet'));
        } catch (RuntimeException $exception) {
            return back()->withErrors(['spreadsheet' => $exception->getMessage()]);
        }

        if ($rows === []) {
            return back()->withErrors(['spreadsheet' => 'File tidak memiliki data siswa.']);
        }

        if (count($rows) > 2000) {
            return back()->withErrors(['spreadsheet' => 'Maksimal 2.000 siswa dalam sekali impor.']);
        }

        $defaultSchoolClass = filled($validated['school_class_id'] ?? null)
            ? SchoolClass::findOrFail($validated['school_class_id'])
            : null;
        $preparedRows = [];
        $errors = [];
        $seenNis = [];
        $seenNisn = [];
        $seenEmails = [];
        $seenUsernames = [];
        $seenCards = [];

        foreach ($rows as $row) {
            $line = (int) $row['_row'];
            $data = [
                'nis' => trim((string) ($row['nis'] ?? '')),
                'nisn' => filled($row['nisn'] ?? null) ? trim((string) $row['nisn']) : null,
                'nama_lengkap' => trim((string) ($row['nama_lengkap'] ?? '')),
                'email' => filled($row['email'] ?? null) ? Str::lower(trim((string) $row['email'])) : null,
                'password' => filled($row['password'] ?? null) ? (string) $row['password'] : null,
                'card_uid' => filled($row['uid_kartu'] ?? null) ? trim((string) $row['uid_kartu']) : null,
                'status' => strtolower(trim((string) ($row['status'] ?? 'aktif'))),
                'kelas' => filled($row['kelas'] ?? null) ? trim((string) $row['kelas']) : null,
                'tahun_ajaran' => filled($row['tahun_ajaran'] ?? null) ? trim((string) $row['tahun_ajaran']) : null,
                'ruangan' => filled($row['ruangan'] ?? null) ? trim((string) $row['ruangan']) : null,
            ];

            $validator = Validator::make($data, [
                'nis' => ['required', 'string', 'max:50'],
                'nisn' => ['nullable', 'digits:10'],
                'nama_lengkap' => ['required', 'string', 'max:255'],
                'email' => ['nullable', 'email', 'max:255'],
                'password' => ['nullable', Password::min(8)->letters()->numbers()],
                'card_uid' => ['nullable', 'string', 'max:255'],
                'status' => ['required', 'in:aktif,nonaktif,active,inactive,1,0'],
                'kelas' => ['nullable', 'string', 'max:100'],
                'tahun_ajaran' => ['nullable', 'string', 'max:30'],
                'ruangan' => ['nullable', 'string', 'max:80'],
            ]);

            if ($validator->fails()) {
                $errors[] = 'Baris '.$line.': '.$validator->errors()->first();

                continue;
            }

            $rowSchoolClass = $defaultSchoolClass;
            if (! $rowSchoolClass) {
                if (! $data['kelas'] || ! $data['tahun_ajaran']) {
                    $errors[] = 'Baris '.$line.': pilih kelas tujuan atau isi kolom Kelas dan Tahun Ajaran.';

                    continue;
                }

                $rowSchoolClass = SchoolClass::query()
                    ->where('name', $data['kelas'])
                    ->whereHas('academicYear', fn ($query) => $query->where('name', $data['tahun_ajaran']))
                    ->first();

                if (! $rowSchoolClass) {
                    $errors[] = 'Baris '.$line.': kelas '.$data['kelas'].' tahun ajaran '.$data['tahun_ajaran'].' tidak ditemukan.';

                    continue;
                }
            }

            $existing = Student::query()->where('student_number', $data['nis'])->first();
            // Old templates have no room column and must preserve an existing assignment.
            if (! array_key_exists('ruangan', $row)) {
                $data['ruangan'] = $existing?->room_name;
            }
            $existingUserId = $existing?->user_id;
            $username = $data['nisn'] ?: $data['nis'];
            $cardHash = $data['card_uid'] ? hash('sha256', $data['card_uid']) : null;

            if (isset($seenNis[$data['nis']])) {
                $errors[] = 'Baris '.$line.': NIS duplikat di dalam file.';
            }

            if ($data['nisn'] && isset($seenNisn[$data['nisn']])) {
                $errors[] = 'Baris '.$line.': NISN duplikat di dalam file.';
            }

            if ($data['email'] && isset($seenEmails[$data['email']])) {
                $errors[] = 'Baris '.$line.': email duplikat di dalam file.';
            }

            if (isset($seenUsernames[$username])) {
                $errors[] = 'Baris '.$line.': NISN/NIS login bertabrakan dengan baris lain.';
            }

            if ($cardHash && isset($seenCards[$cardHash])) {
                $errors[] = 'Baris '.$line.': UID kartu duplikat di dalam file.';
            }

            if ($data['nisn'] && Student::query()
                ->where('nisn', $data['nisn'])
                ->when($existing, fn ($query) => $query->where('id', '!=', $existing->id))
                ->exists()) {
                $errors[] = 'Baris '.$line.': NISN sudah dipakai siswa lain.';
            }

            if (User::query()
                ->where('username', $username)
                ->when($existingUserId, fn ($query) => $query->where('id', '!=', $existingUserId))
                ->exists()) {
                $errors[] = 'Baris '.$line.': NISN/NIS untuk login sudah dipakai akun lain.';
            }

            if ($data['email'] && User::query()
                ->where('email', $data['email'])
                ->when($existingUserId, fn ($query) => $query->where('id', '!=', $existingUserId))
                ->exists()) {
                $errors[] = 'Baris '.$line.': email sudah dipakai akun lain.';
            }

            if ($cardHash && Student::query()
                ->where('card_uid_hash', $cardHash)
                ->when($existing, fn ($query) => $query->where('id', '!=', $existing->id))
                ->exists()) {
                $errors[] = 'Baris '.$line.': UID kartu sudah dipakai siswa lain.';
            }

            $seenNis[$data['nis']] = true;
            if ($data['nisn']) {
                $seenNisn[$data['nisn']] = true;
            }
            if ($data['email']) {
                $seenEmails[$data['email']] = true;
            }
            $seenUsernames[$username] = true;
            if ($cardHash) {
                $seenCards[$cardHash] = true;
            }

            $preparedRows[] = [$data, $existing, $rowSchoolClass];
        }

        if ($errors !== []) {
            $summary = implode(' ', array_slice(array_unique($errors), 0, 10));

            return back()->withErrors([
                'spreadsheet' => $summary.(count($errors) > 10 ? ' Perbaiki file lalu impor kembali.' : ''),
            ]);
        }

        DB::transaction(function () use ($preparedRows): void {
            foreach ($preparedRows as [$data, $existing, $schoolClass]) {
                $student = $existing ?? new Student;
                $user = $student->user;
                $username = $data['nisn'] ?: $data['nis'];

                if (! $user) {
                    $user = new User([
                        'role' => UserRole::Student,
                        'is_active' => true,
                    ]);
                }
                if (! $user->exists || $data['password']) {
                    $user->setStudentPassword($data['password'] ?? $data['nis']);
                }

                $user->fill([
                    'must_change_password' => false,
                    'name' => $data['nama_lengkap'],
                    'username' => $username,
                    'email' => $data['email'] ?: $user->email,
                    'is_active' => $this->isActive($data['status']),
                ])->save();

                $student->fill([
                    'user_id' => $user->id,
                    'school_class_id' => $schoolClass->id,
                    'student_number' => $data['nis'],
                    'nisn' => $data['nisn'],
                    'full_name' => $data['nama_lengkap'],
                    'room_name' => $data['ruangan'],
                    'card_uid_hash' => $data['card_uid']
                        ? hash('sha256', $data['card_uid'])
                        : $student->card_uid_hash,
                    'is_active' => $this->isActive($data['status']),
                ])->save();
            }
        });

        return back()->with('status', count($preparedRows).' data siswa berhasil diimpor atau diperbarui.');
    }

    private function isActive(string $status): bool
    {
        return in_array($status, ['aktif', 'active', '1'], true);
    }
}
