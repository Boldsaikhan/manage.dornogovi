<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Award extends Model
{
    public const CATEGORIES = [
        'state_high' => 'Төрийн дээд одон, медаль',
        'governor_honor' => 'АЗД шагнал — өргөмжлөл, жуух',
        'governor_leading' => 'АЗД шагнал — тэргүүний',
        'other' => 'Бусад шагнал',
    ];

    public const SUBTYPES = [
        'orgomjol' => 'Өргөмжлөл',
        'juukh' => 'Жуух',
        'team' => 'Тэргүүний хамт олон',
        'employee' => 'Тэргүүний ажилтан',
    ];

    /** Таб → зөвшөөрөгдөх subtype */
    public const CATEGORY_SUBTYPES = [
        'state_high' => [],
        'governor_honor' => ['orgomjol', 'juukh'],
        'governor_leading' => ['team', 'employee'],
        'other' => [],
    ];

    protected $fillable = [
        'category',
        'subtype',
        'year',
        'surname',
        'given_name',
        'register_no',
        'age',
        'gender',
        'nominated_award',
        'years_in_country',
        'years_in_sector',
        'award_date',
        'resolution_number',
        'position',
        'address',
        'last_award',
        'supporting_org',
        'presidential_letter',
        'award_name',
        'work_sector',
        'job_title',
        'total_years',
        'position_years',
        'order_ref',
        'award_note',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'age' => 'integer',
            'years_in_country' => 'integer',
            'years_in_sector' => 'integer',
            'total_years' => 'integer',
            'position_years' => 'integer',
            'award_date' => 'date',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? (string) $this->category;
    }

    public function subtypeLabel(): string
    {
        if (! $this->subtype) {
            return '';
        }

        return self::SUBTYPES[$this->subtype] ?? (string) $this->subtype;
    }

    public function fullName(): string
    {
        return trim(($this->surname ?? '').' '.($this->given_name ?? ''));
    }
}
