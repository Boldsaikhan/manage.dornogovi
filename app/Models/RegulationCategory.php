<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class RegulationCategory extends Model
{
    protected $fillable = ['key', 'label', 'sort_order'];

    /**
     * @return array<string, string>
     */
    public static function scopeMap(): array
    {
        return static::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('label', 'key')
            ->all();
    }

    /**
     * @param  array<string, int>  $counts
     * @return array<int, array{id: int, key: string, label: string, sort_order: int, count: int}>
     */
    public static function manageList(array $counts = []): array
    {
        return static::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'key', 'label', 'sort_order'])
            ->map(fn (self $row) => [
                'id' => $row->id,
                'key' => $row->key,
                'label' => $row->label,
                'sort_order' => (int) $row->sort_order,
                'count' => (int) ($counts[$row->key] ?? 0),
            ])
            ->values()
            ->all();
    }

    public static function keyFor(string $label): string
    {
        $base = Str::slug($label, '_') ?: 'tab';
        $base = substr($base, 0, 48);
        $key = $base;
        $i = 2;

        while (static::query()->where('key', $key)->exists()) {
            $key = $base.'_'.$i;
            $i++;
        }

        return $key;
    }
}
