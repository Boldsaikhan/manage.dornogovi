<?php

use App\Http\Controllers\Admin\SystemSettingsController;
use App\Http\Controllers\CredentialController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LaunchController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SystemViewController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\VaultController;
use App\Models\System;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    // Нэвтэрсэн хэрэглэгчийг шууд самбар руу нь оруулна.
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'systems' => System::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'description', 'category', 'icon']),
    ]);
})->name('home');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Нэвтрэх мэдээллийн сан
    Route::post('/vault/unlock', [VaultController::class, 'unlock'])->name('vault.unlock');
    Route::post('/vault/lock', [VaultController::class, 'lock'])->name('vault.lock');

    Route::post('/credentials', [CredentialController::class, 'store'])->name('credentials.store');
    Route::delete('/credentials/{system}', [CredentialController::class, 'destroy'])->name('credentials.destroy');
    Route::post('/credentials/{system}/reveal', [CredentialController::class, 'reveal'])->name('credentials.reveal');

    // Үүрэг, чиглэлийн биелэлт (дотоод модуль)
    Route::get('/uureg', [TaskController::class, 'index'])->name('tasks.index');
    Route::patch('/uureg/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::post('/uureg/assign-department', [TaskController::class, 'assignDepartment'])->name('tasks.assign-department');

    // Систем рүү нэвтрэх / дотор нь харах
    Route::get('/systems/{system}/launch', LaunchController::class)->name('systems.launch');
    Route::get('/systems/{system}', [SystemViewController::class, 'show'])->name('systems.show');

    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/systems', [SystemSettingsController::class, 'index'])->name('systems.index');
        Route::patch('/systems/{system}', [SystemSettingsController::class, 'update'])->name('systems.update');
        Route::post('/systems/{system}/check-embed', [SystemSettingsController::class, 'checkEmbed'])->name('systems.check-embed');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
