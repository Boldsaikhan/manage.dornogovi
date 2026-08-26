<?php

namespace App\Http\Middleware;

use App\Support\AppLock;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Суулгасан PWA дахин нээхэд (бүтэн хуудас ачаалах) биометрик түгжээ идэвхжүүлнэ.
 */
class EnsurePwaBiometricLock
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user
            && $user->webauthnCredentials()->exists()
            && $request->cookie('pwa_standalone') === '1'
            && ! $request->header('X-Inertia')
            && $request->isMethodSafe()
        ) {
            AppLock::lock($request, AppLock::MODE_BIOMETRIC);
        }

        return $next($request);
    }
}
