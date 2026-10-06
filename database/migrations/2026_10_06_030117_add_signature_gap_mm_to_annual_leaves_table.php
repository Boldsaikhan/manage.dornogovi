<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('annual_leaves', function (Blueprint $table) {
            // Мэдэгдэл дэх «Зөвшөөрсөн» хэсгийн тушаал, нэрийн хоорондын
            // зайг (мм) хэрэглэгч тохируулж хадгалах боломжтой.
            $table->unsignedTinyInteger('signature_gap_mm')->nullable()->after('notice_text');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('annual_leaves', function (Blueprint $table) {
            $table->dropColumn('signature_gap_mm');
        });
    }
};
