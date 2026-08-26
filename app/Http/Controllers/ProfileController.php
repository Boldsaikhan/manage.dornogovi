<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
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
     * Profile information is view-only; updates are not allowed.
     */
    public function update(Request $request): RedirectResponse
    {
        abort(403, 'Профайлын мэдээллийг засварлах боломжгүй.');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
