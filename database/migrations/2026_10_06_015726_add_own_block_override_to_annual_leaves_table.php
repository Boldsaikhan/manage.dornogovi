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
     * Амралт эдэлж буй хүний гарын үсгийн мөрийг (тушаал + нэр) мэдэгдлийн
     * хуудсан дээр нь гараар засаж болохоор.
     */
    public function up(): void
    {
        Schema::table('annual_leaves', function (Blueprint $table) {
            $table->text('own_block_override')->nullable()->after('approver_block_override');
        });
    }

    public function down(): void
    {
        Schema::table('annual_leaves', function (Blueprint $table) {
            $table->dropColumn('own_block_override');
        });
    }
};
