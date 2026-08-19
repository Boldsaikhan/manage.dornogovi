<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('systems', function (Blueprint $table) {
            // false = нээлттэй систем, нэвтрэх мэдээлэл шаардахгүй (жишээ нь
            // нийтэд нээлттэй статик дашбоард). Ийм системд картан дээр
            // "Нээх" товч гарч, vault-ийн урсгал бүхэлдээ алгасагдана.
            $table->boolean('requires_login')->default(true)->after('login_extra_fields');
        });
    }

    public function down(): void
    {
        Schema::table('systems', function (Blueprint $table) {
            $table->dropColumn('requires_login');
        });
    }
};
