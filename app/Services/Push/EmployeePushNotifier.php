<?php

namespace App\Services\Push;

use App\Models\User;
use App\Support\PersonName;
use Illuminate\Support\Collection;

/**
 * Албан хаагчид холбоотой мэдээллийг push-ээр мэдэгдэнэ.
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

        $this->push->sendToUsers($users, $payload);
    }

    /**
     * @param  iterable<int, User|int>  $users
     * @param  array{title: string, body?: string, url?: string, tag?: string}  $payload
     */
    public function notifyUsers(iterable $users, array $payload): void
    {
        $this->push->sendToUsers($users, $payload);
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
