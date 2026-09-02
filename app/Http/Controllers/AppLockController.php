<?php

namespace App\Http\Controllers;

use App\Support\AppLock;
use App\Support\MobileClient;
use App\Support\WebAuthnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class AppLockController extends Controller
{
    /** Апп-аас гарах / дэлгэц нуугдахад түгжинэ (зөвхөн гар утас). Хуруу/царай эсвэл нууц үгээр тайлна. */
    public function lock(Request $request): JsonResponse
    {
        if (! MobileClient::isMobileRequest($request)) {
            AppLock::unlock($request);

            return response()->json([
                'locked' => false,
                'mode' => null,
            ]);
        }

        AppLock::lock(
            $request,
            AppLock::MODE_FULL,
            $request->boolean('idle'),
            $request->boolean('background')
        );

        return response()->json([
            'locked' => true,
            'mode' => AppLock::mode($request),
        ]);
    }

    /**
     * Түгжээ тайлах — хуруу/царай ЭСВЭЛ нууц үг.
     *
     * Биометрик нь заавал биш: assertion ирвэл түүгээр, эс бөгөөс нууц үгээр
     * тайлна. Ингэснээр төхөөрөмжийн биометрик ажиллахгүй болсон ч
     * хэрэглэгч апп-даа орох боломжтой хэвээр үлдэнэ.
     */
    public function unlock(Request $request): JsonResponse
    {
        return $this->finishUnlock($request, true);
    }

    /** Нэрийнхээ дагуу зөвхөн нууц үг — биометрик assertion-ыг үл тооно. */
    public function unlockWithPassword(Request $request): JsonResponse
    {
        return $this->finishUnlock($request, false);
    }

    protected function finishUnlock(Request $request, bool $allowBiometric): JsonResponse
    {
        $user = $request->user();
        $assertion = $request->input('assertion');

        if ($allowBiometric && is_array($assertion)) {
            try {
                WebAuthnService::verifyForUser($request, $user, $assertion);
            } catch (RuntimeException $e) {
                throw ValidationException::withMessages([
                    'webauthn' => $e->getMessage(),
                ]);
            } catch (Throwable $e) {
                report($e);

                throw ValidationException::withMessages([
                    'webauthn' => 'Баталгаажуулалт амжилтгүй боллоо.',
                ]);
            }
        } else {
            $request->validate([
                'password' => ['required', 'string'],
            ]);

            if (! Hash::check($request->input('password'), $user->password)) {
                throw ValidationException::withMessages([
                    'password' => 'Нэвтрэх нэр / нууц үг буруу байна.',
                ]);
            }
        }

        AppLock::unlock($request);

        return response()->json([
            'locked' => false,
            'mode' => null,
        ]);
    }
}
