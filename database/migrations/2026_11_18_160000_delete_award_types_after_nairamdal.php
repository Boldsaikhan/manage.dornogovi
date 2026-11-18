<?php

use App\Models\AwardType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Нэг удаа: «Найрамдал медаль»-аас доош бүртгэгдсэн шагналын нэрүүдийг устгана.
 * Бүртгэлийн мөрөн дэх текст талбар хэвээр үлдэнэ.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('award_types')) {
            return;
        }

        AwardType::deleteAfter('Найрамдал медаль');
    }

    public function down(): void
    {
        // Өгөгдөл устгасан — буцаах боломжгүй.
    }
};
