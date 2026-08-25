<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Утасны жагсаалтын «хэлтэс» ангиллыг цуцлана —
     * хэлтэс нь АЗДТГ-ын албан хаагчдын нэгжид хамаарна.
     */
    public function up(): void
    {
        DB::table('phone_directory_entries')
            ->where('category', 'heltes')
            ->update(['category' => null]);
    }

    public function down(): void
    {
        DB::table('phone_directory_entries')
            ->whereNull('category')
            ->where(function ($q) {
                $q->where('org_name', 'like', '%хэлтэс%')
                    ->orWhere('org_name', 'like', '%Хэлтэс%')
                    ->orWhere('org_name', 'like', '%хэлтсийн%')
                    ->orWhere('org_name', 'like', '%Хэлтсийн%');
            })
            ->update(['category' => 'heltes']);
    }
};
