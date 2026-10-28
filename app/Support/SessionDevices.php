<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Албан хаагч хаанаас, ямар төхөөрөмжөөр нэвтэрсэн байгаа.
 *
 * `sessions` хүснэгтээс уншина — session драйвер `database` үед л
 * боломжтой. Өөр драйвер дээр хоосон жагсаалт буцаана.
 */
class SessionDevices
{
    public static function enabled(): bool
    {
        return config('session.driver') === 'database';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function forUser(?User $user, ?string $currentId = null): array
    {
        if (! $user || ! self::enabled()) {
            return [];
        }

        return DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get(['id', 'ip_address', 'user_agent', 'last_activity'])
            ->map(fn ($row) => [
                'id' => (string) $row->id,
                'is_current' => (string) $row->id === (string) $currentId,
                'device' => self::device($row->user_agent),
                'browser' => self::browser($row->user_agent),
                'ip' => $row->ip_address ?: '—',
                'last_active' => self::humanDiff((int) $row->last_activity),
                'last_active_at' => Carbon::createFromTimestamp((int) $row->last_activity)
                    ->timezone(config('app.timezone'))
                    ->format('Y-m-d H:i'),
            ])
            ->values()
            ->all();
    }

    /** Нэг төхөөрөмжийн холболтыг тасална. */
    public static function revoke(User $user, string $sessionId): bool
    {
        if (! self::enabled()) {
            return false;
        }

        return DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->where('id', $sessionId)
            ->delete() > 0;
    }

    /** Одоогийнхоос бусад бүх холболтыг тасална. */
    public static function revokeOthers(User $user, ?string $currentId): int
    {
        if (! self::enabled()) {
            return 0;
        }

        return DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->when($currentId, fn ($q) => $q->where('id', '!=', $currentId))
            ->delete();
    }

    private static function humanDiff(int $timestamp): string
    {
        $seconds = max(0, time() - $timestamp);

        if ($seconds < 120) {
            return 'Саяхан';
        }

        if ($seconds < 3600) {
            return intdiv($seconds, 60).' минутын өмнө';
        }

        if ($seconds < 86400) {
            return intdiv($seconds, 3600).' цагийн өмнө';
        }

        return intdiv($seconds, 86400).' хоногийн өмнө';
    }

    /** Үйлдлийн систем / төхөөрөмж. */
    private static function device(?string $agent): string
    {
        $agent = (string) $agent;

        return match (true) {
            str_contains($agent, 'iPhone') => 'iPhone',
            str_contains($agent, 'iPad') => 'iPad',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Macintosh'), str_contains($agent, 'Mac OS') => 'Mac',
            str_contains($agent, 'Linux') => 'Linux',
            $agent === '' => 'Тодорхойгүй',
            default => 'Бусад',
        };
    }

    private static function browser(?string $agent): string
    {
        $agent = (string) $agent;

        return match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/'), str_contains($agent, 'Opera') => 'Opera',
            str_contains($agent, 'Firefox') => 'Firefox',
            str_contains($agent, 'Chrome') => 'Chrome',
            str_contains($agent, 'Safari') => 'Safari',
            $agent === '' => '—',
            default => 'Бусад хөтөч',
        };
    }
}
