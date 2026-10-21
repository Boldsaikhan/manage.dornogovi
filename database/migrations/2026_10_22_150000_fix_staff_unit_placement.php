<?php

use App\Support\OfficeUnits;
use Illuminate\Database\Migrations\Migration;

/**
 * Тамгын газрын нэгжийг «байгууллага» биш «хэлтэс» болгож засна.
 *
 * «Цэргийн штаб» зэрэг нэгжид «хэлтэс» гэсэн үг байдаггүй тул утасны
 * жагсаалтад байгууллага гэж ангилагдаж, чөлөөний бүртгэлийн «Хэлтэс»
 * багана хоосон үлдэж байв.
 */
return new class extends Migration
{
    public function up(): void
    {
        OfficeUnits::markAndRepair(['Цэргийн штаб']);
    }

    public function down(): void
    {
        // Буцаахгүй — ангилал нь утасны жагсаалтаас гараар засагдана.
    }
};
