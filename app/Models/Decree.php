<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Decree extends Model
{
    public const CATEGORIES = [
        'blank' => 'Бланкны дугаар',
        'zahiramj' => 'Захирамжийн дугаар',
        'tushaal' => 'Тушаалын дугаар',
    ];

    public const KINDS = [
        'zahiramj_a' => 'Захирамж А',
        'zahiramj_b' => 'Захирамж Б',
        'tushaal_a' => 'Тушаал А',
        'tushaal_b' => 'Тушаал Б',
        'blank' => 'Хэвлэмэл хуудас',
    ];

    protected $fillable = [
        'category',
        'kind',
        'blank_number',
        'number',
        'title',
        'page_count',
        'effective_on',
        'attachment_name',
        'attachment_pages',
        'original_form',
        'file_index',
        'person_name',
        'qty_zahiramj',
        'qty_zahiramj_mn',
        'qty_tushaal',
        'qty_tushaal_mn',
        'qty_assignment',
        'qty_assignment_mn',
        'qty_council',
        'qty_council_mn',
        'num_zahiramj',
        'num_tushaal',
        'void_zahiramj',
        'void_tushaal',
        'issued_on',
        'body',
        'file_path',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'effective_on' => 'date',
            'page_count' => 'integer',
            'attachment_pages' => 'integer',
            'qty_zahiramj' => 'integer',
            'qty_zahiramj_mn' => 'integer',
            'qty_tushaal' => 'integer',
            'qty_tushaal_mn' => 'integer',
            'qty_assignment' => 'integer',
            'qty_assignment_mn' => 'integer',
            'qty_council' => 'integer',
            'qty_council_mn' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function kindLabel(): string
    {
        if ($this->category === 'blank' || $this->kind === 'blank') {
            return 'Бланк';
        }

        return self::KINDS[$this->kind] ?? ($this->kind ?: '—');
    }

    /**
     * Бүртгэлийн дугаарын угтвар — А/Б хэлбэр.
     *
     * Албан ёсны маягтад дугаарыг «А/01» гэж бичдэг. Өгөгдлийн санд зөвхөн
     * дугаарыг хадгалж, угтварыг төрлөөс нь гаргаж харуулна.
     */
    public function numberPrefix(): ?string
    {
        return match ($this->kind) {
            'zahiramj_a', 'tushaal_a' => 'А',
            'zahiramj_b', 'tushaal_b' => 'Б',
            default => null,
        };
    }

    /** Харуулах дугаар: «А/01». Дугааргүй бол хоосон. */
    public function numberDisplay(): string
    {
        $number = trim((string) $this->number);

        if ($number === '') {
            return '';
        }

        $prefix = $this->numberPrefix();

        return $prefix ? $prefix.'/'.$number : $number;
    }

    public function isBlankIssue(): bool
    {
        return $this->category === 'blank';
    }
}
