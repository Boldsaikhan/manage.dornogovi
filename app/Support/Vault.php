<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Нэвтрэх мэдээллийн сан нь нууц үгийн менежер шиг ажиллана: нэг удаа өөрийн нууц үгээр
 * нээгээд, түүнээс хойш тодорхой хугацаанд нэг товшилтоор системүүд рүү нэвтэрнэ.
 */
class Vault
{
    public const SESSION_KEY = 'vault.unlocked_until';

    public const MINUTES = 120;

    public static function unlock(Request $request): void
    {
        $request->session()->put(self::SESSION_KEY, now()->addMinutes(self::MINUTES)->timestamp);
    }

    public static function lock(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
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
        if (! $request->hasSession()) {
            return null;
        }

        $until = $request->session()->get(self::SESSION_KEY);

        if (! is_int($until) || $until <= now()->timestamp) {
            return null;
        }

        return $until;
    }
}
