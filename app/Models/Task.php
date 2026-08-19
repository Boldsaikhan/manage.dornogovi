<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    protected $fillable = [
        'task_source_id', 'text', 'period', 'responsible', 'collaborator',
        'sector', 'department', 'indicator', 'baseline', 'target',
        'progress', 'note', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['progress' => 'integer'];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(TaskSource::class, 'task_source_id');
    }

    /**
     * Хэрэгжилтийн төлөв — статик сайтын `statusOf()`-той ижил логик.
     */
    public function status(): string
    {
        if ($this->progress >= 100) {
            return 'done';
        }

        return $this->progress > 0 ? 'doing' : 'none';
    }
}
