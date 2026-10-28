<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «Салбарын үзүүлэлт» — суурь тоон мэдээллийн талбарууд.
 *
 * Зөвхөн шинэ багана нэмнэ, байгаа өгөгдөлд хүрэхгүй.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('legal_acts')) {
            return;
        }

        Schema::table('legal_acts', function (Blueprint $table) {
            if (! Schema::hasColumn('legal_acts', 'measure_unit')) {
                $table->string('measure_unit', 60)->nullable()->after('number');
            }

            if (! Schema::hasColumn('legal_acts', 'base_year')) {
                $table->string('base_year', 9)->nullable()->after('measure_unit');
            }

            if (! Schema::hasColumn('legal_acts', 'base_value')) {
                $table->string('base_value', 60)->nullable()->after('base_year');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('legal_acts')) {
            return;
        }

        Schema::table('legal_acts', function (Blueprint $table) {
            $table->dropColumn(['measure_unit', 'base_year', 'base_value']);
        });
    }
};
