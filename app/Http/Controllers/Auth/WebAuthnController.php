<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\WebAuthnCredential;
use App\Support\HomeRedirect;
use App\Support\WebAuthnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class WebAuthnController extends Controller
{
    public function registerOptions(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $options = WebAuthnService::registrationOptions($request, $user);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($options);
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'clientDataJSON' => ['required', 'string'],
            'attestationObject' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            $credential = WebAuthnService::register($request, $request->user(), $data);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Биометрик бүртгэл амжилтгүй боллоо.'], 422);
        }

        return response()->json([
            'success' => true,
            'credential' => [
                'id' => $credential->id,
                'device_name' => $credential->device_name,
                'created_at' => $credential->created_at?->toIso8601String(),
            ],
        ]);
    }

    public function destroy(Request $request, WebAuthnCredential $credential): JsonResponse
    {
        abort_unless($credential->user_id === $request->user()->id, 403);

        $credential->delete();

        return response()->json(['success' => true]);
    }

    public function loginOptions(Request $request): JsonResponse
    {
        try {
            $options = WebAuthnService::loginOptions($request);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($options);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['required', 'string'],
            'rawId' => ['nullable', 'string'],
            'clientDataJSON' => ['required', 'string'],
            'authenticatorData' => ['required', 'string'],
            'signature' => ['required', 'string'],
            'userHandle' => ['nullable', 'string'],
        ]);

        try {
            $user = WebAuthnService::authenticate($request, $data);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages([
                'webauthn' => $e->getMessage(),
            ]);
        } catch (Throwable $e) {
            report($e);

            throw ValidationException::withMessages([
                'webauthn' => 'Биометрик нэвтрэлт амжилтгүй боллоо.',
            ]);
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return response()->json([
            'success' => true,
            'redirect' => HomeRedirect::path($user),
        ]);
    }
}
