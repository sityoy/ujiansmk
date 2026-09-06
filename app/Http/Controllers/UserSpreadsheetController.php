<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Students\StudentSpreadsheetService;
use App\Services\Users\ManagedUserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class UserSpreadsheetController extends Controller
{
    public function template(StudentSpreadsheetService $spreadsheet): BinaryFileResponse
    {
        return $this->download(
            $spreadsheet->createWorkbook(
                ['ID', 'Nama', 'Email', 'Akses', 'Password Baru', 'Status'],
                [['', 'Contoh Guru', 'guru@example.sch.id', 'Guru', 'GuruBaru123', 'aktif']],
                'Template Akun',
            ),
            'template-import-akun.xlsx',
        );
    }

    public function export(StudentSpreadsheetService $spreadsheet): BinaryFileResponse
    {
        $rows = User::query()
            ->where('role', '!=', UserRole::Student)
            ->orderBy('role')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): array => [
                $user->id,
                $user->name,
                $user->email,
                $user->role->label(),
                '',
                $user->is_active ? 'aktif' : 'nonaktif',
            ])
            ->all();

        return $this->download(
            $spreadsheet->createWorkbook(
                ['ID', 'Nama', 'Email', 'Akses', 'Password Baru', 'Status'],
                $rows,
                'Data Akun',
            ),
            'data-akun-'.now()->format('Y-m-d-His').'.xlsx',
        );
    }

    public function import(
        Request $request,
        StudentSpreadsheetService $spreadsheet,
        ManagedUserService $managedUsers,
    ): RedirectResponse {
        $request->validate([
            'account_spreadsheet' => ['required', 'file', 'max:10240'],
        ]);

        $file = $request->file('account_spreadsheet');
        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, ['xlsx', 'csv'], true)) {
            return back()->withErrors(['account_spreadsheet' => 'File akun harus berformat .xlsx atau .csv.']);
        }

        try {
            $rows = $spreadsheet->readTable($file);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['account_spreadsheet' => $exception->getMessage()]);
        }

        if ($rows === []) {
            return back()->withErrors(['account_spreadsheet' => 'File tidak memiliki data akun.']);
        }

        $headers = array_keys($rows[0]);

        foreach (['nama_lengkap', 'email', 'akses', 'password_baru'] as $requiredHeader) {
            if (! in_array($requiredHeader, $headers, true)) {
                return back()->withErrors([
                    'account_spreadsheet' => 'Kolom Nama, Email, Akses, dan Password Baru wajib tersedia. Gunakan template dari sistem.',
                ]);
            }
        }

        if (count($rows) > 500) {
            return back()->withErrors(['account_spreadsheet' => 'Maksimal 500 akun dalam sekali impor.']);
        }

        $preparedRows = [];
        $errors = [];
        $seenIds = [];
        $seenEmails = [];

        foreach ($rows as $row) {
            $line = (int) $row['_row'];
            $id = filled($row['id'] ?? null) ? (int) $row['id'] : null;
            $email = Str::lower(trim((string) ($row['email'] ?? '')));
            $role = $managedUsers->roleFromSpreadsheet((string) ($row['akses'] ?? ''));
            $status = Str::lower(trim((string) ($row['status'] ?? 'aktif')));
            $existing = $id
                ? User::query()->where('role', '!=', UserRole::Student)->find($id)
                : User::query()->where('role', '!=', UserRole::Student)->where('email', $email)->first();
            $data = [
                'name' => trim((string) ($row['nama_lengkap'] ?? '')),
                'email' => $email,
                'password' => filled($row['password_baru'] ?? null) ? (string) $row['password_baru'] : null,
                'status' => $status,
            ];

            $validator = Validator::make($data, [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255'],
                'password' => [$existing ? 'nullable' : 'required', Password::min(10)->mixedCase()->numbers()],
                'status' => ['required', 'in:aktif,nonaktif,active,inactive,1,0'],
            ]);

            if ($id && ! $existing) {
                $errors[] = 'Baris '.$line.': ID akun tidak ditemukan.';
            }

            if (! $role) {
                $errors[] = 'Baris '.$line.': Akses tidak valid. Gunakan Super Admin, Panitia, Guru, Pengawas, atau Kepala Sekolah.';
            }

            if ($validator->fails()) {
                $errors[] = 'Baris '.$line.': '.$validator->errors()->first();
            }

            if ($id && isset($seenIds[$id])) {
                $errors[] = 'Baris '.$line.': ID akun duplikat di dalam file.';
            }

            if (isset($seenEmails[$email])) {
                $errors[] = 'Baris '.$line.': email duplikat di dalam file.';
            }

            if (User::query()
                ->where('email', $email)
                ->when($existing, fn ($query) => $query->where('id', '!=', $existing->getKey()))
                ->exists()) {
                $errors[] = 'Baris '.$line.': email sudah dipakai akun lain.';
            }

            if ($existing?->is($request->user()) && ($role !== $existing->role || ! $this->isActive($status))) {
                $errors[] = 'Baris '.$line.': hak akses dan status akun yang sedang digunakan tidak boleh diubah melalui impor.';
            }

            if ($id) {
                $seenIds[$id] = true;
            }
            $seenEmails[$email] = true;
            $preparedRows[] = [$data, $role, $existing];
        }

        if ($errors !== []) {
            return back()->withErrors([
                'account_spreadsheet' => implode(' ', array_slice(array_unique($errors), 0, 10)),
            ]);
        }

        try {
            DB::transaction(function () use ($preparedRows, $managedUsers): void {
                $currentUsers = User::query()
                    ->where('role', '!=', UserRole::Student)
                    ->lockForUpdate()
                    ->get();
                $projectedRoles = $currentUsers->mapWithKeys(
                    fn (User $user): array => [(string) $user->id => $user->role->value],
                )->all();
                $projectedActive = $currentUsers->mapWithKeys(
                    fn (User $user): array => [(string) $user->id => $user->is_active],
                )->all();

                foreach ($preparedRows as $offset => [$data, $role, $existing]) {
                    $key = $existing ? (string) $existing->id : 'new-'.$offset;
                    $projectedRoles[$key] = $role->value;
                    $projectedActive[$key] = $this->isActive($data['status']);
                }

                $managedUsers->assertProjectedRoleCounts(array_count_values($projectedRoles));

                $activeSuperAdmins = collect($projectedRoles)
                    ->filter(fn (string $role, $key): bool => $role === UserRole::SuperAdmin->value && ($projectedActive[$key] ?? false))
                    ->count();

                if ($activeSuperAdmins < 1) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'account_spreadsheet' => 'Minimal harus ada satu Super Admin aktif.',
                    ]);
                }

                foreach ($preparedRows as [$data, $role, $existing]) {
                    $user = $existing ?? new User();
                    $user->fill([
                        'name' => $data['name'],
                        'email' => $data['email'],
                        'role' => $role,
                        'is_active' => $this->isActive($data['status']),
                    ]);

                    if ($data['password']) {
                        $user->password = $data['password'];
                        $user->must_change_password = true;
                    }

                    $user->save();
                }
            });
        } catch (\Illuminate\Validation\ValidationException $exception) {
            throw $exception;
        }

        return back()->with('status', count($preparedRows).' akun berhasil diimpor atau diperbarui.');
    }

    private function download(string $path, string $filename): BinaryFileResponse
    {
        return response()
            ->download(
                $path,
                $filename,
                ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            )
            ->deleteFileAfterSend(true);
    }

    private function isActive(string $status): bool
    {
        return in_array($status, ['aktif', 'active', '1'], true);
    }
}
