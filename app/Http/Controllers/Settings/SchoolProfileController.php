<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\SchoolProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SchoolProfileController extends Controller
{
    public function edit(): View
    {
        return view('settings.school-profile', [
            'profile' => SchoolProfile::query()->first() ?? new SchoolProfile(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'npsn' => ['nullable', 'string', 'max:16'],
            'address' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'principal_name' => ['nullable', 'string', 'max:255'],
        ], [
            'name.required' => 'Nama sekolah wajib diisi.',
            'email.email' => 'Format email sekolah belum benar.',
        ]);

        $profile = SchoolProfile::query()->first() ?? new SchoolProfile();
        $profile->fill($validated)->save();

        return back()->with('status', 'Identitas sekolah berhasil disimpan.');
    }
}
