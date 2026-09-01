<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    protected $fillable = [
        'task_source_id',
        'text',
        'measure',
        'period',
        'responsible',
        'collaborator',
        'sector',
        'department',
        'indicator',
        'baseline',
        'target',
        'progress',
        'note',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'progress' => 'integer',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(TaskSource::class, 'task_source_id');
    }
}
