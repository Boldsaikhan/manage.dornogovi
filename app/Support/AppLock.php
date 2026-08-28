<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Апп/сесс түгжээ — идэвхгүй болсны дараа нууц үг асууна.
 */
class AppLock
{
    public const SESSION_KEY = 'app.locked';

    public const MODE_KEY = 'app.lock_mode';

    /** Нууц үг + (байвал) биометрик */
    public const MODE_FULL = 'full';

    /** Зөвхөн биометрик (нууц үгээр нэвтэрсний дараа) */
    public const MODE_BIOMETRIC = 'biometric';

    /** 30 минут идэвхгүй болсон үед тавигдсан түгжээ */
    public const IDLE_LOCK_KEY = 'app.idle_lock';

    public static function lock(Request $request, string $mode = self::MODE_FULL, bool $idle = false): void
    {
        $request->session()->put(self::SESSION_KEY, true);
        $request->session()->put(self::MODE_KEY, $mode === self::MODE_BIOMETRIC
            ? self::MODE_BIOMETRIC
            : self::MODE_FULL);

        if ($idle) {
            $request->session()->put(self::IDLE_LOCK_KEY, true);
        }

        Vault::lock($request);
    }

    public static function unlock(Request $request): void
    {
        $request->session()->forget([self::SESSION_KEY, self::MODE_KEY, self::IDLE_LOCK_KEY]);
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

    public static function isIdleLock(Request $request): bool
    {
        return (bool) $request->session()->get(self::IDLE_LOCK_KEY);
    }
}
