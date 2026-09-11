<?php

namespace App\Support;

use App\Models\PhoneDirectoryEntry;

/**
 * «ТОМИЛОЛТЫН УДИРДАМЖ» маягтын толгойн мэдээлэл.
 *
 * Бөглөх маягт (ResourceIndex дээрх цонх) болон хэвлэх хуудас хоёулаа
 * ижил «БАТЛАВ» толгой, гарын үсэг зурах хүнийг эндээс авна.
 */
class AssignmentSheet
{
    /** Батлах албан тушаалтны толгойн бичвэр. */
    public const LINES = [
        'governor' => [
            'ДОРНОГОВЬ АЙМГИЙН ЗАСАГ ДАРГА',
        ],
        'chief' => [
            'ДОРНОГОВЬ АЙМГИЙН ЗДТГ-ЫН',
            'ДАРГЫН АЛБАН ҮҮРГИЙГ ТҮР ОРЛОН',
            'ГҮЙЦЭТГЭГЧ',
        ],
    ];

    /** «Удирдлага» ангилал тэмдэглэгдээгүй үед албан тушаалаар нь олох түлхүүрүүд. */
    public const LEADER_POSITIONS = [
        'засаг дарга',
        'засаг даргын орлогч',
        'здтг-ын дарга',
        'тамгын газрын дарга',
    ];

    /** Төсвийн хүснэгтийн зардлын төрлүүд. */
    public const BUDGET_KINDS = ['Байр', 'Зам хоног', 'Түлш, шатахуун'];

    public static function approver(?string $approver): string
    {
        return array_key_exists((string) $approver, self::LINES) ? (string) $approver : 'governor';
    }

    /** @return list<string> */
    public static function lines(?string $approver): array
    {
        return self::LINES[self::approver($approver)];
    }

    /**
     * Гарын үсэг зурах хүний нэр — утасны жагсаалтаас албан тушаалаар нь олно.
     *
     * Бүртгэлийн хүснэгтийн мөр бүрт хэрэглэгддэг тул нэг хүсэлтийн дотор
     * дахин асуухгүйгээр цээжилнэ.
     *
     * @var array<string, string>
     */
    private static array $signerCache = [];

    public static function signerName(?string $approver): string
    {
        $key = self::approver($approver);

        if (array_key_exists($key, self::$signerCache)) {
            return self::$signerCache[$key];
        }

        $needle = match ($key) {
            'chief' => 'здтг-ын дарга',
            default => 'засаг дарга',
        };

        /*
         * Албан тушаалыг том/жижиг үсгээр нь ялгалгүй харьцуулна. Мэдээллийн
         * сангийн LOWER() кирилл үсгийг зөв жижигрүүлдэггүй тул PHP талд
         * шүүнэ.
         */
        $entry = PhoneDirectoryEntry::query()
            ->whereNotNull('position')
            ->orderBy('org_order')
            ->orderBy('sort_order')
            ->get(['person_name', 'position'])
            ->first(fn (PhoneDirectoryEntry $row) => str_contains(
                mb_strtolower((string) $row->position),
                $needle,
            ));

        return self::$signerCache[$key] = trim((string) ($entry->person_name ?? ''));
    }

