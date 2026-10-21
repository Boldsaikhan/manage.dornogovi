<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contract extends Model
{
    protected $fillable = [
        'scope', 'number', 'title', 'counterparty', 'issued_on', 'copies', 'prepared_by', 'issued_by', 'note',
    ];

    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
        ];
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
