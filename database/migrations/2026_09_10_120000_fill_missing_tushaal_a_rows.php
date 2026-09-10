<?php

use App\Support\TushaalARegister2026;
use Illuminate\Database\Migrations\Migration;

/**
 * «А» тушаалын бүртгэлийн дутуу мөрүүдийг нөхнө.
 *
 * Эхний импортын дараа зарим мөр (жишээ нь А/71) орж чадаагүй байсан.
 * Байгаа мөрийг хөндөхгүй — зөвхөн дутууг нь нэмнэ.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        TushaalARegister2026::fillMissing();
    }

    public function down(): void
    {
        // Устгахгүй — аль мөр нь энэ migration-аар орсныг ялгах боломжгүй.
    }
};
