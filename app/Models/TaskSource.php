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

    protected $fillable = ['key', 'name', 'period', 'sort_order', 'layout'];

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
