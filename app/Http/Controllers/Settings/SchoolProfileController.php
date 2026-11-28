<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\SchoolProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SchoolProfileController extends Controller
{
    public function edit(): View
    {
        $profile = SchoolProfile::query()->first();

        return view('settings.school-profile', [
            'profile' => $profile ?? new SchoolProfile,
            'logoUrl' => SchoolProfile::logoUrl($profile),
            'hasLetterhead' => SchoolProfile::letterheadFile($profile) !== null,
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
            'letterhead' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4096,max_height=4096'],
        ], [
            'name.required' => 'Nama sekolah wajib diisi.',
            'email.email' => 'Format email sekolah belum benar.',
        ]);

        $profile = SchoolProfile::query()->first() ?? new SchoolProfile;
        $oldFiles = [];
        $newFiles = [];
        try {
            foreach (['logo' => 'school/logos', 'letterhead' => 'school/letterheads'] as $field => $directory) {
                if ($request->hasFile($field)) {
                    $path = $request->file($field)->store($directory);
                    if (! $path) {
                        throw ValidationException::withMessages([$field => 'Gambar sekolah tidak dapat disimpan. Coba unggah lagi.']);
                    }
                    $attribute = $field.'_path';
                    $oldFiles[] = $profile->{$attribute};
                    $newFiles[$attribute] = $path;
                }
            }

            $profile->fill(Arr::except($validated, ['logo', 'letterhead']) + $newFiles)->save();
        } catch (\Throwable $exception) {
            Storage::delete(array_values($newFiles));

            throw $exception;
        }
        Storage::delete(array_values(array_filter($oldFiles)));

        return back()->with('status', 'Identitas sekolah berhasil disimpan.');
    }

    public function logo(): BinaryFileResponse
    {
        $path = SchoolProfile::query()->first()?->logo_path;
        abort_unless($path && Storage::exists($path), 404);

        return response()->file(Storage::path($path), [
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function letterhead(): BinaryFileResponse
    {
        $path = SchoolProfile::letterheadFile(SchoolProfile::query()->first());
        abort_unless($path && is_file($path), 404);

        return response()->file($path, ['Cache-Control' => 'private, max-age=300']);
    }
}
