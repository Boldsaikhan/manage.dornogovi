<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Салбарын үзүүлэлтийн жилийн багана.
 *
 * `split` = true бол «хагас жил» ба «жилийн эцэс» гэж хоёр нүдтэй.
 */
class IndicatorPeriod extends Model
{
    public const SLOTS = ['half' => 'хагас жил', 'year' => 'жилийн эцэс'];

    protected $fillable = ['label', 'year', 'split', 'sort_order'];

    protected function casts(): array
    {
        return ['split' => 'boolean', 'year' => 'integer'];
    }

    public function values(): HasMany
    {
        return $this->hasMany(SectorIndicatorValue::class);
    }

    /** Энэ баганад ямар нүднүүд байх вэ. */
    public function slots(): array
    {
        return $this->split ? ['half', 'year'] : ['year'];
    }

    public static function ordered()
    {
        return static::query()->orderBy('sort_order')->orderBy('id');
    }
}
