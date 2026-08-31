<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSignupsEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('mahalinkam.signups_enabled'), 403, 'Sign-ups are disabled on this server.');

        return $next($request);
    }
}
