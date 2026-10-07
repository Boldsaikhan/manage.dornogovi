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
     * бичвэрийн өгүүлбэртэй адил («Засаг дарга» мэт богино тушаал
     * байгууллага/сумынхаа нэрийг агуулаагүй бол урдаа нэмж залгана).
     */
    public static function ownPositionLine(AnnualLeave $row): string
    {
        $position = trim((string) $row->position);

        if ($position === '') {
            return 'Албан хаагч';
        }

        $org = self::normalizeShoutyOrgName(trim((string) $row->org_name));

        if ($org !== '' && ! self::orgAlreadyNamedIn($position, $org)) {
            return MongolianCase::genitive($org).' '.$position;
        }

        return $position;
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
     * «Зөвшөөрсөн» хэсэг + өөрийн гарын үсгийн мөр — голлуулж харуулна
     * (хадгалах, хуучин бичвэрээс хуваахад хэрэглэх хавтгай хэлбэр нь).
     * Тушаал, нэрийг нэг таб ("\t") тэмдэгтээр тусгаарлана — харагдах
     * байрлалыг grid (бусад багана жигд) хариуцдаг тул энэ нь зөвхөн
     * утгыг тусгаарлах тэмдэг.
     */
    public static function signaturesPart(AnnualLeave $row): string
    {
        $split = self::splitOverride($row);

        if ($split !== null) {
            return $split[1];
        }

        $fields = self::signatureFields($row);

        return implode("\n", [
            'ЗӨВШӨӨРСӨН:',
            $fields['approverTitle']."\t".$fields['approverName'],
            '',
            $fields['ownTitle']."\t".$fields['ownName'],
        ]);
    }

    /**
     * Зөвшөөрсөн ба өөрийн гарын үсгийн мөрүүдийг (тушаал, нэр тус тусдаа)
     * grid байрлалд харуулахын тулд задлана — тушаалын багана баруун
     * талдаа, нэрийн багана мөн баруун талдаа нэг шугаманд зэрэгцэнэ.
     * Хадгалсан (notice_text) бичвэр байвал түүнээс, үгүй бол бүртгэлийн
     * мэдээллээс шууд бүрдүүлнэ.
     *
     * @return array{approverTitle: string, approverName: string, ownTitle: string, ownName: string}
     */
    public static function signatureFields(AnnualLeave $row): array
    {
        $split = self::splitOverride($row);

        if ($split === null) {
            return [
                'approverTitle' => implode(' ', self::approverLines($row)),
                'approverName' => self::approverName($row),
                'ownTitle' => mb_strtoupper(self::ownPositionLine($row)),
                'ownName' => mb_strtoupper(trim((string) $row->person_name)),
            ];
        }

        // Эхний мөр («ЗӨВШӨӨРСӨН:») толгой — задлахад хэрэггүй тул хасна.
        $rest = preg_replace('/^[^\n]*\n?/u', '', trim($split[1], "\n"), 1);

        // Хуучин хадгалсан өгөгдөлд тушаал, нэр тус тусдаа мөрөнд байж
        // болох тул (одоогийн нэг мөрт tab-аар тусгаарласнаас ялгаатай)
        // блок тус бүрийг эхний хоосон мөрөөр нь ялгаад аль ч хэлбэрийг
        // таньдаг байхаар задална.
        $pieces = preg_split('/\n[ \t]*\n/', trim((string) $rest), 2);
        $approver = self::splitSignatureBlock($pieces[0] ?? '');
        $own = self::splitSignatureBlock($pieces[1] ?? '');

        return [
            'approverTitle' => $approver[0],
            'approverName' => $approver[1],
            'ownTitle' => $own[0],
            'ownName' => $own[1],
        ];
    }

    /**
     * Нэг (тушаал+нэр нэг мөрт, одоогийн хэлбэр) эсвэл хоёр (тушаал, нэр
     * тус тусдаа мөрт, хуучин хэлбэр) мөр бүхий блокийг тушаал, нэр болгон
     * хуваана.
     *
     * @return array{0: string, 1: string}
     */
    private static function splitSignatureBlock(string $block): array
    {
        $lines = array_values(array_filter(
            preg_split('/\n/', trim($block)),
            fn (string $line) => trim($line) !== '',
        ));

        if (count($lines) >= 2) {
            return [trim($lines[0]), trim($lines[1])];
        }

        return self::splitSignatureLine($lines[0] ?? '');
    }

    /**
     * Нэг мөрийг тушаал, нэр болгон хуваана — хооронд нь таб, тасрашгүй
     * зай (хуучнаар хадгалсан өгөгдөлд) эсвэл цуваа энгийн зай орсон
     * байж болно тул сүүлийн ийм тусгаарлагчаар нь, эцсийн үгийн (нэрийн)
     * өмнө нь хуваана.
     *
     * @return array{0: string, 1: string}
     */
    private static function splitSignatureLine(string $line): array
    {
        $line = trim($line);

        if ($line === '') {
            return ['', ''];
        }

        $parts = preg_split('/[\t\x{00A0}]+(?=\S+$)/u', $line, 2);

        if (count($parts) < 2) {
            $parts = preg_split('/ {2,}(?=\S+$)/u', $line, 2);
        }

        return [trim($parts[0] ?? ''), trim($parts[1] ?? '')];
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

        $org = self::normalizeShoutyOrgName(trim((string) $row->org_name));
        $position = trim((string) $row->position);
        $name = trim((string) $row->person_name);

        if ($name === '') {
            return '';
        }

        /*
         * Зарим тушаалын бичвэрт байгууллагын нэр (Дорноговь аймаг дахь …)
         * аль хэдийн орсон байдаг тул хоёуланг зэрэг залгавал давхардана —
         * ийм үед тушаалыг ганцаараа нь хэрэглэнэ. Харин «Засаг дарга» мэт
         * богино тушаал байгууллагын (сумын) нэрийг агуулаагүй тул урдаа
         * байгууллагын нэрийг (харьяалахын тийн ялгалаар) нэмж залгана.
         */
        $parts = [];

        if ($position !== '') {
            if ($org !== '' && ! self::orgAlreadyNamedIn($position, $org)) {
                $parts[] = MongolianCase::genitive($org);
            }

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

    /**
     * Байгууллагын нэрийг ТОМ ҮСГЭЭР бүхэлд нь бичсэн байвал (жишээ нь
     * «ЗАМЫН-ҮҮД СУМ») жижиг үсэг рүү буулгана — эс бөгөөс
     * MongolianCase::genitiveWord() үүнийг товчлол гэж андуурч
     * («СУМ-ын» гэх мэт зурааст холбоос) буруу залгадаг байв. Зөвхөн
     * бүхэлдээ том үсэгтэй үед л хөндөнө — хэвийн бичсэн нэрийг
     * өөрчлөхгүй.
     */
    private static function normalizeShoutyOrgName(string $org): string
    {
        if ($org === '' || $org !== mb_strtoupper($org)) {
            return $org;
        }

        $lower = mb_strtolower($org);

        /*
         * Зөвхөн эхний үсэг болон зураас доторх (Замын-Үүд мэт) үсгийг
         * томруулна — дундах үгсийг (сум, аймаг гэх мэт ерөнхий нэр)
         * жижиг хэвээр үлдээнэ, учир нь зөвхөн өгүүлбэрийн эхлэл болон
         * хос нэрийн зурааст хэсэгт л том үсэг хэрэглэдэг.
         */
        return preg_replace_callback(
            '/(^|-)(\p{L})/u',
            fn (array $m) => $m[1].mb_strtoupper($m[2]),
            $lower,
        ) ?? $lower;
    }

    /**
     * Тушаалын бичвэрт байгууллагын нэр аль хэдийн нэрлэгдсэн эсэхийг
     * шалгана — жишээ нь «Дорноговь аймаг дахь Төрийн албаны салбар
     * зөвлөлийн нарийн бичгийн дарга» гэх мэт урт тушаал байгууллагынхаа
     * нэрийг аль хэдийн агуулдаг, харин «Засаг дарга» мэт богино тушаал
     * агуулдаггүй. Байгууллагын нэрнээс ерөнхий (аймаг, сум, алба, газар
     * гэх мэт) үгсийг хассаны дараа үлдэх онцлог үгсийн аль нэг нь
     * тушаалын бичвэрт байвал нэрлэгдсэн гэж үзнэ.
     */
    private static function orgAlreadyNamedIn(string $position, string $org): bool
    {
        $words = preg_split('/[\s,.]+/u', $org, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($words as $word) {
            if (mb_strlen($word) < 3 || self::isGenericOrgWord($word)) {
                continue;
            }

            if (mb_stripos($position, $word) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Байгууллагын төрлийг заасан ерөнхий (онцлог бус) үг эсэхийг шалгана
     * — «захиргаа», «хэлтэс», «алба» гэх мэт үгс олон янзын байгууллагад
     * давтагддаг тул тухайн байгууллагыг тодорхойлж чадахгүй. Ийм үг
     * тушаалын бичвэрт тохиолдлоо гээд (жишээ нь «Захиргааны дарга» дотор
     * «захиргаа») байгууллагын нэрийг бүхэлд нь нэрлэгдсэн гэж андуурахгүй
     * байхын тулд MongolianCase-ийн түгээмэл үгийн жагсаалтыг (мөн тэдгээрийн
     * харьяалахын тийн ялгалтай хэлбэрийг) ашиглана — тусад нь дутуу
     * жагсаалт хөтлөхгүй.
     */
    private static function isGenericOrgWord(string $word): bool
    {
        static $extra = ['дахь'];

        $lower = mb_strtolower($word);
        $exceptions = MongolianCase::exceptions();

        if (isset($exceptions[$lower]) || in_array($lower, $exceptions, true) || in_array($lower, $extra, true)) {
            return true;
        }

        foreach (['гийн', 'гын', 'ийн', 'ний', 'ын', 'ны', 'ий', 'ы'] as $ending) {
            if (mb_strlen($lower) <= mb_strlen($ending)) {
                continue;
            }

            if (str_ends_with($lower, $ending) && isset($exceptions[mb_substr($lower, 0, -mb_strlen($ending))])) {
                return true;
            }
        }

        return false;
    }
}
