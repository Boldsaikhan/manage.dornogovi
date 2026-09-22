<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Leave extends Model
{
    public const TYPES = [
        'tsalintai' => 'Цалинтай',
        'tsalingui' => 'Цалингүй',
        'eeljiin' => 'Ээлжийн амралтаас',
    ];

    /**
     * «Орлон гарын үсэг зурсан» гэдэг нь одоо утасны жагсаалтаас сонгосон
     * бодит албан хаагч байх ёстой — зөвхөн Аймгийн Засаг дарга, Засаг
     * даргын орлогч, Хэлтсийн дарга нар, Тамгын дарга нараас сонгоно.
     *
     * @return array<string, string> нэр => «нэр — албан тушаал»
     */
    public static function signerOptions(): array
    {
        $entries = PhoneDirectoryEntry::query()
            ->orderBy('org_order')
            ->orderBy('sort_order')
            ->get(['person_name', 'position']);

        $options = [];

        foreach ($entries as $row) {
            $position = mb_strtolower(trim((string) $row->position));

            if ($position === '') {
                continue;
            }

            $isLeaderRole = (str_contains($position, 'засаг') && str_contains($position, 'дарг'))
                || (str_contains($position, 'хэлтс') && str_contains($position, 'дарг'))
                || str_contains($position, 'тамгын');

            if (! $isLeaderRole) {
                continue;
            }

            $name = trim((string) $row->person_name);
            $original = trim((string) $row->position);

            if ($name === '' || isset($options[$name])) {
                continue;
            }

            $options[$name] = $original !== '' ? $name.' — '.$original : $name;
        }

        return $options;
    }

    protected $fillable = [
        'user_id',
        'department_id',
        'scope',
        'org_name',
        'person_name',
        'slip_number',
        'signer',
        'type',
        'start_date',
        'end_date',
        'days',
        'reason',
        'status',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /** Гарын үсэг зурах хүний албан тушаал — үнэмлэхэд том үсгээр гарна. */
    public function signerTitle(): string
    {
        $name = trim((string) $this->signer);

        if ($name === '') {
            return '';
        }

        $position = trim((string) (PhoneDirectoryEntry::positionFor($name) ?? ''));

        return $position !== '' ? mb_strtoupper($position) : '';
    }
}
