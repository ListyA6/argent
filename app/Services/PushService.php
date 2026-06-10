<?php

namespace App\Services;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class PushService
{
    /**
     * Send a notification to every registered device.
     * Dead subscriptions (410/404) are pruned automatically.
     *
     * @return array{sent: int, failed: int}
     */
    public function broadcast(string $title, string $body, string $tag = 'argent'): array
    {
        $subs = PushSubscription::all();

        if ($subs->isEmpty()) {
            return ['sent' => 0, 'failed' => 0];
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => config('argent.vapid.subject'),
                'publicKey' => config('argent.vapid.public'),
                'privateKey' => config('argent.vapid.private'),
            ],
        ]);

        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'tag' => $tag,
            'url' => '/',
        ]);

        foreach ($subs as $sub) {
            $webPush->queueNotification(Subscription::create([
                'endpoint' => $sub->endpoint,
                'keys' => ['p256dh' => $sub->p256dh, 'auth' => $sub->auth],
            ]), $payload);
        }

        $sent = 0;
        $failed = 0;

        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                $sent++;
                continue;
            }

            $failed++;

            if ($report->isSubscriptionExpired()) {
                PushSubscription::where('endpoint_hash', hash('sha256', $report->getEndpoint()))->delete();
            } else {
                Log::warning('Push failed: '.$report->getReason());
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }
}
