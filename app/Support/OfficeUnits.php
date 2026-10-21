<?php

namespace App\Support;

use App\Models\Leave;
use App\Models\PhoneDirectoryEntry;
use Illuminate\Support\Facades\Schema;

/**
 * Тамгын газрын нэгжүүдийг зөв ангилна.
 *
 * Утасны жагсаалтад нэгж нь «байгууллага» болж бүртгэгдсэн байвал
 * чөлөөний бүртгэлд «Хэлтэс» багана хоосон үлдэж, байгууллагын нүдэнд
 * нэгжийн нэр бичигддэг. Нэрэндээ «хэлтэс» гэсэн үгтэй нэгжийг систем
 * өөрөө таньдаг — энд зөвхөн тийм үггүй нэгжүүдийг жагсаана.
 */
class OfficeUnits
{
    /** @var list<string> */
    public const KNOWN = [
        'Цэргийн штаб',
        'Хэвлэл мэдээллийн алба',
        'Санхүү / аж ахуй',
        'Теле камерын хяналтын дэд төв',
    ];

    /**
     * Нэрсийг нэгж болгож тэмдэглээд, байгаа бүртгэлүүдийг зөв байрлуулна.
     *
     * @param  list<string>  $names
     */
    public static function markAndRepair(array $names = self::KNOWN): void
    {
        $wanted = array_map(
            fn (string $name) => mb_strtolower(trim($name)),
            $names,
        );

        /*
         * Кирилл үсгийг SQL-ийн LOWER() зөв хөрвүүлдэггүй (SQLite) тул
         * нэрсийг PHP талд тулгана.
         */
        $matched = PhoneDirectoryEntry::query()
            ->whereNotNull('org_name')
            ->distinct()
            ->pluck('org_name')
            ->filter(fn (string $org) => in_array(mb_strtolower(trim($org)), $wanted, true))
            ->values();

        if ($matched->isNotEmpty()) {
            PhoneDirectoryEntry::query()
                ->whereIn('org_name', $matched->all())
                ->update(['category' => 'heltes']);
        }

        self::repairLeaves();
    }

    /**
     * «АЗДТГ» ангилалтай байгууллагын нэрээр бүртгэгдсэн чөлөөг зөв болгоно.
     */
    public static function repairLeaves(): void
    {
        if (! Schema::hasColumn('leaves', 'unit_name')) {
            return;
        }

        $units = PhoneDirectoryEntry::query()
            ->where('category', 'heltes')
            ->whereNotNull('org_name')
            ->distinct()
            ->pluck('org_name');

        foreach ($units as $unit) {
            Leave::query()
                ->where('org_name', $unit)
                ->update([
                    'org_name' => PhoneDirectoryEntry::PARENT_ORG,
                    'unit_name' => $unit,
                ]);
        }
    }
}
