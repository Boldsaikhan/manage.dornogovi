<?php

namespace App\Http\Controllers;

use App\Support\SessionDevices;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
            'loginSessions' => SessionDevices::forUser(
                $request->user(),
                $request->session()->getId(),
            ),
            'webauthnCredentials' => $request->user()->webauthnCredentials()
                ->latest('id')
                ->get(['id', 'device_name', 'created_at'])
                ->map(fn ($c) => [
                    'id' => $c->id,
                    'device_name' => $c->device_name ?: 'Төхөөрөмж',
                    'created_at' => $c->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i'),
                ]),
        ]);
    }

    /**
     * Нэг төхөөрөмжийн холболтыг тасална.
     */
    public function destroySession(Request $request, string $session): RedirectResponse
    {
        $current = $request->session()->getId();

        if ($session === $current) {
            return back()->with('error', 'Одоо ашиглаж буй холболтыг эндээс таслахгүй — «Гарах» дарна уу.');
        }

        SessionDevices::revoke($request->user(), $session);

        return back()->with('success', 'Тухайн төхөөрөмжийн холболтыг таслав.');
    }

    /**
     * Одоогийнхоос бусад бүх холболтыг тасална.
     */
    public function destroyOtherSessions(Request $request): RedirectResponse
    {
        $count = SessionDevices::revokeOthers($request->user(), $request->session()->getId());

        return back()->with(
            'success',
            $count > 0
                ? $count.' холболтыг таслав.'
                : 'Таслах өөр холболт алга.',
        );
    }

    /**
     * Profile information is view-only; updates are not allowed.
     */
    public function update(Request $request): RedirectResponse
    {
        abort(403, 'Профайлын мэдээллийг засварлах боломжгүй.');
    }

    /**
     * Бүртгэл устгахыг профайлаас хориглоно — зөвхөн супер админ (Хандах эрх) зохицуулна.
     */
    public function destroy(Request $request): RedirectResponse
    {
        abort(403, 'Бүртгэл устгахыг зөвхөн супер админ зохицуулна.');
    }
}
