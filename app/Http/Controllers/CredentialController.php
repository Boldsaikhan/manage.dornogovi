<?php

namespace App\Http\Controllers;

use App\Models\System;
use App\Models\UserCredential;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class CredentialController extends Controller
{
    /**
     * Create or replace the current user's credential for a system.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'system_id' => ['required', 'exists:systems,id'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:500'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        UserCredential::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'system_id' => $data['system_id'],
            ],
            [
                'username_encrypted' => $data['username'],
                'password_encrypted' => $data['password'],
                'note_encrypted' => $data['note'] ?? null,
            ]
        );

        return back()->with('success', 'Нэвтрэх мэдээлэл хадгалагдлаа.');
    }

    /**
     * Delete the current user's credential for a system.
     */
    public function destroy(Request $request, System $system): RedirectResponse
    {
        UserCredential::where('user_id', $request->user()->id)
            ->where('system_id', $system->id)
            ->delete();

        return back()->with('success', 'Нэвтрэх мэдээлэл устгагдлаа.');
    }

    /**
     * Return the decrypted credential. Guarded by the user's own account password,
     * so a walk-up attacker on an unlocked session cannot read the vault.
     */
    public function reveal(Request $request, System $system): JsonResponse
    {
        $request->validate([
            'account_password' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (! Hash::check($request->input('account_password'), $user->password)) {
            throw ValidationException::withMessages([
                'account_password' => 'Нууц үг буруу байна.',
            ]);
        }

        $credential = UserCredential::where('user_id', $user->id)
            ->where('system_id', $system->id)
            ->firstOrFail();

        $credential->forceFill(['last_used_at' => now()])->save();

        return response()->json([
            'username' => $credential->username_encrypted,
            'password' => $credential->password_encrypted,
            'note' => $credential->note_encrypted,
            'entry_url' => $system->entryUrl(),
        ]);
    }
}
