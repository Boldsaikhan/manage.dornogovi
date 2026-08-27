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
    /** Апп-аас гарах / дэлгэц нуугдахад түгжинэ (зөвхөн гар утас). */
    public function lock(Request $request): JsonResponse
    {
        if (! MobileClient::isMobileRequest($request)) {
            AppLock::unlock($request);

            return response()->json([
                'locked' => false,
                'mode' => null,
            ]);
        }

        $mode = $request->user()->webauthnCredentials()->exists()
            ? AppLock::MODE_BIOMETRIC
            : AppLock::MODE_FULL;

        AppLock::lock($request, $mode, $request->boolean('idle'));

        return response()->json([
            'locked' => true,
            'mode' => AppLock::mode($request),
        ]);
    }

    /**
     * Түгжээ тайлах: full → нууц үг (+ биометрик), biometric → зөвхөн биометрик.
     */
    public function unlock(Request $request): JsonResponse
    {
        $user = $request->user();
        $mode = AppLock::mode($request);
        $hasWebAuthn = $user->webauthnCredentials()->exists();
        $requireBiometric = $hasWebAuthn && $request->boolean('require_biometric', true);

        // Биометрик алгасах эсвэл full горимд нууц үг заавал.
        if ($mode === AppLock::MODE_FULL || ! $requireBiometric) {
            $request->validate([
                'password' => ['required', 'string'],
            ]);

            if (! Hash::check($request->input('password'), $user->password)) {
                throw ValidationException::withMessages([
                    'password' => 'Нэвтрэх нэр / нууц үг буруу байна.',
                ]);
            }
        }

        if ($requireBiometric) {
            $assertion = $request->input('assertion');

            if (! is_array($assertion)) {
                throw ValidationException::withMessages([
                    'webauthn' => 'Хурууны хээ эсвэл царайгаар баталгаажуулна уу.',
                ]);
            }

            try {
                WebAuthnService::verifyForUser($request, $user, $assertion);
            } catch (RuntimeException $e) {
                throw ValidationException::withMessages([
                    'webauthn' => $e->getMessage(),
                ]);
            } catch (Throwable $e) {
                report($e);

                throw ValidationException::withMessages([
                    'webauthn' => 'Биометрик баталгаажуулалт амжилтгүй боллоо.',
                ]);
            }
        }

        AppLock::unlock($request);

        return response()->json([
            'locked' => false,
            'mode' => null,
        ]);
    }

    /** Биометрик амжилтгүй (устгасан апп г.м.) үед нууц үгээр түгжээ тайлах. */
    public function unlockWithPassword(Request $request): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (! Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'Нууц үг буруу байна.',
            ]);
        }

        AppLock::unlock($request);

        return response()->json([
            'locked' => false,
            'mode' => null,
            'hint' => 'Биометрикийг Профайл хэсэгт дахин бүртгэнэ үү.',
        ]);
    }
}
