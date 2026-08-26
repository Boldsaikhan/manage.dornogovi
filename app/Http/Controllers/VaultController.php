<?php

namespace App\Http\Controllers;

use App\Models\LoginQrToken;
use App\Support\Vault;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class VaultController extends Controller
{
    public function unlock(Request $request): JsonResponse
    {
        $request->validate([
            'account_password' => ['required', 'string'],
        ]);

        if (! Hash::check($request->input('account_password'), $request->user()->password)) {
            throw ValidationException::withMessages([
                'account_password' => 'Нууц үг буруу байна.',
            ]);
        }

        Vault::unlock($request);

        return response()->json([
            'unlocked' => true,
            'until' => Vault::unlockedUntil($request),
            'minutes' => Vault::MINUTES,
        ]);
    }

    /**
     * Утасны QR-аар сан нээх хүсэлт үүсгэнэ (нэвтэрсэн компьютер).
     */
    public function createUnlockQr(Request $request): JsonResponse
    {
        LoginQrToken::prune();

        LoginQrToken::query()
            ->where('session_id', $request->session()->getId())
            ->where('purpose', LoginQrToken::PURPOSE_VAULT)
            ->where('status', LoginQrToken::PENDING)
            ->update(['status' => LoginQrToken::REJECTED]);

        $clientSecret = LoginQrToken::generateToken();

        $token = LoginQrToken::create([
            'token' => LoginQrToken::generateToken(),
            'status' => LoginQrToken::PENDING,
            'purpose' => LoginQrToken::PURPOSE_VAULT,
            'expected_user_id' => $request->user()->id,
            'requester_ip' => $request->ip(),
            'requester_agent' => substr((string) $request->userAgent(), 0, 500),
            'session_id' => $request->session()->getId(),
            'client_secret_hash' => hash('sha256', $clientSecret),
            'expires_at' => now()->addSeconds(LoginQrToken::TTL_SECONDS),
        ]);

        return response()->json([
            'token' => $token->token,
            'client_secret' => $clientSecret,
            'url' => route('login.qr.show', $token->token),
            'expires_in' => LoginQrToken::TTL_SECONDS,
        ]);
    }

    /**
     * QR зөвшөөрөгдсөн эсэхийг шалгаад сан нээнэ.
     */
    public function unlockQrStatus(Request $request, string $token): JsonResponse
    {
        $request->validate([
            'client_secret' => ['required', 'string', 'min:32'],
        ]);

        $record = LoginQrToken::where('token', $token)
            ->where('purpose', LoginQrToken::PURPOSE_VAULT)
            ->first();

        if (! $record || $record->isExpired()) {
            return response()->json(['status' => 'expired']);
        }

        if (! $record->client_secret_hash
            || ! hash_equals($record->client_secret_hash, hash('sha256', $request->input('client_secret')))) {
            return response()->json(['status' => 'pending']);
        }

        if ($record->status === LoginQrToken::REJECTED) {
            return response()->json(['status' => 'rejected']);
        }

        if ($record->status !== LoginQrToken::APPROVED) {
            return response()->json(['status' => 'pending']);
        }

        if ((int) $record->expected_user_id !== (int) $request->user()->id) {
            return response()->json(['status' => 'rejected']);
        }

        if ((int) $record->user_id !== (int) $request->user()->id) {
            return response()->json(['status' => 'rejected']);
        }

        $record->forceFill([
            'status' => LoginQrToken::CONSUMED,
            'consumed_at' => now(),
            'client_secret_hash' => null,
        ])->save();

        Vault::unlock($request);

        return response()->json([
            'status' => 'approved',
            'unlocked' => true,
            'until' => Vault::unlockedUntil($request),
            'minutes' => Vault::MINUTES,
        ]);
    }

    public function lock(Request $request): JsonResponse
    {
        Vault::lock($request);

        return response()->json(['unlocked' => false]);
    }
}
