<?php

namespace App\Services\Push;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

/**
 * Web Push (VAPID) — албан хаагчийн төхөөрөмж рүү мэдэгдэл илгээнэ.
 */
class WebPushNotifier
{
    public function enabled(): bool
    {
        return filled(config('webpush.vapid.public_key'))
            && filled(config('webpush.vapid.private_key'));
    }

    public function publicKey(): ?string
    {
        $key = config('webpush.vapid.public_key');

        return filled($key) ? (string) $key : null;
    }

    /**
     * @param  iterable<int, User|int>  $users
     * @param  array{title: string, body?: string, url?: string, tag?: string}  $payload
     */
    public function sendToUsers(iterable $users, array $payload): void
    {
        if (! $this->enabled()) {
            return;
        }

        $ids = collect($users)->map(function ($user) {
            return $user instanceof User ? $user->id : (int) $user;
        })->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return;
        }

        $subscriptions = PushSubscription::query()
            ->whereIn('user_id', $ids)
            ->get();

        $this->sendToSubscriptions($subscriptions, $payload);
    }

    /**
     * @param  Collection<int, PushSubscription>  $subscriptions
     * @param  array{title: string, body?: string, url?: string, tag?: string}  $payload
     */
    public function sendToSubscriptions(Collection $subscriptions, array $payload): void
    {
        if (! $this->enabled() || $subscriptions->isEmpty()) {
            return;
        }

        $webPush = $this->client();
        if (! $webPush) {
            return;
        }

        $json = json_encode([
            'title' => $payload['title'],
            'body' => $payload['body'] ?? '',
            'url' => $payload['url'] ?? '/dept-dashboard',
            'tag' => $payload['tag'] ?? 'manage-dornogovi',
            'icon' => '/icons/icon-192.png',
            'badge' => '/icons/icon-192.png',
        ], JSON_UNESCAPED_UNICODE);

        foreach ($subscriptions as $row) {
            try {
                $webPush->queueNotification(
                    Subscription::create([
                        'endpoint' => $row->endpoint,
                        'publicKey' => $row->public_key,
                        'authToken' => $row->auth_token,
                        'contentEncoding' => $row->content_encoding ?: 'aesgcm',
                    ]),
                    $json,
                );
            } catch (Throwable $e) {
                Log::warning('Push queue failed', ['id' => $row->id, 'error' => $e->getMessage()]);
            }
        }

        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                continue;
            }

            $endpoint = $report->getRequest()?->getUri()?->__toString();
            $code = $report->getResponse()?->getStatusCode();

            // Хүчингүй/устгагдсан бүртгэлийг цэвэрлэнэ.
            if (in_array($code, [404, 410], true) && $endpoint) {
                PushSubscription::query()->where('endpoint', $endpoint)->delete();
            } else {
                Log::info('Push delivery failed', [
                    'endpoint' => $endpoint,
                    'reason' => $report->getReason(),
                ]);
            }
        }
    }

    private function client(): ?WebPush
    {
        try {
            return new WebPush([
                'VAPID' => [
                    'subject' => config('webpush.vapid.subject'),
                    'publicKey' => config('webpush.vapid.public_key'),
                    'privateKey' => config('webpush.vapid.private_key'),
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('WebPush init failed', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
