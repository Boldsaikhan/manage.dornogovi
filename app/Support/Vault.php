<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Нэвтрэх мэдээллийн сан — холбосон системүүдийн нэр, нууц үгийг ашиглах зөвшөөрөл.
 *
 * Хугацаагаар өөрөө хаагдахгүй: гараар нээж, гараар хаана. Албан хаагч өөрөө
 * нууц үгээрээ нээх, эсвэл админ «Албан хаагчдын сан» хэсгээс нээж өгч болно.
 */
class Vault
{
    public const SESSION_KEY = 'vault.unlocked_until';

    public static function unlock(Request $request): void
    {
        $request->session()->put(self::SESSION_KEY, true);
    }

    /**
     * Идэвхтэй session дээр нээнэ (хүсэлтгүй газраас, ж: нэвтрэлтийн event).
     */
    public static function unlockCurrentSession(): void
    {
        try {
            session()->put(self::SESSION_KEY, true);
        } catch (\Throwable) {
            // Session байхгүй орчинд (console) алгасна.
        }
    }

    /**
     * Энэ session дээр хаана. Админаас нээж өгсөн нь хэвээр үлдэнэ — түүнийг
     * хэрэглэгч өөрөө «Сан хаах» дарж, эсвэл админ хаана (revoke).
     */
    public static function lock(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }

    public static function isUnlocked(Request $request): bool
    {
        if ($request->hasSession() && $request->session()->get(self::SESSION_KEY)) {
            return true;
        }

        return self::isOpenFor($request->user());
    }

    /**
     * Админаас нээж өгсөн эсэх.
     */
    public static function isOpenFor(?object $user): bool
    {
        return $user instanceof User && $user->vault_opened_at !== null;
    }

    /**
     * Админаас тухайн албан хаагчийн санг нээх — хаах хүртэл нээлттэй.
     */
    public static function grant(User $user, ?User $by = null): void
    {
        $user->forceFill([
            'vault_opened_at' => now(),
            'vault_opened_by' => $by?->id,
        ])->save();
    }

    public static function revoke(?object $user): void
    {
        if (! $user instanceof User || $user->vault_opened_at === null) {
            return;
        }

        $user->forceFill([
            'vault_opened_at' => null,
            'vault_opened_by' => null,
        ])->save();
    }

    /**
     * Админаас хэзээ нээснийг харуулахад.
     */
    public static function openedAt(?object $user): ?int
    {
        return self::isOpenFor($user) ? $user->vault_opened_at->timestamp : null;
    }
}
