<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PinAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->get('argent_authed') === true) {
            return $next($request);
        }

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json(['error' => 'locked'], 401);
        }

        return redirect('/');
    }
}
