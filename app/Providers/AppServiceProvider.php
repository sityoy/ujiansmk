<?php

namespace App\Providers;

use App\Models\SchoolProfile;
use App\Services\Exams\QuestionText;
use App\Support\TablePagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(QuestionText::class);
    }

    public function boot(): void
    {
        Paginator::defaultView('pagination.data');
        Builder::macro('paginateForDisplay', function (string $pageName = 'page') {
            return TablePagination::query($this, $pageName);
        });

        View::composer(
            ['auth.login', 'auth.change-password', 'dashboard', 'layouts.app', 'reports.midterm.print'],
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
