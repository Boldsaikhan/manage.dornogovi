<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnualLeave extends Model
{
    protected $fillable = [
        'user_id',
        'department_id',
        'scope',
        'org_name',
        'position',
        'person_name',
        'work_years',
        'entitled_days',
        'start_date',
        'end_date',
        'signer',
        'substitute_position',
        'substitute_name',
        'substitute_phone',
        'notice_text',
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

    /**
     * «Зөвшөөрсөн» гэдэг нь утасны жагсаалтаас сонгосон удирдах албан
     * тушаалтан байх ёстой — зөвхөн Аймгийн Засаг дарга, Засаг даргын
     * орлогч, Хэлтсийн дарга нар, Тамгын дарга нараас сонгоно.
     *
     * @return array<string, string> нэр => «нэр — албан тушаал»
     */
    public static function signerOptions(): array
    {
        return PhoneDirectoryEntry::leadershipOptions();
    }

    /**
     * Улсад ажилласан жилээр нэмэгдэл амралт олгоно — Хөдөлмөрийн тухай
     * хуулийн үндсэн 15 өдөрт ажилласан жилээс хамаарсан нэмэгдлийг
     * нэмнэ.
     *
     * @see resources/views/annual-leaves/notice.blade.php-д ашигладаг
     *      хүснэгттэй ижил шатлал (6-10 жил → +3, 11-15 → +5, гэх мэт).
     */
    public static function entitledDaysFor(?int $years): ?int
    {
        if ($years === null) {
            return null;
        }

        $extra = match (true) {
            $years <= 5 => 0,
            $years <= 10 => 3,
            $years <= 15 => 5,
            $years <= 20 => 7,
            $years <= 25 => 9,
            $years <= 31 => 11,
            default => 14,
        };

        return 15 + $extra;
    }

    /**
     * Эхлэх огноо + олгох хоногоор дуусах огноог тооцно — амралтын хоног
     * бүр ажлын өдөр (Даваа–Баасан) байх ёстой тул Бямба, Ням өдрийг
     * алгасна. Эхлэх өдөр амралтын эхний өдөр гэж тооцогдоно.
     */
    public static function endDateFor(string|CarbonInterface $start, int $days): ?string
    {
        if ($days < 1) {
            return null;
        }

        $date = $start instanceof CarbonInterface ? $start->copy() : Carbon::parse($start);
        $counted = 0;

        while (true) {
            if (! $date->isWeekend()) {
                $counted++;
            }

            if ($counted >= $days) {
                break;
            }

            $date->addDay();
        }

        return $date->toDateString();
    }
}
