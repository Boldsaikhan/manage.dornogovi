<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Задаргааны мөр бүрт нэмэлт тайлбар. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sector_indicator_value_items')) {
            return;
        }

        Schema::table('sector_indicator_value_items', function (Blueprint $table) {
            if (! Schema::hasColumn('sector_indicator_value_items', 'note')) {
                $table->text('note')->nullable()->after('amount');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sector_indicator_value_items')) {
            return;
        }

        Schema::table('sector_indicator_value_items', function (Blueprint $table) {
            if (Schema::hasColumn('sector_indicator_value_items', 'note')) {
                $table->dropColumn('note');
            }
        });
    }
};
