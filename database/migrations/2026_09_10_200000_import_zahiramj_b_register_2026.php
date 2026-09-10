<?php

use App\Support\ZahiramjBRegister2026;
use Illuminate\Database\Migrations\Migration;

/**
 * 2026 оны «Б» захирамжийн бүртгэлийг цаасан хувиас оруулав (Б/01 – Б/39).
 *
 * Байгаа мөрийг хөндөхгүй — зөвхөн дутуу дугаарыг нэмнэ.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        ZahiramjBRegister2026::fillMissing();
    }

    public function down(): void
    {
        // Устгахгүй — аль мөр нь энэ импортоор орсныг ялгах боломжгүй.
    }
};
