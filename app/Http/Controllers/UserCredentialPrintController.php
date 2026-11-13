<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\SchoolProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserCredentialPrintController extends Controller
{
    /** @var array<int, UserRole> */
    private const PRINTABLE_ROLES = [
        UserRole::Committee,
        UserRole::Teacher,
        UserRole::Proctor,
        UserRole::Principal,
    ];

    public function generate(Request $request): Response
    {
        $printableRoleValues = array_map(
            fn (UserRole $role): string => $role->value,
            self::PRINTABLE_ROLES,
        );

        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'role' => ['nullable', Rule::in($printableRoleValues)],
        ]);

        $query = User::query()
            ->whereIn('role', $printableRoleValues)
            ->where('is_active', true)
            ->when(
                filled($validated['user_id'] ?? null),
                fn ($query) => $query->whereKey((int) $validated['user_id']),
            )
            ->when(
                blank($validated['user_id'] ?? null) && filled($validated['role'] ?? null),
                fn ($query) => $query->where('role', $validated['role']),
            );

        $accountIds = $query->pluck('id')->all();

        if ($accountIds === []) {
            throw ValidationException::withMessages([
                'account_print' => 'Tidak ada akun aktif yang dapat dicetak untuk pilihan tersebut.',
            ]);
        }

        $credentials = DB::transaction(function () use ($accountIds, $printableRoleValues) {
            return User::query()
                ->whereKey($accountIds)
                ->whereIn('role', $printableRoleValues)
                ->where('is_active', true)
                ->orderBy('role')
                ->orderBy('name')
                ->lockForUpdate()
                ->get()
                ->map(function (User $account): array {
                    $temporaryPassword = $this->temporaryPassword();

                    $account->forceFill([
                        'password' => $temporaryPassword,
                        'must_change_password' => true,
                    ])->save();

                    return [
                        'id' => $account->id,
                        'name' => $account->name,
                        'email' => $account->email,
                        'role' => $account->role->label(),
                        'password' => $temporaryPassword,
                    ];
                });
        });

        if ($credentials->isEmpty()) {
            throw ValidationException::withMessages([
                'account_print' => 'Akun berubah atau dinonaktifkan sebelum proses cetak selesai.',
            ]);
        }

        return response()
            ->view('users.print-credentials', [
                'school' => SchoolProfile::query()->first(),
                'credentials' => $credentials,
                'generatedAt' => now(),
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, private')
            ->header('Pragma', 'no-cache')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    private function temporaryPassword(): string
    {
        return 'Ujian'.Str::upper(Str::random(4)).random_int(1000, 9999);
    }
}
