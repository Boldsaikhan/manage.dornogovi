<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Support\AppLock;
use App\Support\HomeRedirect;
use App\Support\MobileClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        AppLock::unlock($request);

        // Зөвхөн гар утаснаас нэвтэрсэн бол биометрик асууна; desktop — нууц үг л хангалттай.
        if (
            MobileClient::isMobileRequest($request)
            && $request->user()->webauthnCredentials()->exists()
        ) {
            AppLock::lock($request, AppLock::MODE_BIOMETRIC);
        }

        return redirect()->intended(HomeRedirect::path());
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
