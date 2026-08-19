<?php

namespace App\Http\Controllers;

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
        ]);
    }

    public function lock(Request $request): JsonResponse
    {
        Vault::lock($request);

        return response()->json(['unlocked' => false]);
    }
}
