<?php

namespace App\Services\Push;

use App\Models\User;
use App\Models\UserNotification;
use App\Support\PersonName;
use Illuminate\Support\Collection;

/**
 * Албан хаагчид холбоотой мэдээллийг in-app + push-ээр мэдэгдэнэ.
 */
class EmployeePushNotifier
{
    public function __construct(private WebPushNotifier $push) {}

    /**
     * Нэр (бүтэн / богино) таарах хэрэглэгчдэд илгээнэ.
     *
     * @param  string|array<int, string|null>|null  $names
     * @param  array{title: string, body?: string, url?: string, tag?: string}  $payload
     */
    public function notifyNamed(string|array|null $names, array $payload, ?int $alsoUserId = null): void
    {
        $users = $this->usersMatchingNames($names);

        if ($alsoUserId) {
            $extra = User::query()->find($alsoUserId);
            if ($extra) {
                $users = $users->push($extra)->unique('id');
            }
        }

        $this->notifyUsers($users, $payload);
    }

    /**
     * @param  iterable<int, User|int>  $users
     * @param  array{title: string, body?: string, url?: string, tag?: string}  $payload
     */
    public function notifyUsers(iterable $users, array $payload): void
    {
        $resolved = collect($users)
            ->map(function ($user) {
                if ($user instanceof User) {
                    return $user;
                }

                return User::query()->find($user);
            })
            ->filter()
            ->unique('id')
            ->values();

        if ($resolved->isEmpty()) {
            return;
        }

        $title = trim((string) ($payload['title'] ?? 'Мэдэгдэл')) ?: 'Мэдэгдэл';
        $body = isset($payload['body']) ? trim((string) $payload['body']) : null;
        $url = isset($payload['url']) ? trim((string) $payload['url']) : null;
        $tag = isset($payload['tag']) ? trim((string) $payload['tag']) : null;

        foreach ($resolved as $user) {
            if ($tag) {
                $exists = UserNotification::query()
                    ->where('user_id', $user->id)
                    ->where('tag', $tag)
                    ->whereNull('read_at')
                    ->exists();

                if ($exists) {
                    continue;
                }
            }

            UserNotification::query()->create([
                'user_id' => $user->id,
                'title' => $title,
                'body' => $body !== '' ? $body : null,
                'url' => $url !== '' ? $url : null,
                'tag' => $tag !== '' ? $tag : null,
            ]);
        }

        $this->push->sendToUsers($resolved, $payload);
    }

    /**
     * @param  string|array<int, string|null>|null  $names
     * @return Collection<int, User>
     */
    public function usersMatchingNames(string|array|null $names): Collection
    {
        $parts = collect(is_array($names) ? $names : [$names])
            ->flatMap(fn ($n) => preg_split('#[/;|,]+#u', (string) $n) ?: [])
            ->map(fn ($n) => trim((string) $n))
            ->filter()
            ->unique()
            ->values();

        if ($parts->isEmpty()) {
            return collect();
        }

        $variants = $parts
            ->flatMap(fn ($n) => array_filter([$n, PersonName::short($n)]))
            ->map(fn ($n) => trim($n))
            ->filter()
            ->unique()
            ->values();

        // SQLite/MySQL LOWER() кирилл үсгийг найдвартай буулгадаггүй тул LIKE-аар хайна.
        return User::query()
            ->where(function ($q) use ($variants) {
                foreach ($variants as $name) {
                    $q->orWhere('name', 'like', '%'.$name.'%');
                }
            })
            ->get();
    }
}
