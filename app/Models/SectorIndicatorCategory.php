<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Салбарын үзүүлэлтийн ангилал — нэгжийн хүснэгтийг бүлэглэнэ.
 */
class SectorIndicatorCategory extends Model
{
    protected $fillable = [
        'department_id',
        'name',
        'sort_order',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function indicators(): HasMany
    {
        return $this->hasMany(SectorIndicator::class, 'category_id');
    }
}
