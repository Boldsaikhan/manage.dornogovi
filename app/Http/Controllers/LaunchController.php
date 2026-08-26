<?php

namespace App\Http\Controllers;

use App\Models\System;
use App\Models\UserCredential;
use App\Support\Vault;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LaunchController extends Controller
{
    /**
     * Систем рүү нэвтрэх завсрын хуудас. Шинэ табд нээгдэж, тохиргоо бүрэн бол
     * нуугдмал маягтаар шууд илгээнэ, үгүй бол мэдээллийг хуулж өгнө.
     */
    public function __invoke(Request $request, System $system): Response
    {
        abort_unless($system->is_active, 404);
        abort_unless($system->isVisibleTo($request->user()), 403);
        abort_unless(Vault::isUnlocked($request), 423, 'Нэвтрэх мэдээллийн сан түгжээтэй байна.');

        $credential = UserCredential::where('user_id', $request->user()->id)
            ->where('system_id', $system->id)
            ->firstOrFail();

        $credential->forceFill(['last_used_at' => now()])->save();

        return response()
            ->view('launch', [
                'system' => $system,
                'username' => $credential->username_encrypted,
                'password' => $credential->password_encrypted,
                'authType' => $credential->auth_type,
                'rememberDevice' => (bool) $credential->remember_device,
                // ДАН-аар нэвтрэх үед нуугдмал маягт хэрэглэхгүй — өргөтгөл бөглөнө.
                'autoSubmit' => $credential->auth_type !== System::AUTH_DAN && $system->canAutoSubmit(),
                'entryUrl' => $credential->auth_type === System::AUTH_DAN
                    ? $system->danEntryUrl()
                    : $system->entryUrl(),
            ])
            // Нэвтрэх мэдээлэл агуулсан хуудсыг хаана ч бүү хадгал.
            ->withHeaders([
                'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
                'Pragma' => 'no-cache',
                'Referrer-Policy' => 'no-referrer',
            ]);
    }
}
