<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Эхлэх огноог заавал биш болгоно.
 *
 * Цаасан бүртгэлд огноо нь дээд мөртэйгээ нийлүүлсэн, эсвэл огт бичигдээгүй
 * мөр байдаг. Огноогүйн улмаас бүх импорт бүтэлгүйтэхээс сэргийлнэ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('travel_assignments', function (Blueprint $table) {
            $table->date('start_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('travel_assignments', function (Blueprint $table) {
            $table->date('start_date')->nullable(false)->change();
        });
    }
};
