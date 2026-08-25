<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Утасны жагсаалтын бүх «хэлтэс» төлвийг сонголтгүй (null) болгоно.
     */
    public function up(): void
    {
        // Багана NOT NULL бол эхлээд nullable болгоно (эс бөгөөс update унана).
        Schema::table('phone_directory_entries', function (Blueprint $table) {
            $table->string('category', 16)->nullable()->default(null)->change();
        });

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
