<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiDailyUsage extends Model
{
    protected $fillable = [
        'user_id',
        'usage_date',
        'questions',
    ];

    protected function casts(): array
    {
        return [
            'usage_date' => 'date',
            'questions' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
