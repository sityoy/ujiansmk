<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordHasBeenChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->requiresPasswordChange()) {
            return redirect()->route('password.change');
        }

        return $next($request);
    }
}
