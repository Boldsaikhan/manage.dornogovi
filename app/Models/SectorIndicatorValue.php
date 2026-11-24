<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function items(): HasMany
    {
        return $this->hasMany(SectorIndicatorValueItem::class, 'sector_indicator_value_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
