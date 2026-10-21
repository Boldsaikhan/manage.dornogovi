<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Гэрээний эх хувийг (Word / PDF) мөрт нь хавсаргана.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            if (! Schema::hasColumn('contracts', 'file_path')) {
                $table->string('file_path')->nullable()->after('note');
            }

            if (! Schema::hasColumn('contracts', 'file_name')) {
                $table->string('file_name')->nullable()->after('file_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            foreach (['file_path', 'file_name'] as $column) {
                if (Schema::hasColumn('contracts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
