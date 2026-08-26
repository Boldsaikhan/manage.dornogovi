<?php

use App\Http\Controllers\Admin\SystemSettingsController;
use App\Http\Controllers\Admin\UserAccessController;
use App\Http\Controllers\AiAssistantController;
use App\Http\Controllers\AppLockController;
use App\Http\Controllers\CredentialController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DecreeController;
use App\Http\Controllers\DepartmentDashboardController;
use App\Http\Controllers\DocumentStandardController;
use App\Http\Controllers\ExtensionController;
use App\Http\Controllers\LaunchController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\LeaveSlipController;
use App\Http\Controllers\ModuleResourceController;
use App\Http\Controllers\PhoneDirectoryController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SystemViewController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\UndoController;
use App\Http\Controllers\VaultController;
use App\Http\Controllers\WorkGroupController;
use App\Support\HomeRedirect;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route(HomeRedirect::routeName());
    }

    // Танилцуулга хуудасгүй — шууд нэвтрэх.
    return redirect()->route('login');
})->name('home');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dept-dashboard', [DepartmentDashboardController::class, 'index'])->name('dept.dashboard');

    Route::post('/vault/unlock', [VaultController::class, 'unlock'])->name('vault.unlock');
    Route::post('/vault/lock', [VaultController::class, 'lock'])->name('vault.lock');

    Route::post('/app-lock', [AppLockController::class, 'lock'])->name('app.lock');
    Route::post('/app-unlock', [AppLockController::class, 'unlock'])->name('app.unlock');
    Route::post('/app-unlock-password', [AppLockController::class, 'unlockWithPassword'])->name('app.unlock.password');

    Route::post('/credentials', [CredentialController::class, 'store'])->name('credentials.store');
    Route::delete('/credentials/{system}', [CredentialController::class, 'destroy'])->name('credentials.destroy');
    Route::post('/credentials/{system}/reveal', [CredentialController::class, 'reveal'])->name('credentials.reveal');

    Route::get('/uureg', [TaskController::class, 'index'])->name('tasks.index');
    Route::get('/uureg/export', [TaskController::class, 'export'])->name('tasks.export');
    Route::post('/uureg', [TaskController::class, 'store'])->name('tasks.store');
    Route::patch('/uureg/bulk', [TaskController::class, 'bulkUpdate'])->name('tasks.bulk');
    Route::patch('/uureg/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::delete('/uureg/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');
    Route::post('/uureg/documents', [TaskController::class, 'storeDocument'])->name('tasks.documents.store');
    Route::get('/uureg/documents/{document}/download', [TaskController::class, 'downloadDocument'])->name('tasks.documents.download');
    Route::post('/uureg/documents/{document}/import', [TaskController::class, 'importDocument'])->name('tasks.documents.import');
    Route::delete('/uureg/documents/{document}', [TaskController::class, 'destroyDocument'])->name('tasks.documents.destroy');

    Route::get('/phone-directory', [PhoneDirectoryController::class, 'index'])->name('phone-directory.index');
    Route::post('/phone-directory', [PhoneDirectoryController::class, 'store'])->name('phone-directory.store');
    Route::get('/phone-directory/export', [PhoneDirectoryController::class, 'export'])->name('phone-directory.export');
    Route::post('/phone-directory/import', [PhoneDirectoryController::class, 'import'])->name('phone-directory.import');
    Route::patch('/phone-directory/category', [PhoneDirectoryController::class, 'updateCategory'])->name('phone-directory.category');
    // Parameterized routes last — otherwise "category"/"export" match as {entry}.
    Route::patch('/phone-directory/{entry}', [PhoneDirectoryController::class, 'update'])->name('phone-directory.update');
    Route::delete('/phone-directory/{entry}', [PhoneDirectoryController::class, 'destroy'])->name('phone-directory.destroy');

    Route::get('/modules/leaves', [LeaveController::class, 'index'])->name('leaves.index');
    Route::post('/modules/leaves', [LeaveController::class, 'store'])->name('leaves.store');
    Route::delete('/modules/leaves/{leave}', [LeaveController::class, 'destroy'])->name('leaves.destroy');
    Route::get('/modules/leaves/{leave}/slip', [LeaveSlipController::class, 'show'])->name('leaves.slip');
    Route::get('/modules/assignments', [ModuleResourceController::class, 'index'])->name('assignments.index');
    Route::get('/modules/regulations', [ModuleResourceController::class, 'index'])->name('regulations.index');
    Route::post('/undo', [UndoController::class, 'store'])->name('undo.store');

    Route::get('/modules/decrees', [DecreeController::class, 'index'])->name('decrees.index');
    Route::get('/modules/decrees/print', [DecreeController::class, 'print'])->name('decrees.print');
    Route::get('/modules/decrees/export', [DecreeController::class, 'export'])->name('decrees.export');
    Route::post('/modules/decrees', [DecreeController::class, 'store'])->name('decrees.store');
    Route::patch('/modules/decrees/{decree}', [DecreeController::class, 'update'])->name('decrees.update');
    Route::post('/modules/decrees/{decree}/image', [DecreeController::class, 'uploadImage'])->name('decrees.image.upload');
    Route::get('/modules/decrees/{decree}/image', [DecreeController::class, 'showImage'])->name('decrees.image.show');
    Route::delete('/modules/decrees/{decree}/image', [DecreeController::class, 'destroyImage'])->name('decrees.image.destroy');
    Route::delete('/modules/decrees/{decree}', [DecreeController::class, 'destroy'])->name('decrees.destroy');
    Route::get('/modules/contracts', [ModuleResourceController::class, 'index'])->name('contracts.index');
    Route::get('/modules/archives', [ModuleResourceController::class, 'index'])->name('archives.index');
    Route::get('/modules/doc_standards', [DocumentStandardController::class, 'index'])->name('doc-standards.index');
    Route::patch('/document-formats/{format}', [DocumentStandardController::class, 'updateFormat'])->name('document-formats.update');
    Route::post('/document-formats/{format}/default', [DocumentStandardController::class, 'setDefaultFormat'])->name('document-formats.default');
    Route::post('/document-standards', [DocumentStandardController::class, 'storeStandard'])->name('document-standards.store');
    Route::delete('/document-standards/{standard}', [DocumentStandardController::class, 'destroyStandard'])->name('document-standards.destroy');
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
    Route::get('/ai/panel', [AiAssistantController::class, 'panel'])->name('ai.panel');
    Route::post('/ai/panel/ask', [AiAssistantController::class, 'panelAsk'])->name('ai.panel.ask');
    Route::post('/ai/confirm', [AiAssistantController::class, 'confirm'])->name('ai.confirm');
    Route::post('/ai/conversations', [AiAssistantController::class, 'newConversation'])->name('ai.conversations.store');

    Route::get('/extension/download', [ExtensionController::class, 'download'])->name('extension.download');
    Route::get('/systems/{system}/launch', LaunchController::class)->name('systems.launch');
    Route::get('/systems/{system}', [SystemViewController::class, 'show'])->name('systems.show');

    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/systems', [SystemSettingsController::class, 'index'])->name('systems.index');
        Route::post('/systems', [SystemSettingsController::class, 'store'])->name('systems.store');
        Route::patch('/systems/reorder', [SystemSettingsController::class, 'reorder'])->name('systems.reorder');
        Route::patch('/systems/{system}', [SystemSettingsController::class, 'update'])->name('systems.update');
        Route::delete('/systems/{system}', [SystemSettingsController::class, 'destroy'])->name('systems.destroy');
        Route::post('/systems/{system}/check-embed', [SystemSettingsController::class, 'checkEmbed'])->name('systems.check-embed');
        Route::patch('/ai-settings', [SystemSettingsController::class, 'updateAi'])->name('ai-settings.update');
        Route::patch('/menu-settings', [SystemSettingsController::class, 'updateMenus'])->name('menu-settings.update');

        Route::get('/users', [UserAccessController::class, 'index'])->name('users.index');
        Route::post('/users', [UserAccessController::class, 'store'])->name('users.store');
        Route::patch('/users/{user}', [UserAccessController::class, 'update'])->name('users.update');
        Route::post('/roles', [UserAccessController::class, 'storeRole'])->name('roles.store');
        Route::patch('/roles/{role}', [UserAccessController::class, 'updateRole'])->name('roles.update');
        Route::delete('/roles/{role}', [UserAccessController::class, 'destroyRole'])->name('roles.destroy');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
