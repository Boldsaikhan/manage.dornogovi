<?php

use App\Models\Leave;
use App\Models\PhoneDirectoryEntry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Тамгын газрын нэгжийг «байгууллага» биш «хэлтэс» болгож засна.
 *
 * «Цэргийн штаб» зэрэг нэгжид «хэлтэс» гэсэн үг байдаггүй тул утасны
 * жагсаалтад байгууллага гэж ангилагдаж, чөлөөний бүртгэлийн «Хэлтэс»
 * багана хоосон үлдэж байв.
 */
return new class extends Migration
{
    /** Нэрэндээ «хэлтэс» гэсэн үггүй ч Тамгын газрын нэгж. */
    private const UNITS = ['Цэргийн штаб'];

    public function up(): void
    {
        foreach (self::UNITS as $name) {
            PhoneDirectoryEntry::query()
                ->whereRaw('LOWER(TRIM(org_name)) = ?', [mb_strtolower($name)])
                ->update(['category' => 'heltes']);
        }

        if (! Schema::hasColumn('leaves', 'unit_name')) {
            return;
        }

        // Нэгж гэж тэмдэглэгдсэн бүх байгууллагын мөрийг зөв байрлуулна.
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

    public function down(): void
    {
        // Буцаахгүй — ангилал нь утасны жагсаалтаас гараар засагдана.
    }
};
