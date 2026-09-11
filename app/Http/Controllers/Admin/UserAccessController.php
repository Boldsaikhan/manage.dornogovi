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
use App\Services\Sms\SmsSender;
use App\Support\ModuleAccess;
use App\Support\RootAdmin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserAccessController extends Controller
{
    public function index(Request $request): Response
    {
        // Дэд хэсэгтэй модулийн ард нь дэд мөрүүдийг нь шууд байрлуулна.
        $subModules = collect(ModuleAccess::subDefinitions())->groupBy('parent');

        $modules = ModuleAccess::definitions()
            ->reject(fn ($m) => $m['key'] === 'systems')
            ->flatMap(fn (array $m) => array_merge(
                [[
                    'key' => $m['key'],
                    'label' => $m['label'],
                    'own_scope' => ModuleAccess::supportsOwnScope($m['key']),
                    'own_levels' => $m['own_levels'] ?? null,
                    'parent' => null,
                ]],
                $subModules->get($m['key'], collect())
                    ->map(fn (array $sub) => [
                        'key' => $sub['key'],
                        'label' => $sub['label'],
                        'own_scope' => $sub['own_scope'],
                        'own_levels' => $sub['own_levels'],
                        'parent' => $sub['parent'],
                    ])
                    ->all(),
            ))
            ->values();

        $directoryRows = PhoneDirectoryEntry::query()
            ->orderBy('org_order')->orderBy('sort_order')->get();

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
                'role_key' => $u->role_key,
                'department_id' => $u->department_id,
                'department' => $u->department?->name,
                'position' => $u->position,
                'is_department_head' => (bool) $u->is_department_head,
                'is_specialist' => (bool) $u->is_specialist,
                'permissions' => $this->effectivePermissions($u),
                // Нэвтрэх нэр нь утасны жагсаалтын дугаартай тааруулагдсан эсэх.
                ...$this->directoryLogin($u),
                'is_root_admin' => RootAdmin::is($u),
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
     * Утасны жагсаалтад бүртгэлтэй дугаар — нэвтрэх нэр нь энэ байх ёстой.
     *
     * @return array{directory_phone: ?string, directory_name: ?string, directory_org: ?string, login_matches_directory: bool}
     */
    private function directoryLogin(User $user): array
    {
        if (RootAdmin::is($user)) {
            // Үндсэн админ нь албан хаагч биш — утасны жагсаалттай тулгахгүй.
            return [
                'directory_phone' => null,
                'directory_name' => null,
                'directory_org' => null,
                'login_matches_directory' => true,
            ];
        }

        $entry = PhoneDirectoryEntry::forUser($user);
        $phone = $entry?->loginPhone();

        return [
            'directory_phone' => $phone,
            'directory_name' => $entry?->person_name,
            // Байгууллагаар нь шүүхэд хэрэглэнэ.
            'directory_org' => $entry?->org_name,
            'login_matches_directory' => $phone !== null
                && $phone === User::normalizePhone($user->phone),
        ];
    }

    /**
     * Нэвтрэх нэрийг утасны жагсаалтын дугаараар солино.
     */
    public function syncLogin(Request $request, User $user): RedirectResponse
    {
        if (RootAdmin::is($user)) {
            return back()->withErrors([
                'phone' => 'Үндсэн супер админы нэвтрэх нэр өөрчлөгддөггүй.',
            ]);
        }

        $entry = PhoneDirectoryEntry::forUser($user);
        $phone = $entry?->loginPhone();

        if ($phone === null) {
            return back()->withErrors([
                'phone' => sprintf('«%s» утасны жагсаалтад олдсонгүй. Эхлээд жагсаалтад бүртгэнэ үү.', $user->name),
            ]);
        }

        if ($phone === User::normalizePhone($user->phone)) {
            return back()->with('info', 'Нэвтрэх нэр аль хэдийн жагсаалтын дугаартай таарч байна.');
        }

        $taken = User::query()
            ->whereKeyNot($user->id)
            ->whereNotNull('phone')
            ->get(['id', 'phone'])
            ->first(fn (User $u) => User::normalizePhone($u->phone) === $phone);

        if ($taken) {
            return back()->withErrors([
                'phone' => sprintf('%s дугаар «%s»-д бүртгэлтэй байна.', $phone, $taken->name),
            ]);
        }

        $old = $user->phone;
        $user->forceFill(['phone' => $phone])->save();

        return back()->with('success', sprintf(
            '«%s»-ийн нэвтрэх нэр %s → %s болж шинэчлэгдлээ.',
            $user->name,
            $old ?: '—',
            $phone,
        ));
    }

    /**
     * Бүх бүртгэлийн нэвтрэх нэрийг утасны жагсаалттай тулгана.
     */
    public function syncAllLogins(): RedirectResponse
    {
        $changed = 0;
        $skipped = 0;
        $used = User::query()->whereNotNull('phone')->pluck('phone', 'id')
            ->map(fn (?string $phone) => User::normalizePhone($phone))
            ->filter()
            ->all();

        foreach (User::query()->orderBy('name')->get() as $user) {
            if (RootAdmin::is($user)) {
                continue;
            }

            $phone = PhoneDirectoryEntry::forUser($user)?->loginPhone();

            if ($phone === null || $phone === User::normalizePhone($user->phone)) {
                continue;
            }

            // Өөр хүнд бүртгэлтэй дугаарыг дарж бичихгүй.
            if (in_array($phone, array_diff_key($used, [$user->id => true]), true)) {
                $skipped++;

                continue;
            }

            $user->forceFill(['phone' => $phone])->save();
            $used[$user->id] = $phone;
            $changed++;
        }

        $message = sprintf('Нэвтрэх нэр %d бүртгэлд шинэчлэгдлээ.', $changed);

        if ($skipped > 0) {
            $message .= sprintf(' %d бүртгэлийн дугаар өөр хүнд бүртгэлтэй тул алгаслаа.', $skipped);
        }

        return back()->with($skipped > 0 ? 'warning' : 'success', $message);
    }

    /**
     * Утасны жагсаалтын «Хэлтэс» ангиллын бүх албан хаагчид нэвтрэх эрх өгнө.
     */
    public function provisionHeltes(HeltesAccountProvisioner $provisioner): RedirectResponse
    {
        $result = $provisioner->run();

        $message = sprintf(
            'Хэлтсийн албан хаагчдад эрх өглөө: %d шинэ, %d шинэчилсэн, %d алгассан.',
            $result['created'],
            $result['updated'],
            count($result['skipped']),
        );

        if ($result['sms_sent'] > 0 || $result['sms_failed'] > 0) {
            $message .= sprintf(' SMS: %d амжилттай', $result['sms_sent']);

            if ($result['sms_failed'] > 0) {
                $message .= sprintf(', %d амжилтгүй', $result['sms_failed']);
            }

            $message .= '.';
        }

        return back()->with('success', $message);
    }

    /**
     * Ролийн загварыг хадгална — тухайн түвшинг сонгоход энэ эрхүүд хэрэгжинэ.
     */
    /**
     * Утасны жагсаалтын мөрөөс тухайн хүний нэвтрэх нэр / нууц үгийг шинэчилнэ.
     *
     * Холбоос нь гар утасны дугаар — бүртгэл нь мөн утсаараа нэвтэрдэг.
     */
    public function updateDirectoryAccount(Request $request, PhoneDirectoryEntry $entry): RedirectResponse
    {
        $data = $request->validate([
            'login' => ['nullable', 'string', 'max:20'],
            'password' => ['nullable', 'string', 'min:6', 'max:255'],
        ]);

        $login = $data['login'] ?? null;
        $password = $data['password'] ?? null;

        if (! $login && ! $password) {
            return back()->withErrors(['login' => 'Нэвтрэх нэр эсвэл нууц үгийн аль нэгийг оруулна уу.']);
        }

        $user = User::query()
            ->whereNotNull('phone')
            ->get(['id', 'phone'])
            ->first(fn (User $u) => User::normalizePhone($u->phone) === User::normalizePhone($entry->mobile_phone));

        // Эрх байхгүй бол энд шууд үүсгэнэ — өөр хуудас руу явуулах шаардлагагүй.
        if (! $user) {
            if (! $password) {
                return back()->withErrors([
                    'password' => 'Шинэ бүртгэл үүсгэхийн тулд нууц үг оруулна уу.',
                ]);
            }

            // User загварт «hashed» cast байгаа тул ЦЭВЭР нууц үг дамжуулна.
            $created = app(HeltesAccountProvisioner::class)->createForEntry(
                $entry,
                $password,
                $login ? User::normalizePhone($login) : null,
            );

            if (! $created) {
                return back()->withErrors([
                    'login' => sprintf(
                        '«%s»-д бүртгэл үүсгэж чадсангүй. Хүний нэр, гар утас нь бүрэн эсэхийг шалгана уу.',
                        $entry->person_name,
                    ),
                ]);
            }

            if ($created->phone && $created->phone !== $entry->mobile_phone) {
                $entry->forceFill(['mobile_phone' => $created->phone])->save();
            }

            return back()->with('success', sprintf(
                '«%s» — нэвтрэх эрх үүслээ. Нэвтрэх нэр: %s',
                $entry->person_name,
                $created->phone,
            ));
        }

        $user = User::findOrFail($user->id);
        $changes = [];

        if ($login) {
            $normalized = User::normalizePhone($login);

            if (! $normalized) {
                return back()->withErrors(['login' => 'Нэвтрэх нэр (утасны дугаар) буруу байна.']);
            }

            $taken = User::query()
                ->whereKeyNot($user->id)
                ->whereNotNull('phone')
                ->get(['id', 'phone'])
                ->contains(fn (User $u) => User::normalizePhone($u->phone) === $normalized);

            if ($taken) {
                return back()->withErrors(['login' => 'Энэ дугаараар өөр бүртгэл байна.']);
            }

            $user->phone = $normalized;
            $changes[] = 'нэвтрэх нэр';
        }

        if ($password) {
            $user->password = Hash::make($password);
            $changes[] = 'нууц үг';
        }

        $user->save();

        // Утасны жагсаалтын дугаарыг ч зэрэг тааруулна.
        if ($login) {
            $entry->forceFill(['mobile_phone' => $user->phone])->save();
        }

        return back()->with('success', sprintf(
            '«%s» — %s шинэчлэгдлээ.',
            $entry->person_name,
            implode(', ', $changes),
        ));
    }

    public function updateRole(Request $request, string $role): RedirectResponse
    {
        $model = Role::query()->where('key', $role)->firstOrFail();

        $request->merge([
            'permissions' => $this->normalizedPermissions($request->input('permissions')),
        ]);

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
            ->filter(fn ($level, $key) => ! ModuleAccess::isOwnLevel($level) || ModuleAccess::supportsOwnScope($key))
            ->all();

        // Загварыг эхлээд хадгална. Хэрэглэгчдийн эрх хуулах нь тусдаа —
        // олон мэргэжилтэн дээр sync алдаа/timeout гарахад загвар буцаж DEFAULTS болохгүй.
        RolePermission::replaceFor($model->key, $permissions);

        $synced = 0;
        $syncFailed = false;
        try {
            $synced = ModuleAccess::syncUsersToRole($model->key);
        } catch (\Throwable $e) {
            report($e);
            $syncFailed = true;
        }

        $redirect = redirect()->route('admin.users.index', [
            'tab' => 'templates',
            'role' => $model->key,
        ]);

        $message = sprintf(
            '«%s» ролийн загвар хадгалагдлаа (%d модуль)%s.',
            $model->label,
            count($permissions),
            $synced > 0 ? sprintf(', %d хэрэглэгчийн эрх шинэчлэгдлээ', $synced) : '',
        );

        if ($syncFailed) {
            return $redirect
                ->with('success', $message)
                ->with('warning', 'Загвар хадгалагдсан. Хэрэглэгчийн эрх хуулахад алдаа гарлаа.');
        }

        return $redirect->with('success', $message);
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

    /**
     * Албан хаагчийн нэвтрэх нууц үгийг шинэчлэх.
     *
     * Ролийн тохиргооноос тусад нь явдаг — эрх хадгалахад нууц үг санамсаргүй
     * солигдохоос сэргийлнэ. Хүсвэл шинэ нууц үгийг SMS-ээр хүргэнэ.
     */
    public function updatePassword(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'notify' => ['boolean'],
        ], [], ['password' => 'нууц үг']);

        // User загварт «hashed» cast байгаа тул ЦЭВЭР нууц үг онооно.
        $user->forceFill([
            'password' => $data['password'],
            'remember_token' => Str::random(60),
        ])->save();

        $notify = $request->boolean('notify') && filled($user->phone);
        $sent = $notify && app(SmsSender::class)->sendLoginCredentials($user, $data['password']);

        app(\App\Services\Push\EmployeePushNotifier::class)->notifyUsers([$user], [
            'title' => 'Нэвтрэх нууц үг шинэчлэгдлээ',
            'body' => $user->name.' — нууц үг админаар шинэчлэгдсэн байна.',
            'url' => '/dept-dashboard',
            'tag' => 'access',
        ]);

        $message = sprintf('«%s»-ийн нэвтрэх нууц үг шинэчлэгдлээ.', $user->name);

        if ($notify) {
            $message .= $sent
                ? sprintf(' %s дугаар руу SMS-ээр илгээлээ.', $user->phone)
                : ' Гэхдээ SMS илгээгдсэнгүй — нууц үгийг өөрөө дамжуулна уу.';
        }

        return back()->with($notify && ! $sent ? 'warning' : 'success', $message);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $request->merge([
            'permissions' => $this->normalizedPermissions($request->input('permissions')),
            'department_id' => $request->input('department_id') ?: null,
            'phone' => $request->input('phone') ?: null,
            'position' => $request->input('position') ?: null,
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($user->id)],
            'department_id' => ['nullable', 'exists:departments,id'],
            'position' => ['nullable', 'string', 'max:255'],
            'is_admin' => ['boolean'],
            'is_department_head' => ['boolean'],
            'is_specialist' => ['boolean'],
            'role_key' => ['nullable', 'string', 'exists:roles,key'],
            'password' => ['nullable', 'string', 'min:8'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['in:'.implode(',', ModuleAccess::LEVELS)],
        ]);

        $beforePermissions = $user->modulePermissions
            ->mapWithKeys(fn (UserModulePermission $p) => [$p->module_key => $p->level])
            ->all();

        $beforeRole = $user->role_key;

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

        // Үндсэн супер админ: эрх нь хасагдахгүй, нэвтрэх нэр нь өөрчлөгдөхгүй.
        $isRoot = RootAdmin::is($user);

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $isRoot ? RootAdmin::phone() : ($data['phone'] ?? null),
            'department_id' => $data['department_id'] ?? null,
            'position' => $data['position'] ?? null,
            'is_admin' => $isRoot || $request->boolean('is_admin'),
            'is_department_head' => $request->boolean('is_department_head'),
            'is_specialist' => $request->boolean('is_specialist'),
        ]);

        // Сонгосон роль — эрхгүй (хоосон) роль ч тэмдэглэгдэнэ.
        if ($request->has('role_key')) {
            $user->role_key = filled($data['role_key'] ?? null) ? $data['role_key'] : null;
        }

        if ($passwordChanged) {
            $user->password = $data['password'];
        }

        $user->save();

        if ($passwordChanged && ! $user->is_admin && config('sms.send_on_password_reset')) {
            app(SmsSender::class)->sendLoginCredentials($user, (string) $data['password']);
        }

        $permissions = $data['permissions'] ?? [];
        $user->modulePermissions()->delete();
        foreach ($permissions as $key => $level) {
            if (! ModuleAccess::find($key)) {
                continue;
            }

            if (ModuleAccess::isOwnLevel($level) && ! ModuleAccess::supportsOwnScope($key)) {
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

        if ($beforeRole !== $user->role_key) {
            $profileChanges[] = 'роль: '.(Role::query()->where('key', $user->role_key)->value('label') ?: '—');
        }

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

    /**
     * Хоосон / хүчингүй түвшинг хасна — хадгалах үед 422 гаргахгүй.
     *
     * @return array<string, string>
     */
    private function normalizedPermissions(mixed $permissions): array
    {
        if (! is_array($permissions)) {
            return [];
        }

        return collect($permissions)
            ->filter(fn ($level, $key) => is_string($key) && $key !== '' && $key !== '__none__')
            ->filter(fn ($level) => is_string($level) && in_array($level, ModuleAccess::LEVELS, true))
            ->all();
    }

    /**
     * Загвар + хэрэглэгчийн мөрийг нэгтгэсэн эрх — жагсаалт/засварт бодит хандалт харагдана.
     *
     * @return array<string, string>
     */
    private function effectivePermissions(User $user): array
    {
        $stored = $user->modulePermissions
            ->mapWithKeys(fn (UserModulePermission $p) => [$p->module_key => $p->level])
            ->all();

        if ($user->is_admin) {
            return $stored;
        }

        $keys = ModuleAccess::definitions()
            ->pluck('key')
            ->merge(collect(ModuleAccess::subDefinitions())->pluck('key'));

        $out = [];

        foreach ($keys as $key) {
            if (! is_string($key) || $key === 'systems') {
                continue;
            }

            $level = ModuleAccess::level($user, $key);

            if (is_string($level) && $level !== '') {
                $out[$key] = $level;
            }
        }

        return $out;
    }

    private function levelLabel(?string $level): string
    {
        return ModuleAccess::levelLabel($level);
    }
}
