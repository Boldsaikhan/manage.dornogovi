<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class TaskDocument extends Model
{
    protected $fillable = [
        'task_source_id',
        'uploaded_by',
        'original_name',
        'path',
        'mime',
        'size',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(TaskSource::class, 'task_source_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    protected static function booted(): void
    {
        static::deleting(function (TaskDocument $doc) {
            if ($doc->path && Storage::disk('local')->exists($doc->path)) {
                Storage::disk('local')->delete($doc->path);
            }
        });
    }
}
