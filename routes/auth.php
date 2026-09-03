<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\QrLoginController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Auth\WebAuthnController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');

    Route::post('register', [RegisteredUserController::class, 'store']);

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    // QR кодоор нэвтрэх — компьютер тал (нэвтрээгүй).
    Route::post('login/qr', [QrLoginController::class, 'create'])
        ->middleware('throttle:30,1')
        ->name('login.qr.create');
    Route::get('login/qr/{token}/status', [QrLoginController::class, 'status'])
        ->middleware('throttle:240,1')
        ->name('login.qr.status');

    Route::post('webauthn/login/options', [WebAuthnController::class, 'loginOptions'])
        ->middleware('throttle:20,1')
        ->name('webauthn.login.options');
    Route::post('webauthn/login', [WebAuthnController::class, 'login'])
        ->middleware('throttle:20,1')
        ->name('webauthn.login');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('webauthn/register/options', [WebAuthnController::class, 'registerOptions'])
        ->middleware('throttle:20,1')
        ->name('webauthn.register.options');
    Route::post('webauthn/register', [WebAuthnController::class, 'register'])
        ->middleware('throttle:20,1')
        ->name('webauthn.register');
    Route::post('webauthn/verify/options', [WebAuthnController::class, 'verifyOptions'])
        ->middleware('throttle:20,1')
        ->name('webauthn.verify.options');
    Route::delete('webauthn/credentials/{credential}', [WebAuthnController::class, 'destroy'])
        ->name('webauthn.destroy');

    // QR кодоор нэвтрэх — утас тал (нэвтэрсэн эрхээр зөвшөөрнө).
    Route::get('qr/{token}', [QrLoginController::class, 'show'])
        ->name('login.qr.show');
    Route::post('qr/{token}/approve', [QrLoginController::class, 'approve'])
        ->middleware('throttle:30,1')
        ->name('login.qr.approve');
    Route::post('qr/{token}/reject', [QrLoginController::class, 'reject'])
        ->middleware('throttle:30,1')
        ->name('login.qr.reject');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
