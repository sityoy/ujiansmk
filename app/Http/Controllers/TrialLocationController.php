<?php

namespace App\Http\Controllers;

use App\Models\TrialLocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TrialLocationController extends Controller
{
    public function index(): View
    {
        return view('tryouts.locations', ['locations' => TrialLocation::query()->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        TrialLocation::create($this->validatedLocation($request));

        return back()->with('status', 'Lokasi simulasi berhasil ditambahkan.');
    }

    public function update(Request $request, TrialLocation $trialLocation): RedirectResponse
    {
        $trialLocation->update($this->validatedLocation($request, $trialLocation));

        return back()->with('status', 'Lokasi simulasi berhasil diperbarui.');
    }

    private function validatedLocation(Request $request, ?TrialLocation $location = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('trial_locations', 'name')->ignore($location)],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_meters' => ['required', 'integer', 'between:10,5000'],
            'max_accuracy_meters' => ['required', 'integer', 'between:5,500'],
        ]);
    }
}
