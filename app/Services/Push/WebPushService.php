<?php

namespace App\Services\Push;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class WebPushService
{
    /**
     * Push delivery is best-effort and additive: database notifications
     * always fire independently of this method's outcome.
     *
     * @return array{sent: int, cleaned: int}
     */
    public function sendToUser(User $user, string $title, string $body, array $data = []): array
    {
        return $this->deliver($user, $title, $body, $data, fn () => new WebPush($this->authConfig()));
    }

    /**
     * @param  callable(): WebPush  $clientFactory  Seam for tests: real delivery
     *                                              always uses the default factory above.
     * @return array{sent: int, cleaned: int}
     */
    public function deliver(User $user, string $title, string $body, array $data, callable $clientFactory): array
    {
        $sent = 0;
        $cleaned = 0;

        if (! $this->isConfigured()) {
            return ['sent' => $sent, 'cleaned' => $cleaned];
        }

        $subscriptions = PushSubscription::where('user_id', $user->id)->get();

        if ($subscriptions->isEmpty()) {
            return ['sent' => $sent, 'cleaned' => $cleaned];
        }

        try {
            $webPush = $clientFactory();
        } catch (\Throwable $e) {
            Log::warning('webpush misconfigured; skipping push delivery', [
                'user_id' => $user->id,
            ]);

            return ['sent' => $sent, 'cleaned' => $cleaned];
        }

        $payload = json_encode(array_merge([
            'title' => $title,
            'body' => $body,
        ], $data)) ?: '';

        foreach ($subscriptions as $subscription) {
            try {
                $report = $webPush->sendOneNotification(
                    Subscription::create([
                        'endpoint' => $subscription->endpoint,
                        'keys' => [
                            'p256dh' => $subscription->public_key,
                            'auth' => $subscription->auth_token,
                        ],
                        'contentEncoding' => $subscription->content_encoding,
                    ]),
                    $payload
                );

                if ($report->isSuccess()) {
                    $sent++;
                } elseif ($report->isSubscriptionExpired()) {
                    $subscription->delete();
                    $cleaned++;
                }
            } catch (\Throwable $e) {
                Log::warning('webpush delivery failed for one subscription', [
                    'user_id' => $user->id,
                    'subscription_id' => $subscription->id,
                ]);
            }
        }

        return ['sent' => $sent, 'cleaned' => $cleaned];
    }

    public function isConfigured(): bool
    {
        return (bool) config('webpush.vapid_public_key')
            && (bool) config('webpush.vapid_private_key')
            && (bool) config('webpush.vapid_subject');
    }

    /**
     * @return array{VAPID: array{subject: string, publicKey: string, privateKey: string}}
     */
    private function authConfig(): array
    {
        return [
            'VAPID' => [
                'subject' => (string) config('webpush.vapid_subject'),
                'publicKey' => (string) config('webpush.vapid_public_key'),
                'privateKey' => (string) config('webpush.vapid_private_key'),
            ],
        ];
    }
}
