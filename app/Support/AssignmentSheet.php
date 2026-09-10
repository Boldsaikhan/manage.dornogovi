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

    /** Тестийн хооронд цээжилсэн нэрийг цэвэрлэнэ. */
    public static function forgetSigners(): void
    {
        self::$signerCache = [];
    }
}
