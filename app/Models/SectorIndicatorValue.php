<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Үзүүлэлт × жилийн багана огтлолцлын утга.
 */
class SectorIndicatorValue extends Model
{
    protected $fillable = ['sector_indicator_id', 'indicator_period_id', 'slot', 'value'];

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(SectorIndicator::class, 'sector_indicator_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(IndicatorPeriod::class, 'indicator_period_id');
    }
}
