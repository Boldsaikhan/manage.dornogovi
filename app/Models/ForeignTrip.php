<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ForeignTrip extends Model
{
    protected $fillable = [
        'user_id',
        'department_id',
        'scope',
        'org_name',
        'position',
        'person_name',
        'destination_country',
        'start_date',
        'end_date',
        'reason',
        'companions',
        'route',
        'funding_source',
        'foreign_contact',
        'accommodation',
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
}