    /**
     * Батлах эрхтэй удирдах албан хаагчид.
     *
     * Утасны жагсаалтын «Удирдлага» ангилалд бүртгэлтэй хүмүүс — Засаг
     * дарга, орлогч, ЗДТГ-ын дарга. Жагсаалт дээр нэр нь өөрчлөгдвөл энд
     * шууд тусна.
     *
     * @return array<string, string> нэр => нэр (албан тушаалтай тайлбар)
     */
    public static function leaders(): array
    {
        if (self::$leaderCache !== null) {
            return self::$leaderCache;
        }

        $entries = PhoneDirectoryEntry::query()
            ->orderBy('org_order')
            ->orderBy('sort_order')
            ->get(['person_name', 'position', 'category']);

        $chosen = $entries->where('category', 'udirdlaga');

        /*
         * Утасны жагсаалт дээр «Удирдлага» ангилал тэмдэглэгдээгүй байвал
         * албан тушаалаар нь олно — Засаг дарга, орлогч, ЗДТГ-ын дарга.
         */
        if ($chosen->isEmpty()) {
            $chosen = $entries->filter(function (PhoneDirectoryEntry $row) {
                $position = mb_strtolower(trim((string) $row->position));

                foreach (self::LEADER_POSITIONS as $needle) {
                    if (str_contains($position, $needle)) {
                        return true;
                    }
                }

                return false;
            });
        }

        $leaders = [];

        foreach ($chosen as $row) {
            $name = trim((string) $row->person_name);
            $position = trim((string) $row->position);

            if ($name === '' || isset($leaders[$name])) {
                continue;
            }

            $leaders[$name] = $position === '' ? $name : $name.' — '.$position;
        }

        return self::$leaderCache = $leaders;
    }

    /** @var array<string, string>|null */
    private static ?array $leaderCache = null;

    /**
     * Батлах хүн бүрийн албан тушаал, толгойн мөрүүд.
     *
     * Сонгосон хүн нь табын үндсэн батлагч бол цаасан маягтын яг тэр
     * бичвэрийг хэрэглэнэ (жишээ нь «ДАРГЫН АЛБАН ҮҮРГИЙГ ТҮР ОРЛОН
     * ГҮЙЦЭТГЭГЧ»). Өөр хүн бол албан тушаалаас нь мөрийг үүсгэнэ.
     *
     * @return list<array{name: string, position: string, lines: list<string>}>
     */
    public static function leaderOptions(?string $approver): array
    {
        $default = self::signerName($approver);

        return PhoneDirectoryEntry::query()
            ->orderBy('org_order')
            ->orderBy('sort_order')
            ->get(['person_name', 'position', 'category'])
            ->filter(fn (PhoneDirectoryEntry $row) => isset(self::leaders()[trim((string) $row->person_name)]))
            ->unique(fn (PhoneDirectoryEntry $row) => trim((string) $row->person_name))
            ->map(function (PhoneDirectoryEntry $row) use ($approver, $default) {
                $name = trim((string) $row->person_name);
                $position = trim((string) $row->position);

                return [
                    'name' => $name,
                    'position' => $position,
                    'lines' => $name === $default
                        ? self::lines($approver)
                        : [self::positionLine($position)],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Албан тушаалаас толгойн мөр үүсгэнэ.
     *
     * «Засаг даргын орлогч» → «ДОРНОГОВЬ АЙМГИЙН ЗАСАГ ДАРГЫН ОРЛОГЧ».
     */
    public static function positionLine(string $position): string
    {
        $position = trim($position);

        if ($position === '') {
            return self::LINES['governor'][0];
        }

        // «Аймгийн Засаг дарга» гэх мэт давхардлыг арилгана.
        $position = preg_replace('/^аймгийн\s+/iu', '', $position) ?? $position;

        $upper = mb_strtoupper($position);

        return str_contains($upper, 'ДОРНОГОВЬ') ? $upper : 'ДОРНОГОВЬ АЙМГИЙН '.$upper;
    }

    /** Сонгосон батлагчийн толгойн мөрүүд. */
    public static function linesFor(?string $approver, ?string $chosenName): array
    {
        $name = trim((string) $chosenName);

        if ($name === '') {
            return self::lines($approver);
        }

        foreach (self::leaderOptions($approver) as $option) {
            if ($option['name'] === $name) {
                return $option['lines'];
            }
        }

        return self::lines($approver);
    }

    /** Тестийн хооронд цээжилсэн нэрийг цэвэрлэнэ. */
    public static function forgetSigners(): void
    {
        self::$signerCache = [];
        self::$leaderCache = null;
    }
}
