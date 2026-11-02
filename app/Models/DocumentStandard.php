<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentStandard extends Model
{
    public const STATUS_IN_FORCE = 'in_force';

    public const STATUS_REFERENCED = 'referenced';

    public const STATUS_REPEALED = 'repealed';

    protected $fillable = [
        'category_id', 'slug', 'title', 'code', 'summary', 'body', 'file_path',
        'source_title', 'source_url', 'source_number', 'status',
        'effective_on', 'amended_on', 'repealed_on',
        'visual', 'examples', 'checks', 'is_official', 'archived_at', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'effective_on' => 'date',
            'amended_on' => 'date',
            'repealed_on' => 'date',
            'visual' => 'array',
            'examples' => 'array',
            'checks' => 'array',
            'is_official' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentStandardCategory::class, 'category_id');
    }

    public function applies(): bool
    {
        return $this->archived_at === null
            && in_array($this->status, [self::STATUS_IN_FORCE, self::STATUS_REFERENCED], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toCard(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'code' => $this->code,
            'summary' => $this->summary,
            'status' => $this->status,
            'category_id' => $this->category_id,
            'category_key' => $this->category?->key,
            'category_label' => $this->category?->label,
            'source_number' => $this->source_number,
            'effective_on' => optional($this->effective_on)->toDateString(),
            'amended_on' => optional($this->amended_on)->toDateString(),
            'repealed_on' => optional($this->repealed_on)->toDateString(),
            'is_official' => $this->is_official,
            'archived_at' => optional($this->archived_at)?->toDateTimeString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDetail(): array
    {
        return $this->toCard() + [
            'body' => $this->body,
            'source_title' => $this->source_title,
            'source_url' => $this->source_url,
            'visual' => $this->visual,
            'examples' => $this->examples ?? [],
            'sort_order' => $this->sort_order,
        ];
    }
}
