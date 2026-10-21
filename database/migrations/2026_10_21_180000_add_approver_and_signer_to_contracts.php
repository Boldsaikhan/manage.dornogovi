<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Гэрээг баталсан болон төлөөлж байгуулсан албан тушаалтан.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            if (! Schema::hasColumn('contracts', 'approved_by_name')) {
                $table->string('approved_by_name')->nullable()->after('prepared_by');
            }

            if (! Schema::hasColumn('contracts', 'signed_by')) {
                $table->string('signed_by')->nullable()->after('approved_by_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            foreach (['approved_by_name', 'signed_by'] as $column) {
                if (Schema::hasColumn('contracts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
