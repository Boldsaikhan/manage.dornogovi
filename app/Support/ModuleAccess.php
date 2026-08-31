<?php

namespace App\Support;

use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserModulePermission;
use App\Services\Ai\AiSettings;
use Illuminate\Support\Collection;

class ModuleAccess
{
    /**
     * view / edit / manage (+ _own хамааралтай хувилбар).
     * edit = хүснэгтэнд мэдээлэл оруулах; manage = хэсэг/импорт гэх мэт удирдлага.
     *
     * @var list<string>
     */
    public const LEVELS = ['view', 'edit', 'manage', 'view_own', 'edit_own', 'manage_own'];

    public static function definitions(): Collection
    {
        return collect(config('modules.items', []))->map(function (array $item) {
            if (($item['key'] ?? null) === 'ai') {
                $item['label'] = app(AiSettings::class)->displayName();
            }

            return $item;
        });
    }

    public static function find(string $key): ?array
    {
        return self::definitions()->firstWhere('key', $key);
    }

    public static function canView(?User $user, string $moduleKey): bool
    {
        if (! $user) {
            return false;
        }

        // Глобал хаасан цэс — хэн ч (админ ч) харахгүй / нээхгүй.
        if (! ModuleVisibility::isEnabled($moduleKey)) {
            return false;
        }

        if ($user->is_admin) {
            return true;
        }

        // Албан хаагчийн самбар — бүх нэвтэрсэн хэрэглэгчид нээлттэй.
        if ($moduleKey === 'dept_dashboard') {
            return true;
        }

        $level = self::level($user, $moduleKey);

        return in_array($level, self::LEVELS, true);
    }

    /**
     * Хүснэгтийн мөр нэмэх/засах/устгах.
     */
    public static function canEdit(?User $user, string $moduleKey): bool
    {
        if (! $user) {
            return false;
        }

        if (! ModuleVisibility::isEnabled($moduleKey)) {
            return false;
        }

        if ($user->is_admin) {
            return true;
        }

        $level = self::level($user, $moduleKey);

        return in_array($level, ['edit', 'manage', 'edit_own', 'manage_own'], true);
    }

    /**
     * Модулийн удирдлага (хэсэг нэмэх, Word импорт, ангилал гэх мэт).
     */
    public static function canManage(?User $user, string $moduleKey): bool
    {
        if (! $user) {
            return false;
        }

        if (! ModuleVisibility::isEnabled($moduleKey)) {
            return false;
        }

        if ($user->is_admin) {
            return true;
        }

        $level = self::level($user, $moduleKey);

        return in_array($level, ['manage', 'manage_own'], true);
    }

    public static function level(?User $user, string $moduleKey): ?string
    {
        if (! $user || $user->is_admin) {
            return null;
        }

        $userLevel = $user->modulePermissions
            ->firstWhere('module_key', $moduleKey)
            ?->level;
        $userLevel = in_array($userLevel, self::LEVELS, true) ? $userLevel : null;

        // Системийн роль: загварт байхгүй модуль хаалттай хэвээр.
        // Загварт байгаа модулийн түвшинг Хандах эрх дээрх хэрэглэгчийн тохиргоо дарж болно
        // (жнь: «хамааралтай» ↔ «бүгд»).
        $roleKey = self::systemRoleKey($user);
        if ($roleKey) {
            $roleLevel = RolePermission::map()[$roleKey][$moduleKey] ?? null;
            $roleLevel = in_array($roleLevel, self::LEVELS, true) ? $roleLevel : null;

            if ($roleLevel === null) {
                return null;
            }

            return $userLevel ?? $roleLevel;
        }

        return $userLevel;
    }

    /**
     * Суурь роль (админ биш): дарга > мэргэжилтэн.
     */
    public static function systemRoleKey(User $user): ?string
    {
        if ($user->is_admin) {
            return null;
        }

        if ($user->is_department_head) {
            return 'department_head';
        }

        if ($user->is_specialist) {
            return 'specialist';
        }

        return null;
    }

