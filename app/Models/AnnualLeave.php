<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnualLeave extends Model
{
    protected $fillable = [
        'user_id',
        'department_id',
        'scope',
        'org_name',
        'position',
        'person_name',
        'work_years',
        'entitled_days',
        'start_date',
        'end_date',
        'substitute_position',
        'substitute_name',
        'substitute_phone',
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
