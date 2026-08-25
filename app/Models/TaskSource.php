<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskSource extends Model
{
    public const KEY_DIRECTIVE = 'directive';

    public const KEY_PREP_PLAN = 'prep_plan';

    protected $fillable = ['key', 'name', 'period', 'sort_order'];

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class)->orderBy('sort_order')->orderBy('id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(TaskDocument::class)->latest();
    }
}
