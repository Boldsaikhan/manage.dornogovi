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
     * Админ UI-д харуулах жагсаалт.
     *
     * @return list<array{key: string, label: string, group: string, group_label: string, enabled: bool}>
     */
    public static function forAdmin(): array
    {
        $groups = config('modules.groups', []);
        $disabled = self::disabledKeys();
        $groupOrder = array_flip(ModuleOrder::groupKeys());

        return ModuleOrder::sortDefinitions(ModuleAccess::definitions())
            ->map(fn (array $item) => [
                'key' => $item['key'],
                'label' => $item['label'],
                'group' => $item['group'],
                'group_label' => $groups[$item['group']] ?? $item['group'],
                'enabled' => ! in_array($item['key'], $disabled, true),
            ])
            ->sortBy(fn (array $item) => $groupOrder[$item['group']] ?? 9999)
            ->values()
            ->all();
    }
}
