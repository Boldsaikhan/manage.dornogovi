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

        return 'dashboard';
    }

    public static function path(?User $user = null): string
    {
        return route(self::routeName($user), absolute: false);
    }
}
