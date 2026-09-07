<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Users\ManagedUserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function index(Request $request, ManagedUserService $managedUsers): View
    {
        $roleValues = array_map(fn (UserRole $role): string => $role->value, $managedUsers->roles());
        $search = trim((string) $request->query('q'));
        $role = in_array($request->query('role'), $roleValues, true) ? $request->query('role') : null;
        $editingUser = $request->integer('edit')
            ? User::query()->where('role', '!=', UserRole::Student)->find($request->integer('edit'))
            : null;

        return view('users.index', [
            'users' => User::query()
                ->where('role', '!=', UserRole::Student)
                ->when($search, fn ($query) => $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                }))
                ->when($role, fn ($query) => $query->where('role', $role))
                ->orderBy('role')
                ->orderBy('name')
                ->paginate(10)
                ->withQueryString(),
            'roles' => $managedUsers->roles(),
            'editingUser' => $editingUser,
            'search' => $search,
            'selectedRole' => $role,
        ]);
    }

    public function store(Request $request, ManagedUserService $managedUsers): RedirectResponse
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);

        $manageableRoles = array_map(fn (UserRole $role): string => $role->value, $managedUsers->roles());

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

        DB::transaction(function () use ($validated, $managedUsers): void {
            User::query()->where('role', '!=', UserRole::Student)->lockForUpdate()->get();
            $managedUsers->assertRoleCapacity(UserRole::from($validated['role']));

            User::create([
                ...$validated,
                'is_active' => true,
                'must_change_password' => true,
            ]);
        });

        return back()->with('status', 'Akun pengguna berhasil dibuat.');
    }

    public function update(Request $request, User $user, ManagedUserService $managedUsers): RedirectResponse
    {
        abort_if($user->role === UserRole::Student, 404);

        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
            'is_active' => $request->boolean('is_active'),
        ]);
        $manageableRoles = array_map(fn (UserRole $role): string => $role->value, $managedUsers->roles());
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'role' => ['required', Rule::in($manageableRoles)],
            'is_active' => ['required', 'boolean'],
            'password' => ['nullable', 'confirmed', Password::min(10)->mixedCase()->numbers()],
        ]);

        $newRole = UserRole::from($validated['role']);

        if ($request->user()->is($user) && ($newRole !== $user->role || ! $validated['is_active'])) {
            throw ValidationException::withMessages([
                'user' => 'Hak akses dan status akun yang sedang digunakan tidak dapat diubah.',
            ]);
        }

        DB::transaction(function () use ($request, $user, $validated, $newRole, $managedUsers): void {
            User::query()->where('role', '!=', UserRole::Student)->lockForUpdate()->get();
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($newRole !== $lockedUser->role) {
                $managedUsers->assertRoleCapacity($newRole, $lockedUser);
            }

            if (
                $lockedUser->role === UserRole::SuperAdmin
                && $lockedUser->is_active
                && ($newRole !== UserRole::SuperAdmin || ! $validated['is_active'])
                && User::query()->where('role', UserRole::SuperAdmin)->where('is_active', true)->count() <= 1
            ) {
                throw ValidationException::withMessages(['user' => 'Minimal harus ada satu Super Admin aktif.']);
            }

            $lockedUser->fill([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'role' => $newRole,
                'is_active' => $validated['is_active'],
            ]);

            if (filled($validated['password'] ?? null)) {
                $lockedUser->password = $validated['password'];
                $lockedUser->must_change_password = ! $request->user()->is($lockedUser);
            }

            $lockedUser->save();
        });

        return redirect()->route('users.index')->with('status', 'Data akun berhasil diperbarui.');
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
