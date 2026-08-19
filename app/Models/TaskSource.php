<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskSource extends Model
{
    protected $fillable = ['name', 'period', 'sort_order'];

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
