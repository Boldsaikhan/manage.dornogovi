<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Апп/сесс түгжээ — гараад буцаж ороход нууц үг ± биометрик асууна.
 */
class AppLock
{
    public const SESSION_KEY = 'app.locked';

    public const MODE_KEY = 'app.lock_mode';

    /** Нууц үг + (байвал) биометрик */
    public const MODE_FULL = 'full';

    /** Зөвхөн биометрик (нууц үгээр нэвтэрсний дараа) */
    public const MODE_BIOMETRIC = 'biometric';

    public static function lock(Request $request, string $mode = self::MODE_FULL): void
    {
        $request->session()->put(self::SESSION_KEY, true);
        $request->session()->put(self::MODE_KEY, $mode === self::MODE_BIOMETRIC
            ? self::MODE_BIOMETRIC
            : self::MODE_FULL);

        Vault::lock($request);
    }

    public static function unlock(Request $request): void
    {
        $request->session()->forget([self::SESSION_KEY, self::MODE_KEY]);
    }

    public static function isLocked(Request $request): bool
    {
        if (! $request->hasSession()) {
            return false;
        }

        return (bool) $request->session()->get(self::SESSION_KEY);
    }

    public static function mode(Request $request): string
    {
        if (! self::isLocked($request)) {
            return self::MODE_FULL;
        }

        $mode = $request->session()->get(self::MODE_KEY);

        return $mode === self::MODE_BIOMETRIC ? self::MODE_BIOMETRIC : self::MODE_FULL;
    }
}
