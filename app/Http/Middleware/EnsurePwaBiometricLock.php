<?php

namespace App\Http\Middleware;

use App\Support\AppLock;
use App\Support\MobileClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Гар утас дахин нээхэд биометрик түгжээ; desktop дээр түгжээг автоматаар тайлна.
 */
class EnsurePwaBiometricLock
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if (! MobileClient::isMobileRequest($request)) {
            if (AppLock::isLocked($request)) {
                AppLock::unlock($request);
            }

            return $next($request);
        }

        if (
            $user->webauthnCredentials()->exists()
            && ! $request->header('X-Inertia')
            && $request->isMethodSafe()
        ) {
            AppLock::lock($request, AppLock::MODE_BIOMETRIC);
        }

        return $next($request);
    }
}
