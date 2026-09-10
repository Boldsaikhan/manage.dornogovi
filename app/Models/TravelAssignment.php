<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TravelAssignment extends Model
{
    /** «БАТЛАВ» хэсэгт гарын үсэг зурах албан тушаалтан. */
    public const APPROVERS = [
        'governor' => 'Засаг дарга',
        'chief' => 'Тамгын газрын дарга',
    ];

    protected $fillable = [
        'user_id',
        'person_name',
        'position', 'department_id', 'approver', 'destination', 'purpose',
        'composition', 'scope_of_work', 'start_date', 'end_date',
        'order_number', 'status', 'note', 'report',
    ];

    public function approverLabel(): string
    {
        return self::APPROVERS[$this->approver] ?? self::APPROVERS['governor'];
    }

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
