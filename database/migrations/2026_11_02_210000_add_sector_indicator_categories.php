<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Салбарын үзүүлэлтийг нэгж доторх ангиллаар бүлэглэнэ.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sector_indicator_categories')) {
            Schema::create('sector_indicator_categories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
                $table->string('name', 120);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['department_id', 'sort_order']);
            });
        }

        if (Schema::hasTable('sector_indicators') && ! Schema::hasColumn('sector_indicators', 'category_id')) {
            Schema::table('sector_indicators', function (Blueprint $table) {
                $table->foreignId('category_id')
                    ->nullable()
                    ->after('department_id')
                    ->constrained('sector_indicator_categories')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sector_indicators') && Schema::hasColumn('sector_indicators', 'category_id')) {
            Schema::table('sector_indicators', function (Blueprint $table) {
                $table->dropConstrainedForeignId('category_id');
            });
        }

        Schema::dropIfExists('sector_indicator_categories');
    }
};
