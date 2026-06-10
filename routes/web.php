<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\PushController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\StatsController;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request) {
    try {
        $categories = Category::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug', 'color', 'icon', 'sound_preset']);
    } catch (\Throwable) {
        // DB not migrated yet (fresh deploy before /setup has been hit).
        $categories = collect();
    }

    return view('app', [
        'authed' => $request->session()->get('argent_authed') === true,
        'categories' => $categories,
        'vapidPublicKey' => config('argent.vapid.public'),
    ]);
});

Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout']);

// No session/CSRF here: these must work on a fresh DB (before the sessions
// table exists) and from cron, where no browser session is involved.
$bare = [
    \Illuminate\Session\Middleware\StartSession::class,
    \Illuminate\View\Middleware\ShareErrorsFromSession::class,
    \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
];
Route::get('/setup', [SetupController::class, 'run'])->withoutMiddleware($bare);
Route::get('/cron/run', [SetupController::class, 'cron'])->withoutMiddleware($bare);

Route::middleware('pin')->prefix('api')->group(function () {
    Route::get('/expenses', [ExpenseController::class, 'index']);
    Route::post('/expenses', [ExpenseController::class, 'store']);
    Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy']);
    Route::get('/suggest', [ExpenseController::class, 'suggest']);
    Route::get('/favorites', [ExpenseController::class, 'favorites']);
    Route::get('/stats', [StatsController::class, 'month']);

    Route::get('/reminders', [ReminderController::class, 'index']);
    Route::post('/reminders', [ReminderController::class, 'store']);
    Route::patch('/reminders/{reminder}', [ReminderController::class, 'update']);
    Route::delete('/reminders/{reminder}', [ReminderController::class, 'destroy']);

    Route::post('/push/subscribe', [PushController::class, 'subscribe']);
    Route::post('/push/unsubscribe', [PushController::class, 'unsubscribe']);
    Route::post('/push/test', [PushController::class, 'test']);

    Route::get('/settings', [SettingController::class, 'index']);
    Route::patch('/settings', [SettingController::class, 'update']);
});

Route::middleware('pin')->get('/export.csv', [ExpenseController::class, 'exportCsv']);
