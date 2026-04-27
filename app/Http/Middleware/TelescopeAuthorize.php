<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Telescope\Telescope;
use Symfony\Component\HttpFoundation\Response;

class TelescopeAuthorize
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $redirectUrl = url('/telescope/login?path=' . urlencode($request->path()));
        return Telescope::check($request) ? $next($request) : redirect()->guest($redirectUrl);
    }
}
