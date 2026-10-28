<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Салбарын үзүүлэлт — нэгжийн суурь тоон мэдээллийн нэг мөр.
 */
class SectorIndicator extends Model
{
    protected $fillable = [
        'department_id',
        'unit_name',
        'title',
        'measure_unit',
        'target',
        'note',
        'sort_order',
        'created_by',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(SectorIndicatorValue::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
