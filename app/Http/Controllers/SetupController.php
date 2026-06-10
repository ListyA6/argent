<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class SetupController extends Controller
{
    /**
     * One-time (idempotent) setup for shared hosting where artisan
     * can't be run over SSH. Protected by SETUP_KEY from .env.
     */
    public function run(Request $request)
    {
        $key = (string) config('argent.setup_key');

        abort_if($key === '' || ! hash_equals($key, (string) $request->query('key', '')), 403);

        $output = '';

        Artisan::call('migrate', ['--force' => true]);
        $output .= Artisan::output();

        Artisan::call('db:seed', ['--force' => true]);
        $output .= Artisan::output();

        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');

        return response('<pre>'.e("Setup complete.\n\n".$output).'</pre>');
    }

    /**
     * Cron fallback: hit this URL every 5 minutes if running
     * `php artisan schedule:run` from cron isn't possible.
     */
    public function cron(Request $request)
    {
        $key = (string) config('argent.setup_key');

        abort_if($key === '' || ! hash_equals($key, (string) $request->query('key', '')), 403);

        Artisan::call('reminders:send');

        return response()->json(['ok' => true, 'output' => trim(Artisan::output())]);
    }
}
