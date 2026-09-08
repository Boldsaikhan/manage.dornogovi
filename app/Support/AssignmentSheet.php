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
        'deputy' => [
            'ДОРНОГОВЬ АЙМГИЙН ЗАСАГ',
            'ДАРГЫН ОРЛОГЧ',
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
     */
    public static function signerName(?string $approver): string
    {
        $needle = match (self::approver($approver)) {
            'deputy' => 'засаг даргын орлогч',
            'chief' => 'здтг-ын дарга',
            default => 'засаг дарга',
        };

        $entry = PhoneDirectoryEntry::query()
            ->whereRaw('LOWER(position) LIKE ?', ['%'.$needle.'%'])
            ->orderBy('org_order')
            ->orderBy('sort_order')
            ->first();

        return trim((string) ($entry->person_name ?? ''));
    }
}
