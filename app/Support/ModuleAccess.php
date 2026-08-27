<?php

namespace App\Support;

use App\Models\User;
use App\Services\Ai\AiSettings;
use Illuminate\Support\Collection;

class ModuleAccess
{
    /** @var array<int, string> */
    public const LEVELS = ['view', 'manage', 'view_own', 'manage_own'];

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

        return in_array($level, ['view', 'manage', 'view_own', 'manage_own'], true);
    }

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

        return $user->modulePermissions()
            ->where('module_key', $moduleKey)
            ->value('level');
    }

    public static function scopeOwnOnly(?User $user, string $moduleKey): bool
    {
        if (! $user || $user->is_admin) {
            return false;
        }

        return in_array(self::level($user, $moduleKey), ['view_own', 'manage_own'], true);
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

    public static function levelLabel(?string $level): string
    {
        return match ($level) {
            'manage' => 'Удирдах (бүгд)',
            'manage_own' => 'Удирдах (хамааралтай)',
            'view' => 'Харах (бүгд)',
            'view_own' => 'Харах (хамааралтай)',
            default => 'Хаалттай',
        };
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

        return self::definitions()
            ->filter(fn (array $item) => self::canView($user, $item['key']))
            ->groupBy('group')
            ->map(function (Collection $items, string $groupKey) use ($groups) {
                return [
                    'key' => $groupKey,
                    'label' => $groups[$groupKey] ?? $groupKey,
                    'items' => $items->values()->all(),
                ];
            })
            ->values()
            ->all();
    }
}
