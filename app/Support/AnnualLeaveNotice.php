<?php

namespace App\Support;

use App\Models\AnnualLeave;

/**
 * «Ээлжийн амралт олгох тухай мэдэгдэл» хэвлэх маягтын бичвэр, гарын үсгийн
 * мэдээлэл.
 */
class AnnualLeaveNotice
{
    /** Зөвшөөрсөн албан тушаалтны толгойн бичвэр — үргэлж ижил. */
    public const APPROVER_LINES = [
        'АЙМГИЙН ЗАСАГ ДАРГЫН ТАМГЫН ГАЗРЫН',
        'ДАРГЫН АЛБАН ҮҮРГИЙГ ТҮР ОРЛОН ГҮЙЦЭТГЭГЧ',
    ];

    /** Зөвшөөрсөн албан тушаалтны нэр — утасны жагсаалтаас ЗДТГ-ын даргаар нь олно. */
    public static function approverName(): string
    {
        return AssignmentSheet::signerName('chief');
    }

    /**
     * Мэдэгдлийн гол өгүүлбэрийг бүртгэлийн мэдээллээс бүрдүүлнэ.
     *
     * Гараар бичсэн бичвэр байвал түүнийг хэвээр нь авна.
     */
    public static function text(AnnualLeave $row): string
    {
        $typed = trim((string) $row->notice_text);

        if ($typed !== '') {
            return $typed;
        }

        $org = trim((string) $row->org_name);
        $position = trim((string) $row->position);
        $name = trim((string) $row->person_name);

        if ($name === '') {
            return '';
        }

        $parts = [];

        if ($org !== '') {
            $parts[] = MongolianCase::genitive($org);
        }

        if ($position !== '') {
            $parts[] = $position;
        }

        $parts[] = MongolianCase::genitive($name);

        $year = $row->start_date?->format('Y') ?? (string) now()->format('Y');
        $parts[] = $year.' оны ээлжийн амралтыг';

        if ($row->start_date) {
            $parts[] = MongolianOrdinal::format($row->start_date).' өдрөөс';
        }

        if ($row->end_date) {
            $parts[] = MongolianOrdinal::format($row->end_date).' өдрийг дуустал';
        }

        $parts[] = $row->entitled_days
            ? 'ажлын '.$row->entitled_days.' өдрөөр олгов.'
            : 'олгов.';

        return implode(' ', $parts);
    }
}
