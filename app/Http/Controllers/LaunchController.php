<?php

namespace App\Http\Controllers;

use App\Models\System;
use App\Models\UserCredential;
use App\Support\Vault;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LaunchController extends Controller
{
    /**
     * Систем рүү нэвтрэх завсрын хуудас. Шинэ табд нээгдэж, тохиргоо бүрэн бол
     * нуугдмал маягтаар шууд илгээнэ, үгүй бол мэдээллийг хуулж өгнө.
     */
    public function __invoke(Request $request, System $system): RedirectResponse|Response
    {
        abort_unless($system->is_active, 404);
        abort_unless($system->isVisibleTo($request->user()), 403);

        if (! Vault::isUnlocked($request)) {
            return redirect()
                ->route('systems.show', $system)
                ->with('warning', 'Нэвтрэх мэдээллийн сан түгжээтэй байна. Эхлээд сангаа нээнэ үү.');
        }

        $credential = UserCredential::where('user_id', $request->user()->id)
            ->where('system_id', $system->id)
            ->first();

        if (! $credential) {
            return redirect()
                ->route('systems.show', $system)
                ->with('info', 'Эхлээд нэвтрэх нэр, нууц үгээ хадгална уу.');
        }

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
