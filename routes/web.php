<?php

use App\Http\Controllers\Admin\SystemSettingsController;
use App\Http\Controllers\Admin\UserAccessController;
use App\Http\Controllers\AiAssistantController;
use App\Http\Controllers\CredentialController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentDashboardController;
use App\Http\Controllers\LaunchController;
use App\Http\Controllers\ModuleResourceController;
use App\Http\Controllers\PhoneDirectoryController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SystemViewController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\VaultController;
use App\Http\Controllers\WorkGroupController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    // Танилцуулга хуудасгүй — шууд нэвтрэх.
    return redirect()->route('login');
})->name('home');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dept-dashboard', [DepartmentDashboardController::class, 'index'])->name('dept.dashboard');

    Route::post('/vault/unlock', [VaultController::class, 'unlock'])->name('vault.unlock');
    Route::post('/vault/lock', [VaultController::class, 'lock'])->name('vault.lock');

    Route::post('/credentials', [CredentialController::class, 'store'])->name('credentials.store');
    Route::delete('/credentials/{system}', [CredentialController::class, 'destroy'])->name('credentials.destroy');
    Route::post('/credentials/{system}/reveal', [CredentialController::class, 'reveal'])->name('credentials.reveal');

    Route::get('/uureg', [TaskController::class, 'index'])->name('tasks.index');
    Route::post('/uureg', [TaskController::class, 'store'])->name('tasks.store');
    Route::patch('/uureg/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::delete('/uureg/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');
    Route::post('/uureg/documents', [TaskController::class, 'storeDocument'])->name('tasks.documents.store');
    Route::get('/uureg/documents/{document}/download', [TaskController::class, 'downloadDocument'])->name('tasks.documents.download');
    Route::delete('/uureg/documents/{document}', [TaskController::class, 'destroyDocument'])->name('tasks.documents.destroy');

    Route::get('/phone-directory', [PhoneDirectoryController::class, 'index'])->name('phone-directory.index');
    Route::post('/phone-directory', [PhoneDirectoryController::class, 'store'])->name('phone-directory.store');
    Route::post('/phone-directory/import', [PhoneDirectoryController::class, 'import'])->name('phone-directory.import');
    Route::delete('/phone-directory/{entry}', [PhoneDirectoryController::class, 'destroy'])->name('phone-directory.destroy');

    Route::get('/modules/leaves', [ModuleResourceController::class, 'index'])->name('leaves.index');
    Route::get('/modules/assignments', [ModuleResourceController::class, 'index'])->name('assignments.index');
    Route::get('/modules/agency_hr', [ModuleResourceController::class, 'index'])->name('agency-hr.index');
    Route::get('/modules/regulations', [ModuleResourceController::class, 'index'])->name('regulations.index');
    Route::get('/modules/decrees', [ModuleResourceController::class, 'index'])->name('decrees.index');
    Route::get('/modules/contracts', [ModuleResourceController::class, 'index'])->name('contracts.index');
    Route::get('/modules/archives', [ModuleResourceController::class, 'index'])->name('archives.index');
    Route::get('/modules/doc_standards', [ModuleResourceController::class, 'index'])->name('doc-standards.index');
    Route::get('/modules/plans', [ModuleResourceController::class, 'index'])->name('plans.index');
    Route::get('/modules/meetings', [ModuleResourceController::class, 'index'])->name('meetings.index');
    Route::get('/modules/reports', [ModuleResourceController::class, 'index'])->name('reports.index');
    Route::get('/modules/onboarding', [ModuleResourceController::class, 'index'])->name('onboarding.index');

    Route::post('/modules/{module}', [ModuleResourceController::class, 'store'])->name('modules.store');
    Route::delete('/modules/{module}/{id}', [ModuleResourceController::class, 'destroy'])->name('modules.destroy');

    Route::get('/work-groups', [WorkGroupController::class, 'index'])->name('work-groups.index');
    Route::post('/work-groups', [WorkGroupController::class, 'store'])->name('work-groups.store');
    Route::post('/work-groups/{workGroup}/tasks', [WorkGroupController::class, 'storeTask'])->name('work-groups.tasks.store');
    Route::patch('/work-group-tasks/{task}', [WorkGroupController::class, 'updateTask'])->name('work-groups.tasks.update');

    Route::get('/ai', [AiAssistantController::class, 'index'])->name('ai.index');
    Route::post('/ai/ask', [AiAssistantController::class, 'ask'])->name('ai.ask');

    Route::get('/systems/{system}/launch', LaunchController::class)->name('systems.launch');
    Route::get('/systems/{system}', [SystemViewController::class, 'show'])->name('systems.show');

    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/systems', [SystemSettingsController::class, 'index'])->name('systems.index');
        Route::patch('/systems/{system}', [SystemSettingsController::class, 'update'])->name('systems.update');
        Route::post('/systems/{system}/check-embed', [SystemSettingsController::class, 'checkEmbed'])->name('systems.check-embed');

        Route::get('/users', [UserAccessController::class, 'index'])->name('users.index');
        Route::post('/users', [UserAccessController::class, 'store'])->name('users.store');
        Route::patch('/users/{user}', [UserAccessController::class, 'update'])->name('users.update');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
