<?php

namespace App\Console\Commands;

use App\Models\Expense;
use App\Models\Reminder;
use App\Services\PushService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendReminders extends Command
{
    protected $signature = 'reminders:send';

    protected $description = 'Send any due expense reminders via web push';

    public function handle(PushService $push): int
    {
        $now = now();
        $today = $now->toDateString();

        $due = Reminder::where('enabled', true)
            ->where(function ($q) use ($today) {
                $q->whereNull('last_sent_date')->orWhere('last_sent_date', '<', $today);
            })
            ->get()
            ->filter(function (Reminder $r) use ($now, $today) {
                $target = Carbon::parse("$today {$r->time}", config('app.timezone'));

                // Fire only inside a 45-minute window after the set time, so a
                // cron that was down for hours doesn't blast stale reminders.
                return $now->gte($target) && $now->diffInMinutes($target, true) <= 45;
            });

        foreach ($due as $reminder) {
            [$title, $body] = $this->compose($reminder);
            $result = $push->broadcast($title, $body, 'argent-reminder-'.$reminder->id);
            $reminder->update(['last_sent_date' => $today]);
            $this->info("Reminder {$reminder->time}: sent={$result['sent']} failed={$result['failed']}");
        }

        return self::SUCCESS;
    }

    private function compose(Reminder $reminder): array
    {
        $todayExpenses = Expense::whereDate('spent_at', now()->toDateString())
            ->orderBy('spent_at')
            ->get();

        $label = $reminder->label !== '' ? $reminder->label : 'Expense check';

        if ($todayExpenses->isEmpty()) {
            return [$label, 'Nothing logged today yet. Anything you bought?'];
        }

        $total = number_format($todayExpenses->sum('amount'), 0, ',', '.');
        $lines = $todayExpenses->take(4)
            ->map(fn ($e) => $e->item.' '.number_format($e->amount, 0, ',', '.'))
            ->implode(' · ');

        if ($todayExpenses->count() > 4) {
            $lines .= ' · +'.($todayExpenses->count() - 4).' more';
        }

        return ["Today: Rp{$total} — {$todayExpenses->count()} items", $lines];
    }
}
