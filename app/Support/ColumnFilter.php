<?php

namespace App\Support;

/**
 * Хүснэгтийн баганын хайлт — хоосон талбар («-», «—», «хоосон»).
 */
class ColumnFilter
{
    /** @var list<string> */
    public const EMPTY_NEEDLES = ['-', '—', '–', '−', 'хоосон', 'empty'];

    public static function isEmptyNeedle(mixed $needle): bool
    {
        $text = mb_strtolower(trim((string) $needle));

        return $text !== '' && in_array($text, self::EMPTY_NEEDLES, true);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>|\Illuminate\Database\Query\Builder  $query
     */
    public static function applyEmpty($query, string $field): void
    {
        $query->where(function ($inner) use ($field) {
            $inner->whereNull($field)->orWhere($field, '');
        });
    }
}
