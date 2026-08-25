<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('org_employee_phones', function (Blueprint $table) {
            $table->string('unit_type', 32)->nullable()->after('unit');
        });

        // Нэрэндээ «хэлтэс» агуулсан нэгжийг автоматаар төрөл «хэлтэс» болгоно.
        DB::table('org_employee_phones')
            ->where(function ($q) {
                $q->where('unit', 'like', '%хэлтэс%')
                    ->orWhere('unit', 'like', '%Хэлтэс%')
                    ->orWhere('unit', 'like', '%хэлтсийн%')
                    ->orWhere('unit', 'like', '%Хэлтсийн%');
            })
            ->update(['unit_type' => 'heltes']);
    }

    public function down(): void
    {
        Schema::table('org_employee_phones', function (Blueprint $table) {
            $table->dropColumn('unit_type');
        });
    }
};
