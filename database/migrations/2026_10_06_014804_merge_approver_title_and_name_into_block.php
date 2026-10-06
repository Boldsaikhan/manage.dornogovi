<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Тушаал, нэрийг тусдаа засдаг болгосон ч хэрэглэгч бүх «Зөвшөөрсөн»
     * хэсгийг (толгой тайлбар + тушаал + нэр) нэг доороо, нэг талбарт
     * засах хэрэгцээтэй байгаа тул буцаан нэгтгэнэ.
     */
    public function up(): void
    {
        Schema::table('annual_leaves', function (Blueprint $table) {
            $table->dropColumn(['approver_title_override', 'approver_name_override']);
            $table->text('approver_block_override')->nullable()->after('signer');
        });
    }

    public function down(): void
    {
        Schema::table('annual_leaves', function (Blueprint $table) {
            $table->dropColumn('approver_block_override');
            $table->string('approver_title_override')->nullable()->after('signer');
            $table->string('approver_name_override')->nullable()->after('approver_title_override');
        });
    }
};
