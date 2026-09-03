<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TaskSource extends Model
{
    public const KEY_DIRECTIVE = 'directive';

    public const KEY_PREP_PLAN = 'prep_plan';

    public const LAYOUTS = [self::KEY_DIRECTIVE, self::KEY_PREP_PLAN];

    public const COLUMN_SECTOR = 'sector';

    public const COLUMN_MEASURE = 'measure';

    public const COLUMN_TEXT = 'text';

    public const COLUMN_PERIOD = 'period';

    public const COLUMN_RESPONSIBLE = 'responsible';

    public const COLUMN_COLLABORATOR = 'collaborator';

    public const COLUMN_NOTE = 'note';

    protected $fillable = ['key', 'name', 'period', 'sort_order', 'layout', 'columns'];

    protected function casts(): array
    {
        return [
            'columns' => 'array',
        ];
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class)->orderBy('sort_order')->orderBy('id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(TaskDocument::class)->latest();
    }

    public function isSystem(): bool
    {
        return in_array($this->key, [self::KEY_DIRECTIVE, self::KEY_PREP_PLAN], true);
    }

    public function isPrepLayout(): bool
    {
        $layout = $this->layout ?: $this->key;

        return $layout === self::KEY_PREP_PLAN;
    }

    /**
     * Хэсэг нэмэхэд сонгох боломжтой хүснэгтийн толгой.
     *
     * @return list<array{key: string, label: string, field: string, type: string, width: int}>
     */
    public static function columnCatalog(): array
    {
        return [
            ['key' => self::COLUMN_SECTOR, 'label' => 'Ажлын чиглэл', 'field' => 'sector', 'type' => 'text', 'width' => 140],
            ['key' => self::COLUMN_MEASURE, 'label' => 'Арга хэмжээ', 'field' => 'measure', 'type' => 'multiline', 'width' => 280],
            ['key' => self::COLUMN_TEXT, 'label' => 'Үүрэг чиглэл', 'field' => 'text', 'type' => 'multiline', 'width' => 320],
            ['key' => self::COLUMN_PERIOD, 'label' => 'Хугацаа', 'field' => 'period', 'type' => 'period', 'width' => 120],
            ['key' => self::COLUMN_RESPONSIBLE, 'label' => 'Хариуцах эзэн', 'field' => 'responsible', 'type' => 'people', 'width' => 180],
            ['key' => self::COLUMN_COLLABORATOR, 'label' => 'Хяналт тавих', 'field' => 'collaborator', 'type' => 'people', 'width' => 200],
            ['key' => self::COLUMN_NOTE, 'label' => 'Хэрэгжилт', 'field' => 'note', 'type' => 'multiline', 'width' => 160],
        ];
    }

    /**
     * @return list<string>
     */
    public static function columnKeys(): array
    {
        return array_column(self::columnCatalog(), 'key');
    }

    /**
     * @param  list<string>|null  $keys
     * @return list<string>
     */
    public static function normalizeColumnKeys(?array $keys): array
    {
        if (! is_array($keys)) {
            return [];
        }

        $allowed = self::columnKeys();
        $picked = [];

        // Хэрэглэгчийн сонгосон дарааллыг хэвээр нь үлдээнэ — багана яг
        // тэр дарааллаараа гарна.
        foreach ($keys as $key) {
            if (is_string($key)
                && in_array($key, $allowed, true)
                && ! in_array($key, $picked, true)) {
                $picked[] = $key;
            }
        }

        return $picked;
    }

    /**
     * @param  list<string>  $keys
     */
    public static function layoutForColumns(array $keys): string
    {
        if (in_array(self::COLUMN_SECTOR, $keys, true) && ! in_array(self::COLUMN_TEXT, $keys, true)) {
            return self::KEY_PREP_PLAN;
        }

        return self::KEY_DIRECTIVE;
    }

    /**
     * @return list<string>
     */
    public function columnKeyList(): array
    {
        $stored = self::normalizeColumnKeys(is_array($this->columns) ? $this->columns : null);

        if ($stored !== []) {
            return $stored;
        }

        return $this->isPrepLayout()
            ? [self::COLUMN_SECTOR, self::COLUMN_TEXT, self::COLUMN_PERIOD, self::COLUMN_RESPONSIBLE, self::COLUMN_COLLABORATOR, self::COLUMN_NOTE]
            : [self::COLUMN_TEXT, self::COLUMN_PERIOD, self::COLUMN_RESPONSIBLE, self::COLUMN_COLLABORATOR, self::COLUMN_NOTE];
    }

    /**
     * @return list<array{key: string, label: string, field: string, type: string, width: int}>
     */
    public function resolvedColumns(): array
    {
        $catalog = [];
        foreach (self::columnCatalog() as $column) {
            $catalog[$column['key']] = $column;
        }

        $custom = self::normalizeColumnKeys(is_array($this->columns) ? $this->columns : null) !== [];
        $out = [];

        foreach ($this->columnKeyList() as $key) {
            if (! isset($catalog[$key])) {
                continue;
            }

            $column = $catalog[$key];

            if (! $custom && $this->isPrepLayout()) {
                if ($key === self::COLUMN_TEXT) {
                    $column['label'] = 'Арга хэмжээ';
                }
                if ($key === self::COLUMN_COLLABORATOR) {
                    $column['label'] = 'Хамтран хэрэгжүүлэх';
                }
            } elseif (! $custom && $key === self::COLUMN_COLLABORATOR) {
                $column['label'] = 'Хяналт тавих албан тушаалтан';
            }

            $out[] = $column;
        }

        return $out;
    }

    public static function keyFor(string $name): string
    {
        $base = Str::slug($name, '_') ?: 'section';
        $base = substr($base, 0, 24);
        $key = $base;
        $i = 2;

        while (static::query()->where('key', $key)->exists()) {
            $key = $base.'_'.$i;
            $i++;
        }

        return $key;
    }
}
