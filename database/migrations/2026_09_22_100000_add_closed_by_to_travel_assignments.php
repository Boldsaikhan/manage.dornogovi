<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Томилолтын зардлыг тооцоо хийхийг зөвшөөрсөн («хаасан») албан хаагчийг
 * мөр дээр нь хадгална.
 *
 * Энэ нь «БАТЛАВ» хэсэгт гарын үсэг зурах батлагчаас (approved_by) өөр
 * хүн байж болно — үнэмлэхийн ар талын «тооцоо хийхийг зөвшөөрсөн» блокт
 * гардаг.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('travel_assignments', 'closed_by')) {
            return;
        }

        Schema::table('travel_assignments', function (Blueprint $table) {
            $table->string('closed_by')->nullable()->after('approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('travel_assignments', function (Blueprint $table) {
            $table->dropColumn('closed_by');
        });
    }
};
