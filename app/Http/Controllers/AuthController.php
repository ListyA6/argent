<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $key = 'pin:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json([
                'error' => 'too_many_attempts',
                'retry_in' => RateLimiter::availableIn($key),
            ], 429);
        }

        $pin = (string) $request->input('pin', '');

        if (! hash_equals((string) config('argent.pin'), $pin)) {
            RateLimiter::hit($key, 60);

            return response()->json(['error' => 'wrong_pin'], 422);
        }

        RateLimiter::clear($key);
        $request->session()->put('argent_authed', true);
        $request->session()->regenerate();

        return response()->json(['ok' => true]);
    }

    public function logout(Request $request)
    {
        $request->session()->flush();

        return response()->json(['ok' => true]);
    }
}
