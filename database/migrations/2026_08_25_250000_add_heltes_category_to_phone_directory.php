<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Нэрэндээ «хэлтэс» агуулсан бүлгүүдийг Хэлтэс ангилалд шилжүүлнэ.
        DB::table('phone_directory_entries')
            ->where(function ($q) {
                $q->where('org_name', 'like', '%хэлтэс%')
                    ->orWhere('org_name', 'like', '%Хэлтэс%')
                    ->orWhere('org_name', 'like', '%хэлтсийн%')
                    ->orWhere('org_name', 'like', '%Хэлтсийн%');
            })
            ->update(['category' => 'heltes']);
    }

    public function down(): void
    {
        DB::table('phone_directory_entries')
            ->where('category', 'heltes')
            ->update(['category' => 'baiguullaga']);
    }
};
