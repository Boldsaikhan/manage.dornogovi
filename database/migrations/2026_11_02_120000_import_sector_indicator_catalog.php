<?php

use App\Support\SectorIndicatorCatalog;
use Illuminate\Database\Migrations\Migration;

/**
 * Төрийн захиргааны удирдлагын хэлтсийн салбарын үзүүлэлтийг хүснэгтээс оруулна.
 *
 * Нэгж олдохгүй бол алгасна. Дахин ажиллуулахад мөрийг нэрээр нь олж нөхнө.
 */
return new class extends Migration
{
    public function up(): void
    {
        SectorIndicatorCatalog::import();
    }

    public function down(): void
    {
        // Оруулсан тоон мэдээллийг буцааж устгахгүй.
    }
};
