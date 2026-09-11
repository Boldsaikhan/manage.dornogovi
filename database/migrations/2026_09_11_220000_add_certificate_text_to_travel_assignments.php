<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «Албан томилолтын үнэмлэх»-ийн бичвэр.
 *
 * Үнэмлэхийн дээд талын догол мөрийг (хэн, хаана, хэдэн хоног ажиллахаар
 * томилогдсон тухай) гараар бичиж оруулна.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('travel_assignments', 'certificate_text')) {
            return;
        }

        Schema::table('travel_assignments', function (Blueprint $table) {
            $table->text('certificate_text')->nullable()->after('report');
        });
    }

    public function down(): void
    {
        Schema::table('travel_assignments', function (Blueprint $table) {
            $table->dropColumn('certificate_text');
        });
    }
};
