<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\SchoolProfile;
use App\Models\TrialQuestionBank;
use App\Models\User;
use App\Services\Exams\QuestionText;
use App\Support\TablePagination;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(QuestionText::class);
    }

    public function boot(): void
    {
        Gate::define('author-trial-questions', function (User $user): bool {
            return match ($user->role) {
                UserRole::SuperAdmin, UserRole::Committee => true,
                UserRole::Teacher => TrialQuestionBank::query()->where('teacher_user_id', $user->id)
                    ->whereNull('archived_at')->exists(),
                default => false,
            };
        });

        RateLimiter::for('school-login', function (Request $request): array {
            $input = $request->input('login');
            $login = is_string($input) ? Str::lower(trim(substr($input, 0, 255))) : '';

            return [
                Limit::perMinute(5)->by('account:'.$request->ip().'|'.$login),
                Limit::perMinute(360)->by('school:'.$request->ip()),
            ];
        });

        foreach (['trial-start' => 20, 'trial-attendance' => 15, 'trial-security' => 30, 'trial-answer' => 120, 'trial-incident' => 30, 'trial-submit' => 10] as $name => $maximum) {
            RateLimiter::for($name, function (Request $request) use ($name, $maximum): array {
                if ($user = $request->user()) {
                    return [Limit::perMinute($maximum)->by('account:'.$user->id)];
                }

                $limits = [Limit::perMinute($maximum)->by('session:'.$request->session()->getId())];
                if (in_array($name, ['trial-start', 'trial-attendance'], true)) {
                    $limits[] = Limit::perMinute(360)->by('school:'.$request->ip());
                }

                return $limits;
            });
        }

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
