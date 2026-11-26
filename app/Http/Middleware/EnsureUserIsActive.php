<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $student = $user?->role === UserRole::Student ? $user->student : null;
        if ($user && (! $user->is_active || ($student && ! $student->is_active))) {
            $notice = $student && ! $student->is_active
                ? $student->inactiveNotice() : 'Akun Anda sedang dinonaktifkan. Hubungi panitia ujian.';
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $request->expectsJson()
                ? response()->json(['message' => $notice], 403)
                : redirect()->route('login')->withErrors(['login' => $notice]);
        }

        return $next($request);
    }
}
