<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Meeting extends Model
{
    protected $fillable = [
        'title', 'held_at', 'attendees', 'minutes', 'transcript', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'held_at' => 'datetime',
            'attendees' => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
