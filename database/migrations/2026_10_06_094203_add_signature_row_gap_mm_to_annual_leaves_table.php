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
            // Мэдэгдэл дэх «Зөвшөөрсөн» хэсгийн мөр хоорондын зайг (мм,
            // 0.5-ийн нарийвчлалтай — 1, 1.5 гэх мэт) хэрэглэгч тохируулж
            // хадгалах боломжтой.
            $table->decimal('signature_row_gap_mm', 3, 1)->nullable()->after('signature_gap_mm');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('annual_leaves', function (Blueprint $table) {
            $table->dropColumn('signature_row_gap_mm');
        });
    }
};
