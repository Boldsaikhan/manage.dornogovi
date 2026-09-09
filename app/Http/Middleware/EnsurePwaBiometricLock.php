<?php

namespace App\Http\Middleware;

use App\Support\AppLock;
use App\Support\MobileClient;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        /*
         * Сесс дуусаад «намайг сана» күүкигээр эргэж нэвтэрсэн үе.
         *
         * Гар утсан дээр нууц үг дахин асуухгүй — зөвхөн хуруу/царайгаар
         * баталгаажуулна. Биометрик бүртгүүлээгүй хүнд түгжээ тавихгүй,
         * эс бөгөөс апп-даа орох арга үгүй болно.
         */
        if (MobileClient::isMobileRequest($request)
            && Auth::viaRemember()
            && ! AppLock::isLocked($request)
            && $user->webauthnCredentials()->exists()) {
            // idle = true — доорх «хуучин үлдэгдэл» цэвэрлэгээнд тайлагдахгүй.
            AppLock::lock($request, AppLock::MODE_BIOMETRIC, idle: true);

            return $next($request);
        }

        // Хуучин «дэлгэц алга болоход шууд түгжих» үлдэгдэл — идэвхгүй/дэвсгэр биш бол тайлна.
        if (MobileClient::isMobileRequest($request)
            && AppLock::isLocked($request)
            && AppLock::mode($request) !== AppLock::MODE_BIOMETRIC
            && ! AppLock::isIdleLock($request)
            && ! AppLock::isBackgroundLock($request)) {
            AppLock::unlock($request);
        }

        return $next($request);
    }
}
