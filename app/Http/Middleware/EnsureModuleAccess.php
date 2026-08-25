<?php

namespace App\Http\Middleware;

use App\Support\ModuleAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleAccess
{
    public function handle(Request $request, Closure $next, string $moduleKey, string $level = 'view'): Response
    {
        $user = $request->user();
        $allowed = $level === 'manage'
            ? ModuleAccess::canManage($user, $moduleKey)
            : ModuleAccess::canView($user, $moduleKey);

        if (! $allowed) {
            abort(403, 'Энэ модульд хандах эрхгүй.');
        }

        return $next($request);
    }
}
