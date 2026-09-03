<?php

namespace App\Http\Controllers;

use App\Models\System;
use App\Models\UserCredential;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SystemViewController extends Controller
{
    /**
     * Дотор нь нээгдэхийг зөвшөөрдөг системийг iframe-д харуулна.
     * Нэвтрэх шаардлагатай бол нэр/нууц үг хадгалах хэсэгтэй.
     */
    public function show(Request $request, System $system): Response
    {
        abort_unless($system->is_active, 404);
        abort_unless($system->isVisibleTo($request->user()), 403);

        $credential = UserCredential::query()
            ->where('user_id', $request->user()->id)
            ->where('system_id', $system->id)
            ->first();

        return Inertia::render('Systems/View', [
            'system' => [
                'id' => $system->id,
                'name' => $system->name,
                'icon' => $system->icon,
                'entry_url' => $system->entryUrl(),
                'is_embeddable' => (bool) $system->is_embeddable,
                'embed_blocked_by' => $system->embed_blocked_by,
                'requires_login' => (bool) $system->requires_login,
                'supports_dan' => (bool) $system->supports_dan,
                'has_credential' => $credential !== null,
                // Хадгалсан нэвтрэх нэр — нууц үг биш тул сан нээлттэй үед харуулна.
                'saved_username' => \App\Support\Vault::isUnlocked($request)
                    ? $credential?->username_encrypted
                    : null,
                // ДАН-аар нэвтэрдэг систем — анхнаасаа ДАН горим сонгогдоно.
                'auth_type' => $credential?->auth_type
                    ?? ($system->supports_dan ? System::AUTH_DAN : System::AUTH_PASSWORD),
                'remember_device' => (bool) ($credential?->remember_device),
            ],
            'target' => $system->entryUrl(),
        ]);
    }
}
