<?php

namespace App\Support;

use App\Models\User;

/**
 * Нэвтрэх / нүүр хуудасны чиглүүлэлт — албан хаагчийн самбар.
 */
class HomeRedirect
{
    public static function routeName(?User $user = null): string
    {
        $user ??= auth()->user();

        if ($user && ModuleAccess::canView($user, 'dept_dashboard')) {
            return 'dept.dashboard';
        }

        // Fallback — самбар харагдахгүй бол үүрэг даалгавар эсвэл профайл.
        if ($user && ModuleAccess::canView($user, 'tasks')) {
            return 'tasks.index';
        }

        return 'profile.edit';
    }

    public static function path(?User $user = null): string
    {
        return route(self::routeName($user), absolute: false);
    }
}
