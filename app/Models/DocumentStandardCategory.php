<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentStandardCategory extends Model
{
    protected $fillable = [
        'parent_id', 'key', 'label', 'summary', 'sort_order',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function standards(): HasMany
    {
        return $this->hasMany(DocumentStandard::class, 'category_id');
    }
}
