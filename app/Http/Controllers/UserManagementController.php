<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function index(): View
    {
        return view('users.index', [
            'users' => User::query()
                ->orderBy('role')
                ->orderBy('name')
                ->paginate(20),
            'roles' => array_values(array_filter(
                UserRole::cases(),
                fn (UserRole $role): bool => $role !== UserRole::Student,
            )),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);

        $manageableRoles = array_map(
            fn (UserRole $role): string => $role->value,
            array_filter(UserRole::cases(), fn (UserRole $role): bool => $role !== UserRole::Student),
        );

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in($manageableRoles)],
            'password' => [
                'required',
                'confirmed',
                Password::min(10)->mixedCase()->numbers(),
            ],
        ]);

        User::create([
            ...$validated,
            'is_active' => true,
        ]);

        return back()->with('status', 'Akun pengguna berhasil dibuat.');
    }

    public function toggle(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->is($user)) {
            return back()->withErrors(['user' => 'Akun yang sedang digunakan tidak dapat dinonaktifkan.']);
        }

        if (
            $user->role === UserRole::SuperAdmin
            && $user->is_active
            && User::query()->where('role', UserRole::SuperAdmin)->where('is_active', true)->count() <= 1
        ) {
            return back()->withErrors(['user' => 'Minimal harus ada satu Super Admin aktif.']);
        }

        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('status', 'Status akun berhasil diperbarui.');
    }
}
