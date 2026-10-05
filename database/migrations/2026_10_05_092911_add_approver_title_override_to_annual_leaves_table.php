<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    /**
     * «Зөвшөөрсөн» хэсгийн толгой бичвэрийг (жишээ нь «ЗАСАГ ДАРГЫН ҮҮРЭГ
     * ГҮЙЦЭТГЭГЧ») мэдэгдлийн хуудсан дээр нь гараар засаж болохоор.
     */
    public function up(): void
    {
        Schema::table('annual_leaves', function (Blueprint $table) {
            $table->text('approver_title_override')->nullable()->after('signer');
        });
    }

    public function down(): void
    {
        Schema::table('annual_leaves', function (Blueprint $table) {
            $table->dropColumn('approver_title_override');
        });
    }
};