    /**
     * Ролийн загварыг тухайн рольтой бүх хэрэглэгчид хуулна.
     */
    public static function syncUsersToRole(string $roleKey): int
    {
        $field = Role::SYSTEM_FIELDS[$roleKey] ?? null;
        if (! $field) {
            return 0;
        }

        $map = RolePermission::map()[$roleKey] ?? [];
        $desired = [];
        foreach ($map as $module => $level) {
            if ($module === '__none__' || ! self::find($module) || ! in_array($level, self::LEVELS, true)) {
                continue;
            }

            if (in_array($level, ['view_own', 'edit_own', 'manage_own'], true) && ! self::supportsOwnScope($module)) {
                continue;
            }

            $desired[$module] = $level;
        }

        $query = User::query()->where($field, true)->where('is_admin', false);

        // Мэргэжилтэн загвар — дарга нарт бүү хүр.
        if ($roleKey === 'specialist') {
            $query->where('is_department_head', false);
        }

        $count = 0;
        foreach ($query->get() as $user) {
            try {
                UserModulePermission::query()->where('user_id', $user->id)->delete();

                foreach ($desired as $module => $level) {
                    UserModulePermission::query()->create([
                        'user_id' => $user->id,
                        'module_key' => $module,
                        'level' => $level,
                    ]);
                }
                $count++;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $count;
    }

    public static function scopeOwnOnly(?User $user, string $moduleKey): bool
    {
        if (! $user || $user->is_admin) {
            return false;
        }

        return in_array(self::level($user, $moduleKey), ['view_own', 'edit_own', 'manage_own'], true);
    }

    public static function manageOwnOnly(?User $user, string $moduleKey): bool
    {
        if (! $user || $user->is_admin) {
            return false;
        }

        return self::level($user, $moduleKey) === 'manage_own';
    }

    public static function supportsOwnScope(string $moduleKey): bool
    {
        return filled(self::find($moduleKey)['own_scope'] ?? null);
    }

    public static function isOwnLevel(?string $level): bool
    {
        return in_array($level, ['view_own', 'edit_own', 'manage_own'], true);
    }

    public static function levelLabel(?string $level): string
    {
        return match ($level) {
            'manage' => 'Удирдах (бүгд)',
            'manage_own' => 'Удирдах (хамааралтай)',
            'edit' => 'Оруулах (бүгд)',
            'edit_own' => 'Оруулах (хамааралтай)',
            'view' => 'Харах (бүгд)',
            'view_own' => 'Харах (хамааралтай)',
            default => 'Хаалттай',
        };
    }

    /**
     * Үүрэг даалгавар — «Оруулах (хамааралтай)»: зөвхөн хэрэгжилт, биелэлт.
     */
    public static function tasksProgressOnly(?User $user): bool
    {
        if (! $user || $user->is_admin) {
            return false;
        }

        return self::level($user, 'tasks') === 'edit_own';
    }

    /**
     * @return array{canEdit: bool, canEditProgress: bool, canManage: bool}
     */
    public static function taskPagePermissions(?User $user): array
    {
        $progressOnly = self::tasksProgressOnly($user);

        return [
            'canEdit' => self::canEdit($user, 'tasks') && ! $progressOnly,
            'canEditProgress' => self::canEdit($user, 'tasks'),
            'canManage' => self::canManage($user, 'tasks'),
        ];
    }

    /**
     * Хажуугийн цэсэд харагдах модулиуд.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function navFor(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $groups = config('modules.groups', []);
        $definitions = self::definitions()->keyBy('key');
        $result = [];

        foreach (ModuleOrder::groupKeys() as $groupKey) {
            $items = [];

            foreach (ModuleOrder::itemKeys() as $itemKey) {
                $definition = $definitions->get($itemKey);
                if (! $definition || ($definition['group'] ?? null) !== $groupKey) {
                    continue;
                }

                if (! self::canView($user, $itemKey)) {
                    continue;
                }

                $items[] = $definition;
            }

            if ($items === []) {
                continue;
            }

            $result[] = [
                'key' => $groupKey,
                'label' => $groups[$groupKey] ?? $groupKey,
                'items' => $items,
            ];
        }

        return $result;
    }
}
