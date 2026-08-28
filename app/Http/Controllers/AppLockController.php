<?php

namespace App\Http\Controllers;

use App\Support\AppLock;
use App\Support\MobileClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AppLockController extends Controller
{
    /** Апп-аас гарах / дэлгэц нуугдахад түгжинэ (зөвхөн гар утас). Нууц үгээр тайлана. */
    public function lock(Request $request): JsonResponse
    {
        if (! MobileClient::isMobileRequest($request)) {
            AppLock::unlock($request);

            return response()->json([
                'locked' => false,
                'mode' => null,
            ]);
        }

        AppLock::lock($request, AppLock::MODE_FULL, $request->boolean('idle'));

        return response()->json([
            'locked' => true,
            'mode' => AppLock::mode($request),
        ]);
    }

    /** Түгжээ тайлах — зөвхөн нууц үг. */
    public function unlock(Request $request): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (! Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'Нэвтрэх нэр / нууц үг буруу байна.',
            ]);
        }

        AppLock::unlock($request);

        return response()->json([
            'locked' => false,
            'mode' => null,
        ]);
    }

    public function unlockWithPassword(Request $request): JsonResponse
    {
        return $this->unlock($request);
    }
}
