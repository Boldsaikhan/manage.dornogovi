<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Утасны жагсаалтын бүх «хэлтэс» төлвийг сонголтгүй (null) болгоно.
     */
    public function up(): void
    {
        DB::table('phone_directory_entries')
            ->where('category', 'heltes')
            ->update(['category' => null]);

        // Нэрэндээ хэлтэс агуулсан боловч өөр төлөвтэй үлдсэн бүлгүүдийг ч цэвэрлэнэ.
        DB::table('phone_directory_entries')
            ->where(function ($q) {
                $q->where('org_name', 'like', '%хэлтэс%')
                    ->orWhere('org_name', 'like', '%Хэлтэс%')
                    ->orWhere('org_name', 'like', '%хэлтсийн%')
                    ->orWhere('org_name', 'like', '%Хэлтсийн%');
            })
            ->where('category', 'heltes')
            ->update(['category' => null]);
    }

    public function down(): void
    {
        // Буцаахгүй — хэлтэс ангилал дахин ашиглагдахгүй.
    }
};
