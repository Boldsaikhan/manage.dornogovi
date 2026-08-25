<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Decree extends Model
{
    public const CATEGORIES = [
        'blank' => 'Бланкны дугаар',
        'decree' => 'Захирамжийн дугаар',
    ];

    public const KINDS = [
        'zahiramj_a' => 'Захирамж А',
        'zahiramj_b' => 'Захирамж Б',
        'tushaal_a' => 'Тушаал А',
        'tushaal_b' => 'Тушаал Б',
    ];

    protected $fillable = [
        'category', 'kind', 'blank_number', 'number', 'title', 'issued_on', 'body', 'file_path', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function kindLabel(): string
    {
        if ($this->kind === 'blank' || $this->category === 'blank') {
            return 'Бланк';
        }

        return self::KINDS[$this->kind] ?? ($this->kind ?: '—');
    }
}
