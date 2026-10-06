<?php

namespace App\Support;

use App\Models\AnnualLeave;
use App\Models\PhoneDirectoryEntry;

/**
 * «Ээлжийн амралт олгох тухай мэдэгдэл» хэвлэх маягтын бичвэр, гарын үсгийн
 * мэдээлэл.
 */
class AnnualLeaveNotice
{
    /** Хэн ч сонгоогүй үед хэрэглэх урьдач толгойн бичвэр. */
    public const DEFAULT_APPROVER_LINES = [
        'АЙМГИЙН ЗАСАГ ДАРГЫН ТАМГЫН ГАЗРЫН',
        'ДАРГЫН АЛБАН ҮҮРГИЙГ ТҮР ОРЛОН ГҮЙЦЭТГЭГЧ',
    ];

    /**
     * Зөвшөөрсөн албан тушаалтны толгойн бичвэр — «Зөвшөөрсөн» баганад
     * сонгосон хүний өөрийнх нь албан тушаалаар, үгүй бол урьдач
     * (ЗДТГ-ын дарга) бичвэрээр үүсгэнэ.
     *
     * @return list<string>
     */
    public static function approverLines(AnnualLeave $row): array
    {
        $name = trim((string) $row->signer);

        if ($name === '') {
            return self::DEFAULT_APPROVER_LINES;
        }

        $position = trim((string) (PhoneDirectoryEntry::positionFor($name) ?? ''));

        return $position !== '' ? [mb_strtoupper($position)] : self::DEFAULT_APPROVER_LINES;
    }

    /**
     * Зөвшөөрсөн албан тушаалтны нэр — том үсгээр (сонгоогүй үед ЗДТГ-ын
     * даргаар нь олно).
     */
    public static function approverName(AnnualLeave $row): string
    {
        $name = trim((string) $row->signer);

        return mb_strtoupper($name !== '' ? $name : AssignmentSheet::signerName('chief'));
    }

    /**
     * Амралт эдэлж буй хүний гарын үсгийн мөрөнд гарах албан тушаал —
     * утасны жагсаалтад байгаагаар нь, өөрчлөлтгүйгээр харуулна.
     */
    public static function ownPositionLine(AnnualLeave $row): string
    {
        $position = trim((string) $row->position);

        return $position !== '' ? $position : 'Албан хаагч';
    }

    /**
     * Мэдэгдлийн хуудасны БҮХ агуулга (өгүүлбэр + Зөвшөөрсөн хэсэг +
     * өөрийн гарын үсгийн мөр) нэг доороо — хуудсан дээр хоёр (өгүүлбэр,
     * гарын үсэг) хэсэгтэйгээр боловч нэг л Хадгалах/Дахин үүсгэх товчоор
     * чөлөөтэй засварлана. Хэрэглэгч гараар засварласан бол (notice_text)
     * тэрийг нь бүхэлд нь, эс бөгөөс бүртгэлийн мэдээллээс автоматаар
     * нэгтгэж үүсгэнэ.
     */
    public static function fullText(AnnualLeave $row): string
    {
        $override = trim((string) $row->notice_text);

        if ($override !== '') {
            return $override;
        }

        return self::bodyPart($row)."\n\n".self::signaturesPart($row);
    }

    /**
     * Мэдэгдлийн өгүүлбэр хэсэг — 2 талдаа тэнцүүлсэн (justify), урд
     * талдаа мөр шилжсэн таб зайтай харагдана.
     */
    public static function bodyPart(AnnualLeave $row): string
    {
        $split = self::splitOverride($row);

        return $split !== null ? $split[0] : self::text($row);
    }

    /**
     * «Зөвшөөрсөн» хэсэг + өөрийн гарын үсгийн мөр — голлуулж харуулна.
     * Тушаал, нэрийн хооронд зай байна (тушаал ба нэр нэг мөрөнд үлдэхийн
     * тулд таб ("\t") бус тасрашгүй зай (NBSP) хэрэглэнэ — таб тэмдэгт
     * тэмдэгтийн өргөнөөс хамаарч урт тушаалтай мөрийг дараагийн мөрөнд
     * хийсвэрлэн шилжүүлж байсан).
     */
    public static function signaturesPart(AnnualLeave $row): string
    {
        $split = self::splitOverride($row);

        if ($split !== null) {
            return $split[1];
        }

        $tab = str_repeat("\u{00A0}", 6);

        return implode("\n", [
            'ЗӨВШӨӨРСӨН:',
            implode(' ', self::approverLines($row)).$tab.self::approverName($row),
            '',
            mb_strtoupper(self::ownPositionLine($row)).$tab.mb_strtoupper(trim((string) $row->person_name)),
        ]);
    }

    /**
     * Хадгалсан бичвэрийг эхний хоосон мөрөөр нь өгүүлбэр, гарын үсгийн
     * хэсэг болгон хуваана (засварлах хоёр талбарт тус тусад нь харуулахын
     * тулд).
     *
     * @return array{0: string, 1: string}|null
     */
    private static function splitOverride(AnnualLeave $row): ?array
    {
        $override = trim((string) $row->notice_text, "\n");

        if ($override === '') {
            return null;
        }

        $pieces = preg_split('/\n[ \t]*\n/', $override, 2);

        return [trim($pieces[0] ?? ''), trim($pieces[1] ?? '')];
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
