<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\PhoneDirectoryEntry;
use App\Models\RolePermission;
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
                'is_specialist' => (bool) $u->is_specialist,
                'permissions' => $u->modulePermissions
                    ->mapWithKeys(fn (UserModulePermission $p) => [$p->module_key => $p->level])
                    ->all(),
            ]);

        return Inertia::render('Admin/UserAccess', [
            'users' => $users,
            'departments' => Department::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            'modules' => $modules,
            'people' => PhoneDirectoryEntry::accountPeopleOptions(),
            'roles' => collect(RolePermission::ROLES)
                ->map(fn (string $label, string $key) => [
                    'key' => $key,
                    'label' => $label,
                    'field' => RolePermission::ROLE_FIELDS[$key],
                ])
                ->values(),
            'rolePermissions' => RolePermission::map(),
        ]);
    }

    /**
     * Ролийн загварыг хадгална — тухайн түвшинг сонгоход энэ эрхүүд хэрэгжинэ.
     */
    public function updateRole(Request $request, string $role): RedirectResponse
    {
        abort_unless(array_key_exists($role, RolePermission::ROLES), 404);

        $data = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['in:view,manage'],
        ]);

        $permissions = collect($data['permissions'] ?? [])
            ->filter(fn ($level, $key) => ModuleAccess::find($key) !== null)
            ->all();

        RolePermission::replaceFor($role, $permissions);

        return back()->with('success', sprintf(
            '«%s» ролийн загвар хадгалагдлаа (%d модуль).',
            RolePermission::ROLES[$role],
            count($permissions),
        ));
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
            'is_specialist' => ['boolean'],
        ]);

        User::create([
            ...$data,
            'password' => Hash::make($data['password']),
            'email_verified_at' => now(),
            'is_admin' => $request->boolean('is_admin'),
            'is_department_head' => $request->boolean('is_department_head'),
            'is_specialist' => $request->boolean('is_specialist'),
        ]);

        return back()->with('success', sprintf('«%s» нэмэгдлээ. Эрхийг дээрээс тохируулна уу.', $data['name']));
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
            'is_specialist' => ['boolean'],
            'password' => ['nullable', 'string', 'min:8'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['in:view,manage'],
        ]);

        $beforePermissions = $user->modulePermissions
            ->mapWithKeys(fn (UserModulePermission $p) => [$p->module_key => $p->level])
            ->all();

        $beforeProfile = [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'department_id' => $user->department_id,
            'position' => $user->position,
            'is_admin' => (bool) $user->is_admin,
            'is_department_head' => (bool) $user->is_department_head,
            'is_specialist' => (bool) $user->is_specialist,
        ];

        $passwordChanged = ! empty($data['password']);

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'department_id' => $data['department_id'] ?? null,
            'position' => $data['position'] ?? null,
            'is_admin' => $request->boolean('is_admin'),
            'is_department_head' => $request->boolean('is_department_head'),
            'is_specialist' => $request->boolean('is_specialist'),
        ]);

        if ($passwordChanged) {
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

        $profileChanges = $this->profileChangeLines($beforeProfile, [
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'department_id' => $data['department_id'] ?? null,
            'position' => $data['position'] ?? null,
            'is_admin' => $request->boolean('is_admin'),
            'is_department_head' => $request->boolean('is_department_head'),
            'is_specialist' => $request->boolean('is_specialist'),
        ], $passwordChanged);

        $permissionChanges = $this->permissionChangeLines($beforePermissions, $permissions);

        if ($profileChanges === [] && $permissionChanges === []) {
            return back()->with('info', 'Өөрчлөлт оруулаагүй байна.');
        }

        $parts = [];
        if ($profileChanges !== []) {
            $parts[] = 'Профайл: '.implode('; ', $profileChanges);
        }
        if ($permissionChanges !== []) {
            $parts[] = 'Эрх: '.implode('; ', $permissionChanges);
        }

        $flashKey = $profileChanges !== [] && $permissionChanges !== []
            ? 'success'
            : ($permissionChanges !== [] ? 'warning' : 'success');

        return back()->with($flashKey, implode('. ', $parts).'.');
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<int, string>
     */
    private function profileChangeLines(array $before, array $after, bool $passwordChanged): array
    {
        $lines = [];

        if ($before['name'] !== $after['name']) {
            $lines[] = 'нэр';
        }
        if ($before['email'] !== $after['email']) {
            $lines[] = 'и-мэйл';
        }
        if (($before['phone'] ?? null) !== ($after['phone'] ?? null)) {
            $lines[] = 'утас';
        }
        if (($before['department_id'] ?? null) != ($after['department_id'] ?? null)) {
            $lines[] = 'хэлтэс';
        }
        if (($before['position'] ?? null) !== ($after['position'] ?? null)) {
            $lines[] = 'албан тушаал';
        }
        if ($before['is_admin'] !== $after['is_admin']) {
            $lines[] = $after['is_admin'] ? 'супер админ нэмэгдлээ' : 'супер админ хасагдлаа';
        }
        if ($before['is_department_head'] !== $after['is_department_head']) {
            $lines[] = $after['is_department_head'] ? 'хэлтсийн дарга боллоо' : 'хэлтсийн дарга эрх хасагдлаа';
        }
        if ($before['is_specialist'] !== $after['is_specialist']) {
            $lines[] = $after['is_specialist'] ? 'мэргэжилтэн боллоо' : 'мэргэжилтэн эрх хасагдлаа';
        }
        if ($passwordChanged) {
            $lines[] = 'нууц үг солигдлоо';
        }

        return $lines;
    }

    /**
     * @param  array<string, string>  $before
     * @param  array<string, string>  $after
     * @return array<int, string>
     */
    private function permissionChangeLines(array $before, array $after): array
    {
        $keys = array_unique([...array_keys($before), ...array_keys($after)]);
        $lines = [];

        foreach ($keys as $key) {
            $old = $before[$key] ?? null;
            $new = $after[$key] ?? null;

            if ($old === $new) {
                continue;
            }

            $label = ModuleAccess::find($key)['label'] ?? $key;
            $lines[] = sprintf(
                '%s (%s → %s)',
                $label,
                $this->levelLabel($old),
                $this->levelLabel($new),
            );
        }

        return $lines;
    }

    private function levelLabel(?string $level): string
    {
        return match ($level) {
            'manage' => 'Удирдах',
            'view' => 'Харах',
            default => 'Хаалттай',
        };
    }
}
