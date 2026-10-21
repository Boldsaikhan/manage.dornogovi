<?php

use App\Support\OfficeUnits;
use Illuminate\Database\Migrations\Migration;

/**
 * Нэрэндээ «хэлтэс» гэсэн үггүй Тамгын газрын нэгжүүдийг зөв ангилна.
 *
 * «Хэвлэл мэдээллийн алба», «Санхүү / аж ахуй», «Теле камерын хяналтын
 * дэд төв» зэрэг нэгж байгууллага гэж ангилагдсан тул чөлөөний
 * бүртгэлд хэлтсийн нүд хоосон үлдэж байв.
 */
return new class extends Migration
{
    public function up(): void
    {
        OfficeUnits::markAndRepair();
    }

    public function down(): void
    {
        // Буцаахгүй — ангиллыг утасны жагсаалтаас гараар засна.
    }
};
