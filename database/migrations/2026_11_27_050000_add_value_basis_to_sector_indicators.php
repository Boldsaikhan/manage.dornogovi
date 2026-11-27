<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Мөр бүрийн дүнг өссөн эсвэл тухайн оноор харуулах сонголт. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sector_indicators')) {
            return;
        }

        Schema::table('sector_indicators', function (Blueprint $table) {
            if (! Schema::hasColumn('sector_indicators', 'value_basis')) {
                $table->string('value_basis', 20)->default('year')->after('measure_unit');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sector_indicators')) {
            return;
        }

        Schema::table('sector_indicators', function (Blueprint $table) {
            if (Schema::hasColumn('sector_indicators', 'value_basis')) {
                $table->dropColumn('value_basis');
            }
        });
    }
};
