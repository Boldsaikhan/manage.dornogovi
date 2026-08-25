<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use App\Models\UserModulePermission;
use App\Support\ModuleAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserAccessController extends Controller
{
    public function index(Request $request): Response
    {
        $modules = ModuleAccess::definitions()
            ->reject(fn ($m) => $m['key'] === 'systems')
            ->values();

        $users = User::query()
            ->with(['department:id,name', 'modulePermissions'])
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'phone' => $u->phone,
                'is_admin' => (bool) $u->is_admin,
                'department_id' => $u->department_id,
                'department' => $u->department?->name,
                'position' => $u->position,
                'is_department_head' => (bool) $u->is_department_head,
                'permissions' => $u->modulePermissions
                    ->mapWithKeys(fn (UserModulePermission $p) => [$p->module_key => $p->level])
                    ->all(),
            ]);

        return Inertia::render('Admin/UserAccess', [
            'users' => $users,
            'departments' => Department::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            'modules' => $modules,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:8'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'position' => ['nullable', 'string', 'max:255'],
            'is_admin' => ['boolean'],
            'is_department_head' => ['boolean'],
        ]);

        User::create([
            ...$data,
            'password' => Hash::make($data['password']),
            'email_verified_at' => now(),
            'is_admin' => $request->boolean('is_admin'),
            'is_department_head' => $request->boolean('is_department_head'),
        ]);

        return back()->with('success', 'Албан хаагч нэмлээ.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($user->id)],
            'department_id' => ['nullable', 'exists:departments,id'],
            'position' => ['nullable', 'string', 'max:255'],
            'is_admin' => ['boolean'],
            'is_department_head' => ['boolean'],
            'password' => ['nullable', 'string', 'min:8'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['in:view,manage'],
        ]);

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'department_id' => $data['department_id'] ?? null,
            'position' => $data['position'] ?? null,
            'is_admin' => $request->boolean('is_admin'),
            'is_department_head' => $request->boolean('is_department_head'),
        ]);

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        $user->save();

        $permissions = $data['permissions'] ?? [];
        $user->modulePermissions()->delete();
        foreach ($permissions as $key => $level) {
            if (! ModuleAccess::find($key)) {
                continue;
            }
            $user->modulePermissions()->create([
                'module_key' => $key,
                'level' => $level,
            ]);
        }

        return back()->with('success', 'Эрх шинэчиллээ.');
    }
}
