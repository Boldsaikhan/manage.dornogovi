<?php

namespace App\Http\Controllers;

use App\Models\System;
use App\Models\UserCredential;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
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
            'auth_type' => ['nullable', Rule::in([System::AUTH_PASSWORD, System::AUTH_DAN])],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:500'],
            'note' => ['nullable', 'string', 'max:1000'],
            'remember_device' => ['nullable', 'boolean'],
        ]);

        $authType = $data['auth_type'] ?? System::AUTH_PASSWORD;

        if ($authType === System::AUTH_DAN) {
            $system = System::findOrFail($data['system_id']);

            if (! $system->supports_dan) {
                throw ValidationException::withMessages([
                    'auth_type' => 'Энэ систем ДАН-аар нэвтрэх тохиргоогүй байна.',
                ]);
            }

            $register = self::normalizeRegister($data['username']);

            if ($register === null) {
                throw ValidationException::withMessages([
                    'username' => 'Регистрийн дугаар буруу байна (жишээ: УХ98010112).',
                ]);
            }

            $data['username'] = $register;
        }

        UserCredential::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'system_id' => $data['system_id'],
            ],
            [
                'auth_type' => $authType,
                'username_encrypted' => $data['username'],
                'password_encrypted' => $data['password'],
                'note_encrypted' => $data['note'] ?? null,
                'remember_device' => (bool) ($data['remember_device'] ?? false),
            ]
        );

        return back()->with('success', 'Нэвтрэх мэдээлэл хадгалагдлаа.');
    }

    /**
     * Регистрийн дугаарыг «2 үсэг + 8 орон» хэлбэрт цэгцэлнэ. Буруу бол null.
     */
    private static function normalizeRegister(string $value): ?string
    {
        $clean = preg_replace('/\\s+/u', '', mb_strtoupper(trim($value)));

        return preg_match('/^[\\x{0410}-\\x{042F}\\x{0401}\\x{04AE}\\x{04E8}]{2}\\d{8}$/u', (string) $clean)
            ? $clean
            : null;
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
            'auth_type' => $credential->auth_type,
            'entry_url' => $credential->auth_type === System::AUTH_DAN
                ? $system->danEntryUrl()
                : $system->entryUrl(),
        ]);
    }
}
