<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiAuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'conversation_id',
        'question',
        'intent',
        'tools',
        'sources',
        'provider',
        'success',
        'error',
        'latency_ms',
    ];

    protected function casts(): array
    {
        return [
            'tools' => 'array',
            'sources' => 'array',
            'success' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
