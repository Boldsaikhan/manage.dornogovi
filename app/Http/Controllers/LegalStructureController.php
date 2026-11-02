<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserModulePermission;
use App\Support\ModuleAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Эрх зүйн акт, тогтоол бүлгийн бүтэц ба гурван цэсийн хандах эрх.
 */
class LegalStructureController extends Controller
{
    /** Энэ цэснээс тохируулах модулиуд. */
    public const KEYS = ModuleAccess::LEGAL_GROUP_KEYS;

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless(ModuleAccess::canView($user, 'legal_structure'), 403);

        $definitions = ModuleAccess::definitions()->keyBy('key');

        $structure = collect(self::KEYS)
            ->map(function (string $key) use ($definitions, $user) {
                $item = $definitions->get($key);

                return [
                    'key' => $key,
                    'label' => $item['label'] ?? $key,
                    'route' => $item['route'] ?? null,
                    'canOpen' => ModuleAccess::canView($user, $key),
                    'options' => $this->levelOptions($item ?? ['key' => $key]),
                ];
            })
            ->values();

        $users = User::query()
            ->with(['department:id,name', 'modulePermissions'])
            ->orderBy('name')
            ->get()
            ->map(fn (User $person) => [
                'id' => $person->id,
                'name' => $person->name,
                'position' => $person->position,
                'department' => $person->department?->name,
                'is_admin' => (bool) $person->is_admin,
                'permissions' => collect(self::KEYS)
                    ->mapWithKeys(fn (string $key) => [
                        $key => $person->is_admin ? '' : (ModuleAccess::level($person, $key) ?? ''),
                    ])
                    ->all(),
            ])
            ->values();

        return Inertia::render('Modules/LegalStructure', [
            'heading' => 'Эрх зүйн акт, тогтоол',
            'structure' => $structure,
            'users' => $users,
            'canManage' => ModuleAccess::canManage($user, 'legal_structure'),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless(ModuleAccess::canManage($request->user(), 'legal_structure'), 403);
        abort_if($user->is_admin, 403);

        $incoming = $request->input('permissions', []);

        if (is_array($incoming)) {
            foreach (self::KEYS as $key) {
                if (($incoming[$key] ?? null) === '') {
                    $incoming[$key] = null;
                }
            }

            $request->merge(['permissions' => $incoming]);
        }

        $data = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['nullable', 'string', Rule::in(ModuleAccess::LEVELS)],
        ]);

        $definitions = ModuleAccess::definitions()->keyBy('key');

        foreach (self::KEYS as $key) {
            $level = $data['permissions'][$key] ?? null;
            $level = is_string($level) && $level !== '' ? $level : ModuleAccess::LEVEL_CLOSED;
            $definition = $definitions->get($key) ?? ['key' => $key];

            if ($level !== ModuleAccess::LEVEL_CLOSED && ! $this->levelAllowed($definition, $level)) {
                $level = ModuleAccess::LEVEL_CLOSED;
            }

            UserModulePermission::query()->updateOrCreate(
                ['user_id' => $user->id, 'module_key' => $key],
                ['level' => $level],
            );
        }

        return back()->with('success', $user->name.'-н хандах эрхийг хадгаллаа.');
    }

    /**
     * @param  array<string, mixed>  $module
     * @return list<array{value: string, label: string}>
     */
    private function levelOptions(array $module): array
    {
        $options = [['value' => '', 'label' => 'Хаалттай']];
        $key = (string) ($module['key'] ?? '');

        if (ModuleAccess::supportsOwnScope($key)) {
            $labels = [
                'view_own' => 'Харах (хамааралтай)',
                'edit_own' => 'Оруулах (хамааралтай)',
                'add_own' => 'Нэмэх, оруулах (хамааралтай)',
                'manage_own' => 'Удирдах (хамааралтай)',
            ];
            $levels = is_array($module['own_levels'] ?? null) && $module['own_levels'] !== []
                ? $module['own_levels']
                : ['view_own', 'edit_own', 'add_own', 'manage_own'];

            foreach ($levels as $value) {
                if (isset($labels[$value])) {
                    $options[] = ['value' => $value, 'label' => $labels[$value]];
                }
            }
        }

        if (! empty($module['unit_scope'])) {
            $options[] = ['value' => 'manage_unit', 'label' => 'Удирдах (хэлтсийн)'];
        }

        $options[] = ['value' => 'view', 'label' => 'Харах (бүгд)'];
        $options[] = ['value' => 'edit', 'label' => 'Оруулах (бүгд)'];
        $options[] = ['value' => 'manage', 'label' => 'Удирдах (бүгд)'];

        return $options;
    }

    /**
     * @param  array<string, mixed>  $module
     */
    private function levelAllowed(array $module, string $level): bool
    {
        return collect($this->levelOptions($module))->contains(fn (array $option) => $option['value'] === $level);
    }
}
