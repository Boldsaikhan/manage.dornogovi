<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Leave extends Model
{
    public const TYPES = [
        'tsalintai' => 'Цалинтай',
        'tsalingui' => 'Цалингүй',
        'eeljiin' => 'Ээлжийн амралтаас',
    ];

    public const SIGNERS = [
        'acting' => 'Даргын албан үүргийг түр орлон гүйцэтгэгч',
        'head' => 'Хэлтсийн дарга',
    ];

    protected $fillable = [
        'user_id',
        'department_id',
        'scope',
        'org_name',
        'person_name',
        'slip_number',
        'signer',
        'type',
        'start_date',
        'end_date',
        'days',
        'reason',
        'status',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
