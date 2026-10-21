<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Гэрээний бүртгэлийг албан ёсны маягтын багануудтай тааруулна.
 *
 * Маягтад «Хувь» болон «Боловсруулсан албан тушаалтан» багана байдаг.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            if (! Schema::hasColumn('contracts', 'copies')) {
                $table->unsignedSmallInteger('copies')->nullable()->after('issued_on');
            }

            if (! Schema::hasColumn('contracts', 'prepared_by')) {
                $table->string('prepared_by')->nullable()->after('copies');
            }
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            foreach (['copies', 'prepared_by'] as $column) {
                if (Schema::hasColumn('contracts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
