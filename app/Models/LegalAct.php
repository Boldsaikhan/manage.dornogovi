<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Эрх зүйн акт, тогтоол.
 *
 * Албан хаагч бүр өөрийн салбарт хамаарах хууль, тогтоол, журмыг
 * legalinfo.mn болон бусад эх сурвалжаас нэгтгэн бүртгэнэ.
 */
class LegalAct extends Model
{
    /** Үндсэн эх сурвалж — шинэ мөр нэмэхэд заавар болгож харуулна. */
    public const SOURCE_URL = 'https://legalinfo.mn/mn';

    protected $fillable = [
        'scope',
        'title',
        'number',
        // Салбарын үзүүлэлт — суурь тоон мэдээлэл.
        'measure_unit',
        'base_year',
        'base_value',
        'approved_on',
        'effective_on',
        'unit_name',
        'source_url',
        'note',
        'file_path',
        'file_name',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'approved_on' => 'date',
            'effective_on' => 'date',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
