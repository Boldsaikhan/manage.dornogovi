<?php

namespace App\Support;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Цэсийн модулиудыг глобал нээх/хаах.
 * Хаасан модуль бүх хэрэглэгчид (админ орно) цэсэнд харагдахгүй, URL-аар ч нээгдэхгүй.
 */
class ModuleVisibility
{
    public const SETTING_KEY = 'modules.disabled';

    /**
     * @return list<string>
     */
    public static function disabledKeys(): array
    {
        $raw = Cache::remember('app_setting:'.self::SETTING_KEY, 60, function () {
            return AppSetting::query()->where('key', self::SETTING_KEY)->value('value');
        });

        if (! filled($raw)) {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded)
            ? array_values(array_filter($decoded, fn ($k) => is_string($k) && $k !== ''))
            : [];
    }

    public static function isEnabled(string $moduleKey): bool
    {
        return ! in_array($moduleKey, self::disabledKeys(), true);
    }

    /**
     * @param  list<string>  $disabledKeys
     */
    public static function setDisabled(array $disabledKeys): void
    {
        $allowed = ModuleAccess::definitions()->pluck('key')->all();
        $disabled = array_values(array_unique(array_filter(
            $disabledKeys,
            fn ($key) => is_string($key) && in_array($key, $allowed, true)
        )));

        AppSetting::query()->updateOrCreate(
            ['key' => self::SETTING_KEY],
            ['value' => json_encode($disabled, JSON_UNESCAPED_UNICODE)]
        );

        Cache::forget('app_setting:'.self::SETTING_KEY);
    }

    /**
     * Админ UI-д харуулах жагсаалт (хуучин flat хэлбэр).
     *
     * @return list<array{key: string, label: string, group: string, group_label: string, enabled: bool}>
     */
    public static function forAdmin(): array
    {
        return collect(self::groupsForAdmin())
            ->flatMap(fn (array $group) => $group['items'])
            ->values()
            ->all();
    }

    /**
     * Админ UI-д харуулах бүлэглэсэн жагсаалт — хадгалсан дарааллыг яг тусгана.
     *
     * @return list<array{key: string, label: string, items: list<array{key: string, label: string, group: string, group_label: string, enabled: bool}>}>
     */
    public static function groupsForAdmin(): array
    {
        $groups = config('modules.groups', []);
        $disabled = self::disabledKeys();
        $definitions = ModuleAccess::definitions()->keyBy('key');
        $result = [];

        foreach (ModuleOrder::groupKeys() as $groupKey) {
            $items = [];

            foreach (ModuleOrder::itemKeys() as $itemKey) {
                $definition = $definitions->get($itemKey);
                if (! $definition || ($definition['group'] ?? null) !== $groupKey) {
                    continue;
                }

                $items[] = [
                    'key' => $itemKey,
                    'label' => $definition['label'],
                    'group' => $groupKey,
                    'group_label' => $groups[$groupKey] ?? $groupKey,
                    'enabled' => ! in_array($itemKey, $disabled, true),
                ];
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
