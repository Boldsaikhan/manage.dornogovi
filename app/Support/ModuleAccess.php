<?php

namespace App\Support;

use App\Models\User;
use App\Services\Ai\AiSettings;
use Illuminate\Support\Collection;

class ModuleAccess
{
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

        // Албан хаагчийн самбар болон системүүд — бүх нэвтэрсэн хэрэглэгчид нээлттэй.
        if (in_array($moduleKey, ['dept_dashboard', 'systems'], true)) {
            return true;
        }

        return $user->modulePermissions()
            ->where('module_key', $moduleKey)
            ->whereIn('level', ['view', 'manage'])
            ->exists();
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

        return $user->modulePermissions()
            ->where('module_key', $moduleKey)
            ->where('level', 'manage')
            ->exists();
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
