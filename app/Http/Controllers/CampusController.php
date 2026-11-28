<?php

namespace App\Http\Controllers;

use App\Models\Campus;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CampusController extends Controller
{
    public function index(): View
    {
        return view('campuses.index', [
            'campuses' => Campus::query()
                ->withCount(['examSessions', 'dailyCheckins'])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Campus::create([
            ...$this->validatedCampus($request),
            'is_active' => true,
        ]);

        return back()->with('status', 'Lokasi ujian berhasil ditambahkan.');
    }

    public function update(Request $request, Campus $campus): RedirectResponse
    {
        $campus->update($this->validatedCampus($request, $campus));

        return back()->with('status', 'Lokasi dan radius ujian berhasil diperbarui.');
    }

    public function toggle(Campus $campus): RedirectResponse
    {
        $campus->update(['is_active' => ! $campus->is_active]);

        return back()->with('status', 'Status lokasi berhasil diperbarui.');
    }

    public function destroy(Campus $campus): RedirectResponse
    {
        if ($campus->examSessions()->exists() || $campus->dailyCheckins()->exists()) {
            return back()->withErrors([
                'campus' => 'Lokasi tidak dapat dihapus karena sudah memiliki sesi atau riwayat absensi. Nonaktifkan lokasi agar riwayat ujian tetap aman.',
            ]);
        }

        try {
            $campus->delete();
        } catch (QueryException) {
            return back()->withErrors([
                'campus' => 'Lokasi tidak dapat dihapus karena masih digunakan oleh data ujian.',
            ]);
        }

        return back()->with('status', 'Lokasi ujian berhasil dihapus.');
    }

    /** @return array<string, mixed> */
    private function validatedCampus(Request $request, ?Campus $campus = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('campuses', 'name')->ignore($campus),
            ],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_meters' => ['required', 'integer', 'between:10,5000'],
            'max_accuracy_meters' => ['required', 'integer', 'between:5,500'],
        ]);
    }
}
