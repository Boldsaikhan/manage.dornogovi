<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Нүдний утгын нэг мөр задаргаа.
 */
class SectorIndicatorValueItem extends Model
{
    protected $fillable = [
        'sector_indicator_value_id',
        'label',
        'amount',
        'sort_order',
    ];

    public function value(): BelongsTo
    {
        return $this->belongsTo(SectorIndicatorValue::class, 'sector_indicator_value_id');
    }
}
