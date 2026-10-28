<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Нэвтрэх мэдээллийн сан нь нууц үгийн менежер шиг ажиллана: нэг удаа өөрийн нууц үгээр
 * нээгээд, түүнээс хойш тодорхой хугацаанд нэг товшилтоор системүүд рүү нэвтэрнэ.
 */
class Vault
{
    public const SESSION_KEY = 'vault.unlocked_until';

    public const MINUTES = 240;

    public static function unlock(Request $request): void
    {
        $request->session()->put(self::SESSION_KEY, now()->addMinutes(self::MINUTES)->timestamp);
    }

    /**
     * Нээлттэй сангийн хугацааг хэрэглэгчийн идэвх дээр сунгана — ажлын
     * өдрийн турш холбосон систем дээр дарахад дахин нууц үг асуухгүй.
     * Хаалттай буюу хугацаа нь дууссан санг энэ нээхгүй.
     */
    public static function touch(Request $request): void
    {
        if (! $request->user() || self::unlockedUntil($request) === null) {
            return;
        }

        $request->session()->put(self::SESSION_KEY, now()->addMinutes(self::MINUTES)->timestamp);
    }

    /**
     * Идэвхтэй session дээр нээнэ (хүсэлтгүй газраас, ж: нэвтрэлтийн event).
     */
    public static function unlockCurrentSession(): void
    {
        try {
            session()->put(self::SESSION_KEY, now()->addMinutes(self::MINUTES)->timestamp);
        } catch (\Throwable) {
            // Session байхгүй орчинд (console) алгасна.
        }
    }

    public static function lock(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);

        // Админаас нээсэн байсан бол түүнийг нь ч хаана — эс бөгөөс
        // «Сан хаах» дараад хаагдахгүй байх болно.
        self::revoke($request->user());
    }

    /**
     * Админаас тухайн албан хаагчийн санг нээх (өөрийнх нь session биш тул
     * хугацаагаар бүртгэнэ).
     */
    public static function grant(User $user, int $minutes, ?User $by = null): void
    {
        $user->forceFill([
            'vault_open_until' => now()->addMinutes($minutes),
            'vault_opened_by' => $by?->id,
        ])->save();
    }

    public static function revoke(?object $user): void
    {
        if (! $user instanceof User || ! $user->vault_open_until) {
            return;
        }

        $user->forceFill([
            'vault_open_until' => null,
            'vault_opened_by' => null,
        ])->save();
    }

    /**
     * Админаас нээсэн хугацаа (хүчинтэй бол), эсвэл null.
     */
    public static function grantedUntil(?object $user): ?int
    {
        if (! $user instanceof User || ! $user->vault_open_until) {
            return null;
        }

        $until = $user->vault_open_until->timestamp;

        return $until > now()->timestamp ? $until : null;
    }

    public static function isUnlocked(Request $request): bool
    {
        return self::unlockedUntil($request) !== null;
    }

    /**
     * Түгжээ тайлагдсан бол дуусах хугацаа, эсвэл null.
     */
    public static function unlockedUntil(Request $request): ?int
    {
        $best = null;

        if ($request->hasSession()) {
            $until = $request->session()->get(self::SESSION_KEY);

            if (is_int($until) && $until > now()->timestamp) {
                $best = $until;
            }
        }

        $granted = self::grantedUntil($request->user());

        if ($granted !== null && ($best === null || $granted > $best)) {
            $best = $granted;
        }

        return $best;
    }
}
