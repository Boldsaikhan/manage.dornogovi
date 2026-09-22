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

        /*
         * Албан тушаалын бичвэрт байгууллагын нэр (Дорноговь аймаг дахь …)
         * ихэвчлэн аль хэдийн орсон байдаг тул хоёуланг зэрэг залгавал
         * давхардана. Тушаал байвал түүнийг ганцаараа, эс бөгөөс
         * байгууллагын нэрийг орлуулан хэрэглэнэ.
         */
        $parts = [];

        if ($position !== '') {
            $parts[] = $position;
        } elseif ($org !== '') {
            $parts[] = MongolianCase::genitive($org);
        }

        $parts[] = self::nameGenitive($name);

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

    /**
     * Товч нэрийн («Б.Чинзүрх») харьяалахын тийн ялгал.
     *
     * MongolianCase::genitive() эхний үсгийг л том болгодог тул цэгийн
     * дараах өгсөн нэрийг («Чинзүрх») биш өргөс үсгийг («Б») томруулж
     * алдаа гаргадаг («Б.чинзүрхийн»). Цэгийн дараах хэсгийг тусад нь
     * залгаж, угтварыг хэвээр нь үлдээнэ.
     */
    private static function nameGenitive(string $name): string
    {
        $dot = strrpos($name, '.');

        if ($dot === false) {
            return MongolianCase::genitive($name);
        }

        $prefix = mb_substr($name, 0, $dot + 1);
        $given = mb_substr($name, $dot + 1);

        return $given === '' ? $name : $prefix.MongolianCase::genitiveWord($given);
    }
}
