<?php

namespace App\Http\Middleware;

use App\Support\AppLock;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Утас / PWA дахин нээхэд биометрик түгжээ идэвхжүүлнэ.
 */
class EnsurePwaBiometricLock
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user
            && $user->webauthnCredentials()->exists()
            && ! $request->header('X-Inertia')
            && $request->isMethodSafe()
            && $this->shouldLockOnDevice($request)
        ) {
            AppLock::lock($request, AppLock::MODE_BIOMETRIC);
        }

        return $next($request);
    }

    private function shouldLockOnDevice(Request $request): bool
    {
        if ($request->cookie('pwa_standalone') === '1') {
            return true;
        }

        $ua = strtolower($request->userAgent() ?? '');

        return str_contains($ua, 'android')
            || str_contains($ua, 'iphone')
            || str_contains($ua, 'ipad')
            || str_contains($ua, 'ipod')
            || str_contains($ua, 'mobile');
    }
}
