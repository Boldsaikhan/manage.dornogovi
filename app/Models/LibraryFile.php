<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class LibraryFile extends Model
{
    protected $fillable = [
        'title',
        'original_name',
        'path',
        'mime',
        'size',
        'folder',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function displayName(): string
    {
        return filled($this->title) ? (string) $this->title : (string) $this->original_name;
    }

    public function extension(): string
    {
        return strtolower(pathinfo((string) $this->original_name, PATHINFO_EXTENSION));
    }

    protected static function booted(): void
    {
        static::deleting(function (LibraryFile $file) {
            if ($file->path && Storage::disk('local')->exists($file->path)) {
                Storage::disk('local')->delete($file->path);
            }
        });
    }
}
