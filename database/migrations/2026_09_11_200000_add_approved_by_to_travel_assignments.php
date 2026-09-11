<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Томилолтыг баталсан удирдах албан хаагчийг мөр дээр нь хадгална.
 *
 * Урьд нь батлах албан тушаалтныг табаас нь таамаглаж байсан. Одоо утасны
 * жагсаалтын «Удирдлага» ангилалд байгаа хүнээс сонгоно.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('travel_assignments', 'approved_by')) {
            return;
        }

        Schema::table('travel_assignments', function (Blueprint $table) {
            $table->string('approved_by')->nullable()->after('approver');
        });
    }

    public function down(): void
    {
        Schema::table('travel_assignments', function (Blueprint $table) {
            $table->dropColumn('approved_by');
        });
    }
};
