<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «Ээлжийн амралт олгох тухай мэдэгдэл» хэвлэх маягтын гол өгүүлбэрийг
 * гараар засаж болохоор хадгална (гараар бичээгүй бол мэдээллээс автоматаар
 * бүрдэнэ).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('annual_leaves', function (Blueprint $table) {
            $table->text('notice_text')->nullable()->after('substitute_phone');
        });
    }

    public function down(): void
    {
        Schema::table('annual_leaves', function (Blueprint $table) {
            $table->dropColumn('notice_text');
        });
    }
};
