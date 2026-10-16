<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

/**
 * Чөлөөний хүснэгтийн шинэ багана бэлэн эсэх.
 *
 * Deploy хийгдээд migration нь ажиллаж амжаагүй (эсвэл бүтэлгүйтсэн)
 * үед шинэ код хуучин схем дээр 500 алдаа өгдөг. Тиймээс шинэ багана
 * бүрийг ашиглахын өмнө шалгаж, байхгүй бол тэр боломжийг түр унтраана.
 */
class LeaveSchema
{
    /** @var array<string, bool> */
    private static array $cache = [];

    public static function has(string $column): bool
    {
        if (array_key_exists($column, self::$cache)) {
            return self::$cache[$column];
        }

        try {
            return self::$cache[$column] = Schema::hasColumn('leaves', $column);
        } catch (\Throwable $e) {
            return self::$cache[$column] = false;
        }
    }

    public static function hasRules(): bool
    {
        return self::has('rule_code');
    }

    public static function hasUnit(): bool
    {
        return self::has('unit_name');
    }

    /**
     * Байгаа багануудыг л сонгоно.
     *
     * @param  list<string>  $columns
     * @return list<string>
     */
    public static function only(array $columns): array
    {
        return array_values(array_filter($columns, fn (string $c) => self::has($c)));
    }

    public static function forget(): void
    {
        self::$cache = [];
    }
}
