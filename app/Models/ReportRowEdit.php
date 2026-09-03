<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Тайлангийн нэг нүдний засвар. JSON эх өгөгдлийг дарж бичнэ.
 */
class ReportRowEdit extends Model
{
    /** «Хэлтэс» багана — харагдах хүрээг тогтоодог тусгай багана. */
    public const DEPARTMENT_COLUMN = 'department';

    protected $fillable = [
        'report_key',
        'row_index',
        'column_key',
        'value',
        'department_id',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'row_index' => 'integer',
            'department_id' => 'integer',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
