<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contract extends Model
{
    protected $fillable = [
        'scope', 'number', 'title', 'counterparty', 'issued_on', 'copies', 'prepared_by', 'approved_by_name', 'signed_by', 'issued_by', 'note',
        'file_path', 'file_name',
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
