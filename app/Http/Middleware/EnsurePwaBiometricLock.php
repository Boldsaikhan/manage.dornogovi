<?php

namespace App\Http\Middleware;

use App\Support\AppLock;
use App\Support\MobileClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Desktop дээр түгжээг тайлана.
 * Гар утасны түгжээг энд автоматаар тавьдаггүй — зөвхөн дэлгэц алга болоход (клиент) түгжинэ.
 * Цэс хооронд шилжихэд баталгаажуулалт асуухгүй.
 */
class EnsurePwaBiometricLock
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if (! MobileClient::isMobileRequest($request) && AppLock::isLocked($request)) {
            AppLock::unlock($request);
        }

        // Хуучин «дэлгэц алга болоход шууд түгжих» үлдэгдэл — идэвхгүй биш бол тайлна.
        if (MobileClient::isMobileRequest($request)
            && AppLock::isLocked($request)
            && ! AppLock::isIdleLock($request)) {
            AppLock::unlock($request);
        }

        return $next($request);
    }
}
