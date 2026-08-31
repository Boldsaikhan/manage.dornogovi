<?php

namespace App\Support;

use App\Models\AppSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Цэсийн бүлэг, модулиудын дарааллыг админаас тохируулна.
 */
class ModuleOrder
{
    public const GROUPS_KEY = 'modules.group_order';

    public const ITEMS_KEY = 'modules.item_order';

    /**
     * @return list<string>
     */
    public static function groupKeys(): array
    {
        $default = array_keys(config('modules.groups', []));
        $saved = self::loadJson(self::GROUPS_KEY);

        return self::mergeOrder($saved, $default);
    }

    /**
     * @return list<string>
     */
    public static function itemKeys(): array
    {
        $default = ModuleAccess::definitions()->pluck('key')->all();
        $saved = self::loadJson(self::ITEMS_KEY);

        return self::mergeOrder($saved, $default);
    }

    /**
     * @param  list<string>  $groupOrder
     * @param  list<string>  $itemOrder
     */
    public static function setOrder(array $groupOrder, array $itemOrder): void
    {
        $allowedGroups = array_keys(config('modules.groups', []));
        $allowedItems = ModuleAccess::definitions()->pluck('key')->all();

        $groups = self::sanitize($groupOrder, $allowedGroups);
        $items = self::sanitize($itemOrder, $allowedItems);

        self::storeJson(self::GROUPS_KEY, $groups);
        self::storeJson(self::ITEMS_KEY, $items);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $definitions
     * @return Collection<int, array<string, mixed>>
     */
    public static function sortDefinitions(Collection $definitions): Collection
    {
        $itemOrder = array_flip(self::itemKeys());

        return $definitions
            ->sortBy(fn (array $item) => $itemOrder[$item['key']] ?? 9999)
            ->values();
    }

    /**
     * @param  list<string>  $saved
     * @param  list<string>  $default
     * @return list<string>
     */
    private static function mergeOrder(array $saved, array $default): array
    {
        $result = [];

        foreach ($saved as $key) {
            if (in_array($key, $default, true) && ! in_array($key, $result, true)) {
                $result[] = $key;
            }
        }

        foreach ($default as $key) {
            if (! in_array($key, $result, true)) {
                $result[] = $key;
            }
        }

        return $result;
    }

    /**
     * @param  list<string>  $order
     * @param  list<string>  $allowed
     * @return list<string>
     */
    private static function sanitize(array $order, array $allowed): array
    {
        return self::mergeOrder(
            array_values(array_filter($order, fn ($key) => is_string($key) && $key !== '')),
            $allowed
        );
    }

    /**
     * @return list<string>
     */
    private static function loadJson(string $key): array
    {
        $raw = Cache::remember('app_setting:'.$key, 60, function () use ($key) {
            return AppSetting::query()->where('key', $key)->value('value');
        });

        if (! filled($raw)) {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded)
            ? array_values(array_filter($decoded, fn ($value) => is_string($value) && $value !== ''))
            : [];
    }

    /**
     * @param  list<string>  $values
     */
    private static function storeJson(string $key, array $values): void
    {
        AppSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => json_encode(array_values($values), JSON_UNESCAPED_UNICODE)]
        );

        Cache::forget('app_setting:'.$key);
    }
}
