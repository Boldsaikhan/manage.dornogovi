<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\PhoneDirectoryEntry;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserModulePermission;
use App\Services\HeltesAccountProvisioner;
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
            ->map(fn (array $m) => [
                'key' => $m['key'],
                'label' => $m['label'],
                'own_scope' => ModuleAccess::supportsOwnScope($m['key']),
            ])
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
            'roles' => Role::ordered()->map(fn (Role $role) => [
                'key' => $role->key,
                'label' => $role->label,
                // Зөвхөн суурь роль хэрэглэгчийн чагттай холбоотой.
                'field' => Role::SYSTEM_FIELDS[$role->key] ?? null,
                'is_system' => $role->is_system,
            ])->values(),
            'rolePermissions' => RolePermission::map(),
            'heltesCount' => app(HeltesAccountProvisioner::class)->eligibleCount(),
        ]);
    }

    /**
     * Утасны жагсаалтын «Хэлтэс» ангиллын бүх албан хаагчид нэвтрэх эрх өгнө.
     */
    public function provisionHeltes(HeltesAccountProvisioner $provisioner): RedirectResponse
    {
        $result = $provisioner->run();

        return back()->with('success', sprintf(
            'Хэлтсийн албан хаагчдад эрх өглөө: %d шинэ, %d шинэчилсэн, %d алгассан.',
            $result['created'],
            $result['updated'],
            count($result['skipped']),
        ));
    }

    /**
     * Ролийн загварыг хадгална — тухайн түвшинг сонгоход энэ эрхүүд хэрэгжинэ.
     */
    public function updateRole(Request $request, string $role): RedirectResponse
    {
        $model = Role::query()->where('key', $role)->firstOrFail();

        $data = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['in:'.implode(',', ModuleAccess::LEVELS)],
            'label' => ['nullable', 'string', 'max:60'],
        ]);

        // Суурь ролийн нэрийг өөрчлөхгүй.
        if (! $model->is_system && filled($data['label'] ?? null)) {
            $model->update(['label' => trim($data['label'])]);
        }

        $permissions = collect($data['permissions'] ?? [])
            ->filter(fn ($level, $key) => ModuleAccess::find($key) !== null)
            ->filter(fn ($level, $key) => ! in_array($level, ['view_own', 'manage_own'], true) || ModuleAccess::supportsOwnScope($key))
            ->all();

        RolePermission::replaceFor($model->key, $permissions);

        $synced = ModuleAccess::syncUsersToRole($model->key);

        return back()->with('success', sprintf(
            '«%s» ролийн загвар хадгалагдлаа (%d модуль)%s.',
            $model->label,
            count($permissions),
            $synced > 0 ? sprintf(', %d хэрэглэгчийн эрх шинэчлэгдлээ', $synced) : '',
        ));
    }

    /**
     * Шинэ роль нэмнэ.
     */
    public function storeRole(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:60'],
            'copy_from' => ['nullable', 'string', 'exists:roles,key'],
        ]);

        $label = trim($data['label']);

        if (Role::query()->where('label', $label)->exists()) {
            return back()->with('warning', 'Ийм нэртэй роль аль хэдийн байна.');
        }

        $role = Role::create([
            'key' => Role::keyFor($label),
            'label' => $label,
            'is_system' => false,
            'sort_order' => (int) Role::query()->max('sort_order') + 1,
        ]);

        // Хүсвэл өөр ролийн эрхийг хуулж эхлэнэ.
        if (filled($data['copy_from'] ?? null)) {
            RolePermission::replaceFor($role->key, RolePermission::map()[$data['copy_from']] ?? []);
        }

        return back()->with('success', sprintf('«%s» роль нэмэгдлээ.', $role->label));
    }

    /**
     * Өөрийн үүсгэсэн ролийг устгана (суурь роль устахгүй).
     */
    public function destroyRole(string $role): RedirectResponse
    {
        $model = Role::query()->where('key', $role)->firstOrFail();

        abort_if($model->is_system, 403, 'Суурь ролийг устгах боломжгүй.');

        RolePermission::query()->where('role', $model->key)->delete();
        $label = $model->label;
        $model->delete();

        return back()->with('success', sprintf('«%s» роль устгагдлаа.', $label));
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
            'permissions.*' => ['in:'.implode(',', ModuleAccess::LEVELS)],
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

            if (in_array($level, ['view_own', 'manage_own'], true) && ! ModuleAccess::supportsOwnScope($key)) {
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

        $summary = implode('. ', $parts).'.';
        app(\App\Services\Push\EmployeePushNotifier::class)->notifyUsers([$user], [
            'title' => 'Хандах эрх / бүртгэл шинэчлэгдлээ',
            'body' => $summary,
            'url' => '/dept-dashboard',
            'tag' => 'access',
        ]);

        return back()->with($flashKey, $summary);
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
        return ModuleAccess::levelLabel($level);
    }
}
