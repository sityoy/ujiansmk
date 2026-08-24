<?php

namespace App\Providers;

use App\Models\SchoolProfile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        View::composer(
            ['auth.login', 'layouts.app', 'reports.midterm.print'],
            function ($view): void {
                $schoolProfile = null;

                try {
                    if (Schema::hasTable('school_profiles')) {
                        $schoolProfile = SchoolProfile::query()->first();
                    }
                } catch (Throwable) {
                    // Aplikasi tetap dapat menampilkan halaman instalasi sebelum migrasi selesai.
                }

                $view->with('schoolProfile', $schoolProfile);
            },
        );
    }
}
